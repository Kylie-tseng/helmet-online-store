<?php
require_once '../config.php';
require_once __DIR__ . '/../staff/includes/staff_layout.php';
require_once __DIR__ . '/../includes/cart_functions.php';
require_once __DIR__ . '/../includes/order_status_helpers.php';

staffRequireAuth();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$flashMessage = '';

$allowedStatuses = ['pending', 'pending_payment', 'paid', 'shipped', 'completed', 'cancelled'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$cancelableOrderStatuses = ['pending', 'pending_payment', 'paid'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    if ($action === 'cancel_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $orderId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    $flashMessage = '找不到此訂單';
                } elseif (!in_array((string)($order['status'] ?? ''), $cancelableOrderStatuses, true)) {
                    $flashMessage = '此訂單已出貨，無法取消';
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :order_id");
                    $stmt->execute([':order_id' => $orderId]);

                    $stmt = $pdo->prepare("SELECT product_id, size, quantity FROM order_items WHERE order_id = :order_id");
                    $stmt->execute([':order_id' => $orderId]);
                    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($order_items as $item) {
                        $sz = $item['size'] ?? null;
                        if ($sz === null || $sz === '' || $sz === getCartSizeNoneValue() || $sz === 'N') {
                            continue;
                        }

                        $stmt = $pdo->prepare("UPDATE product_sizes
                                               SET stock = stock + :quantity, updated_at = NOW()
                                               WHERE product_id = :product_id AND size = :size");
                        $stmt->execute([
                            ':quantity' => (int)($item['quantity'] ?? 0),
                            ':product_id' => (int)($item['product_id'] ?? 0),
                            ':size' => (string)$sz
                        ]);
                    }

                    $pdo->commit();
                    $flashMessage = '訂單已成功取消。';
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $flashMessage = '取消失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'update_status') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');
        if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            try {
                if ($newStatus === 'cancelled') {
                    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => $orderId]);
                    $current = $stmt->fetch(PDO::FETCH_ASSOC);
                    $currentStatus = (string)($current['status'] ?? '');

                    if (!in_array($currentStatus, $cancelableOrderStatuses, true)) {
                        $flashMessage = '此訂單已出貨，無法取消';
                        goto update_status_end;
                    }

                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :order_id");
                    $stmt->execute([':order_id' => $orderId]);

                    $stmt = $pdo->prepare("SELECT product_id, size, quantity FROM order_items WHERE order_id = :order_id");
                    $stmt->execute([':order_id' => $orderId]);
                    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($order_items as $item) {
                        $sz = $item['size'] ?? null;
                        if ($sz === null || $sz === '' || $sz === getCartSizeNoneValue() || $sz === 'N') {
                            continue;
                        }
                        $stmt = $pdo->prepare("UPDATE product_sizes
                                               SET stock = stock + :quantity, updated_at = NOW()
                                               WHERE product_id = :product_id AND size = :size");
                        $stmt->execute([
                            ':quantity' => (int)($item['quantity'] ?? 0),
                            ':product_id' => (int)($item['product_id'] ?? 0),
                            ':size' => (string)$sz
                        ]);
                    }

                    $pdo->commit();
                    $flashMessage = '訂單已成功取消。';
                    goto update_status_end;
                }

                $stmt = $pdo->prepare("UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
                $flashMessage = '訂單狀態已更新。';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $flashMessage = '更新失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'update_note') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $staffNote = trim($_POST['staff_note'] ?? '');
        if ($orderId > 0 && $hasStaffNoteColumn) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET staff_note = :note, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':note' => ($staffNote !== '' ? $staffNote : null), ':id' => $orderId]);
                $flashMessage = '訂單備註已更新。';
            } catch (Throwable $e) {
                $flashMessage = '備註更新失敗。';
            }
        }
    }
}

update_status_end:

// 訂單清單（與 staff/orders 同一個資料查詢邏輯）
$orders = [];
try {
    $staffNoteSelect = $hasStaffNoteColumn ? "o.staff_note" : "NULL AS staff_note";
    $sql = "SELECT o.id, o.final_amount, o.status, o.created_at, {$staffNoteSelect},
                   u.name AS user_name
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE 1=1";
    $params = [];
    if ($status !== '') {
        $sql .= " AND o.status = :status";
        $params[':status'] = $status;
    }
    if ($q !== '') {
        $sql .= " AND (CAST(o.id AS CHAR) LIKE :q OR u.name LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    $sql .= " ORDER BY o.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $orders = [];
}

// 訂單統計摘要（套用同一套查詢條件）
$stats = [
    'pending' => 0,
    'paid' => 0,
    'shipped' => 0,
    'completed' => 0,
    'cancelled' => 0,
];
try {
    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = "o.status = :status";
        $params[':status'] = $status;
    }
    if ($q !== '') {
        $where[] = "(CAST(o.id AS CHAR) LIKE :q OR u.name LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT o.status, COUNT(*) AS cnt
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
            GROUP BY o.status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $s = (string)($r['status'] ?? '');
        $cnt = (int)($r['cnt'] ?? 0);
        if ($s === 'pending' || $s === 'pending_payment') $stats['pending'] += $cnt;
        elseif ($s === 'paid') $stats['paid'] += $cnt;
        elseif ($s === 'shipped') $stats['shipped'] += $cnt;
        elseif ($s === 'completed') $stats['completed'] += $cnt;
        elseif ($s === 'cancelled') $stats['cancelled'] += $cnt;
    }
} catch (Throwable $e) {
}

