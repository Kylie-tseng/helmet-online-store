<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/cart_functions.php';

staffRequireAuth();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$flashMessage = (string)($_SESSION['staff_orders_flash'] ?? '');
unset($_SESSION['staff_orders_flash']);

$allowedStatuses = ['pending', 'shipped', 'completed', 'return_requested', 'cancelled'];
$lockedStatuses = ['completed', 'cancelled'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$hasStaffNoteColumn = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM orders");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if ((string)$col['Field'] === 'staff_note') {
            $hasStaffNoteColumn = true;
            break;
        }
    }
} catch (Throwable $e) {
    $hasStaffNoteColumn = false;
}

if (!function_exists('staffOrdersRedirectWithFlash')) {
    function staffOrdersRedirectWithFlash(string $message): void
    {
        $_SESSION['staff_orders_flash'] = $message;
        $query = [];
        $q = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    if ($action === 'update_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');
        if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            staffOrdersRedirectWithFlash('狀態更新失敗：資料不正確。');
        }

        try {
            $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $orderId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                staffOrdersRedirectWithFlash('找不到此訂單。');
            }

            $currentStatus = get_order_status_key((string)($current['status'] ?? ''));
            if (in_array($currentStatus, $lockedStatuses, true) && $currentStatus !== $newStatus) {
                staffOrdersRedirectWithFlash('此訂單已結案，無法再變更狀態。');
            }

            $stmt = $pdo->prepare("UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
            markUserCouponUsedAfterOrderStatusChange($pdo, $orderId, $newStatus);
            staffOrdersRedirectWithFlash('訂單狀態已更新。');
        } catch (Throwable $e) {
            staffOrdersRedirectWithFlash('更新失敗，請稍後再試。');
        }
    } elseif ($action === 'update_note') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $staffNote = (string)($_POST['staff_note'] ?? '');
        if ($orderId <= 0) {
            staffOrdersRedirectWithFlash('備註更新失敗：資料不正確。');
        }
        if (!$hasStaffNoteColumn) {
            staffOrdersRedirectWithFlash('目前資料表缺少 staff_note 欄位。');
        }

        try {
            $stmt = $pdo->prepare("UPDATE orders SET staff_note = :note, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':note' => $staffNote, ':id' => $orderId]);
            staffOrdersRedirectWithFlash('訂單備註已更新。');
        } catch (Throwable $e) {
            staffOrdersRedirectWithFlash('備註更新失敗。');
        }
    } else {
        staffOrdersRedirectWithFlash('不支援的操作。');
    }
}

