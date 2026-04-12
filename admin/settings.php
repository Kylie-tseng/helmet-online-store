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

/** 此頁可編輯的設定鍵（其餘若 DB 已有列則保留，不因表單缺欄位被覆寫） */
$formKeys = ['site_name', 'free_shipping_threshold'];

$defaults = [
    'site_name' => 'HelmetVRse',
    'free_shipping_threshold' => '3000',
];

$settings = $defaults;
try {
    $in = implode(',', array_fill(0, count($formKeys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$in})");
    $stmt->execute(array_values($formKeys));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $k = (string)($row['setting_key'] ?? '');
        $v = (string)($row['setting_value'] ?? '');
        if ($k !== '') {
            $settings[$k] = $v;
        }
    }
} catch (Throwable $e) {
}

// POST：只更新站台名稱、免運門檻
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    try {
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        if ($siteName === '') {
            $siteName = $defaults['site_name'];
        }

        $rawTh = $_POST['free_shipping_threshold'] ?? $settings['free_shipping_threshold'] ?? $defaults['free_shipping_threshold'];
        $threshold = (int)(is_numeric($rawTh) ? $rawTh : (int)$defaults['free_shipping_threshold']);
        if ($threshold < 0) {
            $threshold = 0;
        }

        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
                               VALUES (:k, :v, NOW(), NOW())
                               ON DUPLICATE KEY UPDATE
                                   setting_value = :v2,
                                   updated_at = NOW()");
        $stmt->execute([':k' => 'site_name', ':v' => $siteName, ':v2' => $siteName]);
        $stmt->execute([':k' => 'free_shipping_threshold', ':v' => (string)$threshold, ':v2' => (string)$threshold]);

        $fixedEmail = getSiteContactEmail();
        $stmt->execute([':k' => 'site_contact_email', ':v' => $fixedEmail, ':v2' => $fixedEmail]);

        $settings['site_name'] = $siteName;
        $settings['free_shipping_threshold'] = (string)$threshold;

        $flashMessage = '其他設定已儲存。';
    } catch (Throwable $e) {
        $flashMessage = '儲存失敗，請稍後再試。';
        $flashType = 'error';
    }
}

staffPageStart($pdo, '其他設定', 'settings');
?>
<section class="staff-panel">
    <?php if ($flashMessage !== ''): ?>
        <div class="staff-notice <?php echo $flashType === 'success' ? '' : 'error'; ?>">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    <?php endif; ?>

    <div class="staff-panel-head">
        <h2>其他設定</h2>
        <p class="staff-panel-subtitle">提供不影響現有流程的基本設定</p>
    </div>

    <form method="POST" class="staff-form-grid" style="margin-top: 12px;">
        <input type="hidden" name="action" value="save_settings">

        <label class="staff-field">
            <span>站台名稱</span>
            <input type="text" name="site_name" class="staff-input" maxlength="120"
                   value="<?php echo htmlspecialchars((string)($settings['site_name'] ?? $defaults['site_name'])); ?>">
        </label>

        <label class="staff-field">
            <span>免運門檻（元）</span>
            <input type="number" min="0" step="1" name="free_shipping_threshold" class="staff-input"
                   value="<?php echo htmlspecialchars((string)($settings['free_shipping_threshold'] ?? $defaults['free_shipping_threshold'])); ?>">
        </label>

        <div class="staff-form-actions staff-field-wide" style="grid-column:1 / -1; display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap;">
            <button type="submit" class="staff-btn">儲存設定</button>
        </div>
    </form>
</section>

<?php staffPageEnd(); ?>
