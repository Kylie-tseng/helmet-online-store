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
    [
        'title' => '安全帽知識',
        'desc' => '認識安全帽種類、材質差異與選購重點。',
        'href' => 'helmet_knowledge.php',
    ],
    [
        'title' => '頭圍測量教學',
        'desc' => '了解正確測量方式，找到更適合自己的尺寸。',
        'href' => 'head_measure.php',
    ],
    [
        'title' => '安全帽保養教學',
        'desc' => '學會清潔、保存與更換內襯的方法。',
        'href' => 'helmet_care.php',
    ],
    [
        'title' => '尺寸挑選建議',
        'desc' => '依照頭型與配戴感受，選擇合適尺寸。',
        'href' => 'head_measure.php',
    ],
    [
        'title' => '購買流程說明',
        'desc' => '快速了解選購、加入購物車與結帳流程。',
        'href' => 'shipping.php',
    ],
    [
        'title' => '常見問題',
        'desc' => '整理購買前後最常遇到的問題與解答。',
        'href' => 'faq.php',
    ],
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
<body class="guide-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="guide-hero">
        <div class="container">
            <header class="guide-hero-inner page-hero-header">
                <h1 class="guide-hero-title page-hero-title">購物指南</h1>
                <p class="guide-hero-subtitle page-hero-subtitle">從挑選安全帽到日常保養，快速找到你需要的資訊。</p>
            </header>
        </div>
    </section>

    <section class="guide-grid-section">
        <div class="container">
            <div class="guide-grid">
                <?php foreach ($guide_cards as $card): ?>
                    <article class="guide-card">
                        <a href="<?php echo htmlspecialchars($card['href']); ?>" class="guide-card-link">
                            <div class="guide-card-content">
                                <h2 class="guide-card-title"><?php echo htmlspecialchars($card['title']); ?></h2>
                                <p class="guide-card-desc"><?php echo htmlspecialchars($card['desc']); ?></p>
                            </div>
                            <span class="guide-card-arrow" aria-hidden="true">→</span>
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
