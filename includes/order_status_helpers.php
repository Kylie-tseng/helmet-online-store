<?php

function appOrderStatusMap(): array
{
    return [
        'pending' => '待處理',
        'pending_payment' => '待付款',
        'paid' => '已付款',
        'shipped' => '已出貨',
        'completed' => '已完成',
        'return_requested' => '退貨申請中',
        'cancelled' => '已取消',
        'progress' => '處理中',
        'done' => '已完成',
        'active' => '上架中',
        'inactive' => '未上架',
    ];
}

function appOrderStatusLabel(string $status): string
{
    $key = strtolower(trim($status));
    $map = appOrderStatusMap();
    return $map[$key] ?? $status;
}

function appStatusBadgeClass(string $status): string
{
    $status = strtolower(trim($status));
    if (in_array($status, ['pending', 'pending_payment', '待處理', '待出貨', '處理中', 'pending_refund', '待退款', 'return_requested', '退貨申請中'], true)) {
        return 'pending';
    }
    if (in_array($status, ['paid', 'shipped', '已出貨', '已付款', 'progress'], true)) {
        return 'progress';
    }
    if (in_array($status, ['completed', '已完成', 'approved', '核准', 'done', 'refunded', '已退款', 'active'], true)) {
        return 'done';
    }
    if (in_array($status, ['cancelled', '取消', 'rejected', '駁回', 'inactive'], true)) {
        return 'danger';
    }
    return 'neutral';
}

function appRefundStatusLabel(string $status): string
{
    $key = strtolower(trim($status));
    $map = [
        'pending_refund' => '待退款',
        'refunded' => '已退款',
    ];
    return $map[$key] ?? ($status !== '' ? $status : '待退款');
}

function get_order_status_key(string $status): string
{
    $key = strtolower(trim($status));
    if ($key === 'pending' || $key === 'shipped' || $key === 'completed' || $key === 'return_requested' || $key === 'cancelled') {
        return $key;
    }

    // 舊資料兼容：統一映射到前後台共同顯示狀態
    if (in_array($key, ['pending_payment', 'paid', 'processing', 'progress'], true)) {
        return 'pending';
    }
    if (in_array($key, ['done'], true)) {
        return 'completed';
    }

    return 'pending';
}

function get_order_status_text(string $status): string
{
    $map = [
        'pending' => '待處理',
        'shipped' => '已出貨',
        'completed' => '已完成',
        'return_requested' => '退貨申請中',
        'cancelled' => '已取消',
    ];
    $key = get_order_status_key($status);
    return $map[$key] ?? '待處理';
}

function get_order_status_modifier_class(string $status): string
{
    $key = get_order_status_key($status);
    if ($key === 'return_requested') {
        return 'order-status--pending';
    }
    return 'order-status--' . $key;
}

function get_order_status_class(string $status): string
{
    return 'order-status-badge ' . get_order_status_modifier_class($status);
}

/**
 * 會員前台是否可取消：未出貨／未完成／未取消即可（含信用卡 paid、貨到付款 pending 等），不依 payment_method。
 *
 * 注意：若 orders.status 為 NULL／空字串，與 member_order_list_status_filter_sql(pending) 一致，視同「待處理」，
 * 可取消（否則列表在「待處理」看得到訂單卻沒有取消按鈕）。
 */
function can_cancel_order_status(string $status): bool
{
    $s = strtolower(trim($status));
    if ($s === '') {
        return true;
    }
    // 與「待處理」篩選分組一致：出貨前狀態
    $cancellable = ['pending', 'pending_payment', 'paid', 'processing', 'progress'];
    if (!in_array($s, $cancellable, true)) {
        return false;
    }
    return true;
}

function can_cancel_order(array $order): bool
{
    return can_cancel_order_status((string)($order['status'] ?? ''));
}

/** 會員中心訂單狀態篩選：合法 GET 值（空字串 = 全部） */
function member_order_status_filter_whitelist(): array
{
    return ['', 'pending', 'shipped', 'completed', 'return_requested', 'cancelled'];
}

/**
 * 與 get_order_status_key() 分組一致：某篩選鍵對應的 orders.status 原始值（不含空字串；空字串於 pending 另行處理）
 */
function member_order_status_db_values_for_filter_key(string $filterKey): array
{
    $k = strtolower(trim($filterKey));
    switch ($k) {
        case 'pending':
            return ['pending', 'pending_payment', 'paid', 'processing', 'progress'];
        case 'shipped':
            return ['shipped'];
        case 'completed':
            return ['completed', 'done'];
        case 'cancelled':
            return ['cancelled'];
        case 'return_requested':
            return ['return_requested'];
        default:
            return [];
    }
}

