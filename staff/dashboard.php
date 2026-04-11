<?php

require_once '../config.php';

require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

$summary = [
    'pending_orders' => 0,
    'pending_returns' => 0,
    'low_stock_products' => 0,
    'today_sales' => 0.0,
    'today_orders' => 0,
    'inactive_products' => 0,
];

// 待處理訂單數
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $s = (string)$row['status'];
        $c = (int)$row['cnt'];
        if (in_array($s, ['pending', 'pending_payment'], true)) {
            $summary['pending_orders'] += $c;
        }
    }
} catch (Throwable $e) {
}

// 待處理退貨數
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'return_requests'");
    $hasReturnRequests = (bool)$stmt->fetchColumn();
    if ($hasReturnRequests) {
        $hasRefundStatusColumn = false;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM return_requests");
            foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if ((string)($c['Field'] ?? '') === 'refund_status') {
                    $hasRefundStatusColumn = true;
                    break;
                }
            }
        } catch (Throwable $e) {
            $hasRefundStatusColumn = false;
        }

        if ($hasRefundStatusColumn) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM return_requests WHERE refund_status = 'pending_refund'");
            $summary['pending_returns'] = (int)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM return_requests WHERE status IN ('pending','pending_payment')");
            $summary['pending_returns'] = (int)$stmt->fetchColumn();
        }
    }
} catch (Throwable $e) {
}

// 今日營收、今日訂單數（與營收口徑一致：已付款／已出貨／已完成）
try {
    $stmt = $pdo->query(
        "SELECT COALESCE(SUM(final_amount), 0) AS sales,
                COUNT(*) AS cnt
         FROM orders
         WHERE status IN ('paid','shipped','completed')
           AND DATE(created_at) = CURDATE()"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $summary['today_sales'] = (float)($row['sales'] ?? 0);
    $summary['today_orders'] = (int)($row['cnt'] ?? 0);
} catch (Throwable $e) {
}

// 低庫存商品數（總庫存 ≤ 5）
try {
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT p.id, COALESCE(SUM(ps.stock), 0) AS total_stock
            FROM products p
            LEFT JOIN product_sizes ps ON ps.product_id = p.id
            GROUP BY p.id
        ) t WHERE t.total_stock <= 5"
    );
    $summary['low_stock_products'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
}

// 未上架商品數（status = inactive）
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'inactive'");
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
            <p class="staff-entry-desc">總庫存 ≤ 5 的商品需留意補貨。</p>
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
