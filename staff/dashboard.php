<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

$quickStats = [
    'pending_orders' => 0,
    'processing_orders' => 0,
    'inactive_products' => 0,
    'low_stock_products' => 0,
];

try {
    $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $s = (string)$row['status'];
        $c = (int)$row['cnt'];
        if (in_array($s, ['pending', 'pending_payment'], true)) {
            $quickStats['pending_orders'] += $c;
        }
        if (in_array($s, ['paid', 'shipped'], true)) {
            $quickStats['processing_orders'] += $c;
        }
    }
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'inactive'");
    $quickStats['inactive_products'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM (
                            SELECT p.id, COALESCE(SUM(ps.stock), 0) AS total_stock
                            FROM products p
                            LEFT JOIN product_sizes ps ON ps.product_id = p.id
                            GROUP BY p.id
                        ) t WHERE t.total_stock <= 5");
    $quickStats['low_stock_products'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

staffPageStart($pdo, '店員工作入口', 'dashboard');
?>
<section class="staff-entry-grid">
    <a href="orders.php" class="staff-entry-card">
        <h2>訂單處理</h2>
        <p>處理待確認、待出貨與訂單狀態更新。</p>
        <div class="staff-entry-meta">目前待處理：<?php echo number_format($quickStats['pending_orders']); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>
    <a href="products.php" class="staff-entry-card">
        <h2>商品管理</h2>
        <p>調整商品內容、上下架、價格與圖片。</p>
        <div class="staff-entry-meta">未上架商品：<?php echo number_format($quickStats['inactive_products']); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>
    <a href="products.php?filter=low_stock" class="staff-entry-card">
        <h2>低庫存提醒</h2>
        <p>即時檢視庫存低於或等於 5 的商品。</p>
        <div class="staff-entry-meta">低庫存商品：<?php echo number_format($quickStats['low_stock_products']); ?></div>
        <span class="staff-entry-cta">查看低庫存</span>
    </a>
    <a href="returns.php" class="staff-entry-card">
        <h2>退貨申請</h2>
        <p>追蹤退貨流程與退款狀態。</p>
        <div class="staff-entry-meta">以申請清單逐案處理</div>
        <span class="staff-entry-cta">前往功能</span>
    </a>
    <a href="sales_report.php" class="staff-entry-card">
        <h2>銷售統計</h2>
        <p>檢視銷售趨勢與熱銷商品。</p>
        <div class="staff-entry-meta">處理中訂單：<?php echo number_format($quickStats['processing_orders']); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>
    <?php if ((string)($_SESSION['role'] ?? '') === 'admin'): ?>
        <a href="../admin/dashboard.php" class="staff-entry-card">
            <h2>管理後台</h2>
            <p>前往完整管理者系統進行權限級操作。</p>
            <div class="staff-entry-meta">僅 admin 可見</div>
        </a>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>
