<?php
require_once __DIR__ . '/includes/auth.php';
auth_logout();
flash_set('success', 'Logged out.');
redirect('index.php');
