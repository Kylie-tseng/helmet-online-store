<?php
date_default_timezone_set('Asia/Taipei');

require_once '../config.php';

require_once __DIR__ . '/includes/staff_layout.php';

require_once __DIR__ . '/../includes/order_status_helpers.php';

staffRequireAuth();

// 與 PHP「今天」一致：本連線以台灣時區解讀 orders.created_at 之 DATE()
try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Throwable $e) {
}

$today = date('Y-m-d');

$summary = [
    'pending_orders' => 0,
    'pending_returns' => 0,
    'low_stock_products' => 0,
    'today_sales' => 0.0,
    'today_orders' => 0,
    'inactive_products' => 0,
];

// 待處理訂單（與訂單處理頁「待處理」概覽同口徑：全表即時 COUNT）
try {
    $enumStatuses = app_orders_discover_status_enum($pdo);
    $ob = app_orders_compute_overview_buckets($pdo, $enumStatuses);
    $summary['pending_orders'] = (int) ($ob['pending'] ?? 0);
} catch (Throwable $e) {
}

// 待處理退貨（依申請 status；與 returns.php 待處理語意一致，不用 refund_status 代替）
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'return_requests'");
    $hasReturnRequests = (bool)$stmt->fetchColumn();
    if ($hasReturnRequests) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM return_requests WHERE status IN ('pending','pending_payment')");
        $summary['pending_returns'] = (int)$stmt->fetchColumn();
    }
} catch (Throwable $e) {
}

// 今日訂單＝今日建立且非 cancelled；今日營收＝同上日期內 paid／shipped／completed 之 final_amount 加總
try {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN status IN ('paid','shipped','completed') THEN final_amount ELSE 0 END), 0) AS sales,
                COUNT(CASE WHEN status <> 'cancelled' THEN 1 END) AS cnt
         FROM orders
         WHERE DATE(created_at) = :today"
    );
    $stmt->execute([':today' => $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['today_sales'] = (float)($row['sales'] ?? 0);
    $summary['today_orders'] = (int)($row['cnt'] ?? 0);
} catch (Throwable $e) {
}

// 低庫存商品「種數」：任一尺寸 stock < 5 之商品只算一次（與 product_catalog 低庫存篩選一致）
try {
    $stmt = $pdo->query('SELECT COUNT(DISTINCT product_id) FROM product_sizes WHERE stock < 5');
    $summary['low_stock_products'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

// 未上架／非上架中（status 非 active）
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status <> 'active'");
    $summary['inactive_products'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

staffPageStart($pdo, '店員工作入口', 'dashboard');
?>

<style>
    .staff-page .staff-dashboard-entry .staff-entry-grid {
        gap: 10px;
    }
    .staff-page .staff-dashboard-entry .staff-entry-card--stack {
        display: flex;
        flex-direction: column;
        min-height: 0;
        padding: 12px 14px;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(17, 24, 39, 0.06);
    }
    .staff-page .staff-dashboard-entry .staff-entry-card--stack h2 {
        margin: 0 0 6px;
        font-size: 16px;
    }
    .staff-page .staff-dashboard-entry .staff-entry-card--stack .staff-entry-desc {
        margin: 0;
        color: #555;
        font-size: 13px;
        line-height: 1.45;
    }
    .staff-page .staff-dashboard-entry .staff-entry-card--stack .staff-entry-meta-line {
        margin: 8px 0 0;
        color: #111;
        font-size: 13px;
        font-weight: 600;
    }
    .staff-page .staff-dashboard-entry .staff-entry-card--stack .staff-btn {
        margin-top: 10px;
        align-self: flex-start;
        height: 32px;
        padding: 0 12px;
        font-size: 13px;
        border-radius: 8px;
    }
</style>

<section class="staff-dashboard-entry" aria-label="店員工作入口">
    <div class="staff-entry-grid">
        <article class="staff-entry-card staff-entry-card--stack">
            <h2>訂單管理</h2>
            <p class="staff-entry-desc">處理待付款與待出貨等訂單狀態。</p>
            <p class="staff-entry-meta-line">待處理訂單：<?php echo number_format($summary['pending_orders']); ?> 筆</p>
            <a href="orders.php" class="staff-btn">前往功能</a>
        </article>

        <article class="staff-entry-card staff-entry-card--stack">
            <h2>商品管理</h2>
            <p class="staff-entry-desc">上架、下架與編輯商品與庫存。</p>
            <p class="staff-entry-meta-line">未上架商品：<?php echo number_format($summary['inactive_products']); ?> 件</p>
            <a href="products.php" class="staff-btn">前往功能</a>
        </article>

        <article class="staff-entry-card staff-entry-card--stack">
            <h2>低庫存提醒</h2>
            <p class="staff-entry-desc">任一品項尺寸庫存 &lt; 5 需留意補貨（與低庫存清單相同口徑）。</p>
            <p class="staff-entry-meta-line">低庫存商品：<?php echo number_format($summary['low_stock_products']); ?> 種</p>
            <a href="product_catalog.php?filter=low_stock" class="staff-btn">查看低庫存</a>
        </article>

        <article class="staff-entry-card staff-entry-card--stack">
            <h2>退貨申請</h2>
            <p class="staff-entry-desc">審核退貨與更新處理進度。</p>
            <p class="staff-entry-meta-line">待處理退貨：<?php echo number_format($summary['pending_returns']); ?> 件</p>
            <a href="returns.php" class="staff-btn">前往功能</a>
        </article>

        <article class="staff-entry-card staff-entry-card--stack">
            <h2>銷售與營運</h2>
            <p class="staff-entry-desc">本日已入帳訂單之營收與筆數。</p>
            <p class="staff-entry-meta-line">
                今日營收 <?php echo htmlspecialchars(staffCurrency((float)$summary['today_sales'])); ?>
                ／ 今日訂單 <?php echo number_format($summary['today_orders']); ?> 筆
            </p>
            <a href="sales_report.php" class="staff-btn">前往功能</a>
        </article>
    </div>
</section>

<?php staffPageEnd(); ?>
