<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('order_success.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$display_order_id = null;
if (isset($_GET['order_id']) && (int)$_GET['order_id'] > 0) {
    $oid = (int)$_GET['order_id'];
    $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$oid, $user_id]);
    if ($stmt->fetch()) {
        $display_order_id = $oid;
    }
}

try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    if ($parts_category) {
        $parts_category_id = $parts_category['id'];
    }
} catch (PDOException $e) {
}

require_once 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訂單送出完成 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <div class="checkout-container">
        <div class="container" style="max-width: 640px; margin: 0 auto; padding: 2rem 1rem 4rem;">
            <h1 class="checkout-page-title" style="text-align: center; margin-bottom: 1.5rem;">訂單送出完成</h1>
            <div class="order-section" style="text-align: center; line-height: 1.7;">
                <p>感謝您的訂購。</p>
                <p>我們已收到您的訂單。</p>
                <p>訂單確認信已寄至您的信箱（若您有填寫 Email）。</p>
                <?php if ($display_order_id !== null): ?>
                    <p style="margin-top: 1.25rem;">
                        <strong>訂單編號：</strong>#<?php echo (int)$display_order_id; ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="form-actions" style="justify-content: center; flex-wrap: wrap; gap: 1rem; margin-top: 2rem;">
                <a href="profile.php?tab=orders" class="btn-primary">查看訂單管理</a>
                <a href="index.php" class="btn-secondary">繼續購物</a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>Powered by HelmetVRse</p>
            </div>
        </div>
    </footer>
</body>
</html>
