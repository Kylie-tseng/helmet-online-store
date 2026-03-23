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
    <title>安全帽知識 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="knowledge-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="knowledge-shell">
        <div class="container">
            <header class="knowledge-hero page-hero-header">
                <h1 class="page-hero-title">安全帽知識</h1>
                <p class="page-hero-subtitle">從帽型、認證到尺寸挑選，快速掌握選購安全帽的重要重點。</p>
            </header>

            <div class="knowledge-content">
                <div class="knowledge-grid">
                    <section class="knowledge-section">
                        <h2 class="knowledge-section-title">帽型怎麼挑？</h2>
                        <p class="knowledge-section-intro">不同騎乘需求對應不同的防護等級：</p>
                        <table class="knowledge-table">
                            <tr><th>帽型</th><th>保護力</th><th>通風性</th></tr>
                            <tr><td>全罩式</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐</td></tr>
                            <tr><td>3/4 罩</td><td>⭐⭐⭐</td><td>⭐⭐⭐⭐</td></tr>
                            <tr><td>半罩式</td><td>⭐⭐⭐⭐</td><td>⭐⭐⭐</td></tr>
                        </table>
                    </section>

                    <section class="knowledge-section">
                        <h2 class="knowledge-section-title">認證標誌大解密</h2>
                        <ul class="knowledge-list">
                            <li><strong>CNS (台灣)</strong>：在地銷售必備基礎認證。</li>
                            <li><strong>DOT (美國)</strong>：北美廣泛採用的安全標準。</li>
                            <li><strong>ECE 22.06</strong>：目前全球最嚴格的歐盟測試。</li>
                            <li><strong>SNELL</strong>：專為賽事競技設計的最高標準。</li>
                        </ul>
                    </section>

                    <section class="knowledge-section">
                        <h2 class="knowledge-section-title">找到你的 Perfect Fit</h2>
                        <p>我們提供 <span class="knowledge-highlight">S、M、L、XL</span> 完整尺寸。</p>
                        <p><strong>測量步驟：</strong></p>
                        <ol class="knowledge-steps">
                            <li>使用軟尺環繞眉毛上方約 1 公分處。</li>
                            <li>水平繞過後腦勺最突出的位置。</li>
                            <li>多量測幾次取最大值，確保長途佩戴不壓迫。</li>
                        </ol>
                    </section>
                </div>

                <section class="knowledge-note-section">
                    <h2 class="knowledge-section-title">小叮嚀：安全帽也有壽命</h2>
                    <p>安全帽的保修期通常為 <span class="knowledge-highlight">3-5 年</span>，若曾發生過強力撞擊，即使外觀無損也建議立即更換，因為內部緩衝結構可能已經受損。</p>
                    <p>合適的安全帽有助於更安全、更舒適的騎乘。
選擇過小—可能會引起疼痛，從而導致危險的分心，
選擇過大—在碰撞中可能無法完全保護騎士的頭部。</p>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
