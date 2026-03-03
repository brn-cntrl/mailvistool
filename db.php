<?php
/**
 * Database connection helper
 * Automatically sets up database if it doesn't exist
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $dbPath;
    
    private function __construct() {
        $this->dbPath = __DIR__ . '/database/mailvis.db';
        $this->ensureDatabaseExists();
        $this->connect();
    }
    
    private function ensureDatabaseExists() {
        $dbDir = dirname($this->dbPath);
        $schemaPath = __DIR__ . '/schema.sql';
        
        // Create directory if needed
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        // Check if database needs setup
        $needsSetup = !file_exists($this->dbPath) || filesize($this->dbPath) == 0;
        
        // Also check if tables exist (in case file exists but is empty)
        if (!$needsSetup && file_exists($this->dbPath)) {
            try {
                $tempPdo = new PDO('sqlite:' . $this->dbPath);
                $tempPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $tables = $tempPdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
                
                // If no tables or missing critical tables, need setup
                if (empty($tables) || !in_array('emails', $tables)) {
                    $needsSetup = true;
                }
            } catch (Exception $e) {
                $needsSetup = true;
            }
        }
        
        if ($needsSetup) {
            $this->setup($schemaPath);
        }
    }
    
    private function setup($schemaPath) {
        if (!file_exists($schemaPath)) {
            throw new Exception("Schema file not found: $schemaPath");
        }
        
        $schema = file_get_contents($schemaPath);
        
        try {
            // Create database
            $pdo = new PDO('sqlite:' . $this->dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Execute schema
            $pdo->exec($schema);
            
            // Insert default routing rules from config
            $this->insertDefaultRules($pdo);
            
            error_log("Database auto-setup completed: " . $this->dbPath);
            
        } catch (Exception $e) {
            error_log("Database setup failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function insertDefaultRules($pdo) {
        $configPath = __DIR__ . '/config.php';
        
        if (!file_exists($configPath)) {
            error_log("No config.php found - skipping default routing rules");
            return;
        }
        
        $config = require $configPath;
        $rules = $config['routing_rules'] ?? [];
        
        if (empty($rules)) {
            error_log("No default routing rules configured in config.php");
            return;
        }
        
        $stmt = $pdo->prepare('
            INSERT INTO routing_rules (keyword, recipient_email, recipient_name, priority)
            VALUES (?, ?, ?, ?)
        ');
        
        foreach ($rules as $keyword => $rule) {
            try {
                $stmt->execute([
                    $keyword,
                    $rule['email'],
                    $rule['name'],
                    $rule['priority'] ?? 0
                ]);
                error_log("Added routing rule: '$keyword' → {$rule['email']}");
            } catch (Exception $e) {
                error_log("Failed to add routing rule '$keyword': " . $e->getMessage());
            }
        }
    }
    
    private function connect() {
        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>