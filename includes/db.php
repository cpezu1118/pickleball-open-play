<?php

function db(): mysqli
{
    static $mysqli = null;
    if ($mysqli instanceof mysqli) {
        return $mysqli;
    }

    $config = require __DIR__ . '/../config.php';
    $db = $config['db'];

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $port = (int) ($db['port'] ?? 3306);
    $mysqli = new mysqli(
        $db['host'],
        $db['user'],
        $db['pass'],
        $db['name'],
        $port
    );
    $mysqli->set_charset($db['charset'] ?? 'utf8mb4');

    return $mysqli;
}

function setting_get(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (string) $row['value'] : $default;
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}
