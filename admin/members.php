<?php
require_once '../config.php';
require_once __DIR__ . '/../staff/includes/staff_layout.php';

staffRequireAuth();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// 檢查 users 表可用狀態欄位（is_active / status）
$statusColumn = '';
$hasIsActive = false;
$hasStatus = false;
$statusActiveValue = 1;
$statusInactiveValue = 0;
$statusLabelActive = '啟用';
$statusLabelInactive = '停用';

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = [];
    foreach ($cols as $c) {
        $colNames[(string)($c['Field'] ?? '')] = true;
    }
    if (!empty($colNames['is_active'])) {
        $statusColumn = 'is_active';
        $hasIsActive = true;
        $statusActiveValue = 1;
        $statusInactiveValue = 0;
    } elseif (!empty($colNames['status'])) {
        $statusColumn = 'status';
        $hasStatus = true;
        $statusActiveValue = 'active';
        $statusInactiveValue = 'inactive';
    }
} catch (Throwable $e) {
    $statusColumn = '';
}

$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all'); // all / active / inactive
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}

// POST：停用 / 恢復
$flashMessage = '';
$flashType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_member') {
    $memberId = (int)($_POST['member_id'] ?? 0);
    if ($memberId > 0 && $statusColumn !== '') {
        try {
            $stmt = $pdo->prepare("SELECT {$statusColumn} FROM users WHERE id = :id AND role = 'member' LIMIT 1");
            $stmt->execute([':id' => $memberId]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($current) {
                $currentVal = $current[$statusColumn] ?? null;
                $newVal = $currentVal ? 0 : 1;
                if ($statusColumn === 'status') {
                    // status：字串 active/inactive
                    $newVal = ((string)$currentVal === 'active') ? 'inactive' : 'active';
                } elseif ($statusColumn === 'is_active') {
                    $newVal = ((int)$currentVal === 1) ? 0 : 1;
                }
                $upd = $pdo->prepare("UPDATE users SET {$statusColumn} = :v, updated_at = NOW() WHERE id = :id");
                $upd->execute([':v' => $newVal, ':id' => $memberId]);
                $flashMessage = '帳號狀態已更新。';
            } else {
                $flashMessage = '找不到此會員帳號。';
                $flashType = 'error';
            }
        } catch (Throwable $e) {
            $flashMessage = '更新失敗，請稍後再試。';
            $flashType = 'error';
        }
    } elseif ($memberId > 0 && $statusColumn === '') {
        $flashMessage = '目前資料表沒有可用狀態欄位（is_active/status），無法停用/恢復。';
        $flashType = 'error';
    }
}

// 顯示欄位（動態避免欄位不存在）
$userSelectCols = ['u.id', 'u.name', 'u.username', 'u.email', 'u.role'];
$maybePhone = false;
$maybeAddress = false;
$maybeCreatedAt = false;

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = [];
    foreach ($cols as $c) {
        $colNames[(string)($c['Field'] ?? '')] = true;
    }
    if (!empty($colNames['phone'])) {
        $maybePhone = true;
        $userSelectCols[] = 'u.phone';
    }
    if (!empty($colNames['address'])) {
        $maybeAddress = true;
        $userSelectCols[] = 'u.address';
    }
    if (!empty($colNames['created_at'])) {
        $maybeCreatedAt = true;
    }
} catch (Throwable $e) {
}

$statusSelect = $statusColumn ? ("u.{$statusColumn} AS status_value") : 'NULL AS status_value';

