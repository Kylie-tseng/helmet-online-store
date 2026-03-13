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
<body class="offer-detail-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="offer-hero offer-rider">
        <div class="offer-hero-bg"></div>
        <div class="offer-hero-overlay"></div>
        <div class="container offer-hero-content">
            <h1 class="offer-hero-title">騎士節活動</h1>
            <p class="offer-hero-highlight">全館商品 8 折</p>
            <p class="offer-hero-text">騎士節限定回饋，為每一趟騎行帶來更完整的裝備支持。</p>
            <div class="offer-hero-actions">
                <a href="products.php" class="promo-btn">前往購物</a>
                <a href="#claim" class="promo-btn">領取優惠</a>
            </div>
        </div>
    </section>

    <main class="offer-detail-main">
        <div class="container">
            <?php if ($coupon_message !== ''): ?>
                <div class="cart-message <?php echo htmlspecialchars($coupon_message_type); ?>">
                    <?php echo htmlspecialchars($coupon_message); ?>
                </div>
            <?php endif; ?>

            <section class="offer-summary">
                <h2 class="offer-section-title">活動重點</h2>
                <div class="offer-summary-grid">
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">折扣優惠</div>
                        <p class="offer-summary-value">8 折</p>
                        <p class="offer-summary-text">套用 RIDER20，可享 20% 折扣回饋。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">活動期間</div>
                        <p class="offer-summary-value">2025-2099</p>
                        <p class="offer-summary-text">2025-01-01 至 2099-12-31，限活動期間使用。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">使用資格</div>
                        <p class="offer-summary-value">會員限定</p>
                        <p class="offer-summary-text">每個會員帳號限領一次，不可重複領取。</p>
                    </article>
                </div>
            </section>

            <section class="offer-steps">
                <h2 class="offer-section-title">使用方式</h2>
                <div class="offer-steps-grid">
                    <article class="offer-step-card">
                        <div class="offer-step-number">01</div>
                        <h3 class="offer-step-title">領取 RIDER20</h3>
                        <p class="offer-step-text">先完成優惠券領取，並確認券已存入會員帳號。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">02</div>
                        <h3 class="offer-step-title">選購活動商品</h3>
                        <p class="offer-step-text">將需要的商品加入購物車，前往結帳頁進行折扣套用。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">03</div>
                        <h3 class="offer-step-title">結帳套用折扣</h3>
                        <p class="offer-step-text">輸入 RIDER20 後由系統計算，折扣結果以結帳顯示為準。</p>
                    </article>
                </div>
            </section>

            <section class="offer-notes">
                <h2 class="offer-section-title">注意事項</h2>
                <div class="offer-notes-card">
                    <ul class="offer-notes-list">
                        <li>活動期間限定，逾期後優惠券將無法套用。</li>
                        <li>優惠券不得與其他折扣併用，請以結帳頁可用狀態為準。</li>
                        <li>如有訂單取消或退款，優惠券規則依系統與平台公告處理。</li>
                    </ul>
                </div>
            </section>

            <section class="offer-claim-card" id="claim">
                <h2 class="offer-section-title">領取優惠</h2>
                <?php if ($is_logged_in): ?>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success">您已領取 RIDER20 優惠券。</p>
                        <div class="offer-claim-actions">
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php else: ?>
                        <p>點擊下方按鈕即可領取 RIDER20，結帳時可套用 8 折優惠。</p>
                        <div class="offer-claim-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="claim_coupon">
                                <button type="submit" class="btn">立即領取 RIDER20</button>
                            </form>
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>請先登入會員後再領取此優惠券。</p>
                    <div class="offer-claim-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_rider_day.php'); ?>" class="btn">前往登入</a>
                        <a href="register.php" class="btn">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
