<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$message = '';
$message_type = '';
$edit_coupon = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_coupon') {
        $coupon_id = isset($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : 0;
        if ($coupon_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = :id");
                $stmt->execute([':id' => $coupon_id]);
                $message = '優惠券已刪除';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = '刪除失敗，請稍後再試';
                $message_type = 'error';
            }
        }
    } elseif ($action === 'toggle_coupon') {
        $coupon_id = isset($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : 0;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
        if ($coupon_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE coupons SET is_active = :is_active WHERE id = :id");
                $stmt->execute([
                    ':is_active' => $is_active === 1 ? 0 : 1,
                    ':id' => $coupon_id
                ]);
                $message = '優惠券狀態已更新';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = '更新狀態失敗，請稍後再試';
                $message_type = 'error';
            }
        }
    } elseif ($action === 'save_coupon') {
        $coupon_id = isset($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : 0;
        $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $discount_type = trim($_POST['discount_type'] ?? '');
        $discount_value = isset($_POST['discount_value']) ? (float)$_POST['discount_value'] : 0;
        $minimum_amount = isset($_POST['minimum_amount']) ? (float)$_POST['minimum_amount'] : 0;
        $start_date = trim($_POST['start_date'] ?? '');
        $expire_date = trim($_POST['expire_date'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];
        if ($coupon_code === '') {
            $errors[] = '請輸入 coupon code';
        }
        if (!in_array($discount_type, ['percent', 'fixed'], true)) {
            $errors[] = '折扣類型錯誤';
        }
        if ($discount_value <= 0) {
            $errors[] = '折扣數值需大於 0';
        }
        if ($discount_type === 'percent' && $discount_value > 100) {
            $errors[] = '百分比折扣不可超過 100';
        }
        if ($minimum_amount < 0) {
            $errors[] = '最低消費不可小於 0';
        }
        if ($start_date === '' || $expire_date === '') {
            $errors[] = '請填寫有效期限';
        }
        if ($start_date !== '' && $expire_date !== '' && $start_date > $expire_date) {
            $errors[] = '開始日期不可晚於到期日期';
        }

        if (empty($errors)) {
            try {
                if ($coupon_id > 0) {
                    $stmt = $pdo->prepare("UPDATE coupons
                                           SET coupon_code = :coupon_code,
                                               discount_type = :discount_type,
                                               discount_value = :discount_value,
                                               minimum_amount = :minimum_amount,
                                               start_date = :start_date,
                                               expire_date = :expire_date,
                                               is_active = :is_active
                                           WHERE id = :id");
                    $stmt->execute([
                        ':coupon_code' => $coupon_code,
                        ':discount_type' => $discount_type,
                        ':discount_value' => $discount_value,
                        ':minimum_amount' => $minimum_amount,
                        ':start_date' => $start_date,
                        ':expire_date' => $expire_date,
                        ':is_active' => $is_active,
                        ':id' => $coupon_id
                    ]);
                    $message = '優惠券已更新';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO coupons
                                           (coupon_code, discount_type, discount_value, minimum_amount, start_date, expire_date, is_active)
                                           VALUES
                                           (:coupon_code, :discount_type, :discount_value, :minimum_amount, :start_date, :expire_date, :is_active)");
                    $stmt->execute([
                        ':coupon_code' => $coupon_code,
                        ':discount_type' => $discount_type,
                        ':discount_value' => $discount_value,
                        ':minimum_amount' => $minimum_amount,
                        ':start_date' => $start_date,
                        ':expire_date' => $expire_date,
                        ':is_active' => $is_active
                    ]);
                    $message = '優惠券已新增';
                }
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = '儲存失敗，coupon code 可能重複';
                $message_type = 'error';
            }
        } else {
            $message = implode('、', $errors);
            $message_type = 'error';
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = :id");
        $stmt->execute([':id' => $edit_id]);
        $edit_coupon = $stmt->fetch();
    }
}

try {
    $stmt = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC, id DESC");
    $coupons = $stmt->fetchAll();
} catch (PDOException $e) {
    $coupons = [];
    if ($message === '') {
        $message = '讀取優惠券列表失敗';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>優惠券管理 - HelmetVRse</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="announcement-bar">
        <div class="announcement-content">管理後台系統</div>
    </div>

    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="../index.php">HelmetVRse</a>
            </div>
            <div class="nav-right">
                <a href="index.php" style="color: #FFFFFF; text-decoration: none; font-size: 14px;">返回後台首頁</a>
                <a href="../logout.php" style="color: #FFFFFF; text-decoration: none; font-size: 14px; margin-left: 20px;">登出</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1 class="dashboard-title">優惠券管理</h1>
                <p class="dashboard-subtitle">新增、修改、停用與刪除優惠券</p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="checkout-form-wrapper" style="margin-bottom: 24px;">
                <form method="POST" class="checkout-form">
                    <input type="hidden" name="action" value="save_coupon">
                    <input type="hidden" name="coupon_id" value="<?php echo $edit_coupon ? (int)$edit_coupon['id'] : 0; ?>">

                    <div class="form-section">
                        <h2 class="form-section-title"><?php echo $edit_coupon ? '修改優惠券' : '新增優惠券'; ?></h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Coupon Code</label>
                                <input type="text" name="coupon_code" class="form-input" required
                                       value="<?php echo htmlspecialchars($edit_coupon['coupon_code'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">折扣類型</label>
                                <select name="discount_type" class="form-input" required>
                                    <option value="percent" <?php echo (($edit_coupon['discount_type'] ?? '') === 'percent') ? 'selected' : ''; ?>>percent</option>
                                    <option value="fixed" <?php echo (($edit_coupon['discount_type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>fixed</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">折扣數值</label>
                                <input type="number" step="0.01" min="0.01" name="discount_value" class="form-input" required
                                       value="<?php echo htmlspecialchars($edit_coupon['discount_value'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">最低消費</label>
                                <input type="number" step="0.01" min="0" name="minimum_amount" class="form-input" required
                                       value="<?php echo htmlspecialchars($edit_coupon['minimum_amount'] ?? '0'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">開始日期</label>
                                <input type="date" name="start_date" class="form-input" required
                                       value="<?php echo htmlspecialchars($edit_coupon['start_date'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">到期日期</label>
                                <input type="date" name="expire_date" class="form-input" required
                                       value="<?php echo htmlspecialchars($edit_coupon['expire_date'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1"
                                    <?php echo !isset($edit_coupon['is_active']) || (int)$edit_coupon['is_active'] === 1 ? 'checked' : ''; ?>>
                                啟用優惠券
                            </label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary"><?php echo $edit_coupon ? '更新優惠券' : '新增優惠券'; ?></button>
                            <?php if ($edit_coupon): ?>
                                <a href="coupons.php" class="btn-secondary">取消編輯</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="cart-table-wrapper">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Coupon Code</th>
                            <th>折扣類型</th>
                            <th>折扣數值</th>
                            <th>最低消費</th>
                            <th>有效期限</th>
                            <th>是否啟用</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">尚無優惠券資料</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon): ?>
                                <tr class="cart-table-row">
                                    <td><?php echo htmlspecialchars($coupon['coupon_code']); ?></td>
                                    <td><?php echo htmlspecialchars($coupon['discount_type']); ?></td>
                                    <td>
                                        <?php if ($coupon['discount_type'] === 'percent'): ?>
                                            <?php echo rtrim(rtrim(number_format((float)$coupon['discount_value'], 2, '.', ''), '0'), '.'); ?>%
                                        <?php else: ?>
                                            NT$ <?php echo number_format((float)$coupon['discount_value'], 0); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>NT$ <?php echo number_format((float)$coupon['minimum_amount'], 0); ?></td>
                                    <td><?php echo htmlspecialchars($coupon['start_date']); ?> ~ <?php echo htmlspecialchars($coupon['expire_date']); ?></td>
                                    <td><?php echo (int)$coupon['is_active'] === 1 ? '啟用' : '停用'; ?></td>
                                    <td>
                                        <a href="coupons.php?edit=<?php echo (int)$coupon['id']; ?>" class="btn-secondary" style="margin-right: 6px;">修改</a>

                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_coupon">
                                            <input type="hidden" name="coupon_id" value="<?php echo (int)$coupon['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo (int)$coupon['is_active']; ?>">
                                            <button type="submit" class="btn-secondary"><?php echo (int)$coupon['is_active'] === 1 ? '停用' : '啟用'; ?></button>
                                        </form>

                                        <form method="POST" style="display:inline;" onsubmit="return confirm('確定要刪除此優惠券嗎？');">
                                            <input type="hidden" name="action" value="delete_coupon">
                                            <input type="hidden" name="coupon_id" value="<?php echo (int)$coupon['id']; ?>">
                                            <button type="submit" class="btn-delete">刪除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
