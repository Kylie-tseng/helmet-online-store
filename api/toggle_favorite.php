<?php
require_once '../config.php';
require_once '../includes/cart_functions.php';

function buildRedirectLocation($redirect) {
    $redirect = trim((string)$redirect);
    if ($redirect === '') {
        return '../products.php';
    }

    // Prevent open redirect or malformed protocol-relative URL
    if (preg_match('/^\s*https?:\/\//i', $redirect) || strpos($redirect, '//') === 0) {
        return '../products.php';
    }

    // Absolute path should be used directly (already from web root)
    if (strpos($redirect, '/') === 0) {
        return $redirect;
    }

    // Relative page path (project root) should go one level up from /api
    return '../' . ltrim($redirect, '/');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../products.php');
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : 'products.php';
$redirect = $redirect !== '' ? $redirect : 'products.php';
$redirect_location = buildRedirectLocation($redirect);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?redirect=' . urlencode($redirect) . '&notice=favorite');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if ($product_id <= 0) {
    header('Location: ' . $redirect_location);
    exit;
}

toggleFavorite($pdo, $user_id, $product_id);

unset($_SESSION['favorite_message']);

header('Location: ' . $redirect_location);
exit;
