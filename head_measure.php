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
    <title>頭圍量測教學 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="measure-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="measure-shell">
        <div class="container">
            <header class="measure-hero page-hero-header">
                <h1 class="page-hero-title">頭圍量測教學</h1>
                <p class="page-hero-subtitle">選擇正確的尺寸是安全的第一步，請參考以下步驟進行測量。</p>
            </header>

            <div class="measure-content">
                <div class="measure-steps-grid">
                    <section class="measure-step-card">
                        <span class="measure-step-num">1</span>
                        <h3>準備工具</h3>
                        <p>準備一條布尺（軟尺）。若無布尺，可用線繩代替，測量後再用長直尺讀取長度。</p>
                    </section>
                    <section class="measure-step-card">
                        <span class="measure-step-num">2</span>
                        <h3>測量位置</h3>
                        <p>將布尺置於眉毛上方約 <span class="measure-highlight">1公分</span> 處，水平繞過耳後與後腦勺最突出的位置。</p>
                    </section>
                    <section class="measure-step-card">
                        <span class="measure-step-num">3</span>
                        <h3>讀取數值</h3>
                        <p>建議重複測量 2-3 次，取其中 <span class="measure-highlight">最大</span> 的數值作為參考依據。</p>
                    </section>
                </div>

                <section class="measure-size-section">
                    <h2 class="measure-size-title">HelmetVRse 標準尺寸對照表</h2>
                    <table class="measure-size-table">
                        <thead>
                            <tr>
                                <th>尺寸標籤</th>
                                <th>適合頭圍 (cm)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>S</strong></td>
                                <td>54 - 55 cm</td>
                            </tr>
                            <tr>
                                <td><strong>M</strong></td>
                                <td>56 - 57 cm</td>
                            </tr>
                            <tr>
                                <td><strong>L</strong></td>
                                <td>58 - 59 cm</td>
                            </tr>
                            <tr>
                                <td><strong>XL</strong></td>
                                <td>60 - 61 cm</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="measure-tip-section">
                    <h3>專業小叮嚀</h3>
                    <ul class="measure-tip-list">
                        <li><strong>新帽體感：</strong>新買的安全帽內襯較緊是正常的，佩戴時兩頰應有微壓迫感，但不應有「頭痛」的感覺。</li>
                        <li><strong>臉型影響：</strong>若您的臉型較寬或有戴眼鏡，建議在尺寸邊界時選擇大一號（例如測得 57.5cm，建議選 L）。</li>
                        <li><strong>安全第一：</strong>安全帽不可過鬆，若搖頭時帽子會晃動，代表保護力將大幅下降。</li>
                    </ul>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
