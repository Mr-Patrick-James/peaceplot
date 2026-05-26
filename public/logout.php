<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

// Log logout before session is destroyed
$user = getUserInfo();
if ($user) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        if ($conn) {
            logActivity($conn, 'LOGOUT', 'users', $user['id'], 'User "' . $user['username'] . '" logged out');
        }
    } catch (Exception $e) { /* silent */ }
}

logout();

// Detect base path dynamically
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = dirname($scriptPath); // Go up from /public to root
$basePath = ($basePath === '/' || $basePath === '\\') ? '' : $basePath;

header('Location: ' . $basePath . '/index.php');
exit;
?>
