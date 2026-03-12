<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$coupon_code = 'SAVE300';
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
    <title>滿額折扣活動 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">滿額折扣活動</h1>
                <p class="section-subtitle">滿 2000 元折抵 300 元（優惠券代碼：SAVE300）</p>
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
                        <p class="product-price">套用 SAVE300，單筆消費滿 NT$2000 可折抵 NT$300。</p>
                        <p class="product-price">折抵金額固定，不可重複使用。</p>
                        <p class="product-price">有效期限：2025-01-01 ~ 2099-12-31</p>
                    </div>
                </article>
                <article class="product-card">
                    <div class="product-info">
                        <h2 class="product-name">使用條件</h2>
                        <p class="product-price">需先領取 SAVE300 優惠券。</p>
                        <p class="product-price">結帳金額需達最低門檻 NT$2000。</p>
                        <p class="product-price">每個會員帳號限領一次。</p>
                    </div>
                </article>
            </div>

            <div class="profile-card" style="margin-top: 20px;">
                <h2 class="card-title">領取操作</h2>
                <?php if ($is_logged_in): ?>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success">您已領取 SAVE300 優惠券。</p>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="claim_coupon">
                            <button type="submit" class="btn">立即領取 SAVE300</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <p>請先登入會員後再領取此優惠券。</p>
                    <a href="login.php?redirect=<?php echo urlencode('coupon_discount.php'); ?>" class="btn">前往登入</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
