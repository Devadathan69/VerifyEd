<?php
// Copy this file to config.php and adjust the database settings for your MySQL server.
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'verifyed',
    'db_user' => 'root',
    'db_password' => '',
    'base_url' => 'http://localhost:8000',
    'max_upload_bytes' => 5 * 1024 * 1024,
    // Leave false locally. Set true on an HTTPS production site.
    'session_secure' => false,
    // Keep false in production so internal errors are logged, not displayed to visitors.
    'app_debug' => false,
];
