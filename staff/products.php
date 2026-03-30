<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/product_card_image.php';
require_once __DIR__ . '/../includes/product_query_helpers.php';

staffRequireAuth();

$search = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
$filter = trim($_GET['filter'] ?? '');
$isLowStockMode = ($filter === 'low_stock');
$role = (string)($_SESSION['role'] ?? 'staff');
$productFormHrefBase = $role === 'admin' ? '../staff/product_form.php' : 'product_form.php';
$flashMessage = '';
$products = [];
$categories = [];
$hasCategoryDescriptionColumn = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId > 0 && $action === 'toggle_status') {
        try {
            $stmt = $pdo->prepare("UPDATE products
                                   SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END,
                                       updated_at = NOW()
                                   WHERE id = :id");
            $stmt->execute([':id' => $productId]);
            $flashMessage = '商品狀態已更新。';
        } catch (Throwable $e) {
            $flashMessage = '商品狀態更新失敗。';
        }
    } elseif ($productId > 0 && $action === 'delete_product') {
        try {
            $pdo->beginTransaction();

            // 先刪圖片/尺寸庫存，最後刪商品
            $pdo->prepare("DELETE FROM product_images WHERE product_id = :pid")->execute([':pid' => $productId]);
            $pdo->prepare("DELETE FROM product_sizes WHERE product_id = :pid")->execute([':pid' => $productId]);
            $pdo->prepare("DELETE FROM products WHERE id = :pid")->execute([':pid' => $productId]);

            $pdo->commit();
            $flashMessage = '商品已刪除。';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $flashMessage = '商品刪除失敗，可能因關聯資料存在。';
        }
    }
}

// 分類管理（用於建立/修改/刪除）
try {
    $pdo->query("SELECT 1 FROM categories LIMIT 1");
    $cols = $pdo->query("SHOW COLUMNS FROM categories");
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
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description, created_at, updated_at)
                                           VALUES (:name, :description, NOW(), NOW())");
                    $stmt->execute([':name' => $name, ':description' => $description]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name)
                                           VALUES (:name)");
                    $stmt->execute([':name' => $name]);
                }
                $flashMessage = '分類已新增。';
            } catch (Throwable $e) {
                $flashMessage = '分類新增失敗。';
            }
        }
    } elseif ($action === 'category_update') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim((string)($_POST['category_name'] ?? ''));
        $description = $hasCategoryDescriptionColumn ? trim((string)($_POST['category_description'] ?? '')) : '';
        if ($categoryId > 0 && $name !== '') {
            try {
                if ($hasCategoryDescriptionColumn) {
                    $stmt = $pdo->prepare("UPDATE categories
                                           SET name = :name,
                                               description = :description,
                                               updated_at = NOW()
                                           WHERE id = :id");
                    $stmt->execute([':name' => $name, ':description' => $description, ':id' => $categoryId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE categories
                                           SET name = :name,
                                               updated_at = NOW()
                                           WHERE id = :id");
                    $stmt->execute([':name' => $name, ':id' => $categoryId]);
                }
                $flashMessage = '分類已更新。';
            } catch (Throwable $e) {
                $flashMessage = '分類更新失敗。';
            }
        }
    } elseif ($action === 'category_delete') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId > 0) {
            try {
                $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $categoryId]);
                $flashMessage = '分類已刪除。';
            } catch (Throwable $e) {
                $flashMessage = '分類刪除失敗，可能因關聯資料存在。';
            }
        }
    }
}

try {
    $sql = "SELECT id, name" . ($hasCategoryDescriptionColumn ? ", description" : "") . " FROM categories ORDER BY id";
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categories = [];
}

try {
    $sql = "SELECT p.id, p.name, p.price, p.status, c.name AS category_name,
                   " . primaryImageSubquery('p', 'pi') . " AS primary_image,
                   (
                       SELECT COALESCE(SUM(ps.stock), 0)
                       FROM product_sizes ps
                       WHERE ps.product_id = p.id
                   ) AS total_stock
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (
                    p.name LIKE :search
                    OR c.name LIKE :search
                    OR COALESCE(p.style, '') LIKE :search
                    OR COALESCE(p.description, '') LIKE :search
                 )";
        $params[':search'] = '%' . $search . '%';
    }
    if ($isLowStockMode) {
        $sql .= " AND (
                    SELECT COALESCE(SUM(ps.stock), 0)
                    FROM product_sizes ps
                    WHERE ps.product_id = p.id
                 ) <= 5";
    }
    $sql .= " ORDER BY
                CASE
                    WHEN c.name = '全罩式安全帽' THEN 1
                    WHEN c.name = '半罩式安全帽' THEN 2
                    WHEN c.name = '3/4罩安全帽' THEN 3
                    WHEN c.name = '周邊與配件' THEN 4
                    ELSE 99
                END ASC,
                p.id ASC
              LIMIT 120";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $products = [];
}

