<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$coupon_activities = [
    [
        'title' => '新會員優惠',
        'lines' => [
            '新會員註冊後即可使用優惠券',
            '優惠券：NEW100',
            '滿 500 元折抵 100 元'
        ]
    ],
    [
        'title' => '安全帽週年慶',
        'lines' => [
            '全館安全帽限時優惠',
            '優惠券：HELMET10',
            '全館商品 9 折'
        ]
    ],
    [
        'title' => '滿額折扣活動',
        'lines' => [
            '購物滿額即可使用優惠券',
            '優惠券：SAVE300',
            '滿 2000 元折抵 300 元'
        ]
    ],
    [
        'title' => '騎士節活動',
        'lines' => [
            '騎士節限定優惠',
            '優惠券：RIDER20',
            '全館商品 8 折'
        ]
    ],
    [
        'title' => '滿三千免運',
        'lines' => [
            '全站消費滿 NT$3000 即享免運優惠'
        ]
    ]
];

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
    <title>優惠券專區 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">優惠券專區</h1>
                <p class="section-subtitle">一次掌握目前所有活動優惠與折扣資訊</p>
            </div>
            <div class="products-grid">
                <?php foreach ($coupon_activities as $activity): ?>
                    <article class="product-card">
                        <div class="product-info">
                            <h2 class="product-name"><?php echo htmlspecialchars($activity['title']); ?></h2>
                            <?php foreach ($activity['lines'] as $line): ?>
                                <p class="product-price"><?php echo htmlspecialchars($line); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3 class="footer-title">關於我們</h3>
                    <ul class="footer-links">
                        <li><a href="about.php">公司簡介</a></li>
                        <li><a href="about.php#history">發展歷程</a></li>
                        <li><a href="about.php#mission">經營理念</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">顧客服務</h3>
                    <ul class="footer-links">
                        <li><a href="guide.php">購物指南</a></li>
                        <li><a href="faq.php">常見問題</a></li>
                        <li><a href="return.php">退換貨政策</a></li>
                        <li><a href="shipping.php">運送說明</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">聯絡我們</h3>
                    <ul class="footer-links">
                        <li>電話：02-2905-2000</li>
                        <li>Email：service@helmetvr.com</li>
                        <li>地址：新北市新莊區中正路510號</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>Powered by HelmetVRse</p>
            </div>
        </div>
    </footer>
</body>
</html>