/**
 * 會員訂單列表 WHERE 子句（AND ...），與 badge 所用 get_order_status_key 對齊；$params 會合併綁定值
 */
function member_order_list_status_filter_sql(string $orderStatusFilter, array &$params): string
{
    $k = strtolower(trim($orderStatusFilter));
    if ($k === '' || !in_array($k, member_order_status_filter_whitelist(), true)) {
        return '';
    }

    $vals = member_order_status_db_values_for_filter_key($k);
    if ($vals === []) {
        return '';
    }

    $placeholders = [];
    foreach ($vals as $i => $v) {
        $name = ':mos' . $i;
        $params[$name] = $v;
        $placeholders[] = $name;
    }

    if ($k === 'pending') {
        return ' AND (status IN (' . implode(', ', $placeholders) . ') OR TRIM(COALESCE(status, \'\')) = \'\')';
    }

    if (count($placeholders) === 1) {
        return ' AND status = ' . $placeholders[0];
    }

    return ' AND status IN (' . implode(', ', $placeholders) . ')';
}

/** 篩選值對應中文（空狀態文案用） */
function member_order_status_filter_label(string $filterValue): string
{
    $v = strtolower(trim($filterValue));
    $map = [
        'pending' => '待處理',
        'shipped' => '已出貨',
        'completed' => '已完成',
        'return_requested' => '退貨申請中',
        'cancelled' => '已取消',
    ];
    return $map[$v] ?? $v;
}

/**
 * 訂單列表空狀態文案
 *
 * @param int $userOrderTotal 該會員訂單總筆數（無篩選）
 */
function member_orders_empty_message(int $userOrderTotal, string $orderStatusFilter, string $orderQ): string
{
    $orderQ = trim($orderQ);
    $orderStatusFilter = trim($orderStatusFilter);

    if ($userOrderTotal <= 0) {
        return '目前尚未有任何訂單。';
    }

    if ($orderQ !== '' && $orderStatusFilter !== '') {
        return '目前沒有符合搜尋與狀態條件的訂單。';
    }
    if ($orderQ !== '') {
        return '目前沒有符合搜尋條件的訂單。';
    }
    if ($orderStatusFilter !== '') {
        $label = member_order_status_filter_label($orderStatusFilter);
        return '目前沒有' . $label . '訂單。';
    }

    return '目前尚未有任何訂單。';
}

/**
 * 計入營收、消費統計的「有效訂單」狀態（不含 cancelled／待處理未付款等）。
 *
 * @return list<string>
 */
function app_orders_valid_revenue_statuses(): array
{
    // done：舊資料／部分流程與 completed 同義，計入營收與銷量統計
    return ['paid', 'shipped', 'completed', 'done'];
}

/**
 * 讀取 orders.status 實際 ENUM（店員／管理者訂單頁共用）。
 *
 * @return list<string>
 */
function app_orders_discover_status_enum(PDO $pdo): array
{
    $fallback = ['pending', 'pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'return_requested'];
    $out = $fallback;
    try {
        $row = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        $type = (string) ($row['Type'] ?? '');
        if (preg_match('/^enum\((.+)\)$/i', $type, $m)) {
            $vals = [];
            if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $mm)) {
                foreach ($mm[1] as $raw) {
                    $vals[] = str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);
                }
            }
            if ($vals !== []) {
                $out = $vals;
            }
        }
    } catch (Throwable $e) {
    }
    return $out;
}

/**
 * 訂單狀態概覽（全表 COUNT，不受清單 q／status 篩選影響）。
 *
 * @param list<string> $allowedStatuses
 * @return array{pending:int,paid:int,shipped:int,completed:int,cancelled:int}
 */
