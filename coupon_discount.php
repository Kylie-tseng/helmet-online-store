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
<body class="offer-detail-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="offer-hero offer-discount">
        <div class="offer-hero-bg"></div>
        <div class="offer-hero-overlay"></div>
        <div class="container offer-hero-content">
            <h1 class="offer-hero-title">滿額折扣活動</h1>
            <p class="offer-hero-highlight">滿 NT$2000 折 NT$300</p>
            <p class="offer-hero-text">購物滿額即可享受限時回饋，讓每次升級裝備都更值得。</p>
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
                        <div class="offer-summary-label">折抵金額</div>
                        <p class="offer-summary-value">NT$300</p>
                        <p class="offer-summary-text">套用 SAVE300，單筆消費滿 NT$2000 即可折抵。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">消費門檻</div>
                        <p class="offer-summary-value">NT$2000</p>
                        <p class="offer-summary-text">需先達最低門檻，系統才會成功套用折扣。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">活動期間</div>
                        <p class="offer-summary-value">2025-2099</p>
                        <p class="offer-summary-text">2025-01-01 至 2099-12-31，每會員限領取一次。</p>
                    </article>
                </div>
            </section>

            <section class="offer-steps">
                <h2 class="offer-section-title">使用方式</h2>
                <div class="offer-steps-grid">
                    <article class="offer-step-card">
                        <div class="offer-step-number">01</div>
                        <h3 class="offer-step-title">領取 SAVE300</h3>
                        <p class="offer-step-text">先在本頁完成優惠券領取，領取後才可在結帳時使用。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">02</div>
                        <h3 class="offer-step-title">選購並達門檻</h3>
                        <p class="offer-step-text">加入商品後確認小計達 NT$2000，避免結帳時無法折抵。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">03</div>
                        <h3 class="offer-step-title">結帳套用折抵</h3>
                        <p class="offer-step-text">在結帳頁輸入 SAVE300，即可完成 NT$300 折抵。</p>
                    </article>
                </div>
            </section>

            <section class="offer-notes">
                <h2 class="offer-section-title">注意事項</h2>
                <div class="offer-notes-card">
                    <ul class="offer-notes-list">
                        <li>此優惠券為固定折抵金額，不可重複套用於同一筆訂單。</li>
                        <li>優惠不得與其他折扣同時使用，實際折扣依系統計算為準。</li>
                        <li>若結帳金額低於門檻，系統將自動取消折抵。</li>
                    </ul>
                </div>
            </section>

            <section class="offer-claim-card" id="claim">
                <h2 class="offer-section-title">領取優惠</h2>
                <?php if ($is_logged_in): ?>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success">您已領取 SAVE300 優惠券。</p>
                        <div class="offer-claim-actions">
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php else: ?>
                        <p>領取後可於結帳頁輸入 SAVE300 使用滿額折抵。</p>
                        <div class="offer-claim-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="claim_coupon">
                                <button type="submit" class="btn">立即領取 SAVE300</button>
                            </form>
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>請先登入會員後再領取此優惠券。</p>
                    <div class="offer-claim-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_discount.php'); ?>" class="btn">前往登入</a>
                        <a href="register.php" class="btn">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
