<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY id");
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
    <title>關於我們 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">關於我們</h1>
                <p class="section-subtitle">以騎乘安全與選購體驗為核心，提供更清楚的安全帽資訊與服務</p>
            </div>
            <div class="product-card" style="max-width: 900px; margin: 0 auto;">
                <div class="product-info" style="text-align: left;">
                    <h2 class="product-name">品牌理念</h2>
                    <p>HelmetVRse 專注於提供完整的安全帽產品與知識內容，協助每位騎士找到合適的裝備。</p>
                    <h2 class="product-name">服務內容</h2>
                    <p>我們提供商品選購、尺寸教學、保養指南與常見問題整理，讓購物流程更清楚、使用更安心。</p>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
