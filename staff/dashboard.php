<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

$quickStats = [
    'pending_orders' => 0,
    'inactive_products' => 0,
    'low_stock_products' => 0,
    'pending_returns' => 0,
    'today_orders' => 0,
    'today_sales' => 0.0,
    'hidden_reviews_count' => 0,
];

try {
    $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $s = (string)$row['status'];
        $c = (int)$row['cnt'];
        if (in_array($s, ['pending', 'pending_payment'], true)) {
            $quickStats['pending_orders'] += $c;
        }
    }
} catch (Throwable $e) {
}

// 已隱藏的評價數（供評價管理入口使用）
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
    $hasReviewsTable = (bool)$stmt->fetchColumn();
    if ($hasReviewsTable) {
        $cols = $pdo->query("SHOW COLUMNS FROM reviews")->fetchAll(PDO::FETCH_ASSOC);
        $hiddenCol = '';
        foreach ($cols as $c) {
            $field = (string)($c['Field'] ?? '');
            if ($field === 'is_hidden') {
                $hiddenCol = 'is_hidden';
                break;
            }
            if ($field === 'hidden') {
                $hiddenCol = 'hidden';
                break;
            }
        }
        if ($hiddenCol !== '') {
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE {$hiddenCol} = 1");
            $stmt2->execute();
            $quickStats['hidden_reviews_count'] = (int)$stmt2->fetchColumn();
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
            // 與退貨申請/退款狀態一致：未退款前視為「待處理退貨」
            $stmt = $pdo->query("SELECT COUNT(*) FROM return_requests WHERE refund_status = 'pending_refund'");
            $quickStats['pending_returns'] = (int)$stmt->fetchColumn();
        } else {
            // 後備：若資料表沒有 refund_status，才用 status 判斷 pending
            $stmt = $pdo->query("SELECT COUNT(*) FROM return_requests WHERE status IN ('pending','pending_payment')");
            $quickStats['pending_returns'] = (int)$stmt->fetchColumn();
        }
    }
} catch (Throwable $e) {
}

// 今日訂單數 / 今日營收（店員基本統計）
try {
    $stmt = $pdo->query("SELECT
                            COUNT(*) AS cnt,
                            COALESCE(SUM(final_amount), 0) AS sales
                        FROM orders
                        WHERE status IN ('paid','shipped','completed')
                          AND DATE(created_at) = CURDATE()");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $quickStats['today_orders'] = (int)($row['cnt'] ?? 0);
    $quickStats['today_sales'] = (float)($row['sales'] ?? 0);
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
        <div class="staff-entry-meta">
            待處理訂單：<?php echo number_format($quickStats['pending_orders']); ?>
        </div>
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
        <div class="staff-entry-meta">待處理退貨：<?php echo number_format($quickStats['pending_returns']); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>
    <a href="sales_report.php" class="staff-entry-card">
        <h2>今日營運</h2>
        <p>快速查看今日營收與訂單概況。</p>
        <div class="staff-entry-meta">
            今日營收：<?php echo htmlspecialchars(staffCurrency((float)$quickStats['today_sales'])); ?>
            ｜今日訂單：<?php echo number_format($quickStats['today_orders']); ?>
        </div>
        <span class="staff-entry-cta">前往功能</span>
    </a>

    <a href="reviews.php" class="staff-entry-card">
        <h2>評價管理</h2>
        <p>管理商品評價、隱藏或移除不當內容。</p>
        <div class="staff-entry-meta">已隱藏評論：<?php echo number_format($quickStats['hidden_reviews_count']); ?></div>
        <span class="staff-entry-cta">前往功能</span>
    </a>
</section>
<?php staffPageEnd(); ?>
