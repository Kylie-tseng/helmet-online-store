<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY id");
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
    <title>關於我們 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="about-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="about-main">
        <section class="about-hero-section">
            <div class="about-hero-media">
                <img src="assets/images/index2.jpg" alt="HelmetVRse 品牌主視覺">
            </div>
            <div class="about-hero-overlay">
                <div class="about-container">
                    <p class="about-eyebrow">BRAND STORY</p>
                    <h1 class="about-hero-title">關於 HelmetVRse</h1>
                    <p class="about-hero-subtitle">以騎乘安全與選購體驗為核心，提供更清楚、更安心的安全帽資訊與服務。</p>
                </div>
            </div>
        </section>

        <section class="about-philosophy-section" id="history">
            <div class="about-container">
                <div class="about-philosophy-grid">
                    <article class="about-story-card">
                        <p class="about-eyebrow">OUR PHILOSOPHY</p>
                        <h2 class="about-section-title">以資訊整合結合 VR 體驗，重塑安全帽選購流程</h2>
                        <p>HelmetVRse 的核心，不只是彙整商品資訊與規格比較，更透過 VR 選購體驗，讓使用者在購買前即可先行模擬配戴與風格呈現。當選購不再只依賴想像與片段評論，判斷會更直覺，也更貼近真實需求。</p>
                        <p>我們希望把「資訊」與「體驗」整合成一套完整流程：先理解尺寸、用途與保養，再透過 VR 視覺化比較建立選擇信心。無論是第一次購買，或是日常升級裝備，都能有效降低試戴與選購的不確定性，做出更安心的決策。</p>
                    </article>
                    <figure class="about-story-image">
                        <img src="assets/images/index3.jpg" alt="騎士裝備與安全帽展示">
                    </figure>
                </div>
            </div>
        </section>

        <section class="about-vr-section">
            <div class="about-container">
                <div class="about-vr-grid">
                    <figure class="about-vr-media">
                        <img src="https://placehold.co/960x620/E6E8EB/2A2F36?text=VR+Shopping+Experience" alt="VR 選購體驗示意圖">
                    </figure>
                    <article class="about-vr-content">
                        <p class="about-eyebrow">VR EXPERIENCE</p>
                        <h2 class="about-section-title">VR 選購體驗</h2>
                        <p>透過 VR 互動情境，使用者可在同一個流程中模擬配戴不同安全帽款式，快速觀察外型比例、風格搭配與整體視覺感受。相較於僅看商品圖，VR 提供更具情境感的比較方式。</p>
                        <p>這種視覺化的選購模式，能有效降低實體試戴受限帶來的落差，並提升篩選效率與選擇準確度。從「看規格」進一步走向「先體驗再決定」，是 HelmetVRse 最重要的差異化價值。</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="about-services-section">
            <div class="about-container">
                <header class="about-section-header">
                    <p class="about-eyebrow">WHAT WE PROVIDE</p>
                    <h2 class="about-section-title">我們提供的服務與內容</h2>
                    <p class="about-section-subtitle">從挑選到日常使用，把你真正需要的資訊整理成一目了然的導覽。</p>
                </header>

                <div class="about-services-grid">
                    <article class="about-service-item">
                        <h3>商品選購參考</h3>
                        <p>依照騎乘情境、預算與風格整理商品資訊，協助你快速篩選適合自己的安全帽款式。</p>
                    </article>
                    <article class="about-service-item">
                        <h3>頭圍量測與尺寸觀念</h3>
                        <p>提供頭圍量測教學與尺寸判讀重點，降低尺寸不合或配戴不穩定的風險。</p>
                    </article>
                    <article class="about-service-item">
                        <h3>保養與使用知識</h3>
                        <p>整理內襯清潔、鏡片保養與日常維護建議，讓安全帽維持舒適、穩定與安全狀態。</p>
                    </article>
                    <article class="about-service-item">
                        <h3>常見問題整理</h3>
                        <p>針對新手常見疑問彙整 FAQ，讓你在比較與購買前，就能先掌握關鍵差異。</p>
                    </article>
                    <article class="about-service-item about-service-item-vr">
                        <h3>VR 體驗選購</h3>
                        <p>透過模擬配戴與視覺化比較，讓選購從靜態閱讀升級為直覺體驗，協助你更快找到風格與保護需求都契合的裝備。</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="about-why-section" id="mission">
            <div class="about-container">
                <header class="about-section-header">
                    <p class="about-eyebrow">WHY HELMETVRSE</p>
                    <h2 class="about-section-title">為什麼選擇 HelmetVRse</h2>
                </header>

                <div class="about-why-grid">
                    <article class="about-why-item">
                        <h3>資訊集中且易懂</h3>
                        <p>把原本分散、難懂的內容整合成同一套閱讀節奏，讓你更快找到重點。</p>
                    </article>
                    <article class="about-why-item">
                        <h3>以騎乘情境出發</h3>
                        <p>不只看規格，還會對照通勤、長途或休閒騎乘需求，幫助你做實際判斷。</p>
                    </article>
                    <article class="about-why-item">
                        <h3>選購到使用都可追蹤</h3>
                        <p>從挑選、配戴到保養都有對應內容，讓每一次購買都更有把握。</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="about-cta-section">
            <div class="about-container">
                <p class="about-cta-text">開始你的 VR 選購體驗</p>
                <div class="about-cta-actions">
                    <a href="products.php" class="about-cta-btn primary">前往 VR 商場</a>
                </div>
            </div>
        </section>
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
