<?php
date_default_timezone_set('Asia/Taipei');

require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

// 與 PHP 日曆／單日查詢一致：本連線以台灣時區解讀 TIMESTAMP（不變更資料表結構）
try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Throwable $e) {
    // 無權限時略過，仍依 PHP 端 Asia/Taipei
}

$today = date('Y-m-d');

/** 本月 KPI／日曆「有效銷售」：已付款／已出貨／已完成（不含 cancelled） */
$validOrderStatuses = "('paid', 'shipped', 'completed')";

/** 店員銷售頁：可切換月份（2025-09 ～ 2026-06） */
$staffSalesAllowedMonths = [
    '2025-09', '2025-10', '2025-11', '2025-12',
    '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06',
];

/** 錨點 hash（與 HTML id 一致；所有 reload 連結請經 staff_sales_build_url） */
const STAFF_SALES_HASH_CALENDAR = 'sales-calendar';
const STAFF_SALES_HASH_DAILY = 'daily-detail';

/**
 * @param array<int, string> $allowedMonths
 */
function staff_sales_resolve_month(array $allowedMonths): string
{
    $raw = trim((string)($_GET['month'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}$/', $raw) && in_array($raw, $allowedMonths, true)) {
        return $raw;
    }
    $nowYm = date('Y-m');
    if (in_array($nowYm, $allowedMonths, true)) {
        return $nowYm;
    }

    return $allowedMonths[count($allowedMonths) - 1];
}

/**
 * 該月最後可選日期（字串 Y-m-d）。
 */
function staff_sales_last_selectable_day(string $ym): string
{
    $tz = new DateTimeZone('Asia/Taipei');
    $first = new DateTimeImmutable($ym . '-01', $tz);

    return $first->format('Y-m-t');
}

/**
 * 選取日期邏輯（與 $_GET 對齊）：
 * - 僅在「未帶 date」（鍵不存在或空字串）時，預設為今天（台灣時間）；今天不在當前 month 則該月 1 日。
 * - 只要有傳非空 date，就以該參數為主；合法且屬於 $ym 則採用；否則絕不用「今天」覆蓋，改為該月 1 日。
 */
function staff_sales_resolve_selected_date(string $ym): string
{
    $tz = new DateTimeZone('Asia/Taipei');
    $cap = staff_sales_last_selectable_day($ym);
    $dateKeyMissing = !array_key_exists('date', $_GET);
    $raw = $dateKeyMissing ? '' : trim((string)$_GET['date']);

    if ($dateKeyMissing || $raw === '') {
        $today = date('Y-m-d');
        if (substr($today, 0, 7) === $ym) {
            return strcmp($today, $cap) <= 0 ? $today : $cap;
        }
        $first = $ym . '-01';

        return strcmp($first, $cap) <= 0 ? $first : $cap;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $try = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
        if ($try instanceof DateTimeImmutable && $try->format('Y-m') === $ym) {
            $last = (int)$try->format('t');
            $dom = (int)$try->format('j');
            if ($dom >= 1 && $dom <= $last) {
                $picked = $try->format('Y-m-d');

                return strcmp($picked, $cap) <= 0 ? $picked : $cap;
            }
        }
    }

    $first = $ym . '-01';

    return strcmp($first, $cap) <= 0 ? $first : $cap;
}

/**
 * @param array<int, string> $allowedMonths
 */
function staff_sales_month_index(string $ym, array $allowedMonths): int
{
    $idx = array_search($ym, $allowedMonths, true);

    return $idx === false ? 0 : $idx;
}

/**
 * 所有會重新載入的連結一律帶 hash，避免捲動位置亂跳。
 *
 * @param string $hash 請使用 STAFF_SALES_HASH_CALENDAR、STAFF_SALES_HASH_DAILY 或 ''
 */
function staff_sales_build_url(string $monthYm, string $dateYmd, string $hash): string
{
    $q = http_build_query([
        'month' => $monthYm,
        'date' => $dateYmd,
    ]);
    $base = 'sales_report.php?' . $q;
    if ($hash !== '') {
        $base .= '#' . ltrim($hash, '#');
    }

    return $base;
}

/**
 * 上一月／下一月：只帶 month，不帶 date，避免舊 date 套到錯誤月份。
 *
 * @param string $hash 請使用 STAFF_SALES_HASH_CALENDAR、STAFF_SALES_HASH_DAILY 或 ''
 */
function staff_sales_build_month_only_url(string $monthYm, string $hash): string
{
    $q = http_build_query(['month' => $monthYm]);
    $base = 'sales_report.php?' . $q;
    if ($hash !== '') {
        $base .= '#' . ltrim($hash, '#');
    }

    return $base;
}

$calendarYm = staff_sales_resolve_month($staffSalesAllowedMonths);
[$calendarYStr, $calendarMStr] = explode('-', $calendarYm);
$calendarYear = (int)$calendarYStr;
$calendarMonth = (int)$calendarMStr;

$monthIdx = staff_sales_month_index($calendarYm, $staffSalesAllowedMonths);
$prevYm = $monthIdx > 0 ? $staffSalesAllowedMonths[$monthIdx - 1] : null;
$nextYm = $monthIdx < count($staffSalesAllowedMonths) - 1 ? $staffSalesAllowedMonths[$monthIdx + 1] : null;

$monthRangeTz = new DateTimeZone('Asia/Taipei');
$monthRangeStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $calendarYear, $calendarMonth), $monthRangeTz);
$monthRangeEndEx = $monthRangeStart->modify('+1 month');
$monthClause = ' AND o.created_at >= :ym_start AND o.created_at < :ym_end';

