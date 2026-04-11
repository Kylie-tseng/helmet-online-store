<?php

require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/staff_style_labels.php';

staffRequireAuth();

$styleTableOk = staff_style_labels_ensure_table($pdo);

$categories = [];
$hasCategoryDescriptionColumn = false;

try {
    $pdo->query('SELECT 1 FROM categories LIMIT 1');
    $cols = $pdo->query('SHOW COLUMNS FROM categories');
    foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if ((string)($c['Field'] ?? '') === 'description') {
            $hasCategoryDescriptionColumn = true;
        }
    }
} catch (Throwable $e) {
    $hasCategoryDescriptionColumn = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    if ($action === 'category_add') {
        $name = trim((string)($_POST['category_name'] ?? ''));
        $description = $hasCategoryDescriptionColumn ? trim((string)($_POST['category_description'] ?? '')) : '';
        if ($name !== '') {
            try {
                if ($hasCategoryDescriptionColumn) {
                    $stmt = $pdo->prepare('INSERT INTO categories (name, description, created_at, updated_at)
                                           VALUES (:name, :description, NOW(), NOW())');
                    $stmt->execute([':name' => $name, ':description' => $description]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
                    $stmt->execute([':name' => $name]);
                }
                staffSetToastSuccess('分類已新增。');
                header('Location: category_management.php');
                exit;
            } catch (Throwable $e) {
                $_SESSION['staff_page_flash_error'] = '分類新增失敗。';
            }
        }
    } elseif ($action === 'category_update') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $description = $hasCategoryDescriptionColumn ? trim((string)($_POST['category_description'] ?? '')) : '';
        if ($categoryId > 0 && $name !== '') {
            try {
                if ($hasCategoryDescriptionColumn) {
                    $stmt = $pdo->prepare('UPDATE categories
                                           SET name = :name, description = :description, updated_at = NOW()
                                           WHERE id = :id');
                    $stmt->execute([':name' => $name, ':description' => $description, ':id' => $categoryId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE categories SET name = :name, updated_at = NOW() WHERE id = :id');
                    $stmt->execute([':name' => $name, ':id' => $categoryId]);
                }
                staffSetToastSuccess('分類已更新。');
                header('Location: category_management.php');
                exit;
            } catch (Throwable $e) {
                $_SESSION['staff_page_flash_error'] = '分類更新失敗。';
            }
        }
    } elseif ($action === 'category_delete') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            try {
                $pdo->prepare('DELETE FROM categories WHERE id = :id')->execute([':id' => $categoryId]);
                staffSetToastSuccess('分類已刪除。');
                header('Location: category_management.php');
                exit;
            } catch (Throwable $e) {
                $_SESSION['staff_page_flash_error'] = '分類刪除失敗，可能因關聯資料存在。';
            }
        }
    } elseif ($styleTableOk && $action === 'style_add') {
        $name = trim((string)($_POST['style_name'] ?? ''));
        if ($name !== '') {
            try {
                $pdo->prepare('INSERT INTO staff_style_labels (`name`) VALUES (?)')->execute([$name]);
                staffSetToastSuccess('風格已新增。');
                header('Location: category_management.php');
                exit;
            } catch (Throwable $e) {
                $_SESSION['staff_page_flash_error'] = '風格新增失敗（名稱可能重複）。';
            }
        }
    } elseif ($styleTableOk && $action === 'style_rename') {
        $sid = (int)($_POST['style_id'] ?? 0);
        $newName = trim((string)($_POST['style_name_new'] ?? ''));
        if ($sid > 0 && $newName !== '') {
            try {
                $st = $pdo->prepare('SELECT name FROM staff_style_labels WHERE id = :id LIMIT 1');
                $st->execute([':id' => $sid]);
                $oldName = trim((string)$st->fetchColumn());
                if ($oldName !== '') {
                    $pdo->beginTransaction();
                    $pdo->prepare('UPDATE staff_style_labels SET name = :n WHERE id = :id')->execute([':n' => $newName, ':id' => $sid]);
                    $pdo->prepare('UPDATE products SET style = :new WHERE TRIM(style) = :old')->execute([':new' => $newName, ':old' => $oldName]);
                    $pdo->commit();
                    staffSetToastSuccess('風格名稱已更新。');
                    header('Location: category_management.php');
                    exit;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['staff_page_flash_error'] = '風格名稱更新失敗（名稱可能重複）。';
            }
        }
    } elseif ($styleTableOk && $action === 'style_delete') {
        $sid = (int)($_POST['style_id'] ?? 0);
        if ($sid > 0) {
            try {
                $st = $pdo->prepare('SELECT name FROM staff_style_labels WHERE id = :id LIMIT 1');
                $st->execute([':id' => $sid]);
                $nm = trim((string)$st->fetchColumn());
                if ($nm !== '') {
                    $pdo->prepare('UPDATE products SET style = NULL WHERE TRIM(style) = :s')->execute([':s' => $nm]);
                    $pdo->prepare('DELETE FROM staff_style_labels WHERE id = :id')->execute([':id' => $sid]);
                    staffSetToastSuccess('風格已刪除，相關商品風格欄位已清空。');
                    header('Location: category_management.php');
                    exit;
                }
            } catch (Throwable $e) {
                $_SESSION['staff_page_flash_error'] = '風格刪除失敗。';
            }
        }
    }
}

try {
    $sql = 'SELECT id, name' . ($hasCategoryDescriptionColumn ? ', description' : '') . ' FROM categories ORDER BY id';
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categories = [];
}

$styleRows = staff_style_labels_rows($pdo);
$styleUsage = staff_style_labels_usage_counts($pdo);

staffPageStart($pdo, '分類與風格管理', 'products');
?>
<section class="staff-panel">
    <div class="staff-panel-head staff-panel-head--split">
        <div>
            <h2>商品分類管理</h2>
            <p class="staff-section-lede staff-section-lede--tight" style="margin-top:6px;">新增、修改或刪除商品分類，供商品編輯與篩選使用。</p>
        </div>
        <a href="products.php" class="staff-btn staff-btn-soft">返回商品入口</a>
    </div>

    <form method="POST" class="staff-form-grid staff-category-add-form" action="category_management.php">
        <input type="hidden" name="action" value="category_add">
        <label class="staff-field">
            <span>新分類名稱</span>
            <input type="text" name="category_name" class="staff-input" required>
        </label>
        <?php if ($hasCategoryDescriptionColumn): ?>
            <label class="staff-field staff-field-wide">
                <span>分類描述</span>
                <textarea name="category_description" class="staff-textarea" rows="3"></textarea>
            </label>
        <?php endif; ?>
        <div class="staff-form-actions staff-field-wide staff-category-add-actions">
            <button type="submit" class="staff-btn">新增分類</button>
        </div>
    </form>

    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr>
                    <th>分類</th>
                    <th>描述</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="3">目前沒有分類資料。</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($cat['name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($cat['description'] ?? '')); ?></td>
                            <td>
                                <form method="POST" class="staff-inline-form staff-category-inline-form" action="category_management.php">
                                    <input type="hidden" name="action" value="category_update">
                                    <input type="hidden" name="category_id" value="<?php echo (int)($cat['id'] ?? 0); ?>">
                                    <input type="text" name="category_name" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($cat['name'] ?? '')); ?>">
                                    <?php if ($hasCategoryDescriptionColumn): ?>
                                        <input type="text" name="category_description" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($cat['description'] ?? '')); ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="staff-action-btn staff-action-btn-primary">更新分類</button>
                                </form>
                                <form method="POST" class="staff-inline-form" action="category_management.php" data-app-confirm-title="刪除分類" data-app-confirm="確定刪除此分類？">
                                    <input type="hidden" name="action" value="category_delete">
                                    <input type="hidden" name="category_id" value="<?php echo (int)($cat['id'] ?? 0); ?>">
                                    <button type="submit" class="staff-action-btn staff-action-btn-muted">刪除分類</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="staff-panel">
    <div class="staff-panel-head">
        <h2>商品風格管理</h2>
        <p class="staff-section-lede staff-section-lede--tight" style="margin-top:6px;">維護商品可選風格標籤；變更名稱時會一併更新既有商品的風格欄位。</p>
    </div>

    <?php if (!$styleTableOk): ?>
        <p class="staff-empty-hint">無法建立風格選項資料表（權限或資料庫限制）。風格仍可於各商品的編輯頁手動輸入既有字串。</p>
    <?php else: ?>
        <form method="POST" class="staff-form-grid staff-category-add-form" action="category_management.php">
            <input type="hidden" name="action" value="style_add">
            <label class="staff-field staff-field-wide">
                <span>新增風格名稱</span>
                <input type="text" name="style_name" class="staff-input" maxlength="64" placeholder="例如：競速、復古" required>
            </label>
            <div class="staff-form-actions staff-field-wide staff-category-add-actions">
                <button type="submit" class="staff-btn">新增風格</button>
            </div>
        </form>

        <div class="staff-table-wrap">
            <table class="staff-table">
                <thead>
                    <tr>
                        <th>風格名稱</th>
                        <th>使用中商品數</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($styleRows)): ?>
                        <tr><td colspan="3">尚無風格資料。</td></tr>
                    <?php else: ?>
                        <?php foreach ($styleRows as $sr): ?>
                            <?php
                            $sid = (int)($sr['id'] ?? 0);
                            $sn = trim((string)($sr['name'] ?? ''));
                            $uc = (int)($styleUsage[$sn] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sn); ?></td>
                                <td><?php echo number_format($uc); ?></td>
                                <td>
                                    <?php if ($sid > 0): ?>
                                        <form method="POST" class="staff-inline-form staff-category-inline-form" action="category_management.php">
                                            <input type="hidden" name="action" value="style_rename">
                                            <input type="hidden" name="style_id" value="<?php echo $sid; ?>">
                                            <input type="text" name="style_name_new" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars($sn); ?>" maxlength="64" required>
                                            <button type="submit" class="staff-action-btn staff-action-btn-primary">更新名稱</button>
                                        </form>
                                        <form method="POST" class="staff-inline-form" action="category_management.php" data-app-confirm-title="刪除風格" data-app-confirm="確定刪除此風格？將清空所有使用此風格之商品的風格欄位。">
                                            <input type="hidden" name="action" value="style_delete">
                                            <input type="hidden" name="style_id" value="<?php echo $sid; ?>">
                                            <button type="submit" class="staff-action-btn staff-action-btn-muted">刪除風格</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="staff-empty-hint" style="margin:0;">（預設）</span>
                                    <?php endif; ?>
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
