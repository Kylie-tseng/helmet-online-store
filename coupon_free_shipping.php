<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$is_logged_in = isset($_SESSION['user_id']);

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
    <title>滿三千免運 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="offer-detail-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="offer-hero offer-shipping">
        <div class="offer-hero-bg"></div>
        <div class="offer-hero-overlay"></div>
        <div class="container offer-hero-content">
            <h1 class="offer-hero-title">滿三千免運</h1>
            <p class="offer-hero-highlight">全站滿 NT$3000 免運</p>
            <p class="offer-hero-text">一次購足更划算，達指定門檻即可享有全站免運優惠。</p>
            <div class="offer-hero-actions">
                <a href="products.php" class="promo-btn">立即選購</a>
                <a href="cart.php" class="promo-btn">前往購物車</a>
            </div>
        </div>
    </section>

    <main class="offer-detail-main">
        <div class="container">
            <section class="offer-summary">
                <h2 class="offer-section-title">活動重點</h2>
                <div class="offer-summary-grid">
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">免運門檻</div>
                        <p class="offer-summary-value">NT$3000</p>
                        <p class="offer-summary-text">單筆訂單商品小計滿 NT$3000，運費將自動折抵。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">適用方式</div>
                        <p class="offer-summary-value">自動套用</p>
                        <p class="offer-summary-text">免輸入優惠碼，系統依結帳金額自動判定是否免運。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">活動期間</div>
                        <p class="offer-summary-value">長期活動</p>
                        <p class="offer-summary-text">依網站公告與最終結帳條件計算為準。</p>
                    </article>
                </div>
            </section>

            <section class="offer-steps">
                <h2 class="offer-section-title">使用方式</h2>
                <div class="offer-steps-grid">
                    <article class="offer-step-card">
                        <div class="offer-step-number">01</div>
                        <h3 class="offer-step-title">加入商品</h3>
                        <p class="offer-step-text">先將欲購買商品加入購物車，確認小計累積金額。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">02</div>
                        <h3 class="offer-step-title">達成門檻</h3>
                        <p class="offer-step-text">當訂單小計達 NT$3000 以上，即符合免運活動條件。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">03</div>
                        <h3 class="offer-step-title">自動免運</h3>
                        <p class="offer-step-text">前往結帳時系統自動折抵運費，無須額外操作。</p>
                    </article>
                </div>
            </section>

            <section class="offer-notes">
                <h2 class="offer-section-title">注意事項</h2>
                <div class="offer-notes-card">
                    <ul class="offer-notes-list">
                        <li>免運依結帳時實際商品小計判定，優惠前後規則以系統為準。</li>
                        <li>配送區域與物流限制可能影響最終免運適用條件。</li>
                        <li>平台保留活動調整權利，請以最新公告為準。</li>
                    </ul>
                </div>
            </section>

            <section class="offer-claim-card">
                <h2 class="offer-section-title">會員提示</h2>
                <?php if ($is_logged_in): ?>
                    <p>您目前已登入會員，可直接前往購物車與結帳頁查看免運計算。</p>
                    <div class="offer-claim-actions">
                        <a href="cart.php" class="btn">前往購物車</a>
                        <a href="products.php" class="btn">繼續購物</a>
                    </div>
                <?php else: ?>
                    <p>您尚未登入，仍可先瀏覽活動內容；建議登入後享有完整會員功能。</p>
                    <div class="offer-claim-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_free_shipping.php'); ?>" class="btn">前往登入</a>
                        <a href="register.php" class="btn">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
