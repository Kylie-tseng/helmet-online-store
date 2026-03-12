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
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">滿三千免運</h1>
                <p class="section-subtitle">全站消費滿 NT$3000 即享免運優惠</p>
            </div>

            <div class="products-grid">
                <article class="product-card">
                    <div class="product-info">
                        <h2 class="product-name">優惠內容</h2>
                        <p class="product-price">單筆訂單商品小計滿 NT$3000，自動免運。</p>
                        <p class="product-price">此活動不需領券，結帳時系統自動計算。</p>
                        <p class="product-price">有效期限：長期活動（依網站公告為準）</p>
                    </div>
                </article>
                <article class="product-card">
                    <div class="product-info">
                        <h2 class="product-name">使用方式</h2>
                        <p class="product-price">1. 將商品加入購物車。</p>
                        <p class="product-price">2. 當小計達 NT$3000，運費自動折抵。</p>
                        <p class="product-price">3. 無需輸入優惠碼。</p>
                    </div>
                </article>
            </div>

            <div class="profile-card" style="margin-top: 20px;">
                <h2 class="card-title">會員提示</h2>
                <?php if ($is_logged_in): ?>
                    <p>您目前已登入會員，可直接前往購物車與結帳頁查看免運計算。</p>
                    <a href="cart.php" class="btn">前往購物車</a>
                <?php else: ?>
                    <p>您尚未登入，仍可先瀏覽活動內容；建議登入後享有完整會員功能。</p>
                    <a href="login.php?redirect=<?php echo urlencode('coupon_free_shipping.php'); ?>" class="btn">前往登入</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
