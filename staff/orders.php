<?php

require_once '../config.php';

require_once __DIR__ . '/includes/staff_layout.php';

require_once __DIR__ . '/../includes/cart_functions.php';



staffRequireAuth();



if (!function_exists('staff_orders_discover_status_enum')) {

    /**

     * 讀取 orders.status 實際 ENUM，避免後台選單與資料庫狀態不一致而誤寫狀態。

     *

     * @return list<string>

     */

    function staff_orders_discover_status_enum(PDO $pdo): array

    {

        static $cache = null;

        if ($cache !== null) {

            return $cache;

        }

        $fallback = ['pending', 'pending_payment', 'paid', 'shipped', 'completed', 'cancelled', 'return_requested'];

        $cache = $fallback;

        try {

            $row = $pdo->query("SHOW COLUMNS FROM `orders` LIKE 'status'")->fetch(PDO::FETCH_ASSOC);

            $type = (string)($row['Type'] ?? '');

            if (preg_match('/^enum\((.+)\)$/i', $type, $m)) {

                $vals = [];

                if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $m[1], $mm)) {

                    foreach ($mm[1] as $raw) {

                        $vals[] = str_replace(["\\'", '\\\\'], ["'", '\\'], $raw);

                    }

                }

                if ($vals !== []) {

                    $cache = $vals;

                }

            }

        } catch (Throwable $e) {

        }

        return $cache;

    }

}



if (!function_exists('staff_orders_redirect_with_flash')) {

    function staff_orders_redirect_with_flash(string $message): void

    {

        staffNotifyFlashForRedirect($message);

        $query = [];

        $src = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;

        $q = trim((string)($src['preserve_q'] ?? $src['q'] ?? ''));

        $status = trim((string)($src['preserve_status'] ?? $src['status'] ?? ''));

        if ($q !== '') {

            $query['q'] = $q;

        }

        if ($status !== '') {

            $query['status'] = $status;

        }

        $target = 'orders.php';

        if (!empty($query)) {

            $target .= '?' . http_build_query($query);

        }

        header('Location: ' . $target);

        exit;

    }

}



