<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$coupon_code = 'RIDER20';
$coupon_message = '';
$coupon_message_type = 'success';
$is_logged_in = isset($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_coupon') {
    if (!$is_logged_in) {
        $coupon_message = '請先登入會員再領取優惠券';
        $coupon_message_type = 'error';
    } else {
        $claim_result = claimUserCoupon($pdo, (int)$_SESSION['user_id'], $coupon_code);
        $coupon_message = $claim_result['message'];
        $coupon_message_type = $claim_result['success'] ? 'success' : 'warning';
    }
}

$is_claimed = $is_logged_in ? hasUserCoupon($pdo, (int)$_SESSION['user_id'], $coupon_code) : false;

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
    // ignore
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>騎士節活動 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">騎士節活動</h1>
                <p class="section-subtitle">全館商品 8 折（優惠券代碼：RIDER20）</p>
            </div>

            <?php if ($coupon_message !== ''): ?>
                <div class="cart-message <?php echo htmlspecialchars($coupon_message_type); ?>">
                    <?php echo htmlspecialchars($coupon_message); ?>
                </div>
            <?php endif; ?>

            <div class="products-grid">
                <article class="product-card">
                    <div class="product-info">
                        <h2 class="product-name">優惠內容</h2>
                        <p class="product-price">套用 RIDER20 可享指定活動 8 折優惠。</p>
                        <p class="product-price">折扣方式：百分比折扣（20%）。</p>
                        <p class="product-price">有效期限：2025-01-01 ~ 2099-12-31</p>
                    </div>
                </article>
                <article class="product-card">
                    <div class="product-info">
                        <h2 class="product-name">使用方式</h2>
                        <p class="product-price">先領取優惠券，再於購物車套用 RIDER20。</p>
                        <p class="product-price">本券每個會員帳號限領一次。</p>
                        <p class="product-price">詳情以結帳頁折扣計算結果為準。</p>
                    </div>
                </article>
            </div>

            <div class="profile-card" style="margin-top: 20px;">
                <h2 class="card-title">領取操作</h2>
                <?php if ($is_logged_in): ?>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success">您已領取 RIDER20 優惠券。</p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="claim_coupon">
                            <button type="submit" class="btn">立即領取 RIDER20</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p>請先登入會員後再領取此優惠券。</p>
                    <a href="login.php?redirect=<?php echo urlencode('coupon_rider_day.php'); ?>" class="btn">前往登入</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
