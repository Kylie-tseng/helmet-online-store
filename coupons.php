<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

$today = new DateTime('today');
$coupon_activities = [
    [
        'tag' => '新會員優惠',
        'title' => '新會員專屬優惠',
        'benefit' => '滿 NT$500 折 NT$100',
        'code' => 'NEW100',
        'validity' => $today->format('Y-m-d') . ' ～ ' . $today->modify('+6 months')->format('Y-m-d'),
        'detail_url' => 'coupon_new_member.php',
        'claim_url' => 'coupon_new_member.php#claim'
    ],
    [
        'tag' => '限時折扣',
        'title' => '安全帽週年慶',
        'benefit' => '全館商品 9 折',
        'code' => 'HELMET10',
        'validity' => '活動期間請見詳細頁說明',
        'detail_url' => 'coupon_anniversary.php',
        'claim_url' => 'coupon_anniversary.php#claim'
    ],
    [
        'tag' => '滿額折扣',
        'title' => '滿額折扣活動',
        'benefit' => '滿 NT$2000 折 NT$300',
        'code' => 'SAVE300',
        'validity' => '領取後 3 個月內有效',
        'detail_url' => 'coupon_discount.php',
        'claim_url' => 'coupon_discount.php#claim'
    ],
    [
        'tag' => '節慶活動',
        'title' => '騎士節活動',
        'benefit' => '全館商品 8 折',
        'code' => 'RIDER20',
        'validity' => '活動期間請見詳細頁說明',
        'detail_url' => 'coupon_rider_day.php',
        'claim_url' => 'coupon_rider_day.php#claim'
    ],
    [
        'tag' => '免運活動',
        'title' => '滿三千免運',
        'benefit' => '全站消費滿 NT$3000 即享免運',
        'code' => null,
        'validity' => '活動期間請見詳細頁說明',
        'detail_url' => 'coupon_free_shipping.php',
        'claim_url' => 'coupon_free_shipping.php#claim'
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
<body class="coupon-page-directory">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="coupon-page-shell">
        <div class="container coupon-page-container">
            <section class="coupon-page-header">
                <h1>優惠券專區</h1>
                <p>一次掌握目前所有活動優惠與折扣資訊</p>
            </section>

            <section class="coupon-list-panel" aria-label="優惠券總覽列表">
                <?php foreach ($coupon_activities as $activity): ?>
                    <article class="coupon-list-item">
                        <div class="coupon-item-content">
                            <p class="coupon-item-tag"><?php echo htmlspecialchars($activity['tag']); ?></p>
                            <h2 class="coupon-item-title"><?php echo htmlspecialchars($activity['title']); ?></h2>
                            <p class="coupon-item-benefit"><?php echo htmlspecialchars($activity['benefit']); ?></p>

                            <div class="coupon-item-meta">
                                <p>
                                    <span class="coupon-meta-label">優惠碼</span>
                                    <span class="coupon-meta-value">
                                        <?php echo htmlspecialchars($activity['code'] ?? '免輸入，系統自動套用'); ?>
                                    </span>
                                </p>
                                <p>
                                    <span class="coupon-meta-label">有效期限</span>
                                    <span class="coupon-meta-value"><?php echo htmlspecialchars($activity['validity']); ?></span>
                                </p>
                            </div>
                        </div>

                        <div class="coupon-item-action">
                            <div class="coupon-item-actions">
                                <a href="<?php echo htmlspecialchars($activity['claim_url']); ?>" class="coupon-item-btn coupon-item-btn-primary">立即領取優惠</a>
                                <a href="<?php echo htmlspecialchars($activity['detail_url']); ?>" class="coupon-item-btn coupon-item-btn-secondary">查看詳情</a>
                            </div>
                        </div>
                <?php endforeach; ?>
            </section>
        </div>
    </main>

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
