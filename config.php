<?php
/**
 * Configuration file
 * Edit this file to customize settings
 */

return [
    // IMAP Connection Settings
    'imap' => [
        'host' => 'localhost',
        'port' => 3143,
        'username' => 'test1@localhost',
        'password' => 'test1',
        'encryption' => false,
        'validate_cert' => false,
    ],
    'smtp' => [
        'host' => 'localhost',
        'port' => 3025,
        'username' => '',              // Empty for Greenmail
        'password' => '',              // Empty for Greenmail
        'encryption' => '',            // No encryption for Greenmail
        'from_email' => 'dashboard@test.localhost',
        'from_name' => 'Email Dashboard'
    ],

    // Available recipients for dropdown
    'recipients' => [
        ['email' => 'user1@anfarch.com', 'name' => 'USER1'],
        ['email' => 'user2@anfarch.com', 'name' => 'USER2'],
        ['email' => 'admin@anfarch.com', 'name' => 'ADMIN'],
    ],
    
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
    
    // Database path
    'database_path' => __DIR__ . '/database/mailvis.db',
];
?>