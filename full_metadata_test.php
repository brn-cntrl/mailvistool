<?php
require 'vendor/autoload.php';

use Webklex\PHPIMAP\ClientManager;

// Send multiple test emails with different characteristics
echo "=== Sending Test Emails ===\n";

function sendTestEmail($from, $fromName, $to, $subject, $body, $priority = 'normal') {
    $smtp = fsockopen('localhost', 3025);
    fgets($smtp);
    fwrite($smtp, "HELO localhost\r\n");
    fgets($smtp);
    fwrite($smtp, "MAIL FROM: <$from>\r\n");
    fgets($smtp);
    fwrite($smtp, "RCPT TO: <$to>\r\n");
    fgets($smtp);
    fwrite($smtp, "DATA\r\n");
    fgets($smtp);
    
    fwrite($smtp, "From: $fromName <$from>\r\n");
    fwrite($smtp, "To: <$to>\r\n");
    fwrite($smtp, "Subject: $subject\r\n");
    fwrite($smtp, "Date: " . date('r') . "\r\n");
    fwrite($smtp, "Message-ID: <" . uniqid() . "@example.com>\r\n");
    
    if ($priority === 'high') {
        fwrite($smtp, "X-Priority: 1\r\n");
        fwrite($smtp, "Importance: high\r\n");
    } elseif ($priority === 'low') {
        fwrite($smtp, "X-Priority: 5\r\n");
        fwrite($smtp, "Importance: low\r\n");
    }
    
    fwrite($smtp, "\r\n");
    fwrite($smtp, $body . "\r\n");
    fwrite($smtp, ".\r\n");
    fgets($smtp);
    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);
}

// Create diverse test emails
sendTestEmail('client1@acme.com', 'John Smith', 'test1@localhost', 
    'Project Proposal - Downtown Mall', 
    'We would like to propose a new shopping mall project in downtown.', 
    'high');
echo "✓ Sent: Project Proposal (High Priority)\n";

sendTestEmail('info@techcorp.com', 'Tech Corp Support', 'test1@localhost',
    'Basic Inquiry - Service Pricing',
    'Could you please send me information about your service pricing?',
    'normal');
echo "✓ Sent: Basic Inquiry (Normal Priority)\n";

sendTestEmail('contact@startup.io', 'Jane Doe', 'test1@localhost',
    'General Question - Office Hours',
    'What are your office hours? I would like to schedule a visit.',
    'normal');
echo "✓ Sent: General Question (Normal Priority)\n";

sendTestEmail('urgent@company.com', 'Emergency Contact', 'test1@localhost',
    'URGENT - Permit Approval Needed',
    'We need immediate approval for the building permit.',
    'high');
echo "✓ Sent: Urgent Request (High Priority)\n";

sleep(2);

// Now fetch and display all metadata
echo "\n=== Fetching Emails from IMAP ===\n";

$cm = new ClientManager();
$client = $cm->make([
    'host'          => 'localhost',
    'port'          => 3143,
    'encryption'    => false,
    'validate_cert' => false,
    'username'      => 'test1@localhost',
    'password'      => 'test1',
    'protocol'      => 'imap'
]);

try {
    $client->connect();
    $folder = $client->getFolder('INBOX');
    $messages = $folder->query()->all()->get();
    
    echo "\nFound " . $messages->count() . " emails\n";
    echo str_repeat("=", 100) . "\n\n";
    
    foreach ($messages as $index => $msg) {
        echo "EMAIL #" . ($index + 1) . "\n";
        echo str_repeat("-", 100) . "\n";
        
        // Get raw header for parsing
        $rawHeaders = $msg->getHeader()->raw;
        
        // Parse From
        if (preg_match('/From:\s*(.+?)(?:\r?\n(?!\s)|$)/s', $rawHeaders, $matches)) {
            echo "From (raw): " . trim($matches[1]) . "\n";
            
            // Extract email and name
            if (preg_match('/(.+?)\s*<(.+?)>/', trim($matches[1]), $parts)) {
                echo "  - Sender Name: " . trim($parts[1]) . "\n";
                echo "  - Sender Email: " . trim($parts[2]) . "\n";
            } else {
                echo "  - Sender Email: " . trim($matches[1]) . "\n";
            }
        }
        
        // Parse To
        if (preg_match('/To:\s*(.+?)(?:\r?\n(?!\s)|$)/s', $rawHeaders, $matches)) {
            echo "To (raw): " . trim($matches[1]) . "\n";
        }
        
        echo "Subject: " . $msg->getSubject() . "\n";
        
        // Date - handle as string
        $date = $msg->getDate();
        echo "Date: " . $date . "\n";
        
        echo "Message ID: " . $msg->getMessageId() . "\n";
        
        // Priority
        $priority = 'Normal';
        if (preg_match('/X-Priority:\s*(\d+)/i', $rawHeaders, $matches)) {
            $p = intval($matches[1]);
            if ($p == 1) $priority = 'High';
            elseif ($p >= 4) $priority = 'Low';
        }
        echo "Priority: " . $priority . "\n";
        
        // Importance
        if (preg_match('/Importance:\s*(\w+)/i', $rawHeaders, $matches)) {
            echo "Importance: " . $matches[1] . "\n";
        }
        
        // Size
        echo "Size: " . $msg->getSize() . " bytes\n";
        
        // Flags
        echo "Read: " . ($msg->hasFlag('Seen') ? 'Yes' : 'No') . "\n";
        echo "Answered: " . ($msg->hasFlag('Answered') ? 'Yes' : 'No') . "\n";
        echo "Flagged: " . ($msg->hasFlag('Flagged') ? 'Yes' : 'No') . "\n";
        
        // Body preview
        $body = $msg->getTextBody();
        echo "Body Preview: " . substr($body, 0, 80) . "...\n";
        
        // Attachments
        echo "Attachments: " . $msg->getAttachments()->count() . "\n";
        
        echo "\n";
    }
    
    echo str_repeat("=", 100) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✓ Complete! This shows all metadata available via IMAP.\n";
?>