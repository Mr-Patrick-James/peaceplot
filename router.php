<?php
/**
 * PeacePlot Router for PHP Built-in Server (Desktop App)
 * This file is only used when running via Electron desktop app.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$staticExtensions = ['css','js','jpg','jpeg','png','gif','svg','ico',
                     'woff','woff2','ttf','eot','pdf','xlsx','docx','map','webp'];
$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));

if (in_array($ext, $staticExtensions)) return false;

$file = __DIR__ . $uri;
if (is_file($file)) return false;

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

return false;