$orders = [];
try {
    $staffNoteSelect = $hasStaffNoteColumn ? "o.staff_note" : "NULL AS staff_note";
    $sql = "SELECT o.id, o.final_amount, o.status, o.created_at, o.payment_method, o.shipping_method, o.shipping_address, o.pickup_store, {$staffNoteSelect},
                   u.name AS user_name,
                   u.username AS user_username
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE 1=1";
    $params = [];
    if ($status !== '') {
        $sql .= " AND o.status = :status";
        $params[':status'] = $status;
    }
    if ($q !== '') {
        $sql .= " AND (
                    CAST(o.id AS CHAR) LIKE :q
                    OR u.name LIKE :q
                    OR u.username LIKE :q
                )";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY o.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orders as &$order) {
        $detailStmt = $pdo->prepare("SELECT oi.product_id, oi.quantity, oi.unit_price, oi.subtotal, oi.size,
                                            COALESCE(NULLIF(p.name, ''), CONCAT('商品 #', oi.product_id, '（可能已下架）')) AS product_name
                                     FROM order_items oi
                                     LEFT JOIN products p ON oi.product_id = p.id
                                     WHERE oi.order_id = :order_id");
        $detailStmt->execute([':order_id' => (int)$order['id']]);
        $order['items'] = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($order);
} catch (Throwable $e) {
    $orders = [];
}

staffPageStart($pdo, '訂單管理', 'orders');
?>
<section class="staff-panel">
    <?php if ($flashMessage !== ''): ?>
        <div class="staff-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
    <?php endif; ?>
    <form method="GET" class="staff-toolbar">
        <input
            type="text"
            name="q"
            class="staff-input"
            placeholder="搜尋訂單編號 / 會員名稱 / 會員帳號"
            value="<?php echo htmlspecialchars($q); ?>"
        >
        <select name="status" class="staff-select">
            <option value="">全部</option>
            <?php foreach ($allowedStatuses as $item): ?>
                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $status === $item ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(get_order_status_text($item)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="staff-btn">套用篩選</button>
    </form>

    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>訂單編號</th>
                    <th>會員</th>
                    <th>金額</th>
                    <th>狀態</th>
                    <th>訂單備註</th>
                    <th>日期</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7"><?php echo $q !== '' ? '查無符合搜尋條件的訂單。' : '目前沒有符合條件的訂單。'; ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $orderStatus = (string)($order['status'] ?? '');
                            $statusKey = get_order_status_key($orderStatus);
                            $statusClass = get_order_status_class($orderStatus);
                            $isLocked = in_array($statusKey, $lockedStatuses, true);
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
                                <span class="<?php echo htmlspecialchars($statusClass); ?>">
                                    <?php echo htmlspecialchars(get_order_status_text($orderStatus)); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="staff-inline-form staff-inline-stack">
                                    <input type="hidden" name="action" value="update_note">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <input type="text" name="staff_note" class="staff-input staff-input-mini"
                                           placeholder="輸入備註"
                                           value="<?php echo htmlspecialchars((string)($order['staff_note'] ?? '')); ?>">
                                    <button type="submit" class="staff-action-btn staff-action-btn-muted" <?php echo $hasStaffNoteColumn ? '' : 'disabled'; ?>>儲存</button>
                                </form>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$order['created_at']))); ?></td>
                            <td>
                                <div class="staff-order-actions">
                                    <form method="POST" class="staff-inline-form">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                        <select name="new_status" class="staff-select staff-select-mini" <?php echo $isLocked ? 'disabled' : ''; ?>>
                                            <?php foreach ($allowedStatuses as $item): ?>
                                                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo ($statusKey === $item) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(get_order_status_text($item)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="staff-action-btn staff-action-btn-primary" <?php echo $isLocked ? 'disabled' : ''; ?>>更新</button>
                                    </form>
                                    <button
                                        type="button"
                                        class="staff-action-btn staff-action-btn-muted js-staff-order-toggle"
                                        data-target="staff-order-details-<?php echo $orderId; ?>"
                                    >
                                        查看明細
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr id="staff-order-details-<?php echo $orderId; ?>" class="staff-order-detail-row" style="display:none;">
                            <td colspan="7">
                                <div class="order-detail-panel">
                                    <div class="order-detail-grid">
                                        <p><strong>付款方式：</strong><?php echo htmlspecialchars($paymentMap[(string)($order['payment_method'] ?? '')] ?? (string)($order['payment_method'] ?? '')); ?></p>
                                        <p><strong>配送方式：</strong><?php echo htmlspecialchars($shippingMap[(string)($order['shipping_method'] ?? '')] ?? (string)($order['shipping_method'] ?? '')); ?></p>
                                        <?php if (!empty($order['shipping_address'])): ?>
                                            <p><strong>配送地址：</strong><?php echo htmlspecialchars((string)$order['shipping_address']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($order['pickup_store'])): ?>
                                            <p><strong>取貨門市：</strong><?php echo htmlspecialchars((string)$order['pickup_store']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <table class="order-detail-table">
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
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string)($item['product_name'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars(formatCartSizeForDisplay((string)($item['size'] ?? ''))); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($item['quantity'] ?? '0')); ?></td>
                                                    <td><?php echo htmlspecialchars(staffCurrency((float)($item['unit_price'] ?? 0))); ?></td>
                                                    <td><?php echo htmlspecialchars(staffCurrency((float)($item['subtotal'] ?? 0))); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
    btn.textContent = isHidden ? '收起明細' : '查看明細';
});
</script>
<?php staffPageEnd(); ?>
