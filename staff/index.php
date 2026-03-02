<?php
require_once '../config.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 檢查角色
if ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>員工後台 - HelmetVRse</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- 頂部公告橫幅 -->
    <div class="announcement-bar">
        <div class="announcement-content">
            員工後台管理系統
        </div>
    </div>

    <!-- 導覽列 -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="../index.php">HelmetVRse</a>
            </div>
            <div class="nav-right">
                <a href="../index.php" style="color: #FFFFFF; text-decoration: none; font-size: 14px;">返回首頁</a>
                <a href="../logout.php" style="color: #FFFFFF; text-decoration: none; font-size: 14px; margin-left: 20px;">登出</a>
            </div>
        </div>
    </nav>

    <!-- Dashboard 內容 -->
    <div class="dashboard-container">
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Staff Dashboard</h1>
                <p class="dashboard-subtitle">歡迎，<?php echo htmlspecialchars($_SESSION['user_name']); ?>！</p>
            </div>

            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <h2 class="card-title">訂單管理</h2>
                    <p class="card-content">處理客戶訂單</p>
                    <a href="orders.php" class="btn">管理訂單</a>
                </div>

                <div class="dashboard-card">
                    <h2 class="card-title">商品管理</h2>
                    <p class="card-content">管理商品資訊</p>
                    <a href="products.php" class="btn">管理商品</a>
                </div>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="dashboard-card">
                    <h2 class="card-title">系統管理</h2>
                    <p class="card-content">前往完整管理後台</p>
                    <a href="../admin/index.php" class="btn">管理後台</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3 class="footer-title">關於我們</h3>
                    <ul class="footer-links">
                        <li><a href="../about.php">公司簡介</a></li>
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

