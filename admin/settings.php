<?php
require_once '../config.php';
require_once __DIR__ . '/../staff/includes/staff_layout.php';

staffRequireAuth();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- settings 表確保存在 ---
try {
    $check = $pdo->query("SHOW TABLES LIKE 'settings'");
    $exists = (bool)$check->fetchColumn();
    if (!$exists) {
        $pdo->exec("CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT NOW(),
            updated_at DATETIME NOT NULL DEFAULT NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (Throwable $e) {
    // ignore
}

$flashMessage = '';
$flashType = 'success';

$defaultSettings = [
    'free_shipping_threshold' => '3000',
    'default_discount_type' => 'percent',
    'default_discount_value' => '10',
    'site_name' => 'HelmetVRse',
    'site_contact_email' => '',
];

// 讀取設定
$settings = $defaultSettings;
try {
    $keys = array_keys($defaultSettings);
    $in = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$in})");
    $stmt->execute(array_values($keys));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $k = (string)($row['setting_key'] ?? '');
        $v = (string)($row['setting_value'] ?? '');
        if ($k !== '') $settings[$k] = $v;
    }
} catch (Throwable $e) {
}

// POST：存設定
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    try {
        $updates = [];
        foreach ($defaultSettings as $key => $_) {
            $updates[$key] = (string)($_POST[$key] ?? $settings[$key] ?? '');
        }

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
                               VALUES (:k, :v, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE
                                   setting_value = :v2,
                                   updated_at = NOW()");
        foreach ($updates as $k => $v) {
            $stmt->execute([':k' => $k, ':v' => $v, ':v2' => $v]);
        }

        $flashMessage = '系統設定已更新。';
    } catch (Throwable $e) {
        $flashMessage = '儲存失敗，請稍後再試。';
        $flashType = 'error';
    }

    // 重新讀取
    try {
        $keys = array_keys($defaultSettings);
        $in = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$in})");
        $stmt->execute(array_values($keys));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $k = (string)($row['setting_key'] ?? '');
            $v = (string)($row['setting_value'] ?? '');
            if ($k !== '') $settings[$k] = $v;
        }
    } catch (Throwable $e) {
    }
}

staffPageStart($pdo, '系統設定', 'settings');
?>
<section class="staff-panel">
    <?php if ($flashMessage !== ''): ?>
        <div class="staff-notice <?php echo $flashType === 'success' ? '' : 'error'; ?>">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <div class="staff-panel-head">
        <h2>基本設定</h2>
        <p class="staff-panel-subtitle">提供可用且不破壞現有流程的簡單設定介面</p>
    </div>

    <form method="POST" class="staff-form-grid" style="margin-top: 12px;">
        <input type="hidden" name="action" value="save_settings">

        <label class="staff-field">
            <span>免運門檻（free_shipping_threshold）</span>
            <input type="number" min="0" step="1" name="free_shipping_threshold" class="staff-input"
                   value="<?php echo htmlspecialchars((string)($settings['free_shipping_threshold'] ?? '3000')); ?>">
        </label>

        <label class="staff-field">
            <span>預設折扣類型</span>
            <select name="default_discount_type" class="staff-select">
                <option value="percent" <?php echo ($settings['default_discount_type'] ?? '') === 'percent' ? 'selected' : ''; ?>>percent</option>
                <option value="fixed" <?php echo ($settings['default_discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>fixed</option>
            </select>
        </label>

        <label class="staff-field">
            <span>預設折扣數值</span>
            <input type="number" min="0" step="0.01" name="default_discount_value" class="staff-input"
                   value="<?php echo htmlspecialchars((string)($settings['default_discount_value'] ?? '10')); ?>">
        </label>

        <label class="staff-field">
            <span>站台名稱（site_name）</span>
            <input type="text" name="site_name" class="staff-input"
                   value="<?php echo htmlspecialchars((string)($settings['site_name'] ?? 'HelmetVRse')); ?>">
        </label>

        <label class="staff-field staff-field-wide">
            <span>聯絡 Email（site_contact_email）</span>
            <input type="email" name="site_contact_email" class="staff-input"
                   value="<?php echo htmlspecialchars((string)($settings['site_contact_email'] ?? '')); ?>">
        </label>

        <div class="staff-form-actions staff-field-wide" style="grid-column:1 / -1; display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="staff-btn">儲存設定</button>
        </div>
    </form>
</section>

<?php staffPageEnd(); ?>

