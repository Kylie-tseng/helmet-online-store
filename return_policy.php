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
    <title>退換貨政策 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="return-policy-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="return-policy-shell">
        <div class="container return-policy-container">
            <header class="return-policy-header page-hero-header">
                <h1 class="page-hero-title">退貨政策</h1>
                <p class="page-hero-subtitle">為保障您的購物權益，以下為 HelmetVRse 退換貨與退款說明，請於申請前先行閱讀。</p>
            </header>

            <section class="return-policy-panel" aria-label="退換貨政策內容">
                <article class="return-policy-item">
                    <h2>七日鑑賞期</h2>
                    <p>依《消費者保護法》規定，您可於商品到貨次日起七日內申請退貨。七日鑑賞期屬於「猶豫期」而非「試用期」，退回商品需保持全新狀態，且不得有配戴使用痕跡、異味或人為污損。</p>
                </article>

                <article class="return-policy-item">
                    <h2>退貨條件</h2>
                    <ul>
                        <li>商品須為未使用狀態，並保留完整外盒與配件。</li>
                        <li>原包裝、吊牌、贈品、發票與相關文件須一併退回。</li>
                        <li>若商品有刮痕、變形、污損或包裝缺件，可能影響退貨受理資格。</li>
                    </ul>
                </article>

                <article class="return-policy-item">
                    <h2>瑕疵商品與出貨錯誤</h2>
                    <p>若收到商品有瑕疵、尺寸內容不符或出貨錯誤，請於收貨後儘速聯繫客服。我們將協助您辦理退換貨，並依實際情況安排後續處理。</p>
                    <p>為加速作業，請提供訂單編號、問題描述與清晰照片（含商品本體、外盒與標籤）。</p>
                </article>

                <article class="return-policy-item">
                    <h2>退貨流程</h2>
                    <ol>
                        <li>聯絡客服提出申請，並提供訂單資料與退貨原因。</li>
                        <li>客服確認申請內容後，提供退貨方式與注意事項。</li>
                        <li>依指示完成包裝與寄回，待倉儲驗收後進入退款流程。</li>
                    </ol>
                </article>

                <article class="return-policy-item">
                    <h2>退款說明</h2>
                    <p>退款將依原付款方式辦理：信用卡交易將進行刷退，銀行轉帳將退回原指定帳戶。一般處理時間約為 7 至 14 個工作天，實際入帳時間仍以發卡銀行或金融機構作業為準。</p>
                </article>

                <article class="return-policy-item">
                    <h2>注意事項</h2>
                    <ul>
                        <li><strong class="return-policy-emphasis">本網站目前不提供實體門市現場退款服務。</strong></li>
                        <li>活動檔期商品、組合優惠與特殊專案可能適用不同退換貨條件，請以活動頁面公告為準。</li>
                        <li>若有任何疑問，歡迎聯繫客服：02-2905-2000 / helmetvrsefju@gmail.com。</li>
                    </ul>
                </article>
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