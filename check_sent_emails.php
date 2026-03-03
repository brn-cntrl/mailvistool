<?php
/**
 * View emails that were sent/forwarded from the dashboard
 */

require_once 'db.php';

$db = Database::getInstance()->getConnection();

echo "=== Sent Emails from Dashboard ===\n\n";

$stmt = $db->query('
    SELECT 
        e.id,
        e.subject,
        e.sender_name,
        e.sender_email,
        e.assigned_recipient,
        e.body_preview,
        e.sent_at,
        a.note
    FROM emails e
    LEFT JOIN actions a ON e.id = a.email_id AND a.action_type = "forwarded"
    WHERE e.is_sent = 1
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
    echo "Forwarded To: {$email['assigned_recipient']}\n";
    echo str_repeat("-", 70) . "\n";
    echo "Original From: {$email['sender_name']} <{$email['sender_email']}>\n";
    echo "Subject: Fwd: {$email['subject']}\n";
    echo "\nBody that was sent:\n";
    echo "---------- Forwarded message ---------\n";
    echo "From: {$email['sender_name']} <{$email['sender_email']}>\n";
    echo "Subject: {$email['subject']}\n\n";
    echo $email['body_preview'] . "\n";
    echo str_repeat("=", 70) . "\n\n";
}

echo "✓ Done\n";
?>