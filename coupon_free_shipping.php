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
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/style.css')); ?>">
</head>
<body class="offer-detail-page offer-new-member-page coupon-detail-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="nm-simple-page">
        <div class="container nm-container">
            <section class="nm-simple-coupon">
                <p class="nm-simple-coupon-code">滿三千免運</p>
                <p class="nm-simple-coupon-title">全站滿 NT$3000 免運</p>
                <p class="nm-simple-coupon-subtitle">免輸入優惠碼，自動套用</p>
            </section>

            <section class="nm-simple-list">
                <div class="nm-simple-list-item">
                    <h2>有效期限</h2>
                    <p>長期活動（依網站公告與系統判定為準）。</p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>優惠內容</h2>
                    <p>單筆訂單商品小計滿 NT$3000，系統自動折抵運費。</p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>使用條件</h2>
                    <p>訂單金額需達免運門檻，實際以結帳頁面金額為準。</p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>付款方式</h2>
                    <p>依結帳頁可用付款方式與物流條件為準。</p>
                </div>
                <div class="nm-simple-list-item">
                    <h2>適用範圍</h2>
                    <p>適用於本網站商品訂單，特定配送範圍可能不適用。</p>
                </div>
            </section>

            <section class="nm-simple-steps">
                <h2>使用方式</h2>
                <div class="nm-simple-steps-inline">
                    <span>1 選購商品</span>
                    <span>2 達到 NT$3000</span>
                    <span>3 結帳自動免運</span>
                </div>
            </section>

            <section class="nm-simple-status" id="claim">
                <h2>優惠狀態</h2>
                <?php if ($is_logged_in): ?>
                    <p>帳號：<?php echo htmlspecialchars($_SESSION['user_name'] ?? '會員'); ?></p>
                    <p>符合門檻即可自動套用免運</p>
                    <div class="nm-simple-actions">
                        <a href="cart.php" class="nm-simple-btn nm-simple-btn-primary">前往購物車</a>
                        <a href="products.php" class="nm-simple-btn nm-simple-btn-secondary">繼續購物</a>
                    </div>
                <?php else: ?>
                    <p>登入後可於購物車與結帳頁查看免運計算。</p>
                    <div class="nm-simple-actions">
                        <a href="login.php?redirect=<?php echo urlencode('coupon_free_shipping.php'); ?>" class="nm-simple-btn nm-simple-btn-primary">前往登入</a>
                        <a href="register.php" class="nm-simple-btn nm-simple-btn-secondary">前往註冊</a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <div class="nm-simple-fixed-cta">
        <a href="products.php" class="claim-btn">立即查看活動</a>
    </div>
</body>
</html>
