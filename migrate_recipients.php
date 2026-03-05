<?php
require_once 'db.php';

$db = Database::getInstance()->getConnection();
$config = require 'config.php';

echo "Migrating recipients to database...\n\n";

$recipients = $config['recipients'] ?? [];

if (empty($recipients)) {
    echo "No recipients found in config.php\n";
    exit;
}

$stmt = $db->prepare('
    INSERT OR IGNORE INTO recipients (name, email, is_active) 
    VALUES (?, ?, 1)
');

foreach ($recipients as $recipient) {
    try {
        $stmt->execute([$recipient['name'], $recipient['email']]);
        echo "✓ Added: {$recipient['name']} ({$recipient['email']})\n";
    } catch (Exception $e) {
        echo "✗ Failed: {$recipient['email']} - {$e->getMessage()}\n";
    }
}

echo "\n✅ Migration complete!\n";
echo "You can now remove the 'recipients' array from config.php\n";
?>