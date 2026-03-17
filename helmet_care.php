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
    <style>
        .maintenance-guide { max-width: 1100px; margin: 40px auto; padding: 0 20px; color: #333; }
        .m-section { display: flex; flex-wrap: wrap; gap: 40px; margin-bottom: 60px; align-items: flex-start; }
        .m-content { flex: 1; min-width: 300px; }
        .m-image-placeholder { flex: 1; min-width: 300px; background: #f4f4f4; border-radius: 15px; padding: 40px; text-align: center; border: 2px dashed #ddd; }
        
        .m-title { font-size: 1.8em; color: #2c3e50; margin-bottom: 20px; border-bottom: 3px solid #e67e22; display: inline-block; padding-bottom: 5px; }
        .m-list { list-style: none; padding: 0; }
        .m-list li { margin-bottom: 15px; padding-left: 30px; position: relative; line-height: 1.6; }
        .m-list li::before { content: "★"; position: absolute; left: 0; color: #e67e22; font-weight: bold; }
        
        .notice-box { background: #fff8e1; border-radius: 10px; padding: 25px; border: 1px solid #ffe082; margin-top: 30px; }
        .notice-box h3 { color: #f57c00; margin-top: 0; }
        
        .step-tag { background: #34495e; color: white; padding: 2px 10px; border-radius: 4px; font-size: 0.8em; margin-right: 8px; vertical-align: middle; }
    </style>
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">安全帽保養教學</h1>
                <p class="section-subtitle">良好的保養習慣不僅維持衛生，更能延長安全帽的使用壽命</p>
            </div>

            <div class="maintenance-guide">
                
                <div class="m-section">
                    <div class="m-content">
                        <h2 class="m-title">鏡片與帽殼清潔</h2>
                        <ul class="m-list">
                            <li><span class="step-tag">Step 1</span><strong>清水沖洗：</strong>先用清水沖洗掉表面灰塵與砂石，避免擦拭時產生刮痕。</li>
                            <li><span class="step-tag">Step 2</span><strong>溫和清潔：</strong>使用中性清潔劑（如洗碗精）搭配軟棉布輕拭。避免使用強酸、強鹼或有機溶劑。</li>
                            <li><span class="step-tag">Step 3</span><strong>細節處理：</strong>通風口與接縫處可用軟毛牙刷輕刷，鏡片則需水平輕拭以保護塗層。</li>
                            <li><span class="step-tag">Step 4</span><strong>自然陰乾：</strong>清潔後以吸水布壓乾水分，放置於通風陰涼處，嚴禁強光曝曬。</li>
                        </ul>
                    </div>
                    <div class="m-image-placeholder">
                        <i class="fas fa-spray-can-sparkles" style="font-size: 3em; color: #3498db; margin-bottom: 15px;"></i>
                        <p>鏡片屬於消耗品<br>若刮痕嚴重建議直接更換</p>
                    </div>
                </div>

                <div class="m-section" style="flex-direction: row-reverse;">
                    <div class="m-content">
                        <h2 class="m-title">內襯洗滌與除臭</h2>
                        <ul class="m-list">
                            <li><span class="step-tag">重點 1</span><strong>定期拆洗：</strong>建議 1-2 個月拆洗一次，汗水積累會導致內襯材質劣化並產生異味。</li>
                            <li><span class="step-tag">重點 2</span><strong>溫水手洗：</strong>使用稀釋後的中性洗劑，以手輕壓出髒汙，避免用力搓揉導致變形。</li>
                            <li><span class="step-tag">重點 3</span><strong>晾曬技巧：</strong>內襯嚴禁烘乾。應在通風處自然陰乾，若想加速乾燥可使用電風扇。</li>
                            <li><span class="step-tag">重點 4</span><strong>日常保養：</strong>每次騎乘後可使用消臭噴霧，並將安全帽置於透氣通風處。</li>
                        </ul>
                    </div>
                    <div class="m-image-placeholder">
                        <i class="fas fa-soap" style="font-size: 3em; color: #27ae60; margin-bottom: 15px;"></i>
                        <p>定期清洗內襯<br>有效預防頭皮過敏與毛囊炎</p>
                    </div>
                </div>

                <div class="notice-box">
                    <h3>⚠️ 保養禁忌提醒</h3>
                    <ul style="line-height: 1.8;">
                        <li><strong>嚴禁日曬：</strong>紫外線會加速保麗龍 (EPS) 材質脆化，大幅降低安全性。</li>
                        <li><strong>禁用揮發性溶劑：</strong>去漬油、汽油會損壞帽殼漆面與塑膠結構。</li>
                        <li><strong>不可放置在機車後車廂：</strong>車廂內高溫不通風，是內襯滋生細菌與保麗龍變質的溫床。</li>
                    </ul>
                </div>

            </div>
        </div>
    </section>
</body>
</html>