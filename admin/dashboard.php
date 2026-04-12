<?php
date_default_timezone_set('Asia/Taipei');

require_once '../config.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 檢查角色
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../staff/includes/staff_layout.php';
staffRequireAuth();

try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Throwable $e) {
}

$today = date('Y-m-d');

// --- 管理者儀表板指標 ---
$today_sales = 0.0;
$today_orders = 0;
$total_members = 0;
$low_stock_products = 0;
$active_coupons = 0;
$staff_accounts = 0;

// 今日訂單數＝今日建立且非已取消；今日營收＝同日內 paid／shipped／completed 之 final_amount（與店員工作入口口徑一致）
try {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN status IN ('paid','shipped','completed') THEN final_amount ELSE 0 END), 0) AS sales,
                COUNT(CASE WHEN status <> 'cancelled' THEN 1 END) AS cnt
         FROM orders
         WHERE DATE(created_at) = :today"
    );
    $stmt->execute([':today' => $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $today_orders = (int) ($row['cnt'] ?? 0);
    $today_sales = (float) ($row['sales'] ?? 0);
} catch (Throwable $e) {
}

// 總會員數
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'");
    $total_members = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

// 低庫存商品數（<= 5）
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM (
                            SELECT p.id, COALESCE(SUM(ps.stock), 0) AS total_stock
                            FROM products p
                            LEFT JOIN product_sizes ps ON ps.product_id = p.id
                            GROUP BY p.id
                        ) t WHERE t.total_stock <= 5");
    $low_stock_products = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

// 啟用中優惠券數（is_active=1 且未過期）
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupons
                          WHERE is_active = 1
                            AND expire_date >= :today");
    $stmt->execute([':today' => $today]);
    $active_coupons = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

// 店員帳號數
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'");
    $staff_accounts = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

staffPageStart($pdo, '管理者工作入口', 'dashboard');
?>
<section class="staff-entry-grid staff-entry-grid--admin">
    <a href="members.php" class="staff-entry-card">
        <h2>總會員數</h2>
        <p>追蹤會員規模與成長。</p>
        <div class="staff-entry-meta">會員：<?php echo number_format((int)$total_members); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>

    <a href="staff_accounts.php" class="staff-entry-card">
        <h2>店員帳號數</h2>
        <p>管理店員與角色權限。</p>
        <div class="staff-entry-meta">店員：<?php echo number_format((int)$staff_accounts); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>

    <a href="products.php?filter=low_stock" class="staff-entry-card">
        <h2>低庫存商品</h2>
        <p>即時掌握需要補貨的商品。</p>
        <div class="staff-entry-meta">低庫存：<?php echo number_format((int)$low_stock_products); ?></div>
        <span class="staff-entry-cta">查看低庫存</span>
    </a>

    <a href="orders.php" class="staff-entry-card">
        <h2>今日訂單</h2>
        <p>今日新進訂單數量與營運概況。</p>
        <div class="staff-entry-meta">今日訂單：<?php echo number_format((int)$today_orders); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>

    <a href="sales.php" class="staff-entry-card">
        <h2>今日營收</h2>
        <p>快速查看今日營收與訂單概況。</p>
        <div class="staff-entry-meta">今日營收：<?php echo htmlspecialchars(staffCurrency((float)$today_sales)); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>

    <a href="coupons.php" class="staff-entry-card">
        <h2>啟用中優惠券</h2>
        <p>查看可用的優惠活動。</p>
        <div class="staff-entry-meta">啟用中：<?php echo number_format((int)$active_coupons); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>
</section>
<?php
staffPageEnd();
exit;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理後台 - HelmetVRse</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<!-- 導覽列 -->
    <nav class="navbar unified-navbar">
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
                <h1 class="dashboard-title">Admin Dashboard</h1>
                <p class="dashboard-subtitle">歡迎，<?php echo htmlspecialchars($_SESSION['user_name']); ?>！</p>
            </div>

            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <h2 class="card-title">商品管理</h2>
                    <p class="card-content">管理所有商品資訊</p>
                    <a href="products.php" class="btn">管理商品</a>
                </div>

                <div class="dashboard-card">
                    <h2 class="card-title">訂單管理</h2>
                    <p class="card-content">查看與處理訂單</p>
                    <a href="orders.php" class="btn">管理訂單</a>
                </div>

                <div class="dashboard-card">
                    <h2 class="card-title">會員管理</h2>
                    <p class="card-content">管理會員帳號</p>
                    <a href="users.php" class="btn">管理會員</a>
                </div>
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

