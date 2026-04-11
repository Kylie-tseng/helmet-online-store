<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/order_status_helpers.php';

require_once __DIR__ . '/../includes/cart_functions.php';

staffRequireAuth();

$q = trim($_GET['q'] ?? '');

$qForId = ltrim($q, "# \t");

$qIdToken = ($qForId !== '') ? $qForId : $q;

$status = trim($_GET['status'] ?? '');

$refundStatus = trim($_GET['refund_status'] ?? '');

/** 清單篩選用（非 DB 一對一）：待處理、已同意、已拒絕、已完成 */

$staffReturnStatusFilterKeys = ['pending', 'approved', 'rejected', 'completed'];

$staffRefundFilterKeys = ['pending_refund', 'refunded'];

if ($status !== '' && !in_array($status, $staffReturnStatusFilterKeys, true)) {

    $status = '';

}

if ($refundStatus !== '' && !in_array($refundStatus, $staffRefundFilterKeys, true)) {

    $refundStatus = '';

}

$returns = [];

$returnsTableExists = false;

$hasRefundStatusColumn = false;

$returnReqColumnNames = [];

$returnNoteColumn = null;

$returnStats = ['total' => 0, 'pending_refund' => 0];

try {
    $check = $pdo->query("SHOW TABLES LIKE 'return_requests'");
    $returnsTableExists = (bool)$check->fetchColumn();
} catch (Throwable $e) {
    $returnsTableExists = false;
}

if ($returnsTableExists) {
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM return_requests');
        foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $fn = (string)($c['Field'] ?? '');
            if ($fn !== '') {
                $returnReqColumnNames[$fn] = true;
            }
            if ($fn === 'refund_status') {
                $hasRefundStatusColumn = true;
            }
        }
        foreach (['processing_note', 'staff_note', 'handler_note', 'admin_note'] as $cand) {
            if (!empty($returnReqColumnNames[$cand])) {
                $returnNoteColumn = $cand;
                break;
            }
        }
    } catch (Throwable $e) {
        $hasRefundStatusColumn = false;
        $returnReqColumnNames = [];
        $returnNoteColumn = null;
    }

    try {
        $returnStats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM return_requests")->fetchColumn();
        if ($hasRefundStatusColumn) {
            $returnStats['pending_refund'] = (int)$pdo->query("SELECT COUNT(*) FROM return_requests WHERE refund_status = 'pending_refund'")->fetchColumn();
        }
    } catch (Throwable $e) {
        $returnStats = ['total' => 0, 'pending_refund' => 0];
    }
}

/** 後台可寫入的退貨狀態：預設流程值，並併入資料庫已出現過的值以免舊資料無法更新 */

$allowedReturnStatuses = ['pending', 'approved', 'rejected', 'completed'];

if ($returnsTableExists) {
    try {
        $dist = $pdo->query("SELECT DISTINCT `status` FROM `return_requests` WHERE COALESCE(TRIM(`status`), '') <> ''");
        foreach ($dist->fetchAll(PDO::FETCH_COLUMN) as $v) {
            $v = trim((string) $v);
            if ($v !== '' && !in_array($v, $allowedReturnStatuses, true)) {
                $allowedReturnStatuses[] = $v;
            }
        }
    } catch (Throwable $e) {
    }
}

if (!function_exists('staff_returns_status_filter_sql')) {
    /**
     * @param array<string, mixed> $params
     */
    function staff_returns_status_filter_sql(string $filterKey, array &$params): string
    {
        if ($filterKey === '') {
            return '';
        }
        switch ($filterKey) {
            case 'pending':
                $vals = ['pending', 'pending_payment'];
                break;
            case 'approved':
                $vals = ['approved', 'accepted', 'agreed'];
                break;
            case 'rejected':
                $vals = ['rejected', 'declined'];
                break;
            case 'completed':
                $vals = ['completed', 'done', 'closed'];
                break;
            default:
                return '';
        }
        $ph = [];
        foreach ($vals as $i => $v) {
            $name = ':rsf_' . $filterKey . '_' . $i;
            $ph[] = $name;
            $params[$name] = $v;
        }

        return 'r.status IN (' . implode(', ', $ph) . ')';
    }
}

