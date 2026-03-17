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
    <style>
        /* 簡單修飾退貨內容的樣式 */
        .return-content {
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            line-height: 1.8;
        }
        .return-info-list {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }
        .return-info-list li {
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">退貨服務</h1>
            </div>

            <div class="return-content">
                <p>若您有任何有關退換貨問題，麻煩寄送 email 與我們聯絡！</p>
                <p>我們會儘快與回覆您的來信，謝謝。</p>
                
                <ul class="return-info-list">
                    <li><strong>電話：</strong>02-2905-2000</li>
                    <li><strong>Email：</strong><a href="mailto:helmetvrsefju@gmail.com">helmetvrsefju@gmail.com</a></li>
                    <li><strong>地址：</strong>新北市新莊區中正路510號</li>
                </ul>
            </div>
        </div>
    </section>

    </body>
</html>