<?php
date_default_timezone_set('Asia/Taipei');

require_once '../config.php';
require_once __DIR__ . '/../staff/includes/staff_layout.php';
require_once __DIR__ . '/../includes/admin_sales_analytics.php';

staffRequireAuth();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Throwable $e) {
}

$todayYm = date('Y-m');
$todayYmd = date('Y-m-d');

$validStatuses = app_orders_valid_revenue_statuses();

$range = trim($_GET['range'] ?? 'month');
if (!in_array($range, ['today', 'month', 'all'], true)) {
    $range = 'month';
}

$view = trim((string) ($_GET['view'] ?? 'overview'));
if (!in_array($view, ['overview', 'members'], true)) {
    $view = 'overview';
}

$salesUrl = static function (array $overrides) use ($view, $range): string {
    return 'sales.php?' . http_build_query(array_merge(['view' => $view, 'range' => $range], $overrides));
};

$rangeLabel = ($range === 'today') ? '今日' : (($range === 'month') ? '本月' : '全部');

$enumStatuses = app_orders_discover_status_enum($pdo);
$overviewBuckets = admin_sales_overview_buckets_scoped($pdo, $enumStatuses, $range, $todayYmd, $todayYm);
$statusDistRows = [
    ['key' => 'pending', 'label' => '待處理'],
    ['key' => 'return_requested', 'label' => '退貨申請中'],
    ['key' => 'paid', 'label' => '已付款'],
    ['key' => 'shipped', 'label' => '已出貨'],
    ['key' => 'completed', 'label' => '已完成'],
    ['key' => 'cancelled', 'label' => '已取消'],
];

$paymentLabelMap = ['credit_card' => '信用卡', 'cod' => '貨到付款'];
$shippingLabelMap = ['home' => '宅配到府', 'pickup' => '超商取貨'];

$topProducts = admin_sales_fetch_top_products($pdo, $range, $todayYmd, $todayYm, $validStatuses);
$categorySales = admin_sales_fetch_category_sales($pdo, $range, $todayYmd, $todayYm, $validStatuses);

$trendMerged = admin_sales_fetch_trend_merged($pdo, $range, $todayYmd, $todayYm, $validStatuses);
$trendLabels = $trendMerged['labels'];
$trendSales = $trendMerged['revenue'];
$trendOrders = $trendMerged['order_counts'];

$returnsSummary = admin_sales_fetch_returns_summary($pdo, $range, $todayYmd, $todayYm);

$paymentRows = [];
$shippingRows = [];
$memberSpendRows = [];
if ($view === 'members') {
    $paymentRows = admin_sales_fetch_payment_rows($pdo, $range, $todayYmd, $todayYm);
    $shippingRows = admin_sales_fetch_shipping_rows($pdo, $range, $todayYmd, $todayYm);
    $memberSpendRows = admin_sales_fetch_member_ranking($pdo, $range, $todayYmd, $todayYm, $validStatuses);
}

$statusLabels = [];
$statusCounts = [];
foreach ($statusDistRows as $sr) {
    $statusLabels[] = (string) $sr['label'];
    $statusCounts[] = (int) ($overviewBuckets[(string) $sr['key']] ?? 0);
}

$catLabels = [];
$catAmounts = [];
foreach ($categorySales as $cat) {
    $catLabels[] = (string) ($cat['category_name'] ?? '未分類');
    $catAmounts[] = round((float) ($cat['sold_amount'] ?? 0), 2);
}

$payLabels = [];
$payCounts = [];
foreach ($paymentRows as $pr) {
    $pm = (string) ($pr['pm'] ?? '');
    $payLabels[] = $paymentLabelMap[$pm] ?? ($pm !== '' ? $pm : '未填');
    $payCounts[] = (int) ($pr['cnt'] ?? 0);
}

$shipLabels = [];
$shipCounts = [];
foreach ($shippingRows as $sr) {
    $sm = (string) ($sr['sm'] ?? '');
    $shipLabels[] = $shippingLabelMap[$sm] ?? ($sm !== '' ? $sm : '未填');
    $shipCounts[] = (int) ($sr['cnt'] ?? 0);
}