if (!function_exists('staff_returns_status_display')) {
    function staff_returns_status_display(string $s): string
    {
        $k = strtolower(trim($s));
        $map = [
            'pending' => '待處理',
            'pending_payment' => '待處理',
            'approved' => '已同意',
            'accepted' => '已同意',
            'agreed' => '已同意',
            'rejected' => '已拒絕',
            'declined' => '已拒絕',
            'completed' => '已完成',
            'done' => '已完成',
            'closed' => '已完成',
        ];
        if ($k === '') {
            return '—';
        }

        return $map[$k] ?? $s;
    }
}

if (!function_exists('staff_returns_refund_display')) {
    function staff_returns_refund_display(string $s): string
    {
        $k = strtolower(trim($s));
        if ($k === 'refunded') {
            return '已退款';
        }
        if ($k === 'pending_refund') {
            return '未退款';
        }

        return appRefundStatusLabel($s);
    }
}

if (!function_exists('staff_returns_status_bucket')) {
    /** 操作規則用：將 DB 狀態歸類為 pending／approved／rejected／completed／other */
    function staff_returns_status_bucket(string $s): string
    {
        $k = strtolower(trim($s));
        if (in_array($k, ['pending', 'pending_payment'], true)) {
            return 'pending';
        }
        if (in_array($k, ['approved', 'accepted', 'agreed'], true)) {
            return 'approved';
        }
        if (in_array($k, ['rejected', 'declined'], true)) {
            return 'rejected';
        }
        if (in_array($k, ['completed', 'done', 'closed'], true)) {
            return 'completed';
        }

        return $k === '' ? 'pending' : 'other';
    }
}

if (!function_exists('staff_returns_save_allowed_new_statuses')) {
    /**
     * @return list<string>
     */
    function staff_returns_save_allowed_new_statuses(string $bucket, array $allowedReturnStatuses): array
    {
        switch ($bucket) {
            case 'pending':
                $want = ['pending', 'approved', 'rejected'];
                break;
            case 'approved':
                $want = ['approved', 'rejected', 'completed'];
                break;
            case 'rejected':
                $want = ['rejected'];
                break;
            case 'completed':
                return [];
            default:
                return $allowedReturnStatuses;
        }

        return array_values(array_intersect($want, $allowedReturnStatuses));
    }
}

