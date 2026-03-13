<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$coupon_code = 'HELMET10';
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
    <title>安全帽週年慶 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="offer-detail-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="offer-hero offer-anniversary">
        <div class="offer-hero-bg"></div>
        <div class="offer-hero-overlay"></div>
        <div class="container offer-hero-content">
            <h1 class="offer-hero-title">安全帽週年慶</h1>
            <p class="offer-hero-highlight">全館商品 9 折</p>
            <p class="offer-hero-text">全館安全帽限時優惠，為騎士帶來更完整的安全防護與風格選擇。</p>
            <div class="offer-hero-actions">
                <a href="products.php" class="promo-btn">立即選購</a>
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
                        <p class="offer-summary-value">9 折</p>
                        <p class="offer-summary-text">活動期間套用 HELMET10，全館商品享 10% 折扣。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">活動期間</div>
                        <p class="offer-summary-value">2025-2099</p>
                        <p class="offer-summary-text">2025-01-01 至 2099-12-31，依系統最終結帳判定。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">使用門檻</div>
                        <p class="offer-summary-value">NT$0</p>
                        <p class="offer-summary-text">無最低消費限制，每位會員帳號限領取一次。</p>
                    </article>
                </div>
            </section>

            <section class="offer-steps">
                <h2 class="offer-section-title">使用方式</h2>
                <div class="offer-steps-grid">
                    <article class="offer-step-card">
                        <div class="offer-step-number">01</div>
                        <h3 class="offer-step-title">領取優惠券</h3>
                        <p class="offer-step-text">先於本頁完成 HELMET10 領取，才能在結帳時套用。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">02</div>
                        <h3 class="offer-step-title">前往購物</h3>
                        <p class="offer-step-text">挑選欲購買的安全帽與配件，加入購物車後前往結帳。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">03</div>
                        <h3 class="offer-step-title">輸入套用</h3>
                        <p class="offer-step-text">結帳頁輸入 HELMET10，系統即自動計算 9 折後金額。</p>
                    </article>
                </div>
            </section>

            <section class="offer-notes">
                <h2 class="offer-section-title">注意事項</h2>
                <div class="offer-notes-card">
                    <ul class="offer-notes-list">
                        <li>每位會員帳號限領取一次，領取後請於有效期間內使用。</li>
                        <li>優惠券不得與其他優惠併用，實際可用條件以結帳頁顯示為準。</li>
                        <li>如訂單條件不符，系統將自動取消套用並恢復原價。</li>
                    </ul>
                </div>
            </section>

            <section class="offer-claim-card" id="claim">
                <h2 class="offer-section-title">領取優惠</h2>
                <?php if ($is_logged_in): ?>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success">您已領取 HELMET10 優惠券。</p>
                        <div class="offer-claim-actions">
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php else: ?>
                        <p>尚未領取 HELMET10，立即領取後即可在結帳時套用 9 折優惠。</p>
                        <div class="offer-claim-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="claim_coupon">
                                <button type="submit" class="btn">立即領取 HELMET10</button>
                            </form>
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>請先登入會員後再領取此優惠券。</p>
                    <div class="offer-claim-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_anniversary.php'); ?>" class="btn">前往登入</a>
                        <a href="register.php" class="btn">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
