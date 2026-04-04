<?php
require 'vendor/autoload.php';

use Webklex\PHPIMAP\ClientManager;

echo "=== Greenmail IMAP Test (Composer Library) ===\n\n";

// Step 1: Send a test email via SMTP
echo "Step 1: Sending test email via SMTP...\n";
$smtp = fsockopen('localhost', 3025, $errno, $errstr, 10);
if (!$smtp) {
    die("Failed to connect to SMTP: $errstr ($errno)\n");
}

fgets($smtp);
fwrite($smtp, "HELO localhost\r\n");
fgets($smtp);
fwrite($smtp, "MAIL FROM: <sender@example.com>\r\n");
fgets($smtp);
fwrite($smtp, "RCPT TO: <test1@localhost>\r\n");
fgets($smtp);
fwrite($smtp, "DATA\r\n");
fgets($smtp);
fwrite($smtp, "From: sender@example.com\r\n");
fwrite($smtp, "To: test1@localhost\r\n");
fwrite($smtp, "Subject: Test Email from PHP\r\n");
fwrite($smtp, "Date: " . date('r') . "\r\n");
fwrite($smtp, "\r\n");
fwrite($smtp, "This is a test email sent to Greenmail.\r\n");
fwrite($smtp, "If you can read this via IMAP, everything works!\r\n");
fwrite($smtp, ".\r\n");
fgets($smtp);
fwrite($smtp, "QUIT\r\n");
fclose($smtp);

echo "✓ Email sent successfully!\n\n";
sleep(1);

// Step 2: Connect to IMAP
echo "Step 2: Connecting to IMAP...\n";

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
    echo "✓ Connected to IMAP successfully!\n\n";
    
    // Get inbox
    $folder = $client->getFolder('INBOX');
    
    // Get all messages
    $messages = $folder->query()->all()->get();
    
    echo "Step 3: Found " . $messages->count() . " email(s) in inbox\n\n";
    
    if ($messages->count() > 0) {
        echo "Step 4: Reading emails:\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($messages as $message) {
            echo "Email:\n";
            echo "  From: " . $message->getFrom()[0]->mail . "\n";
            echo "  To: " . $message->getTo()[0]->mail . "\n";
            echo "  Subject: " . $message->getSubject() . "\n";
            echo "  Date: " . $message->getDate() . "\n";
            echo "  Body: " . trim($message->getTextBody()) . "\n";
            echo str_repeat("-", 60) . "\n";
        }
    }
    
    echo "\n✓ Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}