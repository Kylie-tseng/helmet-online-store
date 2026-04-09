<?php
require_once 'config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('favorites.php') . '&notice=favorite');
    exit;
}

if (!isset($_SESSION['compare_list']) || !is_array($_SESSION['compare_list'])) {
    $_SESSION['compare_list'] = [];
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$redirect = isset($input['redirect']) ? trim((string)$input['redirect']) : '';
if ($redirect === '' && isset($input['back'])) {
    $redirect = trim((string)$input['back']);
}
if ($redirect === '') {
    $redirect = 'favorites.php';
}
if ($redirect === '' || strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
    $redirect = 'favorites.php';
}

$product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
$action = isset($input['action']) ? trim((string)$input['action']) : 'add';
$compare_list = array_values(array_unique(array_map('intval', $_SESSION['compare_list'])));
$msg = '';

if ($product_id <= 0) {
    $msg = '商品資料無效';
} else {
    $exists = in_array($product_id, $compare_list, true);

    if ($action === 'remove') {
        if ($exists) {
            $compare_list = array_values(array_filter($compare_list, function ($id) use ($product_id) {
                return (int)$id !== $product_id;
            }));
            $msg = '已從比較清單移除';
        }
    } else {
        $isFavorited = false;
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = :user_id AND product_id = :product_id LIMIT 1");
            $stmt->execute([
                ':user_id' => (int)$_SESSION['user_id'],
                ':product_id' => $product_id
            ]);
            $isFavorited = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $isFavorited = false;
        }

        if (!$isFavorited) {
            $msg = '請先加入收藏，再進行商品比較';
        } elseif ($exists) {
            $msg = '已在比較清單中';
        } elseif (count($compare_list) >= 4) {
            $msg = '最多只能比較 4 個商品';
        } else {
            $compare_list[] = $product_id;
            $msg = '已加入比較清單';
        }
    }
}

$_SESSION['compare_list'] = $compare_list;
$_SESSION['compare_flash'] = $msg;

if ($redirect === '') {
    $redirect = 'favorites.php';
}
header('Location: ' . $redirect);
exit;
