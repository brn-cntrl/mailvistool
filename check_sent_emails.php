<?php
require_once 'db.php';
$db = Database::getInstance()->getConnection();

echo "=== Sent Emails from Dashboard ===\n\n";

$stmt = $db->query('
    SELECT 
        e.id,
        e.subject,
        e.sender_name,
        e.sender_email,
        e.body_preview,
        e.date_received,
        e.sent_at,
        GROUP_CONCAT(r.email) as assigned_recipients
    FROM emails e
    LEFT JOIN email_recipients er ON e.id = er.email_id
    LEFT JOIN recipients r ON er.recipient_id = r.id
    WHERE e.is_sent = 1
    GROUP BY e.id
    ORDER BY e.sent_at DESC
');

$sentEmails = $stmt->fetchAll();

if (empty($sentEmails)) {
    echo "No emails have been sent yet.\n";
    exit;
}

echo "Found " . count($sentEmails) . " sent email(s)\n\n";

foreach ($sentEmails as $email) {
    echo str_repeat("=", 70) . "\n";
    echo "EMAIL ID: {$email['id']}\n";
    echo "Sent At: {$email['sent_at']}\n";
    echo "Forwarded To: {$email['assigned_recipients']}\n";
    echo "Subject: Fwd: {$email['subject']}\n";
    echo str_repeat("-", 70) . "\n";
    echo "\nEXACT BODY THAT WAS SENT:\n\n";
    
    $forwardedMessage = "---------- Forwarded message ---------\n";
    $forwardedMessage .= "IMPORTANT: When replying, please reply directly to the sender below.\n\n";
    $forwardedMessage .= "From: {$email['sender_name']} <{$email['sender_email']}>\n";
    $forwardedMessage .= "Date: {$email['date_received']}\n";
    $forwardedMessage .= "Subject: {$email['subject']}\n\n";
    $forwardedMessage .= $email['body_preview'];
    
    echo $forwardedMessage . "\n";
    echo str_repeat("=", 70) . "\n\n";
}

echo "✓ Done\n";
?>