/**
 * 嵌入 <script> 的 JSON：勿用 htmlspecialchars（會破壞 JS 字面量）；改用 JSON_HEX_* 避免與 HTML 衝突。
 */
function sales_encode_json_for_script($data): string {
    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $j = json_encode($data, $flags);
    return $j !== false ? $j : 'null';
}

staffPageStart($pdo, '銷售統計', 'sales');
?>
<style>
    .staff-sales-analytics .staff-analytics-block {
        margin-bottom: 22px;
    }
    .staff-sales-analytics .staff-analytics-block:last-child { margin-bottom: 0; }
    .staff-sales-analytics .staff-analytics-block-title {
        margin: 0 0 10px;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: #64748b;
        text-transform: uppercase;
    }
    .staff-sales-analytics .staff-analytics-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 18px 20px;
        margin-bottom: 16px;
    }
    .staff-sales-analytics .staff-analytics-card h3 {
        margin: 0 0 12px;
        font-size: 1.05rem;
        font-weight: 600;
        color: #0f172a;
    }
    .staff-sales-analytics .staff-analytics-card--data h3 { margin-bottom: 8px; }
    .staff-sales-analytics .staff-analytics-chart-wrap {
        position: relative;
        min-height: 260px;
        height: 260px;
        max-width: 100%;
    }
    .staff-sales-analytics .staff-analytics-chart-wrap canvas {
        display: block;
        width: 100%;
        height: 100%;
    }
    .staff-sales-analytics .staff-analytics-chart-wrap--short { min-height: 220px; height: 220px; }
    .staff-sales-analytics .staff-analytics-grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 0 16px;
    }
    .staff-sales-analytics .staff-analytics-grid-2 .staff-analytics-card { margin-bottom: 0; }
    .staff-sales-analytics .staff-analytics-grid-2 { margin-bottom: 16px; }
</style>

<section class="staff-panel staff-sales-analytics">
    <div class="staff-panel-head">
        <nav class="staff-range-tabs" aria-label="銷售統計分類">
            <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'overview'])); ?>" class="staff-range-tab <?php echo $view === 'overview' ? 'active' : ''; ?>">營運總覽</a>
            <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'members'])); ?>" class="staff-range-tab <?php echo $view === 'members' ? 'active' : ''; ?>">會員分析</a>
        </nav>
    </div>
</section>

