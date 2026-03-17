<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

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

$guide_cards = [
    ['title' => '安全帽尺寸', 'href' => 'helmet_size.php', 'image' => 'https://placehold.co/640x420/E6E8F0/243047?text=Helmet+Size'],
    ['title' => '安全帽知識', 'href' => 'helmet_knowledge.php', 'image' => 'https://placehold.co/640x420/E6E8F0/243047?text=Helmet+Knowledge'],
    ['title' => '頭圍量測教學', 'href' => 'head_measure.php', 'image' => 'https://placehold.co/640x420/E6E8F0/243047?text=Head+Measure'],
    ['title' => '安全帽保養教學', 'href' => 'helmet_care.php', 'image' => 'https://placehold.co/640x420/E6E8F0/243047?text=Helmet+Care'],
    ['title' => '常見問題 FAQ', 'href' => 'faq.php', 'image' => 'https://placehold.co/640x420/E6E8F0/243047?text=FAQ'],
    ['title' => '優惠券專區', 'href' => 'coupons.php', 'image' => 'https://placehold.co/640x420/E6E8F0/243047?text=Coupons'],
    ['title' => '退貨政策', 'href' => 'return_policy.php', 'image' => 'https://placehold.co/640x420/E6E8F0/243047?text=Return+Policy'],
];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>購物指南 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">購物指南</h1>
                <p class="section-subtitle">請選擇欲查看的主題</p>
            </div>

            <div class="products-grid">
                <?php foreach ($guide_cards as $card): ?>
                    <article class="product-card">
                        <a href="<?php echo htmlspecialchars($card['href']); ?>" style="text-decoration: none; color: inherit; display: block; height: 100%;">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($card['image']); ?>" alt="<?php echo htmlspecialchars($card['title']); ?>">
                            </div>
                            <div class="product-info">
                                <h2 class="product-name"><?php echo htmlspecialchars($card['title']); ?></h2>
                            </div>
                        </a>
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
                        <li><a href="return_policy.php">退貨政策</a></li>
                        <li><a href="shipping.php">運送說明</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">聯絡我們</h3>
                    <ul class="footer-links">
                        <li>電話：02-2905-2000</li>
                        <li>Email：helmetvrsefju@gmail.com</li>
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
