<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$coupon_code = 'NEW100';
$coupon_message = '';
$coupon_message_type = 'success';
$is_logged_in = isset($_SESSION['user_id']);
$user_profile = null;

if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, username, email FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
        $user_profile = $stmt->fetch();
    } catch (PDOException $e) {
        $user_profile = null;
    }
}

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
    <title>新會員優惠 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="offer-detail-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="offer-hero offer-new-member">
        <div class="offer-hero-bg"></div>
        <div class="offer-hero-overlay"></div>
        <div class="container offer-hero-content">
            <h1 class="offer-hero-title">新會員優惠</h1>
            <p class="offer-hero-highlight">滿 NT$500 折 NT$100</p>
            <p class="offer-hero-text">首次加入會員即可享有專屬折扣回饋，開啟你的騎士裝備之旅。</p>
            <div class="offer-hero-actions">
                <a href="products.php" class="promo-btn">前往購物</a>
                <a href="#claim" class="promo-btn">立即領取</a>
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
                        <div class="offer-summary-label">優惠內容</div>
                        <p class="offer-summary-value">折 NT$100</p>
                        <p class="offer-summary-text">優惠碼 NEW100，單筆消費滿 NT$500 可折抵 NT$100。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">適用對象</div>
                        <p class="offer-summary-value">新會員</p>
                        <p class="offer-summary-text">僅限會員帳號領取與使用，每個會員帳號限領一次。</p>
                    </article>
                    <article class="offer-summary-card">
                        <div class="offer-summary-label">有效期間</div>
                        <p class="offer-summary-value">2025-2099</p>
                        <p class="offer-summary-text">有效期限：2025-01-01 至 2099-12-31。</p>
                    </article>
                </div>
            </section>

            <section class="offer-steps">
                <h2 class="offer-section-title">使用方式</h2>
                <div class="offer-steps-grid">
                    <article class="offer-step-card">
                        <div class="offer-step-number">01</div>
                        <h3 class="offer-step-title">註冊並登入</h3>
                        <p class="offer-step-text">完成註冊後登入會員帳號，即可開始領取新會員專屬優惠。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">02</div>
                        <h3 class="offer-step-title">領取 NEW100</h3>
                        <p class="offer-step-text">點擊本頁領取按鈕，優惠券將綁定至你的會員帳號。</p>
                    </article>
                    <article class="offer-step-card">
                        <div class="offer-step-number">03</div>
                        <h3 class="offer-step-title">結帳折抵</h3>
                        <p class="offer-step-text">購物滿 NT$500 後於結帳輸入 NEW100，完成折扣套用。</p>
                    </article>
                </div>
            </section>

            <section class="offer-notes">
                <h2 class="offer-section-title">注意事項</h2>
                <div class="offer-notes-card">
                    <ul class="offer-notes-list">
                        <li>僅限新會員帳號使用，每位會員僅可領取一次。</li>
                        <li>需符合最低消費門檻才可折抵，實際判定以結帳頁為準。</li>
                        <li>優惠券不得與其他優惠併用，逾期將自動失效。</li>
                    </ul>
                </div>
            </section>

            <section class="offer-claim-card" id="claim">
                <h2 class="offer-section-title">優惠券狀態</h2>
                <?php if ($is_logged_in): ?>
                    <p>會員名稱：<?php echo htmlspecialchars($user_profile['name'] ?? $_SESSION['user_name'] ?? '會員'); ?></p>
                    <p>帳號：<?php echo htmlspecialchars($user_profile['username'] ?? ''); ?></p>
                    <p>Email：<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?></p>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success">已領取優惠券（NEW100）</p>
                        <div class="offer-claim-actions">
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php else: ?>
                        <p>領取狀態：尚未領取</p>
                        <div class="offer-claim-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="claim_coupon">
                                <button type="submit" class="btn">立即領取優惠券</button>
                            </form>
                            <a href="products.php" class="btn">前往購物</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>您尚未登入。登入後可領取 NEW100 優惠券。</p>
                    <div class="offer-claim-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_new_member.php'); ?>" class="btn">前往登入</a>
                        <a href="register.php" class="btn">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
