<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

$productId = (int)($_GET['id'] ?? 0);
$isEdit = $productId > 0;
$message = '';
$error = '';
$images = [];

$categories = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY id");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categories = [];
}

$form = [
    'name' => '',
    'category_id' => 0,
    'price' => 0,
    'status' => 'active',
    'description' => '',
    'style' => '',
];
$stocks = ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0];

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, category_id, price, status, description, style FROM products WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $isEdit = false;
            $productId = 0;
        } else {
            $form['name'] = (string)$row['name'];
            $form['category_id'] = (int)$row['category_id'];
            $form['price'] = (float)$row['price'];
            $form['status'] = (string)$row['status'];
            $form['description'] = (string)($row['description'] ?? '');
            $form['style'] = (string)($row['style'] ?? '');
        }
    } catch (Throwable $e) {
        $error = '讀取商品資料失敗。';
    }

    try {
        $stmt = $pdo->prepare("SELECT size, stock FROM product_sizes WHERE product_id = :id");
        $stmt->execute([':id' => $productId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sizeRow) {
            $size = (string)$sizeRow['size'];
            if (isset($stocks[$size])) {
                $stocks[$size] = (int)$sizeRow['stock'];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? 'save_basic');
    if ($isEdit && $action === 'upload_image' && isset($_FILES['image_file'])) {
        $file = $_FILES['image_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string)$file['tmp_name'];
            $original = (string)$file['name'];
            $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                $error = '僅支援 jpg / jpeg / png / webp。';
            } else {
                $targetDir = __DIR__ . '/../assets/images/products/';
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }
                $filename = 'p' . $productId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $targetPath = $targetDir . $filename;
                if (@move_uploaded_file($tmp, $targetPath)) {
                    try {
                        $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = :pid");
                        $sortStmt->execute([':pid' => $productId]);
                        $sort = ((int)$sortStmt->fetchColumn()) + 1;
                        $ins = $pdo->prepare("INSERT INTO product_images (product_id, image_url, sort_order, is_primary, created_at)
                                              VALUES (:pid, :img, :sort, 0, NOW())");
                        $ins->execute([':pid' => $productId, ':img' => $filename, ':sort' => $sort]);
                        $message = '圖片上傳成功。';
                    } catch (Throwable $e) {
                        $error = '圖片資料寫入失敗。';
                    }
                } else {
                    $error = '圖片上傳失敗。';
                }
            }
        } else {
            $error = '請選擇要上傳的圖片。';
        }
    } elseif ($isEdit && $action === 'delete_image') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        if ($imageId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE id = :id AND product_id = :pid LIMIT 1");
                $stmt->execute([':id' => $imageId, ':pid' => $productId]);
                $img = $stmt->fetchColumn();
                if ($img) {
                    $pdo->prepare("DELETE FROM product_images WHERE id = :id AND product_id = :pid")
                        ->execute([':id' => $imageId, ':pid' => $productId]);
                    $path = __DIR__ . '/../assets/images/products/' . basename((string)$img);
                    if (is_file($path)) {
                        @unlink($path);
                    }
                    $message = '圖片已刪除。';
                }
            } catch (Throwable $e) {
                $error = '刪除圖片失敗。';
            }
        }
    } elseif ($isEdit && $action === 'set_primary') {
        $imageId = (int)($_POST['image_id'] ?? 0);
        if ($imageId > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = :pid")
                    ->execute([':pid' => $productId]);
                $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = :id AND product_id = :pid")
                    ->execute([':id' => $imageId, ':pid' => $productId]);
                $pdo->commit();
                $message = '已設為主圖。';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = '設定主圖失敗。';
            }
        }
    } else {
    $name = trim($_POST['name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $status = trim($_POST['status'] ?? 'inactive');
    $description = trim($_POST['description'] ?? '');
    $style = trim($_POST['style'] ?? '');
    $stockS = max(0, (int)($_POST['stock_s'] ?? 0));
    $stockM = max(0, (int)($_POST['stock_m'] ?? 0));
    $stockL = max(0, (int)($_POST['stock_l'] ?? 0));
    $stockXL = max(0, (int)($_POST['stock_xl'] ?? 0));

    $form = [
        'name' => $name,
        'category_id' => $categoryId,
        'price' => $price,
        'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'inactive',
        'description' => $description,
        'style' => $style,
    ];
    $stocks = ['S' => $stockS, 'M' => $stockM, 'L' => $stockL, 'XL' => $stockXL];

        if ($name === '' || $categoryId <= 0 || $price <= 0) {
            $error = '請填寫完整商品名稱、分類與價格。';
        } else {
            try {
                $pdo->beginTransaction();

                if ($isEdit) {
                    $stmt = $pdo->prepare("UPDATE products
                                       SET name = :name,
                                           category_id = :category_id,
                                           price = :price,
                                           status = :status,
                                           description = :description,
                                           style = :style,
                                           updated_at = NOW()
                                       WHERE id = :id");
                    $stmt->execute([
                    ':name' => $name,
                    ':category_id' => $categoryId,
                    ':price' => $price,
                    ':status' => $form['status'],
                    ':description' => $description !== '' ? $description : null,
                    ':style' => $style !== '' ? $style : null,
                    ':id' => $productId,
                    ]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO products (category_id, name, style, description, price, status, is_addon, is_featured, created_at, updated_at)
                                       VALUES (:category_id, :name, :style, :description, :price, :status, 0, 0, NOW(), NOW())");
                    $stmt->execute([
                    ':category_id' => $categoryId,
                    ':name' => $name,
                    ':style' => $style !== '' ? $style : null,
                    ':description' => $description !== '' ? $description : null,
                    ':price' => $price,
                    ':status' => $form['status'],
                    ]);
                    $productId = (int)$pdo->lastInsertId();
                    $isEdit = true;
                }

                $sizeData = ['S' => $stockS, 'M' => $stockM, 'L' => $stockL, 'XL' => $stockXL];
                foreach ($sizeData as $size => $stock) {
                    $check = $pdo->prepare("SELECT id FROM product_sizes WHERE product_id = :pid AND size = :size LIMIT 1");
                    $check->execute([':pid' => $productId, ':size' => $size]);
                    $existing = $check->fetchColumn();
                    if ($existing) {
                        $up = $pdo->prepare("UPDATE product_sizes SET stock = :stock, updated_at = NOW() WHERE id = :id");
                        $up->execute([':stock' => $stock, ':id' => (int)$existing]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO product_sizes (product_id, size, stock, created_at, updated_at)
                                          VALUES (:pid, :size, :stock, NOW(), NOW())");
                        $ins->execute([':pid' => $productId, ':size' => $size, ':stock' => $stock]);
                    }
                }

                $pdo->commit();
                $message = '商品資料已儲存。';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = '儲存失敗，請稍後再試。';
            }
        }
    }
}

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT id, image_url, sort_order, is_primary
                               FROM product_images
                               WHERE product_id = :pid
                               ORDER BY is_primary DESC, sort_order ASC, id ASC");
        $stmt->execute([':pid' => $productId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $images = [];
    }
}

staffPageStart($pdo, $isEdit ? '編輯商品' : '新增商品', 'products');
?>
<section class="staff-panel">
    <?php if ($message !== ''): ?>
        <div class="staff-notice"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="staff-empty-hint"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" class="staff-form-grid">
        <input type="hidden" name="action" value="save_basic">
        <label class="staff-field">
            <span>商品名稱</span>
            <input type="text" name="name" class="staff-input" value="<?php echo htmlspecialchars($form['name']); ?>" required>
        </label>
        <label class="staff-field">
            <span>分類</span>
            <select name="category_id" class="staff-select" required>
                <option value="">請選擇分類</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo ((int)$form['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="staff-field">
            <span>價格</span>
            <input type="number" min="1" step="1" name="price" class="staff-input" value="<?php echo htmlspecialchars((string)(int)$form['price']); ?>" required>
        </label>
        <label class="staff-field">
            <span>狀態</span>
            <select name="status" class="staff-select">
                <option value="active" <?php echo $form['status'] === 'active' ? 'selected' : ''; ?>>上架中</option>
                <option value="inactive" <?php echo $form['status'] === 'inactive' ? 'selected' : ''; ?>>未上架</option>
            </select>
        </label>
        <label class="staff-field">
            <span>風格</span>
            <select name="style" class="staff-select">
                <option value="">不指定</option>
                <?php foreach (['復古', '通勤', '競速', '女性'] as $style): ?>
                    <option value="<?php echo htmlspecialchars($style); ?>" <?php echo $form['style'] === $style ? 'selected' : ''; ?>><?php echo htmlspecialchars($style); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="staff-field staff-field-wide">
            <span>商品介紹</span>
            <textarea name="description" class="staff-textarea" rows="4"><?php echo htmlspecialchars($form['description']); ?></textarea>
        </label>

        <div class="staff-field stock-grid staff-field-wide">
            <span>尺寸庫存</span>
            <div class="staff-size-row">
                <label>S <input type="number" min="0" name="stock_s" class="staff-input" value="<?php echo (int)$stocks['S']; ?>"></label>
                <label>M <input type="number" min="0" name="stock_m" class="staff-input" value="<?php echo (int)$stocks['M']; ?>"></label>
                <label>L <input type="number" min="0" name="stock_l" class="staff-input" value="<?php echo (int)$stocks['L']; ?>"></label>
                <label>XL <input type="number" min="0" name="stock_xl" class="staff-input" value="<?php echo (int)$stocks['XL']; ?>"></label>
            </div>
        </div>

        <div class="staff-form-actions staff-field-wide">
            <button type="submit" class="staff-btn">儲存商品</button>
            <a href="products.php" class="staff-btn staff-btn-soft">返回商品管理</a>
        </div>
    </form>
</section>

<?php if ($isEdit): ?>
<section class="staff-panel">
    <div class="staff-panel-head"><h2>商品圖片管理</h2></div>
    <form method="POST" enctype="multipart/form-data" class="staff-toolbar">
        <input type="hidden" name="action" value="upload_image">
        <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp" class="staff-input staff-file-input">
        <button type="submit" class="staff-btn">上傳圖片</button>
    </form>

    <?php if (empty($images)): ?>
        <div class="staff-empty-hint">目前尚無圖片，請先上傳。</div>
    <?php else: ?>
        <div class="staff-image-grid">
            <?php foreach ($images as $img): ?>
                <article class="staff-image-card">
                    <img src="../assets/images/products/<?php echo htmlspecialchars((string)$img['image_url']); ?>" alt="" class="staff-image-thumb">
                    <div class="staff-image-actions">
                        <?php if ((int)$img['is_primary'] === 1): ?>
                            <span class="staff-badge done">主圖</span>
                        <?php else: ?>
                            <form method="POST" class="staff-inline-form">
                                <input type="hidden" name="action" value="set_primary">
                                <input type="hidden" name="image_id" value="<?php echo (int)$img['id']; ?>">
                                <button type="submit" class="staff-action-btn staff-action-btn-ghost">設為主圖</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" class="staff-inline-form" onsubmit="return confirm('確定刪除此圖片？');">
                            <input type="hidden" name="action" value="delete_image">
                            <input type="hidden" name="image_id" value="<?php echo (int)$img['id']; ?>">
                            <button type="submit" class="staff-action-btn staff-action-btn-muted">刪除</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php staffPageEnd(); ?>