// 最近訂單摘要（不套用狀態過濾，僅套用 q 條件）
$recentOrders = [];
try {
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = "(CAST(o.id AS CHAR) LIKE :q OR u.name LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT o.id, o.final_amount, o.status, o.created_at, u.name AS user_name
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            {$whereSql}
            ORDER BY o.created_at DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recentOrders = [];
}

staffPageStart($pdo, '訂單與營運', 'orders');
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
            placeholder="搜尋訂單編號 / 會員"
            value="<?php echo htmlspecialchars($q); ?>"
        >
        <select name="status" class="staff-select">
            <option value="">全部狀態</option>
            <?php foreach ($allowedStatuses as $item): ?>
                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $status === $item ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(staffStatusLabel($item)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="staff-btn">套用篩選</button>
    </form>

    <div class="staff-stats-grid" style="margin-top: 12px;">
        <article class="staff-stat-card">
            <div class="staff-stat-label">待處理訂單</div>
            <div class="staff-stat-value"><?php echo number_format((int)$stats['pending']); ?></div>
        </article>
        <article class="staff-stat-card">
            <div class="staff-stat-label">已付款</div>
            <div class="staff-stat-value"><?php echo number_format((int)$stats['paid']); ?></div>
        </article>
        <article class="staff-stat-card">
            <div class="staff-stat-label">已出貨</div>
            <div class="staff-stat-value"><?php echo number_format((int)$stats['shipped']); ?></div>
        </article>
        <article class="staff-stat-card">
            <div class="staff-stat-label">已完成</div>
            <div class="staff-stat-value"><?php echo number_format((int)$stats['completed']); ?></div>
        </article>
    </div>

    <div class="staff-panel-head" style="margin-top: 16px; margin-bottom: 10px;">
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
                            <td><?php echo htmlspecialchars(staffCurrency((float)($o['final_amount'] ?? 0))); ?></td>
                            <td><span class="staff-badge <?php echo staffStatusBadgeClass((string)($o['status'] ?? '')); ?>"><?php echo htmlspecialchars(staffStatusLabel((string)($o['status'] ?? ''))); ?></span></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)($o['created_at'] ?? '')))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="staff-panel">
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
                        <td colspan="7">目前沒有符合條件的訂單。</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php $isCancelable = in_array((string)($order['status'] ?? ''), $cancelableOrderStatuses, true); ?>
                        <tr>
                            <td>#<?php echo (int)$order['id']; ?></td>
                            <td><?php echo htmlspecialchars((string)($order['user_name'] ?? '訪客')); ?></td>
                            <td><?php echo htmlspecialchars(staffCurrency((float)($order['final_amount'] ?? 0))); ?></td>
                            <td>
                                <span class="staff-badge <?php echo staffStatusBadgeClass((string)($order['status'] ?? '')); ?>">
                                    <?php echo htmlspecialchars(staffStatusLabel((string)($order['status'] ?? 'unknown'))); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="staff-inline-form staff-inline-stack">
                                    <input type="hidden" name="action" value="update_note">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                    <input
                                        type="text"
                                        name="staff_note"
                                        class="staff-input staff-input-mini"
                                        placeholder="輸入備註"
                                        value="<?php echo htmlspecialchars((string)($order['staff_note'] ?? '')); ?>"
                                    >
                                    <button type="submit" class="staff-action-btn staff-action-btn-muted" <?php echo $hasStaffNoteColumn ? '' : 'disabled'; ?>>儲存</button>
                                </form>
                            </td>
                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)($order['created_at'] ?? '')))); ?></td>
                            <td>
                                <div class="staff-order-actions">
                                    <?php if ($isCancelable): ?>
                                        <form method="POST" class="staff-inline-form">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                            <button type="submit" class="staff-action-btn staff-action-btn-danger">取消訂單</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" class="staff-inline-form">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                        <select name="new_status" class="staff-select staff-select-mini">
                                            <?php foreach ($allowedStatuses as $item): ?>
                                                <?php $disabledCancel = ($item === 'cancelled' && !$isCancelable); ?>
                                                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo ((string)$order['status'] === $item) ? 'selected' : ''; ?> <?php echo $disabledCancel ? 'disabled' : ''; ?>>
                                                    <?php echo htmlspecialchars(staffStatusLabel($item)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="staff-action-btn staff-action-btn-primary">更新</button>
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

<?php staffPageEnd(); ?>

