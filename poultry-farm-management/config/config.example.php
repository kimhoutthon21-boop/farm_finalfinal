<?php
// Copy this file to config/config.php and edit it, or let setup.php create it for you.
// config/config.php is ignored by git so credentials are never committed.
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'poultry_farm',
        'user'    => 'root',      // XAMPP default
        'pass'    => '',          // XAMPP default is an empty password - change it for production
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone'     => 'Asia/Phnom_Penh',
        'base_url'     => null,   // null = auto-detect (works for htdocs/poultry-farm-management)
        'session_name' => 'pfm_session',
        'debug'        => false,  // true shows error details - only on your own computer
    ],
];
