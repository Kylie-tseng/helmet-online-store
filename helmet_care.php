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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安全帽保養教學 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="helmet-care-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="care-shell">
        <div class="container">
            <header class="care-hero page-hero-header">
                <h1 class="page-hero-title">安全帽保養教學</h1>
                <p class="page-hero-subtitle">良好的保養習慣不僅維持衛生，更能延長安全帽的使用壽命。</p>
            </header>

            <div class="care-content">
                <section class="care-section">
                    <article class="care-card">
                        <h2 class="care-title">鏡片與帽殼清潔</h2>
                        <ul class="care-list">
                            <li><strong class="care-label">步驟 1｜清水沖洗</strong>先用清水沖洗掉表面灰塵與砂石，避免擦拭時產生刮痕。</li>
                            <li><strong class="care-label">步驟 2｜溫和清潔</strong>使用中性清潔劑（如洗碗精）搭配軟棉布輕拭。避免使用強酸、強鹼或有機溶劑。</li>
                            <li><strong class="care-label">步驟 3｜細節處理</strong>通風口與接縫處可用軟毛牙刷輕刷，鏡片則需水平輕拭以保護塗層。</li>
                            <li><strong class="care-label">步驟 4｜自然陰乾</strong>清潔後以吸水布壓乾水分，放置於通風陰涼處，嚴禁強光曝曬。</li>
                        </ul>
                    </article>
                </section>

                <section class="care-section">
                    <article class="care-card">
                        <h2 class="care-title">內襯洗滌與除臭</h2>
                        <ul class="care-list">
                            <li><strong class="care-label">重點 1｜定期拆洗</strong>建議 1-2 個月拆洗一次，汗水積累會導致內襯材質劣化並產生異味。</li>
                            <li><strong class="care-label">重點 2｜溫水手洗</strong>使用稀釋後的中性洗劑，以手輕壓出髒汙，避免用力搓揉導致變形。</li>
                            <li><strong class="care-label">重點 3｜晾曬技巧</strong>內襯嚴禁烘乾。應在通風處自然陰乾，若想加速乾燥可使用電風扇。</li>
                            <li><strong class="care-label">重點 4｜日常保養</strong>每次騎乘後可使用消臭噴霧，並將安全帽置於透氣通風處。</li>
                        </ul>
                    </article>
                </section>

                <section class="care-warning-box">
                    <h3>保養禁忌提醒</h3>
                    <ul class="care-warning-list">
                        <li><strong>嚴禁日曬：</strong>紫外線會加速保麗龍 (EPS) 材質脆化，大幅降低安全性。</li>
                        <li><strong>禁用揮發性溶劑：</strong>去漬油、汽油會損壞帽殼漆面與塑膠結構。</li>
                        <li><strong>不可放置在機車後車廂：</strong>車廂內高溫不通風，是內襯滋生細菌與保麗龍變質的溫床。</li>
                    </ul>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