if (!function_exists('staff_orders_display_status_label')) {

    function staff_orders_display_status_label(string $raw): string

    {

        $s = strtolower(trim($raw));

        if ($s === '' || in_array($s, ['pending', 'pending_payment', 'processing', 'progress', 'return_requested'], true)) {

            return '待處理';

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

}



if (!function_exists('staff_orders_status_badge_class')) {

    function staff_orders_status_badge_class(string $raw): string

    {

        $s = strtolower(trim($raw));

        if ($s === '') {

            return staffStatusBadgeClass('pending');

        }

        return staffStatusBadgeClass($s);

    }

}



if (!function_exists('staff_orders_status_option_is_selected')) {

    function staff_orders_status_option_is_selected(string $currentRaw, string $optionValue): bool

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

}



$q = trim($_GET['q'] ?? '');

$qForId = ltrim($q, "# \t");

$qIdToken = ($qForId !== '') ? $qForId : $q;

$status = trim($_GET['status'] ?? '');

/** 清單狀態篩選（非 DB 原始 ENUM 一對一）：全部、待處理、已付款、已出貨、已完成、已取消 */

$staffOrderListFilterKeys = ['pending', 'paid', 'shipped', 'completed', 'cancelled'];




$allowedStatuses = staff_orders_discover_status_enum($pdo);

/** 結案狀態：不可改為其他 status（含舊資料 done ≈ 已完成） */

$lockedRawStatuses = ['completed', 'cancelled', 'done'];



if ($status !== '' && !in_array($status, $staffOrderListFilterKeys, true)) {

    $status = '';

}



$orderColumnNames = [];

try {

    $stmt = $pdo->query('SHOW COLUMNS FROM orders');

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {

        $orderColumnNames[(string)($col['Field'] ?? '')] = true;

    }

} catch (Throwable $e) {

    $orderColumnNames = [];

}



$hasStaffNoteColumn = !empty($orderColumnNames['staff_note']);

$hasShippingCompany = !empty($orderColumnNames['shipping_company']);

$hasTrackingNumber = !empty($orderColumnNames['tracking_number']);

$hasCancelReasonColumn = !empty($orderColumnNames['cancel_reason']);

$hasOrderNoteColumn = !empty($orderColumnNames['order_note']);

$hasCustomerNoteColumn = !empty($orderColumnNames['customer_note']);



$returnsTableExists = false;

try {

    $chkRet = $pdo->query("SHOW TABLES LIKE 'return_requests'");

    $returnsTableExists = (bool)$chkRet->fetchColumn();

} catch (Throwable $e) {

    $returnsTableExists = false;

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim($_POST['action'] ?? '');

    if ($action === 'update_status') {

        $orderId = (int)($_POST['order_id'] ?? 0);

        $newStatus = trim($_POST['new_status'] ?? '');

        if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {

            staff_orders_redirect_with_flash('狀態更新失敗：資料不正確。');

        }



        try {

            $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1');

            $stmt->execute([':id' => $orderId]);

            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {

                staff_orders_redirect_with_flash('找不到此訂單。');

            }



            $currentRaw = strtolower(trim((string)($current['status'] ?? '')));

            $newNorm = strtolower(trim($newStatus));

            if (in_array($currentRaw, $lockedRawStatuses, true) && $currentRaw !== $newNorm) {

                staff_orders_redirect_with_flash('此訂單已結案，無法再變更狀態。');

            }



            $stmt = $pdo->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');

            $stmt->execute([':status' => $newStatus, ':id' => $orderId]);

            markUserCouponUsedAfterOrderStatusChange($pdo, $orderId, $newStatus);

            staff_orders_redirect_with_flash('狀態更新成功。');

        } catch (Throwable $e) {

            staff_orders_redirect_with_flash('更新失敗，請稍後再試。');

        }

    } elseif ($action === 'update_note') {

        $orderId = (int)($_POST['order_id'] ?? 0);

        $staffNote = trim((string)($_POST['staff_note'] ?? ''));

        if ($orderId <= 0) {

            staff_orders_redirect_with_flash('備註更新失敗：資料不正確。');

        }

        if (!$hasStaffNoteColumn) {

            staff_orders_redirect_with_flash('目前資料表缺少 staff_note 欄位。');

        }



        try {

            $stmt = $pdo->prepare('UPDATE orders SET staff_note = :note, updated_at = NOW() WHERE id = :id');

            $stmt->execute([':note' => $staffNote, ':id' => $orderId]);

            staff_orders_redirect_with_flash('店員備註儲存成功。');

        } catch (Throwable $e) {

            staff_orders_redirect_with_flash('備註更新失敗。');

        }

    } elseif ($action === 'update_fulfillment') {

        $orderId = (int)($_POST['order_id'] ?? 0);

        if ($orderId <= 0) {

            staff_orders_redirect_with_flash('物流資訊更新失敗：資料不正確。');

        }



        $shippingMethod = trim((string)($_POST['shipping_method'] ?? ''));

        if ($shippingMethod !== '' && !in_array($shippingMethod, ['pickup', 'home'], true)) {

            staff_orders_redirect_with_flash('配送方式不正確。');

        }

        $shippingAddress = trim((string)($_POST['shipping_address'] ?? ''));

        $pickupStore = trim((string)($_POST['pickup_store'] ?? ''));

        $shippingCompany = trim((string)($_POST['shipping_company'] ?? ''));

        $trackingNumber = trim((string)($_POST['tracking_number'] ?? ''));



        try {

            $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1');

            $stmt->execute([':id' => $orderId]);

            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {

                staff_orders_redirect_with_flash('找不到此訂單。');

            }

            $currentRaw = (string)($current['status'] ?? '');

            if (in_array($currentRaw, $lockedRawStatuses, true)) {

                staff_orders_redirect_with_flash('此訂單已結案，無法變更物流資訊。');

            }



            $sets = [

                'shipping_method = :shipping_method',

                'shipping_address = :shipping_address',

                'pickup_store = :pickup_store',

                'updated_at = NOW()',

            ];

            $params = [

                ':shipping_method' => ($shippingMethod === '' ? null : $shippingMethod),

                ':shipping_address' => $shippingAddress,

                ':pickup_store' => $pickupStore,

                ':id' => $orderId,

            ];

            if ($hasShippingCompany) {

                $sets[] = 'shipping_company = :shipping_company';

                $params[':shipping_company'] = $shippingCompany;

            }

            if ($hasTrackingNumber) {

                $sets[] = 'tracking_number = :tracking_number';

                $params[':tracking_number'] = $trackingNumber;

            }



            $sql = 'UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = :id';

            $upd = $pdo->prepare($sql);

            $upd->execute($params);

            staff_orders_redirect_with_flash('配送／物流資訊已更新。');

        } catch (Throwable $e) {

            staff_orders_redirect_with_flash('物流資訊更新失敗，請稍後再試。');

        }

    } else {

        staff_orders_redirect_with_flash('不支援的操作。');

    }

}



$orders = [];

try {

    $staffNoteSelect = $hasStaffNoteColumn ? 'o.staff_note' : 'NULL AS staff_note';

    $shipCoSelect = $hasShippingCompany ? 'o.shipping_company' : 'NULL AS shipping_company';

    $trackSelect = $hasTrackingNumber ? 'o.tracking_number' : 'NULL AS tracking_number';

    $cancelReasonSelect = $hasCancelReasonColumn ? 'o.cancel_reason' : 'NULL AS cancel_reason';

    $orderNoteSelect = $hasOrderNoteColumn ? 'o.order_note' : 'NULL AS order_note';

    $customerNoteSelect = $hasCustomerNoteColumn ? 'o.customer_note' : 'NULL AS customer_note';



    $sql = "SELECT o.id, o.total_amount, o.discount_amount, o.final_amount, o.status, o.created_at,

                   o.payment_method, o.shipping_method, o.shipping_address, o.pickup_store,

                   {$shipCoSelect}, {$trackSelect},

                   {$staffNoteSelect},

                   {$cancelReasonSelect}, {$orderNoteSelect}, {$customerNoteSelect},

                   u.name AS user_name,

                   u.username AS user_username

            FROM orders o

            LEFT JOIN users u ON u.id = o.user_id

            WHERE 1=1";

    $params = [];

    if ($status !== '') {

        if ($status === 'pending') {

            $pendingVals = array_values(array_intersect(

                ['pending', 'pending_payment', 'processing', 'progress'],

                $allowedStatuses

            ));

            if ($pendingVals !== []) {

                $ph = [];

                foreach ($pendingVals as $i => $v) {

                    $k = ':stf' . $i;

                    $ph[] = $k;

                    $params[$k] = $v;

                }

                $sql .= ' AND (o.status IN (' . implode(', ', $ph) . ') OR TRIM(COALESCE(o.status, \'\')) = \'\')';

            }

        } elseif ($status === 'completed') {

            $doneVals = array_values(array_intersect(['completed', 'done'], $allowedStatuses));

            if ($doneVals !== []) {

                $ph = [];

                foreach ($doneVals as $i => $v) {

                    $k = ':std' . $i;

                    $ph[] = $k;

                    $params[$k] = $v;

                }

                $sql .= ' AND o.status IN (' . implode(', ', $ph) . ')';

            }

        } elseif (in_array($status, $allowedStatuses, true)) {

            $sql .= ' AND o.status = :status';

            $params[':status'] = $status;

        }

    }

    if ($q !== '') {

        $likeEsc = static function (string $s): string {

            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);

        };

        $params[':q_name'] = '%' . $likeEsc($q) . '%';

        $params[':q_user'] = '%' . $likeEsc($q) . '%';

        $params[':q_id'] = '%' . $likeEsc($qIdToken) . '%';

        $sql .= ' AND (

                    CAST(o.id AS CHAR) LIKE :q_id

                    OR COALESCE(u.name, \'\') LIKE :q_name

                    OR COALESCE(u.username, \'\') LIKE :q_user';

        if ($qForId !== '' && ctype_digit($qForId)) {

            $sql .= ' OR o.id = :q_exact_id';

            $params[':q_exact_id'] = (int)$qForId;

        }

        $sql .= ')';

    }

    $sql .= ' ORDER BY o.created_at ASC LIMIT 100';



    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as &$order) {

        $detailSql = "SELECT oi.product_id, oi.quantity, oi.unit_price, oi.subtotal, oi.size,

                            COALESCE(NULLIF(p.name, ''), CONCAT('商品 #', oi.product_id, '（可能已下架）')) AS product_name,

                            " . primaryImageSubquery('p', 'pi') . " AS primary_image

                     FROM order_items oi

                     LEFT JOIN products p ON oi.product_id = p.id

                     WHERE oi.order_id = :order_id";

        $detailStmt = $pdo->prepare($detailSql);

        $detailStmt->execute([':order_id' => (int)$order['id']]);

        $order['items'] = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

        $order['return_requests'] = [];

        if ($returnsTableExists) {

            try {

                $retStmt = $pdo->prepare('SELECT * FROM return_requests WHERE order_id = :oid ORDER BY id ASC');

                $retStmt->execute([':oid' => (int)$order['id']]);

                $order['return_requests'] = $retStmt->fetchAll(PDO::FETCH_ASSOC);

            } catch (Throwable $e) {

                $order['return_requests'] = [];

            }

        }

    }

    unset($order);

} catch (Throwable $e) {

    $orders = [];

}



$orderStats = array_fill_keys($allowedStatuses, 0);

try {

    $stmt = $pdo->query('SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status');

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

        $key = (string)($row['status'] ?? '');

        if (!array_key_exists($key, $orderStats)) {

            $orderStats[$key] = 0;

        }

        $orderStats[$key] += (int)($row['cnt'] ?? 0);

    }

} catch (Throwable $e) {

    $orderStats = array_fill_keys($allowedStatuses, 0);

}



$overviewBuckets = [

    'pending' => 0,

    'paid' => 0,

    'shipped' => 0,

    'completed' => 0,

    'cancelled' => 0,

];

$pendingLike = ['', 'pending', 'pending_payment', 'processing', 'progress', 'return_requested'];

foreach ($orderStats as $dbKey => $cnt) {

    $k = strtolower(trim((string)$dbKey));

    if (in_array($k, $pendingLike, true)) {

        $overviewBuckets['pending'] += (int)$cnt;

        continue;

    }

    if ($k === 'paid') {

        $overviewBuckets['paid'] += (int)$cnt;

    } elseif ($k === 'shipped') {

        $overviewBuckets['shipped'] += (int)$cnt;

    } elseif (in_array($k, ['completed', 'done'], true)) {

        $overviewBuckets['completed'] += (int)$cnt;

    } elseif ($k === 'cancelled') {

        $overviewBuckets['cancelled'] += (int)$cnt;

    }

}



$overviewCards = [

    ['key' => 'pending', 'label' => '待處理'],

    ['key' => 'paid', 'label' => '已付款'],

    ['key' => 'shipped', 'label' => '已出貨'],

    ['key' => 'completed', 'label' => '已完成'],

    ['key' => 'cancelled', 'label' => '已取消'],

];



staffPageStart($pdo, '訂單處理', 'orders');

?>

<style>

    .staff-page .staff-toolbar--orders-filter {

        margin-top: 10px;

    }

    .staff-page .staff-stats-grid--orders-overview {

        display: flex;

        flex-direction: row;

        flex-wrap: nowrap;

        gap: 10px;

        overflow-x: auto;

        padding-bottom: 4px;

        -webkit-overflow-scrolling: touch;

    }

    .staff-page .staff-stats-grid--orders-overview .staff-stat-card {

        flex: 1 1 0;

        min-width: 100px;

    }

    .staff-page .staff-order-actions-row {

        display: flex;

        flex-direction: row;

        flex-wrap: nowrap;

        align-items: center;

        gap: 14px;

        max-width: 100%;

    }

    .staff-page .staff-order-actions-row .staff-inline-form {

        display: inline-flex;

        flex: 0 1 auto;

        flex-wrap: nowrap;

        align-items: center;

        gap: 8px;

        margin: 0;

    }

    .staff-page .staff-order-actions-row .staff-select--order-row {

        max-width: 118px;

        min-width: 0;

    }

    .staff-page .staff-order-actions-row .staff-input--order-note-row {

        width: 120px;

        min-width: 72px;

        max-width: 140px;

    }

    .staff-page .staff-order-actions-row .staff-action-btn--compact {

        padding: 4px 8px;

        font-size: 12px;

        white-space: nowrap;

    }

    @media (max-width: 1100px) {

        .staff-page .staff-order-actions-row .staff-input--order-note-row {

            width: 88px;

        }

        .staff-page .staff-order-actions-row .staff-select--order-row {

            max-width: 100px;

        }

    }

    .staff-page .staff-order-detail-row .order-detail-product-cell {

        display: flex;

        align-items: center;

        gap: 10px;

    }

    .staff-page .staff-order-detail-row .order-detail-product-thumb {

        width: 44px;

        height: 44px;

        object-fit: contain;

        border-radius: 8px;

        border: 1px solid #e5e7eb;

        background: #fff;

        flex-shrink: 0;

    }

</style>

<section class="staff-panel">

    <div class="staff-panel-head">

        <h2>訂單狀態概覽</h2>

    </div>

    <div class="staff-stats-grid staff-stats-grid--compact staff-stats-grid--orders-overview">

        <?php foreach ($overviewCards as $card): ?>

            <?php $k = (string)$card['key']; ?>

            <article class="staff-stat-card">

                <div class="staff-stat-label"><?php echo htmlspecialchars((string)$card['label']); ?></div>

                <div class="staff-stat-value"><?php echo number_format((int)($overviewBuckets[$k] ?? 0)); ?></div>

            </article>

        <?php endforeach; ?>

    </div>

    <form method="GET" class="staff-toolbar staff-toolbar--orders-filter">

        <input

            type="text"

            name="q"

            class="staff-input"

            placeholder="搜尋訂單編號 / 會員名稱 / 會員帳號"

            value="<?php echo htmlspecialchars($q); ?>"

        >

        <select name="status" class="staff-select">

            <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>全部</option>

            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>待處理</option>

            <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>已付款</option>

            <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>已出貨</option>

            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>

            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>已取消</option>

        </select>

        <button type="submit" class="staff-btn">套用篩選</button>

    </form>



    <div class="staff-panel-head staff-panel-head--tight-top">

        <h2>訂單清單</h2>

    </div>

    <div class="staff-table-wrap">

        <table class="staff-table">

            <thead>

                <tr>

                    <th>訂單編號</th>

                    <th>會員</th>

                    <th>金額</th>

                    <th>訂單狀態</th>

                    <th>付款方式</th>

                    <th>配送方式</th>

                    <th>建立時間</th>

                    <th>操作</th>

                </tr>

            </thead>

            <tbody>

                <?php if (empty($orders)): ?>

                    <tr>

                        <td colspan="8"><?php echo $q !== '' || $status !== '' ? '查無符合篩選條件的訂單。' : '目前沒有任何訂單紀錄。'; ?></td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($orders as $order): ?>

                        <?php

                            $orderStatus = strtolower(trim((string)($order['status'] ?? '')));

                            $isLocked = in_array($orderStatus, $lockedRawStatuses, true);

                            $orderId = (int)$order['id'];

                            $paymentMap = ['credit_card' => '信用卡', 'cod' => '貨到付款'];

                            $shippingMap = ['pickup' => '超商取貨', 'home' => '宅配到府'];

                        ?>

                        <tr>

                            <td>#<?php echo $orderId; ?></td>

                            <td>

                                <?php echo htmlspecialchars((string)($order['user_name'] ?? '訪客')); ?>

                                <?php if (!empty($order['user_username'])): ?>

                                    <br><small>@<?php echo htmlspecialchars((string)$order['user_username']); ?></small>

                                <?php endif; ?>

                            </td>

                            <td><?php echo htmlspecialchars(staffCurrency((float)($order['final_amount'] ?? 0))); ?></td>

                            <td>

                                <span class="staff-badge <?php echo htmlspecialchars(staff_orders_status_badge_class((string)($order['status'] ?? ''))); ?>">

                                    <?php echo htmlspecialchars(staff_orders_display_status_label((string)($order['status'] ?? ''))); ?>

                                </span>

                            </td>

                            <td><?php

                                $pmRaw = (string)($order['payment_method'] ?? '');

                                echo htmlspecialchars($pmRaw !== '' ? ($paymentMap[$pmRaw] ?? $pmRaw) : '—');

                            ?></td>

                            <td><?php

                                $smRaw = (string)($order['shipping_method'] ?? '');

                                echo htmlspecialchars($smRaw !== '' ? ($shippingMap[$smRaw] ?? $smRaw) : '—');

                            ?></td>

                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$order['created_at']))); ?></td>

                            <td>

                                <div class="staff-order-actions-row">

                                    <form method="POST" class="staff-inline-form">

                                        <input type="hidden" name="preserve_q" value="<?php echo htmlspecialchars($q); ?>">

                                        <input type="hidden" name="preserve_status" value="<?php echo htmlspecialchars($status); ?>">

                                        <input type="hidden" name="action" value="update_status">

                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">

                                        <select name="new_status" class="staff-select staff-select-mini staff-select--order-row" <?php echo $isLocked ? 'disabled' : ''; ?>>

                                            <?php foreach ($allowedStatuses as $item): ?>

                                                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo staff_orders_status_option_is_selected((string)($order['status'] ?? ''), (string)$item) ? 'selected' : ''; ?>>

                                                    <?php echo htmlspecialchars(staff_orders_display_status_label((string)$item)); ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                        <button type="submit" class="staff-action-btn staff-action-btn-primary staff-action-btn--compact" <?php echo $isLocked ? 'disabled' : ''; ?>>更新訂單狀態</button>

                                    </form>

                                    <form method="POST" class="staff-inline-form">

                                        <input type="hidden" name="preserve_q" value="<?php echo htmlspecialchars($q); ?>">

                                        <input type="hidden" name="preserve_status" value="<?php echo htmlspecialchars($status); ?>">

                                        <input type="hidden" name="action" value="update_note">

                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">

                                        <input type="text" name="staff_note" class="staff-input staff-input-mini staff-input--order-note-row"

                                               placeholder="店員備註"

                                               value="<?php echo htmlspecialchars((string)($order['staff_note'] ?? '')); ?>">

                                        <button type="submit" class="staff-action-btn staff-action-btn-muted staff-action-btn--compact" <?php echo $hasStaffNoteColumn ? '' : 'disabled'; ?>>儲存備註</button>

                                    </form>

                                    <button

                                        type="button"

                                        class="staff-action-btn staff-action-btn-muted staff-action-btn--compact js-staff-order-toggle"

                                        data-target="staff-order-details-<?php echo $orderId; ?>"

                                    >

                                        展開訂單

                                    </button>

                                </div>

                            </td>

                        </tr>

                        <tr id="staff-order-details-<?php echo $orderId; ?>" class="staff-order-detail-row" style="display:none;">

                            <td colspan="8">

                                <div class="order-detail-panel">

                                    <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">

                                        <h3>商品清單</h3>

                                    </div>

                                    <?php if (empty($order['items'])): ?>

                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide">尚無商品明細。</p>

                                    <?php else: ?>

                                    <table class="order-detail-table staff-field-wide">

                                        <thead>

                                            <tr>

                                                <th>商品名稱</th>

                                                <th>尺寸</th>

                                                <th>數量</th>

                                                <th>單價</th>

                                                <th>小計</th>

                                            </tr>

                                        </thead>

                                        <tbody>

                                            <?php foreach (($order['items'] ?? []) as $item): ?>

                                                <?php

                                                    $itemName = (string)($item['product_name'] ?? '');

                                                    $itemThumbSrc = '../' . ltrim(resolve_product_card_image_src((string)($item['primary_image'] ?? '')), '/');

                                                ?>

                                                <tr>

                                                    <td class="order-detail-product-cell">

                                                        <img src="<?php echo htmlspecialchars($itemThumbSrc); ?>"

                                                             alt="<?php echo htmlspecialchars($itemName !== '' ? $itemName : '商品'); ?>"

                                                             class="order-detail-product-thumb"

                                                             width="44"

                                                             height="44"

                                                             loading="lazy">

                                                        <span><?php echo htmlspecialchars($itemName); ?></span>

                                                    </td>

                                                    <td><?php echo htmlspecialchars(formatCartSizeForDisplay((string)($item['size'] ?? ''))); ?></td>

                                                    <td><?php echo htmlspecialchars((string)($item['quantity'] ?? '0')); ?></td>

                                                    <td><?php echo htmlspecialchars(staffCurrency((float)($item['unit_price'] ?? 0))); ?></td>

                                                    <td><?php echo htmlspecialchars(staffCurrency((float)($item['subtotal'] ?? 0))); ?></td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                    <?php endif; ?>



                                    <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">

                                        <h3>收件資訊</h3>

                                    </div>

                                    <?php

                                        $dShip = (string)($order['shipping_method'] ?? '');

                                        $dAddr = trim((string)($order['shipping_address'] ?? ''));

                                        $dPick = trim((string)($order['pickup_store'] ?? ''));

                                        $dCo = $hasShippingCompany ? trim((string)($order['shipping_company'] ?? '')) : '';

                                        $dTrk = $hasTrackingNumber ? trim((string)($order['tracking_number'] ?? '')) : '';

                                        $shipLabel = $dShip !== '' ? ($shippingMap[$dShip] ?? $dShip) : '';

                                        $recvAllEmpty = ($shipLabel === '' && $dAddr === '' && $dPick === '' && $dCo === '' && $dTrk === '');

                                    ?>

                                    <?php if ($recvAllEmpty): ?>

                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide">尚無配送方式、地址、門市或物流單號等紀錄。</p>

                                    <?php else: ?>

                                    <div class="order-detail-grid staff-field-wide">

                                        <p><strong>配送方式：</strong><?php echo htmlspecialchars($shipLabel !== '' ? $shipLabel : '—'); ?></p>

                                        <p><strong>收件地址：</strong><?php echo htmlspecialchars($dAddr !== '' ? $dAddr : '—'); ?></p>

                                        <p><strong>取貨門市：</strong><?php echo htmlspecialchars($dPick !== '' ? $dPick : '—'); ?></p>

                                        <?php if ($hasShippingCompany): ?>

                                            <p><strong>物流公司：</strong><?php echo htmlspecialchars($dCo !== '' ? $dCo : '—'); ?></p>

                                        <?php endif; ?>

                                        <?php if ($hasTrackingNumber): ?>

                                            <p><strong>追蹤號碼：</strong><?php echo htmlspecialchars($dTrk !== '' ? $dTrk : '—'); ?></p>

                                        <?php endif; ?>

                                    </div>

                                    <?php endif; ?>



                                    <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">

                                        <h3>付款資訊</h3>

                                    </div>

                                    <div class="order-detail-grid staff-field-wide">

                                        <p><strong>付款方式：</strong><?php echo htmlspecialchars($paymentMap[(string)($order['payment_method'] ?? '')] ?? (string)($order['payment_method'] ?? '—')); ?></p>

                                        <p><strong>訂單小計：</strong><?php echo htmlspecialchars(staffCurrency((float)($order['total_amount'] ?? 0))); ?></p>

                                        <p><strong>折扣金額：</strong><?php echo htmlspecialchars(staffCurrency((float)($order['discount_amount'] ?? 0))); ?></p>

                                        <p><strong>應付金額：</strong><?php echo htmlspecialchars(staffCurrency((float)($order['final_amount'] ?? 0))); ?></p>

                                    </div>



                                    <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">

                                        <h3>取消原因</h3>

                                    </div>

                                    <?php if (strtolower((string)($order['status'] ?? '')) !== 'cancelled'): ?>

                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide">此訂單未取消，無取消原因。</p>

                                    <?php elseif (!$hasCancelReasonColumn): ?>

                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide">目前資料庫未建立取消原因欄位，無法顯示。</p>

                                    <?php else: ?>

                                        <?php $cr = trim((string)($order['cancel_reason'] ?? '')); ?>

                                        <?php if ($cr === ''): ?>

                                            <p class="staff-section-lede staff-section-lede--tight staff-field-wide">此訂單已取消，尚無取消原因紀錄。</p>

                                        <?php else: ?>

                                            <p class="staff-section-lede staff-section-lede--tight staff-field-wide"><?php echo nl2br(htmlspecialchars($cr, ENT_QUOTES, 'UTF-8')); ?></p>

                                        <?php endif; ?>

                                    <?php endif; ?>



                                    <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">

                                        <h3>退貨資訊</h3>

                                    </div>

                                    <?php if (!$returnsTableExists): ?>

                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide">系統未建立退貨申請表，無法顯示退貨資訊。</p>

                                    <?php elseif (empty($order['return_requests'])): ?>

                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide">尚無退貨申請紀錄。</p>

                                    <?php else: ?>

                                        <?php foreach ($order['return_requests'] as $ri => $ret): ?>

                                            <div class="order-detail-grid staff-field-wide" style="margin-bottom:10px;">

                                                <p><strong>申請 #<?php echo (int)($ri + 1); ?></strong></p>

                                                <p><strong>退貨原因：</strong><?php $rr = trim((string)($ret['reason'] ?? '')); echo $rr !== '' ? nl2br(htmlspecialchars($rr, ENT_QUOTES, 'UTF-8')) : '—'; ?></p>

                                                <p><strong>退貨狀態：</strong><?php echo htmlspecialchars(appOrderStatusLabel((string)($ret['status'] ?? ''))); ?></p>

                                                <?php if (array_key_exists('refund_status', $ret)): ?>

                                                    <p><strong>退款狀態：</strong><?php echo htmlspecialchars(appRefundStatusLabel((string)($ret['refund_status'] ?? ''))); ?></p>

                                                <?php endif; ?>

                                                <p><strong>申請時間：</strong><?php echo htmlspecialchars((string)($ret['created_at'] ?? '—')); ?></p>

                                                <?php if (!empty($ret['updated_at'])): ?>

                                                    <p><strong>更新時間：</strong><?php echo htmlspecialchars((string)$ret['updated_at']); ?></p>

                                                <?php endif; ?>

                                            </div>

                                        <?php endforeach; ?>

                                    <?php endif; ?>



                                    <form method="POST" class="staff-form-grid staff-order-fulfillment-form">

                                        <input type="hidden" name="preserve_q" value="<?php echo htmlspecialchars($q); ?>">

                                        <input type="hidden" name="preserve_status" value="<?php echo htmlspecialchars($status); ?>">

                                        <input type="hidden" name="action" value="update_fulfillment">

                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">



                                        <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">

                                            <h3>配送與物流</h3>

                                        </div>

                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide">更新取貨門市、收件地址或物流單號；結案訂單無法修改。</p>



                                        <label class="staff-field">

                                            <span>配送方式</span>

                                            <select name="shipping_method" class="staff-select" <?php echo $isLocked ? 'disabled' : ''; ?>>

                                                <option value="">未指定</option>

                                                <option value="pickup" <?php echo ((string)($order['shipping_method'] ?? '') === 'pickup') ? 'selected' : ''; ?>>超商取貨</option>

                                                <option value="home" <?php echo ((string)($order['shipping_method'] ?? '') === 'home') ? 'selected' : ''; ?>>宅配到府</option>

                                            </select>

                                        </label>

                                        <label class="staff-field staff-field-wide">

                                            <span>配送地址</span>

                                            <input type="text" name="shipping_address" class="staff-input" value="<?php echo htmlspecialchars((string)($order['shipping_address'] ?? '')); ?>" <?php echo $isLocked ? 'disabled' : ''; ?>>

                                        </label>

                                        <label class="staff-field staff-field-wide">

                                            <span>取貨門市</span>

                                            <input type="text" name="pickup_store" class="staff-input" value="<?php echo htmlspecialchars((string)($order['pickup_store'] ?? '')); ?>" <?php echo $isLocked ? 'disabled' : ''; ?>>

                                        </label>

                                        <?php if ($hasShippingCompany): ?>

                                            <label class="staff-field">

                                                <span>物流公司</span>

                                                <input type="text" name="shipping_company" class="staff-input" value="<?php echo htmlspecialchars((string)($order['shipping_company'] ?? '')); ?>" <?php echo $isLocked ? 'disabled' : ''; ?>>

                                            </label>

                                        <?php endif; ?>

                                        <?php if ($hasTrackingNumber): ?>

                                            <label class="staff-field">

                                                <span>追蹤號碼</span>

                                                <input type="text" name="tracking_number" class="staff-input" value="<?php echo htmlspecialchars((string)($order['tracking_number'] ?? '')); ?>" <?php echo $isLocked ? 'disabled' : ''; ?>>

                                            </label>

                                        <?php endif; ?>

                                        <div class="staff-form-actions staff-field-wide">

                                            <button type="submit" class="staff-btn" <?php echo $isLocked ? 'disabled' : ''; ?>>儲存配送與物流</button>

                                        </div>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<script>

document.addEventListener('click', function (e) {

    const btn = e.target.closest('.js-staff-order-toggle');

    if (!btn) return;

    const targetId = btn.getAttribute('data-target');

    const row = targetId ? document.getElementById(targetId) : null;

    if (!row) return;

    const isHidden = row.style.display === 'none' || row.style.display === '';

    row.style.display = isHidden ? 'table-row' : 'none';

    btn.textContent = isHidden ? '收起訂單' : '展開訂單';

});

</script>

<?php staffPageEnd(); ?>

