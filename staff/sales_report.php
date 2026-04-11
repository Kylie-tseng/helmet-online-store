<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

$range = trim($_GET['range'] ?? 'month');
if (!in_array($range, ['today', 'month', 'all'], true)) {
    $range = 'month';
}

$dateClause = '';
$dateParams = [];
if ($range === 'today') {
    $dateClause = ' AND DATE(o.created_at) = CURDATE()';
} elseif ($range === 'month') {
    $dateClause = " AND DATE_FORMAT(o.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
}

$summary = [
    'sales' => 0.0,
    'orders_count' => 0,
    'avg_order' => 0.0,
    'units_sold' => 0,
];
$topProducts = [];
$categorySales = [];
$recentOrders = [];
$trendRows = [];

try {
    $sql = "SELECT COALESCE(SUM(o.final_amount), 0) AS total_sales, COUNT(*) AS total_orders
            FROM orders o
            WHERE o.status IN ('paid', 'shipped', 'completed') {$dateClause}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['sales'] = (float)($row['total_sales'] ?? 0);
    $summary['orders_count'] = (int)($row['total_orders'] ?? 0);
    $summary['avg_order'] = $summary['orders_count'] > 0 ? ($summary['sales'] / $summary['orders_count']) : 0;
} catch (Throwable $e) {
    // keep defaults
}

try {
    $sql = "SELECT COALESCE(SUM(oi.quantity), 0) AS units_sold
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE o.status IN ('paid', 'shipped', 'completed') {$dateClause}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $summary['units_sold'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $summary['units_sold'] = 0;
}

try {
    $sql = "SELECT p.name,
                   c.name AS category_name,
                   SUM(oi.quantity) AS sold_qty,
                   SUM(oi.subtotal) AS sold_amount
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE o.status IN ('paid', 'shipped', 'completed') {$dateClause}
            GROUP BY oi.product_id, p.name, c.name
            ORDER BY sold_qty DESC, sold_amount DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $topProducts = [];
}

try {
    $sql = "SELECT c.id AS category_id,
                   c.name AS category_name,
                   COALESCE(agg.sold_qty, 0) AS sold_qty,
                   COALESCE(agg.sold_amount, 0) AS sold_amount
            FROM categories c
            LEFT JOIN (
                SELECT p.category_id AS cid,
                       SUM(oi.quantity) AS sold_qty,
                       SUM(oi.subtotal) AS sold_amount
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                    AND o.status IN ('paid', 'shipped', 'completed')
                    {$dateClause}
                INNER JOIN products p ON p.id = oi.product_id
                WHERE p.category_id IS NOT NULL
                GROUP BY p.category_id
            ) agg ON agg.cid = c.id
            ORDER BY c.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $categorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categorySales = [];
}

try {
    $sql = "SELECT o.id, o.final_amount, o.status, o.created_at, u.name AS user_name
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE o.status IN ('paid', 'shipped', 'completed') {$dateClause}
            ORDER BY o.created_at DESC
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentOrders = [];
}

try {
    $sql = "SELECT DATE(o.created_at) AS order_date,
                   COUNT(*) AS order_count,
                   COALESCE(SUM(o.final_amount), 0) AS sales_amount
            FROM orders o
            WHERE o.status IN ('paid', 'shipped', 'completed') {$dateClause}
            GROUP BY DATE(o.created_at)
            ORDER BY order_date DESC
            LIMIT 7";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($dateParams);
    $trendRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $trendRows = [];
}

$trendChrono = $trendRows;
if ($range !== 'all') {
    usort($trendChrono, static function (array $a, array $b): int {
        return strcmp((string)($a['order_date'] ?? ''), (string)($b['order_date'] ?? ''));
    });
}

staffPageStart($pdo, '銷售報表', 'sales_report');
?>
<div class="staff-sales-report-layout">
    <header class="staff-sales-report-hero">
        <p class="staff-sales-report-hero-kicker">銷售與營運</p>
        <p class="staff-sales-report-hero-desc">
            依時間範圍檢視<strong>已付款／已出貨／已完成</strong>之有效訂單彙總；數字由訂單與明細即時計算，細節請至「訂單處理」。
        </p>
    </header>

    <section class="staff-panel staff-sales-report-range">
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>時間範圍</h2>
        </div>
        <div class="staff-range-tabs" role="tablist" aria-label="銷售報告時間範圍">
            <a href="sales_report.php?range=today" class="staff-range-tab <?php echo $range === 'today' ? 'active' : ''; ?>" <?php echo $range === 'today' ? 'aria-current="page"' : ''; ?>>今日</a>
            <a href="sales_report.php?range=month" class="staff-range-tab <?php echo $range === 'month' ? 'active' : ''; ?>" <?php echo $range === 'month' ? 'aria-current="page"' : ''; ?>>本月</a>
            <a href="sales_report.php?range=all" class="staff-range-tab <?php echo $range === 'all' ? 'active' : ''; ?>" <?php echo $range === 'all' ? 'aria-current="page"' : ''; ?>>全部</a>
        </div>
        <p class="staff-section-lede staff-section-lede--tight staff-sales-report-range-hint">以下區塊皆套用同一區間。</p>
    </section>

    <section class="staff-stats-grid staff-stats-grid--compact staff-sales-report-summary">
        <article class="staff-stat-card">
            <div class="staff-stat-label">銷售總額</div>
            <div class="staff-stat-value"><?php echo htmlspecialchars(staffCurrency($summary['sales'])); ?></div>
            <div class="staff-stat-note staff-stat-note--compact">依有效訂單統計<br>狀態：已付款 / 已出貨 / 已完成</div>
        </article>
        <article class="staff-stat-card">
            <div class="staff-stat-label">有效訂單數</div>
            <div class="staff-stat-value"><?php echo number_format($summary['orders_count']); ?></div>
            <div class="staff-stat-note staff-stat-note--compact">依有效訂單統計<br>狀態：已付款 / 已出貨 / 已完成</div>
        </article>
        <article class="staff-stat-card">
            <div class="staff-stat-label">平均客單價</div>
            <div class="staff-stat-value"><?php echo htmlspecialchars(staffCurrency($summary['avg_order'])); ?></div>
            <div class="staff-stat-note staff-stat-note--compact">依有效訂單統計<br>狀態：已付款 / 已出貨 / 已完成</div>
        </article>
        <article class="staff-stat-card">
            <div class="staff-stat-label">銷售件數</div>
            <div class="staff-stat-value"><?php echo number_format($summary['units_sold']); ?></div>
            <div class="staff-stat-note staff-stat-note--compact">依有效訂單統計<br>狀態：已付款 / 已出貨 / 已完成</div>
        </article>
    </section>

    <section class="staff-panel staff-sales-report-section">
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>熱門商品</h2>
        </div>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>商品名稱</th>
                        <th>分類</th>
                        <th>銷售數量</th>
                        <th>銷售金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr>
                            <td colspan="4">目前所選期間尚無足夠銷售資料可供分析</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topProducts as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$item['name']); ?></td>
                                <td><?php echo htmlspecialchars((string)($item['category_name'] ?? '未分類')); ?></td>
                                <td><?php echo number_format((int)$item['sold_qty']); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency((float)$item['sold_amount'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="staff-panel staff-sales-report-section">
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>分類銷售表現</h2>
        </div>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>分類</th>
                        <th>銷售件數</th>
                        <th>銷售金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorySales)): ?>
                        <tr><td colspan="3">目前沒有分類資料。</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorySales as $cat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$cat['category_name']); ?></td>
                                <td><?php echo number_format((int)($cat['sold_qty'] ?? 0)); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency((float)($cat['sold_amount'] ?? 0))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="staff-panel staff-sales-report-section">
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>最近訂單摘要</h2>
        </div>
        <p class="staff-section-lede staff-section-lede--tight staff-sales-report-section-hint">此區間內最近有效訂單，最多 10 筆。</p>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>訂單編號</th>
                        <th>會員</th>
                        <th>金額</th>
                        <th>狀態</th>
                        <th>日期</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="5">目前沒有符合條件的訂單資料</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><a href="orders.php?q=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a></td>
                                <td><?php echo htmlspecialchars((string)($o['user_name'] ?? '訪客')); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency((float)$o['final_amount'])); ?></td>
                                <td><span class="staff-badge <?php echo staffStatusBadgeClass((string)$o['status']); ?>"><?php echo htmlspecialchars(staffStatusLabel((string)$o['status'])); ?></span></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)$o['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="staff-panel staff-sales-report-section">
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>近七日銷售趨勢</h2>
        </div>
        <p class="staff-section-lede staff-section-lede--tight staff-sales-report-section-hint">依訂單建立日彙總，最多 7 個有成交的日期。</p>
        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>訂單筆數</th>
                        <th>銷售金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trendChrono)): ?>
                        <tr>
                            <td colspan="3">此區間尚無符合條件之訂單，無法繪製趨勢。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trendChrono as $tr): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($tr['order_date'] ?? '')); ?></td>
                                <td><?php echo number_format((int)($tr['order_count'] ?? 0)); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency((float)($tr['sales_amount'] ?? 0))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<style>
