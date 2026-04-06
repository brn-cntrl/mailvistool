<?php
/**
 * IMAP Sync Script
 * Fetches emails from IMAP server and stores them in the database
 * Applies routing rules to determine intended recipients
 */

require_once 'vendor/autoload.php';
require_once 'db.php';

use Webklex\PHPIMAP\ClientManager;

// Load configuration
$config = require 'config.php';

class EmailSync {
    private $db;
    private $imapClient;
    private $config;
    private $routingRules = [];
    private $verbose = false;
    
    public function __construct($config, $verbose = false) {
        $this->config = $config;
        $this->verbose = $verbose;
        $this->db = Database::getInstance()->getConnection();
        $this->loadRoutingRules();
    }
    
    /**
     * Load routing rules from database
     */
    private function loadRoutingRules() {
        $stmt = $this->db->query('SELECT * FROM routing_rules ORDER BY priority DESC');
        $this->routingRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Connect to IMAP server
     */
    private function connectIMAP() {
        $cm = new ClientManager();
        $this->imapClient = $cm->make($this->config['imap']);
        $this->imapClient->connect();
    }
    
    /**
     * Determine intended recipient based on subject line
     */
    private function matchRoutingRule($subject) {
        $subjectLower = strtolower($subject);
        
        foreach ($this->routingRules as $rule) {
            $keywordLower = strtolower($rule['keyword']);
            
            if (strpos($subjectLower, $keywordLower) !== false) {
                return [
                    'recipient' => $rule['recipient_email'],
                    'rule_id' => $rule['id']
                ];
            }
        }
        
        // No match found
        return [
            'recipient' => null,
            'rule_id' => null
        ];
    }
    
    /**
     * Extract priority from email headers
     */
    private function extractPriority($message) {
        $rawHeaders = $message->getHeader()->raw;
        
        // Use library attributes if possible
        if (isset($message->priority)) {
            $p = (int)$message->priority;
            if ($p === 1) return 'high';
            if ($p >= 4) return 'low';
        }

        // Check X-Priority header
        if (preg_match('/X-Priority:\s*(\d+)/i', $rawHeaders, $matches)) {
            $priority = intval($matches[1]);
            if ($priority == 1) return 'high';
            if ($priority >= 4) return 'low';
        }
        
        // Check Importance header
        if (preg_match('/Importance:\s*(\w+)/i', $rawHeaders, $matches)) {
            $importance = strtolower($matches[1]);
            if ($importance === 'high') return 'high';
            if ($importance === 'low') return 'low';
        }
        
        return 'normal';
    }
    
    /**
     * Parse sender information from raw headers
     */
    private function parseSender($message) {
        try {
            $from = $message->getFrom()->first();
            if ($from) {
                return [
                    'name' => $from->personal,
                    'email' => $from->mail
                ];
            }
        } catch (Exception $e) {
            error_log("Error parsing sender with library: " . $e->getMessage());
        }

        // Fallback to basic parsing if library fails
        $fromRaw = (string)$message->from;
        if (preg_match('/(.+?)\s*<(.+?)>/', $fromRaw, $parts)) {
            return [
                'name' => trim($parts[1], '"\' '),
                'email' => trim($parts[2])
            ];
        }

        return [
            'name' => null,
            'email' => trim($fromRaw, '"\' ')
        ];
    }
    
    /**
     * Check if email already exists in database
     */
    private function emailExists($messageId) {
        $stmt = $this->db->prepare('SELECT id FROM emails WHERE message_id = ?');
        $stmt->execute([$messageId]);
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Store email in database
     */
    private function storeEmail($emailData) {
        $sql = '
            INSERT INTO emails (
                message_id, uid, sender_name, sender_email, subject, 
                body_preview, size, date_received, priority, 
                is_read, is_answered, is_flagged, 
                has_attachments, attachment_count,
                intended_recipient, matched_rule_id
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $emailData['message_id'],
            $emailData['uid'],
            $emailData['sender_name'],
            $emailData['sender_email'],
            $emailData['subject'],
            $emailData['body_preview'],
            $emailData['size'],
            $emailData['date_received'],
            $emailData['priority'],
            $emailData['is_read'],
            $emailData['is_answered'],
            $emailData['is_flagged'],
            $emailData['has_attachments'],
            $emailData['attachment_count'],
            $emailData['intended_recipient'],
            $emailData['matched_rule_id']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Main sync function
     */
    public function sync() {
        $stats = [
            'total_fetched' => 0,
            'new_emails' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        try {
            // Increase limits for large inboxes
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            // Connect to IMAP
            $this->connectIMAP();
            if ($this->verbose) echo "✓ Connected to IMAP server\n";
            
            // Get inbox
            $folder = $this->imapClient->getFolder('INBOX');
            if ($this->verbose) echo "✓ Opened INBOX\n";
            
            // Fetch recent messages (limiting to avoid hang on large inboxes)
            if ($this->verbose) echo "  - Fetching recent messages (limit 50)...\n";
            // $messages = $folder->query()->all()->limit(50)->get();
            $messages = $folder->query()->all()->limit(50)->setFetchOrder('desc')->get();
            $stats['total_fetched'] = $messages->count();
            if ($this->verbose) echo "✓ Found {$stats['total_fetched']} emails\n\n";
            
            // Process each message
            foreach ($messages as $message) {
                try {
                    $messageId = $message->getMessageId();
                    
                    // Skip if already in database
                    if ($this->emailExists($messageId)) {
                        $stats['skipped']++;
                        if ($this->verbose) echo "  - Skipped (already exists): " . substr($message->getSubject(), 0, 50) . "\n";
                        continue;
                    }
                    
                    // Extract sender info
                    $sender = $this->parseSender($message);
                    
                    // Get subject and match routing rule
                    $subject = $message->getSubject();
                    $routing = $this->matchRoutingRule($subject);
                    
                    // Extract priority
                    $priority = $this->extractPriority($message);
                    
                    // Get body preview
                    $bodyPreview = '';
                    if ($message->hasTextBody()) {
                        $body = $message->getTextBody();
                        $bodyPreview = substr($body, 0, 500); // First 500 chars
                    }
                    
                    // Get date - simple conversion
                    $date = $message->getDate();
                    $dateString = date('Y-m-d H:i:s', strtotime((string)$date));
                    
                    // Prepare email data
                    $emailData = [
                        'message_id' => $messageId,
                        'uid' => $message->getUid(),
                        'sender_name' => $sender['name'],
                        'sender_email' => $sender['email'],
                        'subject' => $subject,
                        'body_preview' => $bodyPreview,
                        'size' => $message->getSize(),
                        'date_received' => $dateString,
                        'priority' => $priority,
                        'is_read' => $message->hasFlag('Seen') ? 1 : 0,
                        'is_answered' => $message->hasFlag('Answered') ? 1 : 0,
                        'is_flagged' => $message->hasFlag('Flagged') ? 1 : 0,
                        'has_attachments' => $message->getAttachments()->count() > 0 ? 1 : 0,
                        'attachment_count' => $message->getAttachments()->count(),
                        'intended_recipient' => $routing['recipient'],
                        'matched_rule_id' => $routing['rule_id']
                    ];
                    
                    // Store in database
                    $id = $this->storeEmail($emailData);
                    $stats['new_emails']++;
                    
                    if ($this->verbose) {
                        echo "  ✓ Added: {$subject}\n";
                        echo "    From: {$sender['email']}\n";
                        echo "    To: " . ($routing['recipient'] ?? 'Unassigned') . "\n";
                        echo "    Priority: {$priority}\n\n";
                    }
                    
                } catch (Exception $e) {
                    $stats['errors']++;
                    if ($this->verbose) echo "  ✗ Error processing email: " . $e->getMessage() . "\n\n";
                }
            }
            
            if ($this->verbose) {
                echo str_repeat("=", 60) . "\n";
                echo "Sync Summary:\n";
                echo "  Total fetched: {$stats['total_fetched']}\n";
                echo "  New emails: {$stats['new_emails']}\n";
                echo "  Skipped (duplicates): {$stats['skipped']}\n";
                echo "  Errors: {$stats['errors']}\n";
                echo str_repeat("=", 60) . "\n";
            }
            
            return $stats;
            
        } catch (Exception $e) {
            if ($this->verbose) echo "✗ Sync failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}

// Run sync if called directly (not via API)
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    // Command line - show output
    echo "=== Email Sync Started ===\n\n";
    $syncer = new EmailSync($config, true); // verbose = true
    $syncer->sync();
    echo "\n✓ Sync complete!\n";
} else {
    // Called via web/API - suppress all output
    $syncer = new EmailSync($config, false); // verbose = false
    $syncer->sync();
}
?>
