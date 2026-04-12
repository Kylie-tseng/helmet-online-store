<?php
require_once '../config.php';
require_once __DIR__ . '/../staff/includes/staff_layout.php';

staffRequireAuth();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// 讓停用/恢復可用：檢查 users 表狀態欄位
$statusColumn = '';
$hasIsActive = false;
$hasStatus = false;
$maybeUpdatedAt = false;

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = [];
    foreach ($cols as $c) {
        $colNames[(string)($c['Field'] ?? '')] = true;
    }
    if (!empty($colNames['is_active'])) {
        $statusColumn = 'is_active';
        $hasIsActive = true;
    } elseif (!empty($colNames['status'])) {
        $statusColumn = 'status';
        $hasStatus = true;
    }
    if (!empty($colNames['updated_at'])) {
        $maybeUpdatedAt = true;
    }
} catch (Throwable $e) {
    $statusColumn = '';
}

$flashMessage = '';
$flashType = 'success';

// 新增/更新操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'create_staff_account') {
        $username = strtoupper(trim((string)($_POST['username'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = trim((string) ($_POST['role'] ?? 'staff'));

        $allowedRoles = ['admin', 'staff'];
        $errors = [];
        if ($username === '') {
            $errors[] = '請輸入帳號';
        }
        if ($password === '' || strlen($password) < 6) {
            $errors[] = '密碼至少 6 碼';
        }
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = '角色不正確';
        }

        // 動態欄位：phone/address/is_active 是否存在
        $colNames = [];
        try {
            $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                $colNames[(string) ($c['Field'] ?? '')] = true;
            }
        } catch (Throwable $e) {
            $colNames = [];
        }

        $usePhone = !empty($colNames['phone']);
        $useAddress = !empty($colNames['address']);
        $useIsActive = !empty($colNames['is_active']);

        if (empty($errors)) {
            // 表單不收集：name 用帳號；email 內部唯一占位；phone/address 用空字串滿足 NOT NULL
            $name = $username;
            $email = 'staff+' . bin2hex(random_bytes(10)) . '@noreply.local';
            if (strlen($email) > 100) {
                $email = 'staff+' . bin2hex(random_bytes(8)) . '@noreply.local';
            }
            $phoneVal = '';
            $addressVal = '';

            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $fields = ['username', 'name', 'email', 'password', 'role'];
                $placeholders = [':username', ':name', ':email', ':password', ':role'];
                $params = [
                    ':username' => $username,
                    ':name' => $name,
                    ':email' => $email,
                    ':password' => $hashed,
                    ':role' => $role,
                ];

                if ($usePhone) {
                    $fields[] = 'phone';
                    $placeholders[] = ':phone';
                    $params[':phone'] = $phoneVal;
                }
                if ($useAddress) {
                    $fields[] = 'address';
                    $placeholders[] = ':address';
                    $params[':address'] = $addressVal;
                }
                if ($useIsActive) {
                    $fields[] = 'is_active';
                    $placeholders[] = ':is_active';
                    $params[':is_active'] = 1;
                }

                $sql = 'INSERT INTO users (' . implode(', ', $fields) . ')
                        VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $flashMessage = '店員帳號已建立。';
            } catch (Throwable $e) {
                $flashMessage = '建立失敗：帳號可能已存在，請換一組帳號再試。';
                $flashType = 'error';
            }
        } else {
            $flashMessage = implode('、', $errors);
            $flashType = 'error';
        }
    } elseif ($action === 'update_staff_account') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'staff'));

        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if ($userId > 0 && in_array($role, ['admin', 'staff'], true)) {
            try {
                // 動態欄位
                $colNames = [];
                $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) {
                    $colNames[(string)($c['Field'] ?? '')] = true;
                }

                $set = ['role = :role', 'name = :name', 'email = :email'];
                $params = [
                    ':role' => $role,
                    ':name' => $name,
                    ':email' => $email,
                    ':id' => $userId,
                ];

                if (!empty($colNames['phone'])) {
                    $set[] = 'phone = :phone';
                    $params[':phone'] = $phone;
                }
                if (!empty($colNames['address'])) {
                    $set[] = 'address = :address';
                    $params[':address'] = $address;
                }
                if ($maybeUpdatedAt) {
                    $set[] = 'updated_at = NOW()';
                }

                $sql = "UPDATE users SET " . implode(', ', $set) . " WHERE id = :id AND role IN ('admin','staff')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $flashMessage = '帳號資料已更新。';
            } catch (Throwable $e) {
                $flashMessage = '更新失敗，請稍後再試。';
                $flashType = 'error';
            }
        }
    } elseif ($action === 'toggle_staff_account') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $statusColumn !== '') {
            try {
                $stmt = $pdo->prepare("SELECT {$statusColumn} FROM users WHERE id = :id AND role IN ('admin','staff') LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($current) {
                    $currentVal = $current[$statusColumn] ?? null;
                    if ($statusColumn === 'is_active') {
                        $newVal = ((int)$currentVal === 1) ? 0 : 1;
                    } else {
                        $newVal = ((string)$currentVal === 'active') ? 'inactive' : 'active';
                    }
                    $set = "{$statusColumn} = :v";
                    if ($maybeUpdatedAt) {
                        $set .= ", updated_at = NOW()";
                    }
                    $upd = $pdo->prepare("UPDATE users SET {$set} WHERE id = :id");
                    $upd->execute([':v' => $newVal, ':id' => $userId]);
                    $flashMessage = '帳號狀態已更新。';
                } else {
                    $flashMessage = '找不到此員工帳號。';
                    $flashType = 'error';
                }
            } catch (Throwable $e) {
                $flashMessage = '更新失敗，請稍後再試。';
                $flashType = 'error';
            }
        } else {
            $flashMessage = '目前資料表沒有可用狀態欄位（is_active/status），無法停用/恢復。';
            $flashType = 'error';
        }
    }
}

