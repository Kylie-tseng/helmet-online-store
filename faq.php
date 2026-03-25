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
    <title>常見問題 FAQ - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="faq-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="faq-shell">
        <div class="container">
            <header class="faq-hero page-hero-header">
                <h1 class="page-hero-title">常見問題 FAQ</h1>
                <p class="page-hero-subtitle">整理購買、尺寸、保養與售後相關問題，快速找到你需要的答案。</p>
            </header>

            <div class="faq-content">
                <section class="faq-section">
                    <h2 class="faq-section-title">選購與尺寸</h2>

                    <details class="faq-item">
                        <summary class="faq-question">第一次買安全帽，該怎麼挑選？</summary>
                        <div class="faq-answer">
                            <p>可先從騎乘需求、帽型、尺寸與認證標準開始了解，建議先查看安全帽知識與頭圍量測教學。</p>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">尺寸太緊是正常的嗎？</summary>
                        <div class="faq-answer">
                            <p>新帽初戴時內襯較緊屬正常情況，但應為包覆感，不應有明顯頭痛或壓迫不適。</p>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">如果我的尺寸介於兩個尺寸之間怎麼辦？</summary>
                        <div class="faq-answer">
                            <p>可依頭型、是否配戴眼鏡及實際包覆感判斷，若接近上限通常建議選大一號再確認穩定度。</p>
                        </div>
                    </details>
                </section>

                <section class="faq-section">
                    <h2 class="faq-section-title">配送與訂單</h2>

                    <details class="faq-item">
                        <summary class="faq-question">下單後多久會出貨？</summary>
                        <div class="faq-answer">
                            <p>一般會在付款確認後依訂單順序安排出貨，實際時間依商品庫存與配送狀況為準。</p>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">如何查詢訂單狀態？</summary>
                        <div class="faq-answer">
                            <p>可登入會員後至訂單相關頁面查看，或依系統通知信件確認最新狀態。</p>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">可以取消訂單嗎？</summary>
                        <div class="faq-answer">
                            <p>若訂單尚未進入出貨流程，通常可申請取消；若已出貨則需依退換貨政策處理。</p>
                        </div>
                    </details>
                </section>

                <section class="faq-section">
                    <h2 class="faq-section-title">退換貨與售後</h2>

                    <details class="faq-item">
                        <summary class="faq-question">收到商品後可以退貨嗎？</summary>
                        <div class="faq-answer">
                            <p>可依平台退換貨政策辦理，申請前請先確認商品狀態、配件完整性與申請期限。</p>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">商品有瑕疵怎麼處理？</summary>
                        <div class="faq-answer">
                            <p>請盡快聯繫客服並提供訂單資訊與商品照片，方便協助判斷與後續處理。</p>
                        </div>
                    </details>
                </section>

                <section class="faq-section">
                    <h2 class="faq-section-title">保養與使用</h2>

                    <details class="faq-item">
                        <summary class="faq-question">安全帽多久需要更換一次？</summary>
                        <div class="faq-answer">
                            <p>一般建議約 3 至 5 年評估更換；若曾受撞擊，即使外觀正常也建議盡快更換。</p>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">鏡片可以直接用酒精擦嗎？</summary>
                        <div class="faq-answer">
                            <p>不建議，可能傷害鏡片表面處理，建議使用清水、中性清潔劑與柔軟布料清潔。</p>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">內襯多久洗一次比較好？</summary>
                        <div class="faq-answer">
                            <p>可依使用頻率調整，通常 1 至 2 個月清洗一次較合適，夏天或高頻使用可再增加頻率。</p>
                        </div>
                    </details>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