try {
    $where = ["u.role = 'member'"];
    $params = [];

    if ($q !== '') {
        $where[] = "(u.name LIKE :q OR u.username LIKE :q OR u.email LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    if ($statusColumn !== '' && $statusFilter !== 'all') {
        if ($statusColumn === 'is_active') {
            $where[] = "u.{$statusColumn} = :sv";
            $params[':sv'] = ($statusFilter === 'active') ? 1 : 0;
        } elseif ($statusColumn === 'status') {
            $where[] = "u.{$statusColumn} = :sv";
            $params[':sv'] = ($statusFilter === 'active') ? 'active' : 'inactive';
        }
    }

    $sql = "SELECT " . implode(', ', $userSelectCols) . ",
                   {$statusSelect},
                   (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status IN ('paid','shipped','completed')) AS orders_count,
                   (SELECT COALESCE(SUM(o.final_amount), 0) FROM orders o WHERE o.user_id = u.id AND o.status IN ('paid','shipped','completed')) AS total_spent
            FROM users u
            WHERE " . implode(' AND ', $where) . "
            ORDER BY " . ($maybeCreatedAt ? 'u.created_at DESC' : 'u.id DESC') . "
            LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $members = [];
}

staffPageStart($pdo, '會員管理', 'members');
?>
<section class="staff-panel">
    <?php if ($flashMessage !== ''): ?>
        <div class="staff-notice <?php echo $flashType === 'success' ? '' : 'error'; ?>">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="staff-toolbar">
        <input
            type="text"
            name="q"
            class="staff-input"
            placeholder="搜尋會員名稱 / 帳號 / Email"
            value="<?php echo htmlspecialchars($q); ?>"
        >
        <select name="status" class="staff-select">
            <option value="all">全部狀態</option>
            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>啟用</option>
            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>停用</option>
        </select>
        <button type="submit" class="staff-btn">套用篩選</button>
    </form>
</section>

<section class="staff-panel">
    <div class="staff-panel-head">
        <h2>會員列表</h2>
        <p class="staff-panel-subtitle">檢視基本資料、訂單數與消費總額</p>
    </div>

    <div class="staff-table-wrap" style="margin-top: 12px;">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>帳號</th>
                    <th>Email</th>
                    <?php if ($maybePhone): ?><th>電話</th><?php endif; ?>
                    <?php if ($maybeAddress): ?><th>地址</th><?php endif; ?>
                    <th>訂單數</th>
                    <th>消費總額</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="<?php echo $maybePhone && $maybeAddress ? 10 : ($maybePhone || $maybeAddress ? 9 : 8); ?>">目前沒有符合條件的會員。</td></tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <?php
                        $isActiveBadge = false;
                        if ($statusColumn === 'is_active') {
                            $isActiveBadge = ((int)($m['status_value'] ?? 0) === 1);
                        } elseif ($statusColumn === 'status') {
                            $isActiveBadge = ((string)($m['status_value'] ?? '') === 'active');
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($m['name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($m['username'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($m['email'] ?? '')); ?></td>
                            <?php if ($maybePhone): ?><td><?php echo htmlspecialchars((string)($m['phone'] ?? '')); ?></td><?php endif; ?>
                            <?php if ($maybeAddress): ?><td><?php echo htmlspecialchars((string)($m['address'] ?? '')); ?></td><?php endif; ?>
                            <td><?php echo number_format((int)($m['orders_count'] ?? 0)); ?></td>
                            <td><?php echo htmlspecialchars(staffCurrency((float)($m['total_spent'] ?? 0))); ?></td>
                            <td>
                                <?php if ($statusColumn === ''): ?>
                                    <span class="staff-badge pending">未知</span>
                                <?php else: ?>
                                    <span class="staff-badge <?php echo $isActiveBadge ? 'done' : 'danger'; ?>">
                                        <?php echo $isActiveBadge ? '啟用' : '停用'; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($statusColumn === ''): ?>
                                    <span class="staff-badge pending">無法管理</span>
                                <?php else: ?>
                                    <form method="POST" class="staff-inline-form" onsubmit="return confirm('確定要切換此會員帳號狀態嗎？');">
                                        <input type="hidden" name="action" value="toggle_member">
                                        <input type="hidden" name="member_id" value="<?php echo (int)($m['id'] ?? 0); ?>">
                                        <button type="submit" class="staff-action-btn <?php echo $isActiveBadge ? 'staff-action-btn-danger' : 'staff-action-btn-primary'; ?>" style="padding-left: 10px; padding-right: 10px;">
                                            <?php echo $isActiveBadge ? '停用' : '恢復'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php staffPageEnd(); ?>

