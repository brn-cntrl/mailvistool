<?php
/**
 * Configuration file
 * Edit this file to customize settings
 */

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

return [
    // IMAP Connection Settings
    'imap' => [
        'host' => $_ENV['IMAP_HOST'],
        'port' => $_ENV['IMAP_PORT'],
        'username' => $_ENV['IMAP_USERNAME'],
        'password' => $_ENV['IMAP_PASSWORD'],
        'encryption' => $_ENV['IMAP_ENCRYPTION'],
        'validate_cert' => $_ENV['IMAP_VALIDATE_CERT'] === 'true'
    ],
    'smtp' => [
        'host' => $_ENV['SMTP_HOST'],
        'port' => $_ENV['SMTP_PORT'],
        'username' => $_ENV['SMTP_USERNAME'],
        'password' => $_ENV['SMTP_PASSWORD'],
        'encryption' => $_ENV['SMTP_ENCRYPTION'],
        'from_email' => $_ENV['SMTP_FROM_EMAIL'],
        'from_name' => $_ENV['SMTP_FROM_NAME']
    ],

    // Available recipients for dropdown
    // 'recipients' => [
    //     ['email' => 'user1@anfarch.com', 'name' => 'USER1'],
    //     ['email' => 'user2@anfarch.com', 'name' => 'USER2'],
    //     ['email' => 'admin@anfarch.com', 'name' => 'ADMIN'],
    // ],
    
    // Default routing rules (edit as needed)
    // Format: ['keyword' => ['email' => '...', 'name' => '...', 'priority' => N]]
    'routing_rules' => [
        // Add your actual rules here when you know them
        // Examples:
        // 'project proposal' => [
        //     'email' => 'user1@anfarch.com',
        //     'name' => 'USER1',
        //     'priority' => 10
        // ],
        // 'basic inquiry' => [
        //     'email' => 'user2@anfarch.com', 
        //     'name' => 'USER2',
        //     'priority' => 5
        // ],
    ],
    
    // Sync settings
    'sync_limit' => (int)($_ENV['SYNC_LIMIT'] ?? 50),
    
    // Database path
    'database_path' => __DIR__ . '/database/mailvis.db',
];
?>