<?php if ($view === 'overview'): ?>
<section class="staff-panel staff-sales-analytics">
    <div class="staff-panel-head">
        <h2>營運總覽</h2>
    </div>

    <div class="staff-range-tabs" style="margin-top: 4px;">
        <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'overview', 'range' => 'today'])); ?>" class="staff-range-tab <?php echo $range === 'today' ? 'active' : ''; ?>">今日</a>
        <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'overview', 'range' => 'month'])); ?>" class="staff-range-tab <?php echo $range === 'month' ? 'active' : ''; ?>">本月</a>
        <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'overview', 'range' => 'all'])); ?>" class="staff-range-tab <?php echo $range === 'all' ? 'active' : ''; ?>">全部</a>
    </div>

    <div class="staff-analytics-block" style="margin-top: 18px;">
        <p class="staff-analytics-block-title">趨勢圖表</p>
        <div class="staff-analytics-grid-2">
            <div class="staff-analytics-card">
                <h3>營收趨勢</h3>
                <div class="staff-analytics-chart-wrap">
                    <canvas id="salesChartRevenue" aria-label="營收趨勢"></canvas>
                </div>
            </div>
            <div class="staff-analytics-card">
                <h3>訂單趨勢</h3>
                <div class="staff-analytics-chart-wrap">
                    <canvas id="salesChartOrders" aria-label="訂單趨勢"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="staff-analytics-block">
        <p class="staff-analytics-block-title">結構與分類</p>
        <div class="staff-analytics-grid-2">
            <div class="staff-analytics-card">
                <h3>訂單狀態分布</h3>
                <div class="staff-analytics-chart-wrap staff-analytics-chart-wrap--short">
                    <canvas id="salesChartStatus" aria-label="訂單狀態分布"></canvas>
                </div>
            </div>
            <div class="staff-analytics-card">
                <h3>分類銷售表現</h3>
                <div class="staff-analytics-chart-wrap">
                    <canvas id="salesChartCategory" aria-label="分類銷售"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="staff-analytics-block">
        <p class="staff-analytics-block-title">名單與摘要</p>
        <div class="staff-analytics-card staff-analytics-card--data">
        <h3>熱門商品</h3>
        <p class="staff-panel-subtitle" style="margin-top: 0;">此區間銷量前五名</p>
        <div class="staff-table-wrap" style="margin-top: 0;">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>名次</th>
                        <th>商品</th>
                        <th>分類</th>
                        <th>銷量</th>
                        <th>銷售金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr><td colspan="5">此區間尚無資料。</td></tr>
                    <?php else: ?>
                        <?php $rank = 0; ?>
                        <?php foreach ($topProducts as $item): ?>
                            <?php $rank++; ?>
                            <tr>
                                <td><?php echo (int) $rank; ?></td>
                                <td><?php echo htmlspecialchars((string) $item['name']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($item['category_name'] ?? '未分類')); ?></td>
                                <td><?php echo number_format((int) $item['sold_qty']); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency((float) $item['sold_amount'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>

        <div class="staff-stats-grid staff-stats-grid--compact" style="margin-top: 12px;">
            <article class="staff-stat-card">
                <div class="staff-stat-label">退貨申請（<?php echo htmlspecialchars($rangeLabel); ?>）</div>
                <div class="staff-stat-value"><?php echo number_format((int) $returnsSummary['total']); ?></div>
                <div class="staff-stat-note">件</div>
            </article>
            <article class="staff-stat-card">
                <div class="staff-stat-label">待處理退貨</div>
                <div class="staff-stat-value"><?php echo number_format((int) $returnsSummary['pending']); ?></div>
                <div class="staff-stat-note">件</div>
            </article>
            <?php if (!empty($returnsSummary['table_exists'])): ?>
            <article class="staff-stat-card">
                <div class="staff-stat-label">已完成退貨（<?php echo htmlspecialchars($rangeLabel); ?>）</div>
                <div class="staff-stat-value"><?php echo number_format((int) $returnsSummary['completed']); ?></div>
                <div class="staff-stat-note">件</div>
            </article>
            <?php endif; ?>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var labels = <?php echo sales_encode_json_for_script($trendLabels); ?>;
    var sales = <?php echo sales_encode_json_for_script($trendSales); ?>;
    var orders = <?php echo sales_encode_json_for_script($trendOrders); ?>;
    var stLabels = <?php echo sales_encode_json_for_script($statusLabels); ?>;
    var stData = <?php echo sales_encode_json_for_script($statusCounts); ?>;
    var cLabels = <?php echo sales_encode_json_for_script($catLabels); ?>;
    var cData = <?php echo sales_encode_json_for_script($catAmounts); ?>;

    console.log('[銷售統計][營運總覽] Chart defined:', typeof Chart !== 'undefined');
    console.log('[銷售統計][營運總覽] trend labels:', labels, 'len', labels ? labels.length : 0);
    console.log('[銷售統計][營運總覽] trend sales:', sales, 'len', sales ? sales.length : 0);
    console.log('[銷售統計][營運總覽] trend orders:', orders, 'len', orders ? orders.length : 0);
    console.log('[銷售統計][營運總覽] status labels:', stLabels, 'data:', stData);
    console.log('[銷售統計][營運總覽] category labels:', cLabels, 'data:', cData);

    if (typeof Chart === 'undefined') {
        console.error('[銷售統計] Chart.js 未載入');
        return;
    }

    function showNoDataInWrap(canvasId, message) {
        var el = document.getElementById(canvasId);
        if (!el) return;
        var wrap = el.closest('.staff-analytics-chart-wrap');
        if (wrap) wrap.innerHTML = '<p class="staff-panel-subtitle" style="padding:2rem 0;text-align:center;">' + message + '</p>';
    }

    var commonOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'bottom' } } };
    var lineOpts = Object.assign({}, commonOpts, { scales: { y: { beginAtZero: true } } });

    if (labels && labels.length && sales && orders && sales.length === labels.length && orders.length === labels.length) {
        var cRev = document.getElementById('salesChartRevenue');
        var cOrd = document.getElementById('salesChartOrders');
        if (cRev) {
            try {
                new Chart(cRev, {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: '銷售金額', data: sales, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.12)', fill: true, tension: 0.25 }] },
                    options: lineOpts
                });
            } catch (e) { console.error('[銷售統計] 營收圖錯誤', e); showNoDataInWrap('salesChartRevenue', '圖表載入失敗'); }
        }
        if (cOrd) {
            try {
                new Chart(cOrd, {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: '訂單數', data: orders, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.12)', fill: true, tension: 0.25 }] },
                    options: lineOpts
                });
            } catch (e) { console.error('[銷售統計] 訂單圖錯誤', e); showNoDataInWrap('salesChartOrders', '圖表載入失敗'); }
        }
    } else {
        console.warn('[銷售統計] 趨勢資料為空或長度不一致，不繪製折線圖');
        showNoDataInWrap('salesChartRevenue', '目前沒有資料');
        showNoDataInWrap('salesChartOrders', '目前沒有資料');
    }

    if (stLabels && stLabels.length && stData && stData.length === stLabels.length && stData.some(function (n) { return n > 0; })) {
        var cSt = document.getElementById('salesChartStatus');
        if (cSt) {
            try {
                new Chart(cSt, {
                    type: 'doughnut',
                    data: {
                        labels: stLabels,
                        datasets: [{ data: stData, backgroundColor: ['#94a3b8', '#f59e0b', '#3b82f6', '#8b5cf6', '#22c55e', '#ef4444'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
                });
            } catch (e) { console.error('[銷售統計] 狀態圖錯誤', e); showNoDataInWrap('salesChartStatus', '圖表載入失敗'); }
        }
    } else {
        showNoDataInWrap('salesChartStatus', '目前沒有資料');
    }

    if (cLabels && cLabels.length && cData && cData.length === cLabels.length) {
        var cCat = document.getElementById('salesChartCategory');
        if (cCat) {
            try {
                new Chart(cCat, {
                    type: 'bar',
                    data: {
                        labels: cLabels,
                        datasets: [{ label: '銷售金額', data: cData, backgroundColor: '#475569' }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { ticks: { maxRotation: 45, minRotation: 0 } }, y: { beginAtZero: true } }
                    }
                });
            } catch (e) { console.error('[銷售統計] 分類圖錯誤', e); showNoDataInWrap('salesChartCategory', '圖表載入失敗'); }
        }
    } else {
        showNoDataInWrap('salesChartCategory', '目前沒有資料');
    }
});
</script>
<?php endif; ?>

<?php if ($view === 'members'): ?>
<section class="staff-panel staff-sales-analytics">
    <div class="staff-panel-head">
        <h2>會員分析</h2>
    </div>

    <div class="staff-range-tabs" style="margin-top: 4px;">
        <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'members', 'range' => 'today'])); ?>" class="staff-range-tab <?php echo $range === 'today' ? 'active' : ''; ?>">今日</a>
        <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'members', 'range' => 'month'])); ?>" class="staff-range-tab <?php echo $range === 'month' ? 'active' : ''; ?>">本月</a>
        <a href="<?php echo htmlspecialchars($salesUrl(['view' => 'members', 'range' => 'all'])); ?>" class="staff-range-tab <?php echo $range === 'all' ? 'active' : ''; ?>">全部</a>
    </div>

    <div class="staff-analytics-block" style="margin-top: 8px;">
        <p class="staff-analytics-block-title">金流與物流</p>
        <div class="staff-analytics-grid-2">
            <div class="staff-analytics-card">
                <h3>付款方式</h3>
                <div class="staff-analytics-chart-wrap staff-analytics-chart-wrap--short">
                    <canvas id="salesChartPay" aria-label="付款方式"></canvas>
                </div>
            </div>
            <div class="staff-analytics-card">
                <h3>配送方式</h3>
                <div class="staff-analytics-chart-wrap staff-analytics-chart-wrap--short">
                    <canvas id="salesChartShip" aria-label="配送方式"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="staff-analytics-block">
        <p class="staff-analytics-block-title">資料列表</p>
        <div class="staff-analytics-card staff-analytics-card--data">
        <h3>會員消費排行</h3>
        <div class="staff-table-wrap" style="margin-top: 0;">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>會員姓名</th>
                        <th>帳號</th>
                        <th>有效訂單數</th>
                        <th>消費總額</th>
                        <th>平均客單價</th>
                        <th>最近下單</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($memberSpendRows)): ?>
                        <tr><td colspan="6">尚無會員有效訂單資料。</td></tr>
                    <?php else: ?>
                        <?php foreach ($memberSpendRows as $mr): ?>
                            <?php
                            $cnt = (int) ($mr['order_cnt'] ?? 0);
                            $tot = (float) ($mr['total_spent'] ?? 0);
                            $avgM = $cnt > 0 ? ($tot / $cnt) : 0.0;
                            $lastAt = (string) ($mr['last_order_at'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($mr['name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($mr['username'] ?? '')); ?></td>
                                <td><?php echo number_format($cnt); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency($tot)); ?></td>
                                <td><?php echo htmlspecialchars(staffCurrency($avgM)); ?></td>
                                <td><?php echo $lastAt !== '' ? htmlspecialchars(date('Y-m-d H:i', strtotime($lastAt))) : '—'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var payL = <?php echo sales_encode_json_for_script($payLabels); ?>;
    var payD = <?php echo sales_encode_json_for_script($payCounts); ?>;
    var shipL = <?php echo sales_encode_json_for_script($shipLabels); ?>;
    var shipD = <?php echo sales_encode_json_for_script($shipCounts); ?>;
    var colors = ['#334155', '#3b82f6', '#0d9488', '#a855f7', '#ea580c', '#64748b'];

    console.log('[銷售統計][會員分析] Chart defined:', typeof Chart !== 'undefined');
    console.log('[銷售統計][會員分析] payment:', payL, payD);
    console.log('[銷售統計][會員分析] shipping:', shipL, shipD);

    if (typeof Chart === 'undefined') {
        console.error('[銷售統計] Chart.js 未載入');
        return;
    }

    function pieOrEmpty(canvasId, labels, data) {
        var el = document.getElementById(canvasId);
        if (!el) {
            console.warn('[銷售統計] 找不到 canvas:', canvasId);
            return;
        }
        var wrap = el.closest('.staff-analytics-chart-wrap');
        if (!labels || !labels.length || !data || !data.length || labels.length !== data.length || !data.some(function (n) { return n > 0; })) {
            if (wrap) wrap.innerHTML = '<p class="staff-panel-subtitle" style="padding:2rem 0;text-align:center;">目前沒有資料</p>';
            return;
        }
        try {
            new Chart(el, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{ data: data, backgroundColor: labels.map(function (_, i) { return colors[i % colors.length]; }) }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        } catch (e) {
            console.error('[銷售統計] 圓餅圖錯誤', canvasId, e);
            if (wrap) wrap.innerHTML = '<p class="staff-panel-subtitle" style="padding:2rem 0;text-align:center;">圖表載入失敗</p>';
        }
    }
    pieOrEmpty('salesChartPay', payL, payD);
    pieOrEmpty('salesChartShip', shipL, shipD);
});
</script>
<?php endif; ?>

<?php staffPageEnd(); ?>
