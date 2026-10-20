
<?php
// Vercel PHP router for Trekking
// Save as UTF-8 without BOM.

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

$root = realpath(dirname(__DIR__));

$uriPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

$path = rawurldecode($uriPath ?? '/');
$path = trim($path, '/');

// Prevent unsafe paths
if (
    str_contains($path, "\0") ||
    str_contains($path, '\\') ||
    preg_match('~(^|/)\.\.?(/|$)~', $path)
) {
    http_response_code(400);
    exit('Bad request');
}

// Homepage
if ($path === '') {
    $path = 'index.php';
}

// Chat API compatibility
if ($path === 'chat-api.php') {
    $path = 'includes/chat-api.php';
}

// Support directory-style admin URLs
// Example: /admin/bookings/
if (
    preg_match(
        '~^(admin|auth|pages|shop|tours)(?:/[a-zA-Z0-9_-]+)*$~D',
        $path
    )
) {
    $path .= '/index.php';
}

// Allow the website's PHP routes
$allowed =
    $path === 'index.php' ||
    $path === 'includes/chat-api.php' ||
    (bool) preg_match(
        '~^(admin|auth|pages|shop|tours)/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.php$~D',
        $path
    );

// Block internal setup and debug endpoints
if (
    !$allowed ||
    preg_match(
        '~(^|/)(debug-login|fix-account|reset|setup|install)\.php$~i',
        $path
    )
) {
    http_response_code(404);
    exit('Not found');
}

// Locate PHP file
$target = realpath(
    $root .
    DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $path)
);

// Validate requested file
if (
    $root === false ||
    $target === false ||
    !is_file($target) ||
    !str_starts_with(
        $target,
        $root . DIRECTORY_SEPARATOR
    )
) {
    http_response_code(404);
    exit('Not found');
}

// Set working directory
if ($path === 'includes/chat-api.php') {
    chdir($root);
} else {
    chdir(dirname($target));
}

// Execute requested PHP page
require $target;