$q = trim($_GET['q'] ?? '');
$members = [];
$maybePhone = false;
$maybeAddress = false;
$maybeUpdatedAt = false;

try {
    $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = [];
    foreach ($cols as $c) {
        $colNames[(string)($c['Field'] ?? '')] = true;
    }
    $maybePhone = !empty($colNames['phone']);
    $maybeAddress = !empty($colNames['address']);
    $maybeUpdatedAt = !empty($colNames['updated_at']);
} catch (Throwable $e) {
}

try {
    $where = ["u.role IN ('admin','staff')"];
    $params = [];
    if ($q !== '') {
        $where[] = "(u.name LIKE :q OR u.username LIKE :q OR u.email LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    $sql = "SELECT u.id, u.name, u.username, u.email, u.role, ";
    $sql .= ($statusColumn ? "u.{$statusColumn} AS status_value, " : "NULL AS status_value, ");
    $sql .= "u.phone, u.address
            FROM users u
            WHERE " . implode(' AND ', $where) . "
            ORDER BY u.id DESC
            LIMIT 200";

    // 如果 phone/address 不存在，改用 dynamic select
    if (!$maybePhone) {
        $sql = str_replace('u.phone, ', 'NULL AS phone, ', $sql);
    }
    if (!$maybeAddress) {
        $sql = str_replace('u.address', 'NULL AS address', $sql);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $members = [];
}

staffPageStart($pdo, '員工權限', 'staff_accounts');
?>
<section class="staff-panel">
    <?php if ($flashMessage !== ''): ?>
        <div class="staff-notice <?php echo $flashType === 'success' ? '' : 'error'; ?>">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <div class="staff-panel-head" style="margin-bottom: 12px;">
        <h2>新增店員帳號</h2>
        <p class="staff-panel-subtitle">僅需帳號、密碼與角色（不影響會員系統）</p>
    </div>

    <form method="POST" class="staff-form-grid">
        <input type="hidden" name="action" value="create_staff_account">

        <label class="staff-field">
            <span>帳號</span>
            <input type="text" name="username" class="staff-input" required autocomplete="username">
        </label>

        <label class="staff-field">
            <span>密碼</span>
            <input type="password" name="password" class="staff-input" required autocomplete="new-password" minlength="6">
        </label>

        <label class="staff-field staff-field-wide">
            <span>角色</span>
            <select name="role" class="staff-select" required>
                <option value="staff">店員</option>
                <option value="admin">管理者</option>
            </select>
        </label>

        <div class="staff-form-actions staff-field-wide" style="grid-column:1 / -1; display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="staff-btn">建立帳號</button>
        </div>
    </form>
</section>

<section class="staff-panel">
    <div class="staff-panel-head">
        <h2>員工帳號列表</h2>
        <p class="staff-panel-subtitle">可更新姓名/Email/電話/地址、角色與停用狀態</p>
    </div>

    <form method="GET" class="staff-toolbar" style="margin-top: 12px;">
        <input type="text" name="q" class="staff-input" placeholder="搜尋姓名 / 帳號 / Email" value="<?php echo htmlspecialchars($q); ?>">
        <button type="submit" class="staff-btn">查詢</button>
    </form>

    <div class="staff-table-wrap" style="margin-top: 12px;">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>帳號</th>
                    <th>Email</th>
                    <?php if ($maybePhone): ?><th>電話</th><?php endif; ?>
                    <?php if ($maybeAddress): ?><th>地址</th><?php endif; ?>
                    <th>角色</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                    <tr><td colspan="8">目前沒有員工帳號資料。</td></tr>
                <?php else: ?>
                    <?php foreach ($members as $m): ?>
                        <?php
                        $isActiveBadge = true;
                        if ($statusColumn === 'is_active') {
                            $isActiveBadge = ((int)($m['status_value'] ?? 0) === 1);
                        } elseif ($statusColumn === 'status') {
                            $isActiveBadge = ((string)($m['status_value'] ?? '') === 'active');
                        }
                        ?>
                        <tr>
                            <td>
                                <form method="POST" style="display:flex; gap:8px; align-items:center;">
                                    <input type="hidden" name="action" value="update_staff_account">
                                    <input type="hidden" name="user_id" value="<?php echo (int)($m['id'] ?? 0); ?>">
                                    <input type="text" name="name" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($m['name'] ?? '')); ?>">
                            </td>
                            <td><?php echo htmlspecialchars((string)($m['username'] ?? '')); ?></td>
                            <td>
                                    <input type="email" name="email" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($m['email'] ?? '')); ?>">
                            </td>
                            <?php if ($maybePhone): ?>
                                <td>
                                    <input type="text" name="phone" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($m['phone'] ?? '')); ?>">
                                </td>
                            <?php endif; ?>
                            <?php if ($maybeAddress): ?>
                                <td>
                                    <input type="text" name="address" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($m['address'] ?? '')); ?>">
                                </td>
                            <?php endif; ?>
                            <td>
                                <select name="role" class="staff-select staff-select-mini">
                                    <option value="staff" <?php echo ((string)($m['role'] ?? 'staff') === 'staff') ? 'selected' : ''; ?>>店員</option>
                                    <option value="admin" <?php echo ((string)($m['role'] ?? 'admin') === 'admin') ? 'selected' : ''; ?>>管理者</option>
                                </select>
                            </td>
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
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button type="submit" class="staff-action-btn staff-action-btn-primary">更新</button>
                                </form>

                                <?php if ($statusColumn !== ''): ?>
                                    <form method="POST" data-app-confirm-title="切換帳號狀態" data-app-confirm="確定切換帳號狀態？">
                                        <input type="hidden" name="action" value="toggle_staff_account">
                                        <input type="hidden" name="user_id" value="<?php echo (int)($m['id'] ?? 0); ?>">
                                        <button type="submit" class="staff-action-btn <?php echo $isActiveBadge ? 'staff-action-btn-danger' : 'staff-action-btn-primary'; ?>">
                                            <?php echo $isActiveBadge ? '停用' : '恢復'; ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
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

