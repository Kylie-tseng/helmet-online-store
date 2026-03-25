<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

// 抓取導覽列所需資料
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
    
    $stmt_parts = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt_parts->execute();
    $parts_category = $stmt_parts->fetch();
    $parts_category_id = $parts_category ? $parts_category['id'] : null;
} catch (PDOException $e) {
    $categories = [];
    $parts_category_id = null;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>騎士幸運星轉盤 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/wheel.css">
</head>
<body>
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="wheel-page-container">
        <section class="wheel-header">
            <h1>騎士幸運星</h1>
            <p>試試手氣！抽中你的專屬安全帽折扣碼</p>
        </section>

        <div class="wheel-game-area">
            <div class="wheel-pointer"></div>
                <div class="wheel-outer">
                    <div id="wheelImg" class="wheel"></div>
                </div>
            <button type="button" id="spinBtn" class="spin-main-button">立即抽獎</button>
        </div>
    </main>

    <script src="assets/js/lucky-wheel.js"></script>
</body>
</html>