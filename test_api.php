<?php
// Test API directly
header('Content-Type: application/json');

echo json_encode([
    'test' => 'API is working',
    'php_version' => PHP_VERSION,
    'pdo_drivers' => PDO::getAvailableDrivers(),
    'sqlite_loaded' => extension_loaded('pdo_sqlite'),
    'sqlite3_loaded' => extension_loaded('sqlite3')
]);
?>
