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
    <style>
        .knowledge-container { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
        .knowledge-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-top: 30px; }
        .k-card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid #3498db; }
        .k-card h3 { color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .k-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.9em; }
        .k-table th, .k-table td { border-bottom: 1px solid #eee; padding: 10px; text-align: left; }
        .cert-list { list-style: none; padding: 0; }
        .cert-list li { margin-bottom: 10px; padding-left: 20px; position: relative; }
        .cert-list li::before { content: "✓"; position: absolute; left: 0; color: #27ae60; font-weight: bold; }
        .highlight { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">安全帽必備知識</h1>
                <p class="section-subtitle">挑選最適合你的守護，從了解開始</p>
            </div>

            <div class="knowledge-container">
                <div class="knowledge-grid">
                    <div class="k-card">
                        <h3><i class="fas fa-helmet-safety"></i> 帽型該怎麼挑？</h3>
                        <p>不同騎乘需求對應不同的防護等級：</p>
                        <table class="k-table">
                            <tr><th>帽型</th><th>保護力</th><th>通風性</th></tr>
                            <tr><td>全罩式</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐</td></tr>
                            <tr><td>3/4 罩</td><td>⭐⭐⭐</td><td>⭐⭐⭐⭐</td></tr>
                            <tr><td>可樂帽</td><td>⭐⭐⭐⭐</td><td>⭐⭐⭐</td></tr>
                        </table>
                    </div>

                    <div class="k-card">
                        <h3><i class="fas fa-shield-halved"></i> 認證標誌大解密</h3>
                        <ul class="cert-list">
                            <li><strong>CNS (台灣)</strong>：在地銷售必備基礎認證。</li>
                            <li><strong>DOT (美國)</strong>：北美廣泛採用的安全標準。</li>
                            <li><strong>ECE 22.06</strong>：目前全球最嚴格的歐盟測試。</li>
                            <li><strong>SNELL</strong>：專為賽事競技設計的最高標準。</li>
                        </ul>
                    </div>

                    <div class="k-card">
                        <h3><i class="fas fa-ruler"></i> 找到你的 Perfect Fit</h3>
                        <p>我們提供 <span class="highlight">S、M、L、XL</span> 完整尺寸。</p>
                        <p><strong>測量步驟：</strong></p>
                        <ol style="padding-left: 20px; font-size: 0.9em; line-height: 1.6;">
                            <li>使用軟尺環繞眉毛上方約 1 公分處。</li>
                            <li>水平繞過後腦勺最突出的位置。</li>
                            <li>多量測幾次取最大值，確保長途佩戴不壓迫。</li>
                        </ol>
                    </div>
                </div>

                <div class="k-card" style="margin-top: 30px; border-top-color: #e67e22;">
                    <h3><i class="fas fa-lightbulb"></i> 小叮嚀：安全帽也有壽命</h3>
                    <p>安全帽的保修期通常為 <span class="highlight">3-5 年</span>，若曾發生過強力撞擊，即使外觀無損也建議立即更換，因為內部緩衝結構可能已經受損。</p>
                    <p>合適的安全帽有助於更安全、更舒適的騎乘。
選擇過小—可能會引起疼痛，從而導致危險的分心，
選擇過大—在碰撞中可能無法完全保護騎士的頭部。</p>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
