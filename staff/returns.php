<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/order_status_helpers.php';

staffRequireAuth();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$refundStatus = trim($_GET['refund_status'] ?? '');
$returns = [];
$returnsTableName = 'return_requests';
$returnsTableExists = false;
try {
    $check = $pdo->query("SHOW TABLES LIKE 'return_requests'");
    $returnsTableExists = (bool)$check->fetchColumn();
} catch (Throwable $e) {
    $returnsTableExists = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $returnsTableExists) {
    $action = trim($_POST['action'] ?? '');
    if ($action === 'update_refund_status') {
        $returnId = (int)($_POST['return_id'] ?? 0);
        $newRefundStatus = trim($_POST['new_refund_status'] ?? '');
        if ($returnId > 0 && in_array($newRefundStatus, ['pending_refund', 'refunded'], true)) {
            try {
                $stmt = $pdo->prepare("UPDATE return_requests
                                       SET refund_status = :refund_status,
                                           updated_at = NOW()
                                       WHERE id = :id");
                $stmt->execute([':refund_status' => $newRefundStatus, ':id' => $returnId]);
            } catch (Throwable $e) {
                // ignore update error
            }
        }
    }
}

if ($returnsTableExists) {
    try {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(CAST(r.id AS CHAR) LIKE :q OR CAST(r.order_id AS CHAR) LIKE :q OR u.name LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = "r.status = :status";
            $params[':status'] = $status;
        }
        if ($refundStatus !== '') {
            $where[] = "r.refund_status = :refund_status";
            $params[':refund_status'] = $refundStatus;
        }
        $sql = "SELECT r.id, r.order_id, r.reason, r.status, r.refund_status, r.created_at, r.updated_at, u.name AS user_name
                FROM return_requests r
                LEFT JOIN users u ON u.id = r.user_id";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY r.created_at DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $returns = [];
    }
}

staffPageStart($pdo, '退貨申請', 'returns');
?>
<section class="staff-panel">
    <?php if (!$returnsTableExists): ?>
        <div class="staff-empty-hint">
            尚未偵測到退貨申請主資料表 <code>return_requests</code>。
            請先建立該資料表後，此頁即會顯示真實申請清單。
        </div>
    <?php else: ?>
        <form method="GET" class="staff-toolbar">
            <input
                type="text"
                name="q"
                class="staff-input"
                placeholder="搜尋申請編號 / 訂單 / 會員"
                value="<?php echo htmlspecialchars($q); ?>"
            >
            <input
                type="text"
                name="status"
                class="staff-input"
                placeholder="狀態（例如：待處理）"
                value="<?php echo htmlspecialchars($status); ?>"
            >
            <select name="refund_status" class="staff-select">
                <option value="">退款狀態（全部）</option>
                <option value="pending_refund" <?php echo $refundStatus === 'pending_refund' ? 'selected' : ''; ?>>待退款</option>
                <option value="refunded" <?php echo $refundStatus === 'refunded' ? 'selected' : ''; ?>>已退款</option>
            </select>
            <button type="submit" class="staff-btn">查詢</button>
        </form>

        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>申請編號</th>
                        <th>訂單編號</th>
                        <th>會員</th>
                        <th>原因</th>
                        <th>狀態</th>
                        <th>退款狀態</th>
                        <th>日期</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr>
                            <td colspan="8">目前沒有符合條件的退貨申請。</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($returns as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$item['id']); ?></td>
                                <td>#<?php echo htmlspecialchars((string)$item['order_id']); ?></td>
                                <td><?php echo htmlspecialchars((string)($item['user_name'] ?? '未知會員')); ?></td>
                                <td><?php echo htmlspecialchars((string)($item['reason'] ?? '')); ?></td>
                                <td><span class="staff-badge <?php echo appStatusBadgeClass((string)($item['status'] ?? '')); ?>"><?php echo htmlspecialchars(appOrderStatusLabel((string)($item['status'] ?? ''))); ?></span></td>
                                <td>
                                    <span class="staff-badge <?php echo appStatusBadgeClass((string)($item['refund_status'] ?? 'pending_refund')); ?>">
                                        <?php echo htmlspecialchars(appRefundStatusLabel((string)($item['refund_status'] ?? 'pending_refund'))); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime((string)($item['updated_at'] ?: $item['created_at'])))); ?></td>
                                <td>
                                    <form method="POST" class="staff-inline-form">
                                        <input type="hidden" name="action" value="update_refund_status">
                                        <input type="hidden" name="return_id" value="<?php echo (int)$item['id']; ?>">
                                        <select name="new_refund_status" class="staff-select staff-select-mini">
                                            <option value="pending_refund" <?php echo ((string)($item['refund_status'] ?? '') === 'pending_refund') ? 'selected' : ''; ?>>待退款</option>
                                            <option value="refunded" <?php echo ((string)($item['refund_status'] ?? '') === 'refunded') ? 'selected' : ''; ?>>已退款</option>
                                        </select>
                                        <button type="submit" class="staff-action-btn staff-action-btn-primary">更新</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>