staffPageStart($pdo, '商品管理', 'products');
?>
<section class="staff-panel">
    <?php if ($flashMessage !== ''): ?>
        <div class="staff-notice"><?php echo htmlspecialchars($flashMessage); ?></div>
    <?php endif; ?>
    <form method="GET" action="products.php" class="staff-toolbar">
        <input
            type="text"
            name="search"
            class="staff-input"
            placeholder="搜尋商品名稱 / 分類"
            value="<?php echo htmlspecialchars($search); ?>"
        >
        <button type="submit" class="staff-btn">搜尋</button>
        <a href="<?php echo htmlspecialchars($productFormHrefBase); ?>" class="staff-btn staff-btn-soft">新增商品</a>
    </form>

    <section class="staff-panel staff-category-manager">
        <div class="staff-panel-head">
            <h2>分類管理</h2>
        </div>

        <form method="POST" class="staff-form-grid staff-category-add-form">
            <input type="hidden" name="action" value="category_add">
            <label class="staff-field">
                <span>新增分類名稱</span>
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
                                <td>
                                    <?php echo htmlspecialchars((string)($cat['description'] ?? '')); ?>
                                </td>
                                <td>
                                    <form method="POST" class="staff-inline-form staff-category-inline-form">
                                        <input type="hidden" name="action" value="category_update">
                                        <input type="hidden" name="category_id" value="<?php echo (int)($cat['id'] ?? 0); ?>">
                                        <input type="text" name="category_name" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($cat['name'] ?? '')); ?>">
                                        <?php if ($hasCategoryDescriptionColumn): ?>
                                            <input type="text" name="category_description" class="staff-input staff-input-mini" value="<?php echo htmlspecialchars((string)($cat['description'] ?? '')); ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="staff-action-btn staff-action-btn-primary">更新</button>
                                    </form>
                                    <form method="POST" class="staff-inline-form" onsubmit="return confirm('確定刪除此分類？');">
                                        <input type="hidden" name="action" value="category_delete">
                                        <input type="hidden" name="category_id" value="<?php echo (int)($cat['id'] ?? 0); ?>">
                                        <button type="submit" class="staff-action-btn staff-action-btn-muted">刪除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($isLowStockMode): ?>
        <div class="staff-notice staff-inline-notice">
            目前僅顯示低庫存商品（庫存 <= 5）
            <a href="products.php" class="staff-action-btn staff-action-btn-ghost">查看全部商品</a>
        </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="staff-empty-hint">
            <?php echo $isLowStockMode ? '目前沒有低庫存商品。' : '目前沒有商品資料。'; ?>
        </div>
    <?php else: ?>
        <div class="staff-product-grid">
            <?php foreach ($products as $product): ?>
                <?php
                $img = '../' . ltrim(resolve_product_card_image_src((string)($product['primary_image'] ?? '')), '/');
                $isActive = ((string)($product['status'] ?? '') === 'active');
                $isLowStock = ((int)($product['total_stock'] ?? 0) <= 5);
                ?>
                <article class="staff-product-card">
                    <div class="staff-product-media">
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars((string)$product['name']); ?>" class="staff-product-cover" onerror="this.style.display='none'">
                    </div>
                    <div class="staff-product-body">
                        <h3 class="staff-product-name"><?php echo htmlspecialchars((string)$product['name']); ?></h3>
                        <div class="staff-product-meta"><?php echo htmlspecialchars((string)($product['category_name'] ?? '未分類')); ?></div>
                        <div class="staff-product-price"><?php echo htmlspecialchars(staffCurrency((float)($product['price'] ?? 0))); ?></div>
                        <div class="staff-product-info-row">
                            <span class="staff-product-stock">庫存：<?php echo number_format((int)($product['total_stock'] ?? 0)); ?></span>
                            <div class="staff-product-badges">
                                <?php if ($isLowStock): ?>
                                    <span class="staff-badge danger">低庫存</span>
                                <?php endif; ?>
                                <span class="staff-badge <?php echo staffStatusBadgeClass((string)($product['status'] ?? '')); ?>">
                                    <?php echo htmlspecialchars(staffStatusLabel((string)($product['status'] ?? 'unknown'))); ?>
                                </span>
                            </div>
                        </div>
                        <div class="staff-product-actions">
                            <a href="<?php echo htmlspecialchars($productFormHrefBase . '?id=' . (int)$product['id']); ?>" class="staff-action-btn staff-action-btn-primary">編輯商品</a>
                            <form method="POST" class="staff-inline-form">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="staff-action-btn staff-action-btn-muted"><?php echo $isActive ? '下架' : '上架'; ?></button>
                            </form>
                            <form method="POST" class="staff-inline-form" onsubmit="return confirm('確定刪除此商品？這可能會受資料關聯影響。');">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="staff-action-btn staff-action-btn-danger">刪除</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php staffPageEnd(); ?>

