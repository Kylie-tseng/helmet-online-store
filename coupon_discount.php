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
$validity_text = '領取後 3 個月內有效';

if ($is_logged_in && $is_claimed) {
    $claimed_at = getUserCouponClaimedAt($pdo, (int)$_SESSION['user_id'], $coupon_code);
    if (!empty($claimed_at)) {
        try {
            $start_at = (new DateTime((string)$claimed_at))->setTime(0, 0);
            $end_at = (clone $start_at)->modify('+3 months')->setTime(23, 59);
            $validity_text = $start_at->format('Y-m-d H:i') . ' - ' . $end_at->format('Y-m-d H:i');
        } catch (Exception $e) {
            $validity_text = '領取後 3 個月內有效';
        }
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
    // ignore
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>滿額折扣活動 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/style.css')); ?>">
</head>
<body class="offer-detail-page offer-new-member-page coupon-detail-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="nm-simple-page">
        <div class="container nm-container">
            <section class="nm-simple-coupon">
                <p class="nm-simple-coupon-code">滿額折扣活動</p>
                <p class="nm-simple-coupon-title">滿 NT$2000 折 NT$300</p>
                <p class="nm-simple-coupon-subtitle">優惠碼 <?php echo htmlspecialchars($coupon_code); ?></p>
            </section>

            <?php if ($coupon_message !== ''): ?>
                <div class="cart-message <?php echo htmlspecialchars($coupon_message_type); ?>">
                    <?php echo htmlspecialchars($coupon_message); ?>
                </div>
            <?php endif; ?>

            <section class="nm-simple-list">
                <div class="nm-simple-list-item">
                    <h2>有效期限</h2>
                    <p><?php echo htmlspecialchars($validity_text); ?></p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>優惠內容</h2>
                    <p>使用優惠碼 <?php echo htmlspecialchars($coupon_code); ?>，單筆消費滿 NT$2000 可折抵 NT$300。</p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>使用條件</h2>
                    <p>每個會員帳號限領一次，未達門檻時系統不會套用優惠。</p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>付款方式</h2>
                    <p>依結帳頁可用付款方式為準，折抵結果以系統計算顯示為準。</p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>適用範圍</h2>
                    <p>適用於本網站商品訂單，實際適用條件依訂單內容判定。</p>
                </div>
            </section>

            <section class="nm-simple-steps">
                <h2>使用方式</h2>
                <div class="nm-simple-steps-inline">
                    <span>1 登入會員</span>
                    <span>2 領取優惠</span>
                    <span>3 結帳輸入優惠碼</span>
                </div>
            </section>

            <section class="nm-simple-status" id="claim">
                <h2>優惠券狀態</h2>
                <?php if ($is_logged_in): ?>
                    <p>帳號：<?php echo htmlspecialchars($_SESSION['user_name'] ?? '會員'); ?></p>
                    <?php if ($is_claimed): ?>
                        <p class="cart-message success">已領取優惠券（<?php echo htmlspecialchars($coupon_code); ?>）</p>
                        <div class="nm-simple-actions">
                            <a href="products.php" class="nm-simple-btn nm-simple-btn-secondary">前往購物</a>
                        </div>
                    <?php else: ?>
                        <p>尚未領取優惠券</p>
                        <div class="nm-simple-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="claim_coupon">
                                <button type="submit" class="nm-simple-btn nm-simple-btn-primary">立即領取優惠券</button>
                            </form>
                            <a href="products.php" class="nm-simple-btn nm-simple-btn-secondary">前往購物</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p>請先登入會員後再領取此優惠券。</p>
                    <div class="nm-simple-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_discount.php'); ?>" class="nm-simple-btn nm-simple-btn-primary">前往登入</a>
                        <a href="register.php" class="nm-simple-btn nm-simple-btn-secondary">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <div class="nm-simple-fixed-cta">
        <a href="#claim" class="claim-btn">立即領取優惠</a>
    </div>
</body>
</html>