function app_orders_compute_overview_buckets(PDO $pdo, array $allowedStatuses): array
{
    $buckets = [
        'pending' => 0,
        'paid' => 0,
        'shipped' => 0,
        'completed' => 0,
        'cancelled' => 0,
    ];
    try {
        $pendingVals = array_values(array_intersect(
            ['pending', 'pending_payment', 'processing', 'progress'],
            $allowedStatuses
        ));
        if ($pendingVals !== []) {
            $ph = [];
            $params = [];
            foreach ($pendingVals as $i => $v) {
                $k = ':ovp' . $i;
                $ph[] = $k;
                $params[$k] = $v;
            }
            $sql = 'SELECT COUNT(*) FROM orders WHERE (status IN (' . implode(', ', $ph) . ') OR TRIM(COALESCE(status, \'\')) = \'\')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $buckets['pending'] = (int) $stmt->fetchColumn();
        }
        if (in_array('paid', $allowedStatuses, true)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'paid'");
            $buckets['paid'] = (int) $stmt->fetchColumn();
        }
        if (in_array('shipped', $allowedStatuses, true)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'");
            $buckets['shipped'] = (int) $stmt->fetchColumn();
        }
        $doneVals = array_values(array_intersect(['completed', 'done'], $allowedStatuses));
        if ($doneVals !== []) {
            $ph = [];
            $params = [];
            foreach ($doneVals as $i => $v) {
                $k = ':ovd' . $i;
                $ph[] = $k;
                $params[$k] = $v;
            }
            $sql = 'SELECT COUNT(*) FROM orders WHERE status IN (' . implode(', ', $ph) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $buckets['completed'] = (int) $stmt->fetchColumn();
        }
        if (in_array('cancelled', $allowedStatuses, true)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'");
            $buckets['cancelled'] = (int) $stmt->fetchColumn();
        }
    } catch (Throwable $e) {
    }
    return $buckets;
}

/**
 * 店員／管理者訂單介面共用：orders.status 中文標籤（與清單篩選分組一致）。
 */
function app_backoffice_order_status_label(string $raw): string
{
    $s = strtolower(trim($raw));
    if ($s === '' || in_array($s, ['pending', 'pending_payment', 'processing', 'progress'], true)) {
        return '待處理';
    }
    if ($s === 'return_requested') {
        return '退貨申請中';
    }
    if ($s === 'paid') {
        return '已付款';
    }
    if ($s === 'shipped') {
        return '已出貨';
    }
    if ($s === 'completed' || $s === 'done') {
        return '已完成';
    }
    if ($s === 'cancelled') {
        return '已取消';
    }
    $t = trim($raw);
    return $t !== '' ? $t : '未知狀態';
}

/**
 * 訂單清單 status 篩選（pending／paid／shipped／completed／cancelled）→ SQL 片段與參數。
 *
 * @param list<string> $allowedStatuses
 * @return array{0:string,1:array<string,mixed>}
 */
function app_orders_backoffice_list_status_clause(string $statusKey, array $allowedStatuses, string $tableAlias, string $paramPrefix): array
{
    $statusKey = strtolower(trim($statusKey));
    if ($statusKey === '') {
        return ['', []];
    }
    $prefix = $tableAlias !== '' ? ($tableAlias . '.') : '';
    $params = [];
    if ($statusKey === 'pending') {
        $pendingVals = array_values(array_intersect(
            ['pending', 'pending_payment', 'processing', 'progress'],
            $allowedStatuses
        ));
        if ($pendingVals === []) {
            return ['', []];
        }
        $ph = [];
        foreach ($pendingVals as $i => $v) {
            $k = ':' . $paramPrefix . 'p' . $i;
            $ph[] = $k;
            $params[$k] = $v;
        }
        $sql = ' AND (' . $prefix . 'status IN (' . implode(', ', $ph) . ') OR TRIM(COALESCE(' . $prefix . "status, '')) = '')";
        return [$sql, $params];
    }
    if ($statusKey === 'completed') {
        $doneVals = array_values(array_intersect(['completed', 'done'], $allowedStatuses));
        if ($doneVals === []) {
            return ['', []];
        }
        $ph = [];
        foreach ($doneVals as $i => $v) {
            $k = ':' . $paramPrefix . 'd' . $i;
            $ph[] = $k;
            $params[$k] = $v;
        }
        return [' AND ' . $prefix . 'status IN (' . implode(', ', $ph) . ')', $params];
    }
    if (in_array($statusKey, $allowedStatuses, true)) {
        $k = ':' . $paramPrefix . 'one';
        $params[$k] = $statusKey;
        return [' AND ' . $prefix . 'status = ' . $k, $params];
    }
    return ['', []];
}

/** 訂單狀態下拉：目前列 status 與選項值是否應顯示為選中（含 pending 群組、done≈completed）。 */
function app_orders_status_option_is_selected(string $currentRaw, string $optionValue): bool
{
    $c = strtolower(trim($currentRaw));
    $o = strtolower(trim($optionValue));
    if ($c === $o) {
        return true;
    }
    if (in_array($c, ['', 'pending_payment', 'processing', 'progress', 'return_requested'], true) && $o === 'pending') {
        return true;
    }
    if (in_array($c, ['completed', 'done'], true) && in_array($o, ['completed', 'done'], true)) {
        return true;
    }
    return false;
}

