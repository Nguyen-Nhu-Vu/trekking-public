<?php
// trekking/includes/functions.php

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function format_price($price) {
    return number_format($price, 0, ',', '.') . ' ₫';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getRole() {
    return $_SESSION['role'] ?? 'guest';
}

function isAdmin() {
    return getRole() === 'admin';
}

function isManager() {
    return getRole() === 'manager';
}

function isStaff() {
    return getRole() === 'staff';
}

function isCustomer() {
    return getRole() === 'customer';
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Vui lòng đăng nhập để tiếp tục!";
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

function requireStaff() {
    if (!isLoggedIn() || !in_array(getRole(), ['staff', 'manager', 'admin'])) {
        $_SESSION['flash_error'] = "Bạn không có quyền truy cập khu vực này!";
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

function requireManager() {
    if (!isLoggedIn() || !in_array(getRole(), ['manager', 'admin'])) {
        $_SESSION['flash_error'] = "Chức năng này chỉ dành cho cấp Quản lý!";
        header("Location: " . BASE_URL . "admin/index.php");
        exit;
    }
}

function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        $_SESSION['flash_error'] = "Chỉ Admin tối cao mới có quyền truy cập!";
        header("Location: " . BASE_URL . "admin/index.php");
        exit;
    }
}

function getCartCount() {
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}