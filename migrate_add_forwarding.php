<?php
/**
 * Database migration: Add email forwarding fields
 * Run this once: php migrate_add_forwarding.php
 */

require_once 'db.php';

$db = Database::getInstance()->getConnection();

echo "Running database migration...\n";

try {
    // Add new columns to emails table
    $db->exec("ALTER TABLE emails ADD COLUMN assigned_recipient TEXT");
    echo "✓ Added assigned_recipient column\n";
    
    $db->exec("ALTER TABLE emails ADD COLUMN is_sent INTEGER DEFAULT 0");
    echo "✓ Added is_sent column\n";
    
    $db->exec("ALTER TABLE emails ADD COLUMN sent_at DATETIME");
    echo "✓ Added sent_at column\n";
    
    $db->exec("ALTER TABLE emails ADD COLUMN is_selected INTEGER DEFAULT 0");
    echo "✓ Added is_selected column\n";
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "⚠ Columns already exist - migration already run\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}
?>