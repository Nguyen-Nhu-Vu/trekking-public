<?php
/**
 * VNN TRAVEL - VERCEL PHP ROUTER
 *
 * File: api/index.php
 *
 * - Dinh tuyen website PHP
 * - Sua link chi tiet san pham cu
 * - Su dung session chung tu Aiven
 * - Giu API chat va cac trang quan ly
 */

declare(strict_types=1);

$root = realpath(dirname(__DIR__));

if ($root === false) {
    http_response_code(500);
    exit('Khong tim thay thu muc website.');
}

/**
 * LAY DUONG DAN NGUOI DUNG TRUY CAP
 */
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

$uriPath = parse_url(
    $requestUri,
    PHP_URL_PATH
);

$path = rawurldecode($uriPath ?: '/');
$path = trim($path, '/');

/**
 * CHAN DUONG DAN KHONG AN TOAN
 */
if (
    str_contains($path, "\0") ||
    str_contains($path, '\\') ||
    preg_match('~(^|/)\.\.?(/|$)~', $path)
) {
    http_response_code(400);
    exit('Bad request');
}

/**
 * TRANG CHU
 */
if ($path === '') {
    $path = 'index.php';
}

/**
 * SUA LINK CHI TIET SAN PHAM CU
 *
 * Link cu:
 * /product-detail.php?id=15
 *
 * Link dung:
 * /shop/product-detail.php?id=15
 *
 * Khong can sua lai tung nut con mat.
 */
if ($path === 'product-detail.php') {
    $path = 'shop/product-detail.php';
}

/**
 * API CHAT
 */
if ($path === 'chat-api.php') {
    $path = 'includes/chat-api.php';
}

/**
 * HO TRO URL DANG THU MUC
 *
 * /admin/products/
 * /admin/bookings/
 * /shop/
 * /tours/
 */
if (
    preg_match(
        '~^(admin|auth|pages|shop|tours)(?:/[a-zA-Z0-9_-]+)*$~D',
        $path
    )
) {
    $path .= '/index.php';
}

/**
 * CHI CHO PHEP CAC FILE PHP WEBSITE
 */
$allowed =
    $path === 'index.php' ||
    $path === 'includes/chat-api.php' ||
    (bool) preg_match(
        '~^(admin|auth|pages|shop|tours)/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.php$~D',
        $path
    );

/**
 * CHAN FILE CAU HINH, DEBUG VA SETUP
 */
$blocked = (bool) preg_match(
    '~(^|/)(debug-login|fix-account|reset|setup|install)\.php$~i',
    $path
);

if (!$allowed || $blocked) {
    http_response_code(404);
    exit('Not found');
}

/**
 * XAC DINH FILE CAN CHAY
 */
$target = realpath(
    $root .
    DIRECTORY_SEPARATOR .
    str_replace('/', DIRECTORY_SEPARATOR, $path)
);

if (
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

/**
 * KHOI TAO DATABASE VA SESSION CHUNG
 *
 * Session khong con duoc tao bang bo luu tru
 * file tam cua Vercel.
 *
 * config/database.php se dang ky MySQL
 * session handler va goi session_start().
 */
require_once $root . '/config/database.php';

/**
 * CHUYEN WORKING DIRECTORY
 */
if ($path === 'includes/chat-api.php') {
    chdir($root);
} else {
    chdir(dirname($target));
}

/**
 * CHAY TRANG WEB
 */
require $target;
