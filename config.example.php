<?php
/**
 * Copy this file to config.php and edit for your environment.
 *   cp config.example.php config.php
 *
 * Never commit config.php (it may contain database passwords).
 */
return [
    'db' => [
        // Shared hosting: usually 'localhost' and port 3306
        // Docker PHP → MySQL on the Mac: host.docker.internal and the published port
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'pickleball',
        'user' => 'YOUR_DB_USER',
        'pass' => 'YOUR_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app_name' => 'Pickleball Open Play',
    // Leave null to auto-detect. Set if links break in a subfolder, e.g. '/pickleball-open-play'
    'base_path' => null,
    // Used only when settings.admin_password_hash is missing (first login bootstrap).
    'default_admin_password' => 'changeme',
    'slot_minutes' => 15,
];