if (!function_exists('staffReturnsRedirectWithFlash')) {
    function staffReturnsRedirectWithFlash(string $message): void
    {
        staffNotifyFlashForRedirect($message);
        $query = [];
        $src = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
        $q = trim((string)($src['preserve_q'] ?? $src['q'] ?? ''));
        $status = trim((string)($src['preserve_status'] ?? $src['status'] ?? ''));
        $refundStatus = trim((string)($src['preserve_refund_status'] ?? $src['refund_status'] ?? ''));
        if ($q !== '') {
            $query['q'] = $q;
        }
        if ($status !== '') {
            $query['status'] = $status;
        }
        if ($refundStatus !== '') {
            $query['refund_status'] = $refundStatus;
        }
        $target = 'returns.php';
        if (!empty($query)) {
            $target .= '?' . http_build_query($query);
        }
        header('Location: ' . $target);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $returnsTableExists) {
    $action = trim($_POST['action'] ?? '');
    if ($action === 'save_return_handling') {
        $returnId = (int)($_POST['return_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');
        $newRefundStatus = trim($_POST['new_refund_status'] ?? '');
        $processingNote = trim((string)($_POST['processing_note'] ?? ''));
        if ($returnId <= 0 || !in_array($newStatus, $allowedReturnStatuses, true)) {
            staffReturnsRedirectWithFlash('儲存失敗：退貨狀態資料不正確。');
        }
        if ($hasRefundStatusColumn && !in_array($newRefundStatus, ['pending_refund', 'refunded'], true)) {
            staffReturnsRedirectWithFlash('儲存失敗：退款狀態資料不正確。');
        }
        try {
            $stmt = $pdo->prepare('SELECT status, refund_status FROM return_requests WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $returnId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                staffReturnsRedirectWithFlash('找不到此退貨申請。');
            }
            $curStatus = (string)($row['status'] ?? '');
            $curRefund = $hasRefundStatusColumn ? (string)($row['refund_status'] ?? 'pending_refund') : 'pending_refund';
            $bucket = staff_returns_status_bucket($curStatus);
            $newBucket = staff_returns_status_bucket($newStatus);
            if ($bucket === 'completed') {
                staffReturnsRedirectWithFlash('此申請已完成結案，無法再變更。');
            }
            $allowedNew = staff_returns_save_allowed_new_statuses($bucket, $allowedReturnStatuses);
            if ($allowedNew !== [] && !in_array($newStatus, $allowedNew, true)) {
                staffReturnsRedirectWithFlash('儲存失敗：此狀態下不可變更為所選退貨狀態。');
            }
            if ($hasRefundStatusColumn) {
                if ($bucket !== 'approved' && strtolower($newRefundStatus) !== strtolower($curRefund)) {
                    staffReturnsRedirectWithFlash('僅「已同意」之申請可變更退款狀態。');
                }
                if ($newBucket === 'rejected' && strtolower($newRefundStatus) === 'refunded') {
                    staffReturnsRedirectWithFlash('已拒絕之申請不可設為已退款。');
                }
            }
            $sets = ['status = :status', 'updated_at = NOW()'];
            $execParams = [':status' => $newStatus, ':id' => $returnId];
            if ($hasRefundStatusColumn) {
                $rf = $newRefundStatus;
                if ($newBucket === 'rejected') {
                    $rf = 'pending_refund';
                }
                $sets[] = 'refund_status = :refund_status';
                $execParams[':refund_status'] = $rf;
            }
            if ($returnNoteColumn !== null) {
                $sets[] = '`' . str_replace('`', '', $returnNoteColumn) . '` = :pnote';
                $execParams[':pnote'] = $processingNote;
            }
            $sqlUpd = 'UPDATE return_requests SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $upd = $pdo->prepare($sqlUpd);
            $upd->execute($execParams);
            staffReturnsRedirectWithFlash('退貨處理已儲存成功。');
        } catch (Throwable $e) {
            staffReturnsRedirectWithFlash('儲存失敗，請稍後再試。');
        }
    } elseif ($action === 'update_status' || $action === 'update_refund_status') {
        staffReturnsRedirectWithFlash('請使用「儲存」送出退貨處理。');
    } else {
        staffReturnsRedirectWithFlash('不支援的操作。');
    }
}

if ($returnsTableExists) {
    try {
        $where = [];
        $params = [];
        if ($q !== '') {
            $likeEsc = static function (string $s): string {
                return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
            };
            $params[':q_name'] = '%' . $likeEsc($q) . '%';
            $params[':q_rid'] = '%' . $likeEsc($qIdToken) . '%';
            $params[':q_oid'] = '%' . $likeEsc($qIdToken) . '%';
            $searchParts = [
                'CAST(r.id AS CHAR) LIKE :q_rid',
                'CAST(r.order_id AS CHAR) LIKE :q_oid',
                "COALESCE(u.name, '') LIKE :q_name",
            ];
            if ($qForId !== '' && ctype_digit($qForId)) {
                $searchParts[] = '(r.id = :q_exact_id OR r.order_id = :q_exact_oid)';
                $params[':q_exact_id'] = (int) $qForId;
                $params[':q_exact_oid'] = (int) $qForId;
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }
        if ($status !== '') {
            $sf = staff_returns_status_filter_sql($status, $params);
            if ($sf !== '') {
                $where[] = $sf;
            }
        }
        if ($refundStatus !== '' && $hasRefundStatusColumn) {
            $where[] = 'r.refund_status = :refund_status';
            $params[':refund_status'] = $refundStatus;
        }
        $refundSelect = $hasRefundStatusColumn ? 'r.refund_status' : "'pending_refund' AS refund_status";
        $noteColSafe = $returnNoteColumn !== null ? str_replace('`', '', $returnNoteColumn) : '';
        $noteSelectSql = ($returnNoteColumn !== null) ? ('r.`' . $noteColSafe . '` AS return_processing_note') : 'NULL AS return_processing_note';
        $sql = "SELECT r.id, r.order_id, r.reason, r.status, {$refundSelect}, r.created_at, r.updated_at, u.name AS user_name,
                       u.username AS detail_user_username,
                       {$noteSelectSql},
                       o.id AS detail_order_row_id,
                       o.final_amount AS detail_order_final_amount,
                       o.total_amount AS detail_order_total_amount,
                       o.status AS detail_order_status,
                       o.payment_method AS detail_order_payment_method,
                       o.shipping_method AS detail_order_shipping_method,
                       o.created_at AS detail_order_created_at
                FROM return_requests r
                LEFT JOIN orders o ON o.id = r.order_id
                LEFT JOIN users u ON u.id = COALESCE(r.user_id, o.user_id)";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $orderIds = [];
        foreach ($returns as $rw) {
            $oid = (int)($rw['order_id'] ?? 0);
            if ($oid > 0) {
                $orderIds[$oid] = true;
            }
        }
        $itemsByOrder = [];
        if ($orderIds !== []) {
            $ids = array_keys($orderIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            try {
                $itStmt = $pdo->prepare("SELECT oi.order_id, oi.product_id, oi.quantity, oi.unit_price, oi.subtotal, oi.size,
                                                COALESCE(NULLIF(p.name, ''), CONCAT('商品 #', oi.product_id, '（可能已下架）')) AS product_name
                                         FROM order_items oi
                                         LEFT JOIN products p ON p.id = oi.product_id
                                         WHERE oi.order_id IN ({$placeholders})");
                $itStmt->execute($ids);
                foreach ($itStmt->fetchAll(PDO::FETCH_ASSOC) as $ir) {
                    $ok = (int)($ir['order_id'] ?? 0);
                    if (!isset($itemsByOrder[$ok])) {
                        $itemsByOrder[$ok] = [];
                    }
                    $itemsByOrder[$ok][] = $ir;
                }
            } catch (Throwable $e) {
                $itemsByOrder = [];
            }
        }
        foreach ($returns as &$ret) {
            $ok = (int)($ret['order_id'] ?? 0);
            $ret['detail_items'] = $itemsByOrder[$ok] ?? [];
        }
        unset($ret);
    } catch (Throwable $e) {
        $returns = [];
    }
}

staffPageStart($pdo, '退貨申請處理', 'returns');
?>
<section class="staff-panel">
    <?php if (!$returnsTableExists): ?>
        <div class="staff-empty-hint">
            尚未偵測到退貨申請主資料表 <code>return_requests</code>。
            請先建立該資料表後，此頁即會顯示實際申請清單。
        </div>
    <?php else: ?>
        <div class="staff-panel-head">
            <h2>申請概覽</h2>
        </div>
        <div class="staff-stats-grid staff-stats-grid--compact">
            <article class="staff-stat-card">
                <div class="staff-stat-label">退貨申請總數</div>
                <div class="staff-stat-value"><?php echo number_format((int)$returnStats['total']); ?></div>
            </article>
            <article class="staff-stat-card">
                <div class="staff-stat-label">待退款</div>
                <div class="staff-stat-value"><?php echo number_format((int)$returnStats['pending_refund']); ?></div>
                <?php if (!$hasRefundStatusColumn): ?>
                    <div class="staff-stat-note">尚無退款狀態欄位</div>
                <?php endif; ?>
            </article>
        </div>
        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>篩選條件</h2>
        </div>
        <form method="GET" class="staff-toolbar">
            <input
                type="text"
                name="q"
                class="staff-input"
                placeholder="搜尋申請編號、訂單編號、會員名稱"
                value="<?php echo htmlspecialchars($q); ?>"
            >
            <select name="status" class="staff-select" aria-label="退貨狀態">
                <option value="">退貨狀態：全部</option>
                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>待處理</option>
                <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>已同意</option>
                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>已拒絕</option>
                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>
            </select>
            <select name="refund_status" class="staff-select" aria-label="退款狀態" <?php echo $hasRefundStatusColumn ? '' : 'disabled title="尚未建立 refund_status 欄位"'; ?>>
                <option value="">退款狀態：全部</option>
                <option value="pending_refund" <?php echo $refundStatus === 'pending_refund' ? 'selected' : ''; ?>>未退款</option>
                <option value="refunded" <?php echo $refundStatus === 'refunded' ? 'selected' : ''; ?>>已退款</option>
            </select>
            <button type="submit" class="staff-btn">套用篩選</button>
        </form>

        <div class="staff-panel-head staff-panel-head--tight-top">
            <h2>退貨申請清單</h2>
        </div>
        <?php if (!$hasRefundStatusColumn): ?>
            <div class="staff-notice">目前 <code>return_requests</code> 缺少 <code>refund_status</code> 欄位，退款狀態篩選與列表欄位將無法套用；請執行資料庫更新腳本以啟用退款狀態管理。</div>
        <?php endif; ?>

        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>申請編號</th>
                        <th>訂單編號</th>
                        <th>會員</th>
                        <th>退貨原因</th>
                        <th>退貨狀態</th>
                        <th>退款狀態</th>
                        <th>申請日期</th>
                        <th>更新時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr>
                            <td colspan="9">
                                <?php echo $q !== '' || $status !== '' || $refundStatus !== '' ? '查無符合條件的退貨申請' : '目前尚無退貨申請'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $detailPaymentMap = ['credit_card' => '信用卡', 'cod' => '貨到付款'];
                        $detailShipMap = ['pickup' => '超商取貨', 'home' => '宅配到府'];
                        ?>
                        <?php foreach ($returns as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$item['id']); ?></td>
                                <td><?php echo htmlspecialchars((string)(int)($item['order_id'] ?? 0)); ?></td>
                                <td><?php echo htmlspecialchars((string)($item['user_name'] ?? '訪客')); ?></td>
                                <td><?php echo htmlspecialchars((string)($item['reason'] ?? '')); ?></td>
                                <td><span class="staff-badge <?php echo appStatusBadgeClass((string)($item['status'] ?? '')); ?>"><?php echo htmlspecialchars(staff_returns_status_display((string)($item['status'] ?? ''))); ?></span></td>
                                <td>
                                    <?php if ($hasRefundStatusColumn): ?>
                                        <span class="staff-badge <?php echo appStatusBadgeClass((string)($item['refund_status'] ?? 'pending_refund')); ?>">
                                            <?php echo htmlspecialchars(staff_returns_refund_display((string)($item['refund_status'] ?? 'pending_refund'))); ?>
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)($item['created_at'] ?? '')))); ?></td>
                                <td><?php
                                    $uAt = trim((string)($item['updated_at'] ?? ''));
                                    echo $uAt !== '' ? htmlspecialchars(date('Y-m-d H:i', strtotime($uAt))) : '—';
                                ?></td>
                                <td>
                                    <?php
                                    $itemBucket = staff_returns_status_bucket((string)($item['status'] ?? ''));
                                    $itemIsLocked = ($itemBucket === 'completed');
                                    $optStatuses = staff_returns_save_allowed_new_statuses($itemBucket, $allowedReturnStatuses);
                                    $curRf = strtolower((string)($item['refund_status'] ?? 'pending_refund'));
                                    $refundInteract = $hasRefundStatusColumn && ($itemBucket === 'approved') && !$itemIsLocked;
                                    $noteVal = (string)($item['return_processing_note'] ?? '');
                                    $detailRid = (int)($item['id'] ?? 0);
                                    ?>
                                    <button type="button" class="staff-action-btn staff-action-btn-muted js-staff-return-toggle" data-target="staff-return-details-<?php echo $detailRid; ?>">查看詳情</button>
                                    <?php if (!$itemIsLocked): ?>
                                    <div class="staff-return-actions"<?php echo $itemBucket === 'rejected' ? ' style="opacity:0.72"' : ''; ?>>
                                        <form method="POST" class="staff-inline-form staff-inline-stack">
                                            <input type="hidden" name="preserve_q" value="<?php echo htmlspecialchars($q); ?>">
                                            <input type="hidden" name="preserve_status" value="<?php echo htmlspecialchars($status); ?>">
                                            <input type="hidden" name="preserve_refund_status" value="<?php echo htmlspecialchars($refundStatus); ?>">
                                            <input type="hidden" name="action" value="save_return_handling">
                                            <input type="hidden" name="return_id" value="<?php echo (int)$item['id']; ?>">
                                            <label class="staff-field" style="min-width:0;">
                                                <span>退貨狀態</span>
                                                <?php if (count($optStatuses) <= 1): ?>
                                                    <input type="hidden" name="new_status" value="<?php echo htmlspecialchars((string)($item['status'] ?? '')); ?>">
                                                    <select class="staff-select staff-select-mini" disabled>
                                                        <?php foreach ($optStatuses as $st): ?>
                                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ((string)($item['status'] ?? '') === $st) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars(staff_returns_status_display($st)); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <select name="new_status" class="staff-select staff-select-mini">
                                                        <?php foreach ($optStatuses as $st): ?>
                                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ((string)($item['status'] ?? '') === $st) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars(staff_returns_status_display($st)); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                            </label>
                                            <label class="staff-field" style="min-width:0;">
                                                <span>退款狀態</span>
                                                <?php if ($hasRefundStatusColumn): ?>
                                                    <?php if ($refundInteract): ?>
                                                        <select name="new_refund_status" class="staff-select staff-select-mini">
                                                            <option value="pending_refund" <?php echo $curRf === 'pending_refund' ? 'selected' : ''; ?>>未退款</option>
                                                            <option value="refunded" <?php echo $curRf === 'refunded' ? 'selected' : ''; ?>>已退款</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <input type="hidden" name="new_refund_status" value="<?php echo htmlspecialchars($curRf === 'refunded' ? 'refunded' : 'pending_refund'); ?>">
                                                        <select class="staff-select staff-select-mini" disabled>
                                                            <option value="pending_refund" <?php echo $curRf === 'pending_refund' ? 'selected' : ''; ?>>未退款</option>
                                                            <option value="refunded" <?php echo $curRf === 'refunded' ? 'selected' : ''; ?>>已退款</option>
                                                        </select>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="staff-section-lede staff-section-lede--tight">—</span>
                                                <?php endif; ?>
                                            </label>
                                            <label class="staff-field staff-field-wide" style="min-width:0;">
                                                <span>處理備註</span>
                                                <input type="text" name="processing_note" class="staff-input staff-input-mini" placeholder="可留空"
                                                    value="<?php echo htmlspecialchars($noteVal); ?>"
                                                    <?php echo $returnNoteColumn === null ? 'disabled title="資料表尚無處理備註欄位"' : ''; ?>>
                                            </label>
                                            <button type="submit" class="staff-action-btn staff-action-btn-primary">儲存</button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr id="staff-return-details-<?php echo $detailRid; ?>" class="staff-order-detail-row" style="display:none;">
                                <td colspan="9">
                                    <div class="order-detail-panel">
                                        <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">
                                            <h3>訂單基本資訊</h3>
                                        </div>
                                        <?php $hasOrderRow = !empty($item['detail_order_row_id']); ?>
                                        <?php if (!$hasOrderRow): ?>
                                            <p class="staff-section-lede staff-section-lede--tight staff-field-wide">尚無對應訂單資料，或訂單已不存在。</p>
                                        <?php else: ?>
                                            <div class="order-detail-grid staff-field-wide">
                                                <p><strong>訂單編號：</strong><?php echo htmlspecialchars((string)(int)($item['order_id'] ?? 0)); ?></p>
                                                <p><strong>會員：</strong><?php echo htmlspecialchars((string)($item['user_name'] ?? '訪客')); ?><?php
                                                    $du = trim((string)($item['detail_user_username'] ?? ''));
                                                    if ($du !== '') {
                                                        echo ' <small>@' . htmlspecialchars($du) . '</small>';
                                                    }
                                                ?></p>
                                                <p><strong>訂單狀態：</strong><?php echo htmlspecialchars(appOrderStatusLabel((string)($item['detail_order_status'] ?? ''))); ?></p>
                                                <p><strong>訂單建立時間：</strong><?php
                                                    $oct = trim((string)($item['detail_order_created_at'] ?? ''));
                                                    echo $oct !== '' ? htmlspecialchars(date('Y-m-d H:i', strtotime($oct))) : '—';
                                                ?></p>
                                                <p><strong>訂單小計：</strong><?php echo htmlspecialchars(staffCurrency((float)($item['detail_order_total_amount'] ?? 0))); ?></p>
                                                <p><strong>應付金額：</strong><?php echo htmlspecialchars(staffCurrency((float)($item['detail_order_final_amount'] ?? 0))); ?></p>
                                                <p><strong>付款方式：</strong><?php
                                                    $pm = (string)($item['detail_order_payment_method'] ?? '');
                                                    echo htmlspecialchars($pm !== '' ? ($detailPaymentMap[$pm] ?? $pm) : '—');
                                                ?></p>
                                                <p><strong>配送方式：</strong><?php
                                                    $sm = (string)($item['detail_order_shipping_method'] ?? '');
                                                    echo htmlspecialchars($sm !== '' ? ($detailShipMap[$sm] ?? $sm) : '—');
                                                ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">
                                            <h3>商品資訊</h3>
                                        </div>
                                        <?php if (empty($item['detail_items'])): ?>
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
                                                    <?php foreach ($item['detail_items'] as $dit): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars((string)($dit['product_name'] ?? '')); ?></td>
                                                            <td><?php echo htmlspecialchars(formatCartSizeForDisplay((string)($dit['size'] ?? ''))); ?></td>
                                                            <td><?php echo htmlspecialchars((string)($dit['quantity'] ?? '0')); ?></td>
                                                            <td><?php echo htmlspecialchars(staffCurrency((float)($dit['unit_price'] ?? 0))); ?></td>
                                                            <td><?php echo htmlspecialchars(staffCurrency((float)($dit['subtotal'] ?? 0))); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>

                                        <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">
                                            <h3>原始退貨原因</h3>
                                        </div>
                                        <?php $rawReason = trim((string)($item['reason'] ?? '')); ?>
                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide"><?php echo $rawReason !== '' ? nl2br(htmlspecialchars($rawReason, ENT_QUOTES, 'UTF-8')) : '會員未填寫退貨原因。'; ?></p>

                                        <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">
                                            <h3>會員申請時間</h3>
                                        </div>
                                        <?php $apAt = trim((string)($item['created_at'] ?? '')); ?>
                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide"><?php echo $apAt !== '' ? htmlspecialchars(date('Y-m-d H:i', strtotime($apAt))) : '尚無申請時間紀錄。'; ?></p>

                                        <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">
                                            <h3>處理備註</h3>
                                        </div>
                                        <?php if ($returnNoteColumn === null): ?>
                                            <p class="staff-section-lede staff-section-lede--tight staff-field-wide">目前資料表尚無處理備註欄位。</p>
                                        <?php else: ?>
                                            <?php $pn = trim((string)($item['return_processing_note'] ?? '')); ?>
                                            <p class="staff-section-lede staff-section-lede--tight staff-field-wide"><?php echo $pn !== '' ? nl2br(htmlspecialchars($pn, ENT_QUOTES, 'UTF-8')) : '尚無處理備註內容。'; ?></p>
                                        <?php endif; ?>

                                        <div class="staff-panel-head staff-panel-head--tight-top staff-field-wide">
                                            <h3>更新時間</h3>
                                        </div>
                                        <?php $upAt = trim((string)($item['updated_at'] ?? '')); ?>
                                        <p class="staff-section-lede staff-section-lede--tight staff-field-wide"><?php echo $upAt !== '' ? htmlspecialchars(date('Y-m-d H:i', strtotime($upAt))) : '尚無更新時間紀錄。'; ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-staff-return-toggle');
    if (!btn) return;
    const targetId = btn.getAttribute('data-target');
    const row = targetId ? document.getElementById(targetId) : null;
    if (!row) return;
    const isHidden = row.style.display === 'none' || row.style.display === '';
    row.style.display = isHidden ? 'table-row' : 'none';
    btn.textContent = isHidden ? '收起詳情' : '查看詳情';
});
</script>
<?php staffPageEnd(); ?>
