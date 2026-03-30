<?php
require_once '../config.php';
require_once __DIR__ . '/../staff/includes/staff_layout.php';

staffRequireAuth();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$validStatuses = ['paid', 'shipped', 'completed'];
$range = trim($_GET['range'] ?? 'month');
if (!in_array($range, ['today', 'month', 'all'], true)) {
    $range = 'month';
}

$rangeLabel = ($range === 'today') ? '今日' : (($range === 'month') ? '本月' : '全部');

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
    $sql = "SELECT c.name AS category_name,
                   COALESCE(SUM(oi.quantity), 0) AS sold_qty,
                   COALESCE(SUM(oi.subtotal), 0) AS sold_amount
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            LEFT JOIN order_items oi ON oi.product_id = p.id
            LEFT JOIN orders o ON o.id = oi.order_id
                AND o.status IN ('paid', 'shipped', 'completed') {$dateClause}
            WHERE c.name IN ('全罩式安全帽', '半罩式安全帽', '3/4罩安全帽', '周邊與配件')
            GROUP BY c.id, c.name
            ORDER BY CASE
                        WHEN c.name = '全罩式安全帽' THEN 1
                        WHEN c.name = '半罩式安全帽' THEN 2
                        WHEN c.name = '3/4罩安全帽' THEN 3
                        WHEN c.name = '周邊與配件' THEN 4
                        ELSE 99
                     END";
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
            LIMIT 5";
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

// 退貨摘要（簡單版）
$returnsSummary = [
    'total' => 0,
    'pending' => 0,
];
try {
    $check = $pdo->query("SHOW TABLES LIKE 'return_requests'");
    if ($check->fetchColumn()) {
        $rangeClause = '';
        if ($range === 'today') {
            $rangeClause = ' AND DATE(r.created_at) = CURDATE()';
        } elseif ($range === 'month') {
            $rangeClause = " AND DATE_FORMAT(r.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        }
        $stmt = $pdo->query("SELECT COUNT(*) AS total_count,
                                     SUM(CASE WHEN r.status IN ('pending','pending_payment') THEN 1 ELSE 0 END) AS pending_count
                              FROM return_requests r
                              WHERE 1=1 {$rangeClause}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $returnsSummary['total'] = (int)($row['total_count'] ?? 0);
        $returnsSummary['pending'] = (int)($row['pending_count'] ?? 0);
    }
} catch (Throwable $e) {
    // ignore
}

staffPageStart($pdo, '銷售統計', 'sales');
?>
<section class="staff-panel">
    <div class="staff-panel-head">
        <h2>時間篩選</h2>
    </div>
    <div class="staff-range-tabs">
        <a href="sales.php?range=today" class="staff-range-tab <?php echo $range === 'today' ? 'active' : ''; ?>">今日</a>
        <a href="sales.php?range=month" class="staff-range-tab <?php echo $range === 'month' ? 'active' : ''; ?>">本月</a>
        <a href="sales.php?range=all" class="staff-range-tab <?php echo $range === 'all' ? 'active' : ''; ?>">全部</a>
    </div>
</section>

<section class="staff-stats-grid">
    <article class="staff-stat-card">
        <div class="staff-stat-label"><?php echo htmlspecialchars($rangeLabel); ?>銷售額</div>
        <div class="staff-stat-value"><?php echo htmlspecialchars(staffCurrency($summary['sales'])); ?></div>
        <div class="staff-stat-note">依有效訂單統計</div>
    </article>
    <article class="staff-stat-card">
        <div class="staff-stat-label"><?php echo htmlspecialchars($rangeLabel); ?>訂單數</div>
        <div class="staff-stat-value"><?php echo number_format($summary['orders_count']); ?></div>
        <div class="staff-stat-note">狀態：已付款/已出貨/已完成</div>
    </article>
    <article class="staff-stat-card">
        <div class="staff-stat-label">平均客單價</div>
        <div class="staff-stat-value"><?php echo htmlspecialchars(staffCurrency($summary['avg_order'])); ?></div>
        <div class="staff-stat-note">更新至目前時間</div>
    </article>
    <article class="staff-stat-card">
        <div class="staff-stat-label"><?php echo htmlspecialchars($rangeLabel); ?>銷售件數</div>
        <div class="staff-stat-value"><?php echo number_format($summary['units_sold']); ?></div>
        <div class="staff-stat-note">依訂單明細累計</div>
    </article>
</section>

<section class="staff-panel">
    <div class="staff-panel-head">
        <h2>退貨摘要（簡單版）</h2>
    </div>
    <div class="staff-stats-grid" style="margin-top: 10px;">
        <article class="staff-stat-card">
            <div class="staff-stat-label">退貨申請數</div>
            <div class="staff-stat-value"><?php echo number_format((int)$returnsSummary['total']); ?></div>
            <div class="staff-stat-note"><?php echo htmlspecialchars($rangeLabel); ?>範圍統計</div>
        </article>
        <article class="staff-stat-card">
            <div class="staff-stat-label">待處理退貨</div>
            <div class="staff-stat-value"><?php echo number_format((int)$returnsSummary['pending']); ?></div>
            <div class="staff-stat-note">
                <?php
                $rate = $returnsSummary['total'] > 0 ? ($returnsSummary['pending'] / $returnsSummary['total']) * 100 : 0;
                echo htmlspecialchars(number_format($rate, 1)) . '%';
                ?>
            </div>
        </article>
    </div>
</section>

<section class="staff-panel">
    <div class="staff-panel-head">
        <h2>熱門商品</h2>
    </div>
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>商品</th>
                    <th>分類</th>
                    <th>銷售數量</th>
                    <th>銷售金額</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topProducts)): ?>
                    <tr>
                        <td colspan="4">目前尚無足夠銷售資料可供分析。</td>
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

<section class="staff-report-grid">
    <article class="staff-panel">
        <div class="staff-panel-head">
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
                        <tr><td colspan="3">目前沒有分類銷售資料。</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorySales as $cat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$cat['category_name']); ?></td>
                                <td><?php echo number_format((int)$cat['sold_qty']); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency((float)$cat['sold_amount'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="staff-panel">
        <div class="staff-panel-head">
            <h2>最近訂單摘要</h2>
        </div>
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
                        <tr><td colspan="5">目前沒有訂單資料。</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td>#<?php echo (int)$o['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)($o['user_name'] ?? '訪客')); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency((float)$o['final_amount'])); ?></td>
                                <td><span class="staff-badge <?php echo staffStatusBadgeClass((string)($o['status'] ?? '')); ?>"><?php echo htmlspecialchars(staffStatusLabel((string)($o['status'] ?? ''))); ?></span></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)($o['created_at'] ?? '')))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<?php staffPageEnd(); ?>