/* 銷售報表版面（僅此頁，延續 staff 黑白灰風格） */
.staff-page .staff-sales-report-layout {
    max-width: 1080px;
    margin: 0 auto;
}
.staff-page .staff-sales-report-hero {
    margin: 0 0 12px;
    padding: 0 2px 10px;
    border-bottom: 1px solid #e5e7eb;
}
.staff-page .staff-sales-report-hero-kicker {
    margin: 0 0 4px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #6b7280;
}
.staff-page .staff-sales-report-hero-desc {
    margin: 0;
    font-size: 14px;
    line-height: 1.45;
    color: #4b5563;
    max-width: 40rem;
}
.staff-page .staff-sales-report-range {
    padding: 12px 14px 10px;
    margin-bottom: 10px;
}
.staff-page .staff-sales-report-range .staff-panel-head {
    margin-bottom: 6px;
}
.staff-page .staff-sales-report-range-hint {
    margin: 8px 0 0;
    font-size: 13px;
    color: #6b7280;
}
.staff-page .staff-sales-report-summary {
    margin-bottom: 10px;
}
.staff-page .staff-sales-report-summary .staff-stat-note--compact {
    margin-top: 6px;
    margin-bottom: 0;
    line-height: 1.35;
    font-size: 12px;
    color: #6b7280;
}
.staff-page .staff-sales-report-section {
    padding: 12px 14px 12px;
    margin-bottom: 10px;
}
.staff-page .staff-sales-report-section .staff-panel-head {
    margin-bottom: 6px;
}
.staff-page .staff-sales-report-section-hint {
    margin: 0 0 8px;
    font-size: 13px;
    color: #6b7280;
}
.staff-page .staff-sales-report-range .staff-range-tab {
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
.staff-page .staff-sales-report-range .staff-range-tab.active {
    box-shadow: 0 0 0 2px rgba(47, 47, 47, 0.35);
    font-weight: 800;
}
</style>
<?php staffPageEnd(); ?>

