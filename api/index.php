<?php
// Start the session before loading any application output.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
// Vercel entry point for the existing multi-page PHP website.
// Only explicitly recognized web page directories are routed.
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode($path ?? '/');
$path = trim($path, '/');
if ($path === '') $path = 'index.php';
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(^|/)\.\.?(/|$)~', $path)) {
    http_response_code(400); exit('Bad request');
}
if (!preg_match('~^(index\.php|(?:pages|shop|tours|auth|admin)/[a-zA-Z0-9_/-]+\.php)$~D', $path)) {
    http_response_code(404); exit('Not found');
}
if (preg_match('~(^|/)(debug-login|fix-account)\.php$~', $path) || str_contains($path, '/includes/')) {
    http_response_code(404); exit('Not found');
}
$target = realpath($root . DIRECTORY_SEPARATOR . $path);
if ($target === false || !is_file($target) || !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(404); exit('Not found');
}
chdir(dirname($target));
require $target;