// 有傳非空 date 時，單日詳情標題字串以 GET 為準；單日／日曆統計一律綁定解析後的 $selectedDate（不以 $today 取代）
$dateParamMissingOrEmpty = !array_key_exists('date', $_GET) || trim((string)$_GET['date']) === '';
$selectedDate = staff_sales_resolve_selected_date($calendarYm);
$displayDateYmd = $dateParamMissingOrEmpty
    ? $selectedDate
    : trim((string)$_GET['date']);

$summary = [
    'sales' => 0.0,
    'orders_count' => 0,
    'avg_order' => 0.0,
];

$daysInMonth = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $calendarYear, $calendarMonth), new DateTimeZone('Asia/Taipei')))->format('t');

$dailyStats = [];
for ($d = 1; $d <= $daysInMonth; $d++) {
    $key = sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $d);
    $dailyStats[$key] = ['revenue' => 0.0, 'orders' => 0];
}

$dayDetail = [
    'revenue' => 0.0,
    'orders' => 0,
    'units' => 0,
    'pending' => 0,
    'shipped' => 0,
    'completed' => 0,
];

$paramsYm = [
    ':ym_start' => $monthRangeStart->format('Y-m-d H:i:s'),
    ':ym_end' => $monthRangeEndEx->format('Y-m-d H:i:s'),
];

try {
    $sql = "SELECT COALESCE(SUM(o.final_amount), 0) AS total_sales, COUNT(*) AS total_orders
            FROM orders o
            WHERE o.status IN {$validOrderStatuses} {$monthClause}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsYm);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $summary['sales'] = (float)($row['total_sales'] ?? 0);
    $summary['orders_count'] = (int)($row['total_orders'] ?? 0);
    $summary['avg_order'] = $summary['orders_count'] > 0 ? ($summary['sales'] / $summary['orders_count']) : 0;
} catch (Throwable $e) {
    // keep defaults
}

try {
    $sql = "SELECT DATE(o.created_at) AS order_date,
                   COALESCE(SUM(CASE WHEN o.status IN {$validOrderStatuses} THEN o.final_amount ELSE 0 END), 0) AS revenue,
                   SUM(CASE WHEN o.status IN {$validOrderStatuses} THEN 1 ELSE 0 END) AS order_cnt
            FROM orders o
            WHERE o.created_at >= :ym_start AND o.created_at < :ym_end
            GROUP BY DATE(o.created_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsYm);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string)($row['order_date'] ?? '');
        if ($key !== '' && isset($dailyStats[$key])) {
            $dailyStats[$key]['revenue'] = (float)($row['revenue'] ?? 0);
            $dailyStats[$key]['orders'] = (int)($row['order_cnt'] ?? 0);
        }
    }
} catch (Throwable $e) {
    // keep zeros
}

$paramsSel = [':sel' => $selectedDate];

