<?php
require_once '../config.php';

// 只允許 admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// 直接沿用 staff 商品清單／篩選邏輯；商品入口（雙卡片）見 staff/products.php。
require_once __DIR__ . '/../staff/product_catalog.php';
