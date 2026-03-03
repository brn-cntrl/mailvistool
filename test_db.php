<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Database Diagnostic ===\n\n";

// Check if db.php exists
if (!file_exists('db.php')) {
    die("Error: db.php not found\n");
}
echo "✓ db.php exists\n";

// Check if schema.sql exists
if (!file_exists('schema.sql')) {
    die("Error: schema.sql not found\n");
}
echo "✓ schema.sql exists\n";

// Try to use Database class
require_once 'db.php';

echo "✓ db.php loaded\n";

try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Database connection obtained\n";
    
    // Check what tables exist
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "\nTables in database:\n";
    if (empty($tables)) {
        echo "  (none - database is empty!)\n";
    } else {
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
    }
    
    // Check database file location
    echo "\nDatabase file location:\n";
    echo "  " . realpath(__DIR__ . '/database/mailvis.db') . "\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
?>