try {
    $sql = "SELECT
                COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.final_amount ELSE 0 END), 0) AS revenue,
                SUM(CASE WHEN o.status <> 'cancelled' THEN 1 ELSE 0 END) AS order_cnt,
                SUM(CASE WHEN o.status IN ('pending','pending_payment','paid') THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN o.status = 'shipped' THEN 1 ELSE 0 END) AS shipped_cnt,
                SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) AS completed_cnt
            FROM orders o
            WHERE DATE(o.created_at) = :sel";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsSel);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $dayDetail['revenue'] = (float)($row['revenue'] ?? 0);
        $dayDetail['orders'] = (int)($row['order_cnt'] ?? 0);
        $dayDetail['pending'] = (int)($row['pending_cnt'] ?? 0);
        $dayDetail['shipped'] = (int)($row['shipped_cnt'] ?? 0);
        $dayDetail['completed'] = (int)($row['completed_cnt'] ?? 0);
    }
} catch (Throwable $e) {
    // keep defaults
}

try {
    $sql = "SELECT COALESCE(SUM(oi.quantity), 0)
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE DATE(o.created_at) = :sel
              AND o.status <> 'cancelled'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($paramsSel);
    $dayDetail['units'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $dayDetail['units'] = 0;
}

$firstOfMonth = new DateTimeImmutable(sprintf('%04d-%02d-01', $calendarYear, $calendarMonth), new DateTimeZone('Asia/Taipei'));
$leadingEmpty = (int)$firstOfMonth->format('w');
$cellsForDays = $daysInMonth;
$totalCells = $leadingEmpty + $cellsForDays;
$padEnd = ($totalCells % 7 === 0) ? 0 : (7 - ($totalCells % 7));

$weekdayLabels = ['日', '一', '二', '三', '四', '五', '六'];
$todayYmd = $today;

staffPageStart($pdo, '店員銷售', 'sales_report');
?>
<script>
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
</script>
<section class="staff-panel" id="sales-report-page">
    <div id="sales-summary" class="staff-sales-report-section staff-sales-report-section--first">
        <div class="staff-panel-head">
            <h2>本月總結</h2>
        </div>
        <div class="staff-stats-grid staff-stats-grid--compact staff-stats-grid--sales-kpis" aria-label="本月總結">
            <article class="staff-stat-card">
                <div class="staff-stat-label">本月營收</div>
                <div class="staff-stat-value"><?php echo htmlspecialchars(staffCurrency($summary['sales'])); ?></div>
                <p class="staff-sales-kpi-note">本月有效訂單金額加總</p>
            </article>
            <article class="staff-stat-card">
                <div class="staff-stat-label">本月訂單數</div>
                <div class="staff-stat-value"><?php echo number_format($summary['orders_count']); ?></div>
                <p class="staff-sales-kpi-note">已付款 / 已出貨 / 已完成</p>
            </article>
            <article class="staff-stat-card">
                <div class="staff-stat-label">本月平均客單價</div>
                <div class="staff-stat-value"><?php echo htmlspecialchars(staffCurrency($summary['avg_order'])); ?></div>
                <p class="staff-sales-kpi-note">本月營收 ÷ 本月訂單數</p>
            </article>
        </div>
    </div>

    <div id="sales-calendar" class="staff-sales-report-section">
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>本月銷售日曆</h2>
        </div>
        <div class="staff-sales-month-toolbar" role="navigation" aria-label="月份切換">
            <?php if ($prevYm !== null): ?>
                <a class="staff-sales-month-nav-btn staff-btn" href="<?php echo htmlspecialchars(staff_sales_build_month_only_url($prevYm, STAFF_SALES_HASH_CALENDAR)); ?>">上一月</a>
            <?php else: ?>
                <span class="staff-sales-month-nav-btn staff-sales-month-nav-btn--disabled" aria-disabled="true">上一月</span>
            <?php endif; ?>
            <span class="staff-sales-month-label"><?php echo (int)$calendarYear; ?> 年 <?php echo (int)$calendarMonth; ?> 月</span>
            <?php if ($nextYm !== null): ?>
                <a class="staff-sales-month-nav-btn staff-btn" href="<?php echo htmlspecialchars(staff_sales_build_month_only_url($nextYm, STAFF_SALES_HASH_CALENDAR)); ?>">下一月</a>
            <?php else: ?>
                <span class="staff-sales-month-nav-btn staff-sales-month-nav-btn--disabled" aria-disabled="true">下一月</span>
            <?php endif; ?>
        </div>
        <div class="staff-sales-cal-wrap">
            <div class="staff-sales-cal-weekdays" aria-hidden="true">
                <?php foreach ($weekdayLabels as $wd): ?>
                    <div class="staff-sales-cal-wd"><?php echo htmlspecialchars($wd); ?></div>
                <?php endforeach; ?>
            </div>
            <div class="staff-sales-cal-grid">
                <?php for ($i = 0; $i < $leadingEmpty; $i++): ?>
                    <div class="staff-sales-cal-cell staff-sales-cal-cell--empty"></div>
                <?php endfor; ?>
                <?php for ($dom = 1; $dom <= $cellsForDays; $dom++):
                    $key = sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $dom);
                    $stat = $dailyStats[$key] ?? ['revenue' => 0.0, 'orders' => 0];
                    $isToday = ($key === $todayYmd);
                    $isActive = ($key === $selectedDate);
                    $dayClasses = 'staff-sales-cal-day';
                    if ($isActive) {
                        $dayClasses .= ' staff-sales-cal-day--active';
                    } elseif ($isToday) {
                        $dayClasses .= ' staff-sales-cal-day--today';
                    }
                    $href = staff_sales_build_url($calendarYm, $key, STAFF_SALES_HASH_DAILY);
                    ?>
                    <div class="staff-sales-cal-cell">
                        <a id="staff-sales-day-<?php echo htmlspecialchars($key); ?>" class="<?php echo htmlspecialchars($dayClasses); ?>" href="<?php echo htmlspecialchars($href); ?>">
                            <span class="staff-sales-cal-day-num"><?php echo $dom; ?></span>
                            <span class="staff-sales-cal-day-rev"><?php echo htmlspecialchars(staffCurrency($stat['revenue'])); ?></span>
                            <span class="staff-sales-cal-day-orders"><?php echo (int)$stat['orders']; ?> 筆</span>
                        </a>
                    </div>
                <?php endfor; ?>
                <?php for ($i = 0; $i < $padEnd; $i++): ?>
                    <div class="staff-sales-cal-cell staff-sales-cal-cell--empty"></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div id="daily-detail" class="staff-sales-report-section">
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>單日銷售詳情</h2>
        </div>
        <p class="staff-sales-section-lede staff-sales-section-lede--date"><?php echo htmlspecialchars($displayDateYmd); ?></p>
        <div class="staff-sales-day-wrap staff-sales-day-wrap--daily-summary" aria-label="單日銷售詳情">
            <div class="daily-summary-card">
                <div class="daily-summary-title">單日銷售重點</div>
                <div class="daily-summary-revenue"><?php echo htmlspecialchars(staffCurrency($dayDetail['revenue'])); ?></div>
                <div class="daily-summary-meta">共 <?php echo number_format($dayDetail['orders']); ?> 筆訂單、銷售 <?php echo number_format($dayDetail['units']); ?> 件商品</div>
                <div class="daily-summary-status" role="group" aria-label="訂單狀態">
                    <span>待處理 <?php echo number_format($dayDetail['pending']); ?></span>
                    <span class="daily-summary-status__sep" aria-hidden="true">｜</span>
                    <span>已出貨 <?php echo number_format($dayDetail['shipped']); ?></span>
                    <span class="daily-summary-status__sep" aria-hidden="true">｜</span>
                    <span>已完成 <?php echo number_format($dayDetail['completed']); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
(function () {
    var selectedDayId = <?php echo json_encode('staff-sales-day-' . $selectedDate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var hasExplicitDate = <?php echo $dateParamMissingOrEmpty ? 'false' : 'true'; ?>;
    function scrollToHash() {
        var h = window.location.hash;
        if (!h || h.length < 2) {
            return;
        }
        var id = h.slice(1);
        if (id === 'daily-detail' && hasExplicitDate && selectedDayId) {
            var dayEl = document.getElementById(selectedDayId);
            if (dayEl) {
                dayEl.scrollIntoView({ block: 'center', behavior: 'auto' });
                return;
            }
        }
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.scrollIntoView({ block: 'start', behavior: 'auto' });
    }
    function scrollToHashDeferred() {
        scrollToHash();
        window.requestAnimationFrame(function () {
            scrollToHash();
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollToHashDeferred);
    } else {
        scrollToHashDeferred();
    }
    window.addEventListener('load', function onSalesReportLoad() {
        window.removeEventListener('load', onSalesReportLoad);
        scrollToHash();
    });
})();
</script>
<?php staffPageEnd(); ?>
