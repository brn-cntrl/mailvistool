<?php
/**
 * Email forwarding functionality using PHPMailer
 */

require_once 'vendor/autoload.php';
require_once 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Webklex\PHPIMAP\ClientManager;

class EmailForwarder {
    private $db;
    private $config;
    private $imapClient;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->config = require 'config.php';
    }
    
    /**
     * Get full email body from IMAP
     */
    private function getFullEmailBody($uid) {
        try {
            $cm = new ClientManager();
            $client = $cm->make($this->config['imap']);
            $client->connect();
            
            $folder = $client->getFolder('INBOX');
            // Query specifically by UID for speed
            $message = $folder->query()->uid($uid)->get()->first();
            
            if ($message) {
                return $message->getTextBody() ?: $message->getHTMLBody(true);
            }
        } catch (Exception $e) {
            error_log("Error fetching full body for UID $uid: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Send a single email
     */
    private function forwardEmail($emailData) {
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer: $str");
        };
        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->Port = $this->config['smtp']['port'];
            
            if (!empty($this->config['smtp']['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['smtp']['username'];
                $mail->Password = $this->config['smtp']['password'];
            }
            
            if (!empty($this->config['smtp']['encryption'])) {
                $mail->SMTPSecure = $this->config['smtp']['encryption'];
            }
            
            // From
            $mail->setFrom(
                $this->config['smtp']['from_email'],
                $this->config['smtp']['from_name']
            );
            
            // Clean up sender email (ensure no newlines or junk)
            $senderEmail = trim(str_replace(["\r", "\n"], '', $emailData['sender_email']));
            $senderName = trim(str_replace(["\r", "\n"], '', $emailData['sender_name'] ?? ''));

            $mail->addReplyTo(
                $senderEmail,
                $senderName ?: $senderEmail
            );
            
            $recipients = !empty($emailData['assigned_recipients'])
                ? explode(',', $emailData['assigned_recipients'])
                : [];

            if (empty($recipients)) {
                throw new Exception("No recipients assigned for email ID {$emailData['id']}");
            }

            foreach ($recipients as $recipient) {
                $mail->addAddress(trim($recipient));
            }
            
            $mail->Subject = 'Fwd: ' . $emailData['subject'];
            
            // Try to get full body, fallback to preview
            $fullBody = $this->getFullEmailBody($emailData['uid']);
            if (!$fullBody) {
                $fullBody = $emailData['body_preview'];
            }
            
            // Body
            $forwardedMessage = "---------- Forwarded message ---------\n";
            $forwardedMessage .= "IMPORTANT: When replying, please reply directly to the sender below.\n\n";
            $forwardedMessage .= "From: {$emailData['sender_name']} <{$emailData['sender_email']}>\n";
            $forwardedMessage .= "Date: {$emailData['date_received']}\n";
            $forwardedMessage .= "Subject: {$emailData['subject']}\n\n";
            $forwardedMessage .= $fullBody ?? $emailData['body_preview'];
            
            $mail->Body = $forwardedMessage;
            $mail->send();
            
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer Exception: " . $e->getMessage());
            error_log("PHPMailer Stack: " . $e->getTraceAsString());
            throw new Exception("Failed to send email: " . $e->getMessage());
        }
    }
    
    /**
     * Send all selected emails
     */
    public function sendSelectedEmails() {
        $stmt = $this->db->query('
            SELECT e.*, 
                GROUP_CONCAT(r.email) as assigned_recipients
            FROM emails e
            LEFT JOIN email_recipients er ON e.id = er.email_id
            LEFT JOIN recipients r ON er.recipient_id = r.id
            WHERE e.is_selected = 1
            GROUP BY e.id
        ');
        
        $emails = $stmt->fetchAll();

        // DEBUG
        error_log("Found " . count($emails) . " selected emails to send");
        foreach ($emails as $email) {
            error_log("Email ID {$email['id']}: {$email['subject']} -> {$email['assigned_recipient']}");
        }

        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($emails as $email) {
            try {
                error_log("Attempting to send email ID {$email['id']}");
                $this->forwardEmail($email);
                error_log("Successfully sent email ID {$email['id']}");
                
                // Mark as sent
                $updateStmt = $this->db->prepare('
                    UPDATE emails 
                    SET is_sent = 1, sent_at = datetime("now"), is_selected = 0 
                    WHERE id = ?
                ');
                $updateStmt->execute([$email['id']]);
                
                // Log action
                $logStmt = $this->db->prepare('
                    INSERT INTO actions (email_id, action_type, note) 
                    VALUES (?, ?, ?)
                ');
                $recipient = $email['assigned_recipient'] ?? $email['intended_recipient'];
                $logStmt->execute([
                    $email['id'],
                    'forwarded',
                    "Forwarded to {$recipient}"
                ]);
                
                $results['sent']++;
                
            } catch (Exception $e) {
                error_log("FAILED to send email ID {$email['id']}: " . $e->getMessage());
                $results['failed']++;
                $results['errors'][] = [
                    'email_id' => $email['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}
?>
