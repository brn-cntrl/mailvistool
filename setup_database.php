<?php
/**
 * Database setup script
 * Run this once to create the SQLite database and tables
 */

$dbPath = __DIR__ . '/database/mailvis.db';
$schemaPath = __DIR__ . '/schema.sql';

// Create database directory if it doesn't exist
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
    echo "✓ Created database directory: $dbDir\n";
}

// Check if schema file exists
if (!file_exists($schemaPath)) {
    die("Error: schema.sql not found at $schemaPath\n");
}

// Read schema file
$schema = file_get_contents($schemaPath);
if ($schema === false) {
    die("Error: Could not read schema.sql\n");
}

try {
    // Create/open database
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to database: $dbPath\n";
    
    // Execute schema
    $db->exec($schema);
    echo "✓ Schema executed successfully\n";
    
    // Insert some default routing rules
    echo "\nInserting default routing rules...\n";
    
    $rules = [
        ['project proposal', 'david@anfarch.com', 'David', 10],
        ['basic inquiry', 'melissa@anfarch.com', 'Melissa', 5],
        ['general question', 'admin@anfarch.com', 'Admin', 1],
        ['urgent', 'admin@anfarch.com', 'Admin', 20],
        ['permit', 'david@anfarch.com', 'David', 15],
    ];
    
    $stmt = $db->prepare('
        INSERT INTO routing_rules (keyword, recipient_email, recipient_name, priority)
        VALUES (?, ?, ?, ?)
    ');
    
    foreach ($rules as $rule) {
        $stmt->execute($rule);
        echo "  ✓ Added rule: '{$rule[0]}' → {$rule[1]}\n";
    }
    
    // Verify tables were created
    echo "\nVerifying tables...\n";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "  ✓ Table '$table' created ($count rows)\n";
    }
    
    echo "\n✅ Database setup complete!\n";
    echo "Database location: $dbPath\n";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>