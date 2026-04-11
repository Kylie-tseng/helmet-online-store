<?php

require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/product_card_image.php';
require_once __DIR__ . '/../includes/product_query_helpers.php';

staffRequireAuth();

$search = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
$filter = trim($_GET['filter'] ?? '');
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$isLowStockMode = ($filter === 'low_stock');
$role = (string)($_SESSION['role'] ?? 'staff');
$productFormHrefBase = $role === 'admin' ? '../staff/product_form.php' : 'product_form.php';
$products = [];
$categories = [];
$hasCategoryDescriptionColumn = false;

$catalogRedirectQuery = [];
if ($isLowStockMode) {
    $catalogRedirectQuery['filter'] = 'low_stock';
}
if ($categoryFilter > 0) {
    $catalogRedirectQuery['category_id'] = $categoryFilter;
}
if ($search !== '') {
    $catalogRedirectQuery['search'] = $search;
}
$catalogRedirectUrl = 'product_catalog.php' . ($catalogRedirectQuery === [] ? '' : ('?' . http_build_query($catalogRedirectQuery)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);
    $rq = [];
    if (trim((string)($_POST['search'] ?? '')) !== '') {
        $rq['search'] = trim((string)$_POST['search']);
    }
    if ((int)($_POST['category_id'] ?? 0) > 0) {
        $rq['category_id'] = (int)$_POST['category_id'];
    }
    if (trim((string)($_POST['filter'] ?? '')) === 'low_stock') {
        $rq['filter'] = 'low_stock';
    }
    $afterPostUrl = 'product_catalog.php' . ($rq === [] ? '' : ('?' . http_build_query($rq)));
    if ($productId > 0 && $action === 'toggle_status') {
        try {
            $stmt = $pdo->prepare("UPDATE products
                                   SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END
                                   WHERE id = :id");
            $stmt->execute([':id' => $productId]);
            staffSetToastSuccess('商品狀態已更新。');
            header('Location: ' . $afterPostUrl);
            exit;
        } catch (Throwable $e) {
            $_SESSION['staff_page_flash_error'] = '商品狀態更新失敗。';
        }
    } elseif ($productId > 0 && $action === 'delete_product') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM product_images WHERE product_id = :pid')->execute([':pid' => $productId]);
            $pdo->prepare('DELETE FROM product_sizes WHERE product_id = :pid')->execute([':pid' => $productId]);
            $pdo->prepare('DELETE FROM products WHERE id = :pid')->execute([':pid' => $productId]);
            $pdo->commit();
            staffSetToastSuccess('商品已刪除。');
            header('Location: ' . $afterPostUrl);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['staff_page_flash_error'] = '商品刪除失敗，可能因關聯資料存在。';
        }
    }
}

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

try {
    $sql = 'SELECT id, name' . ($hasCategoryDescriptionColumn ? ', description' : '') . ' FROM categories ORDER BY id';
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $categories = [];
}

try {
    $sql = "SELECT p.id, p.name, p.price, p.status, p.style, c.name AS category_name,
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
    if ($categoryFilter > 0) {
        $sql .= ' AND p.category_id = :category_id';
        $params[':category_id'] = $categoryFilter;
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

staffPageStart($pdo, '商品資料管理', $isLowStockMode ? 'low_stock' : 'products');
?>
<section class="staff-panel">
    <div class="staff-panel-head staff-panel-head--split" style="align-items:center;">
        <div>
            <h2>商品清單</h2>
            <p class="staff-section-lede staff-section-lede--tight" style="margin-top:6px;">搜尋、篩選與維護商品資料、上下架、圖片與庫存。</p>
        </div>
        <div style="flex-shrink:0;">
            <a href="products.php" class="staff-btn staff-btn-soft">返回商品入口</a>
        </div>
    </div>

    <form method="GET" action="product_catalog.php" class="staff-toolbar">
        <?php if ($isLowStockMode): ?>
            <input type="hidden" name="filter" value="low_stock">
        <?php endif; ?>
        <select name="category_id" class="staff-select" aria-label="商品分類篩選">
            <option value="0" <?php echo $categoryFilter === 0 ? 'selected' : ''; ?>>全部分類</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)($cat['id'] ?? 0); ?>" <?php echo $categoryFilter === (int)($cat['id'] ?? 0) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars((string)($cat['name'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input
            type="text"
            name="search"
            class="staff-input"
            placeholder="搜尋商品名稱、分類或風格"
            value="<?php echo htmlspecialchars($search); ?>"
        >
        <button type="submit" class="staff-btn">套用篩選</button>
        <?php if (!$isLowStockMode): ?>
            <a href="<?php echo htmlspecialchars($productFormHrefBase); ?>" class="staff-btn staff-btn-soft">新增商品</a>
        <?php endif; ?>
    </form>

    <?php if (empty($products)): ?>
        <div class="staff-empty-hint">
            <?php echo $isLowStockMode ? '查無符合條件的商品。' : '目前沒有商品資料。'; ?>
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
                        <div class="staff-product-meta">
                            分類：<?php echo htmlspecialchars((string)($product['category_name'] ?? '未分類')); ?>
                            <?php
                            $styleLabel = trim((string)($product['style'] ?? ''));
                            if ($styleLabel !== ''):
                            ?>
                                <span class="staff-product-meta-sep">｜</span>風格：<?php echo htmlspecialchars($styleLabel); ?>
                            <?php endif; ?>
                        </div>
                        <div class="staff-product-price"><?php echo htmlspecialchars(staffCurrency((float)($product['price'] ?? 0))); ?></div>
                        <div class="staff-product-info-row">
                            <span class="staff-product-stock">庫存：<?php echo number_format((int)($product['total_stock'] ?? 0)); ?></span>
                            <div class="staff-product-badges">
                                <?php if ($isLowStock): ?>
                                    <span class="staff-badge danger">低庫存</span>
                                <?php endif; ?>
                                <span class="staff-badge <?php echo staffStatusBadgeClass((string)($product['status'] ?? '')); ?>">
                                    <?php echo htmlspecialchars(appOrderStatusLabel((string)($product['status'] ?? ''))); ?>
                                </span>
                            </div>
                        </div>
                        <div class="staff-product-actions">
                            <a href="<?php echo htmlspecialchars($productFormHrefBase . '?id=' . (int)$product['id']); ?>" class="staff-action-btn staff-action-btn-primary">編輯商品</a>
                            <form
                                method="POST"
                                class="staff-inline-form"
                                action="<?php echo htmlspecialchars($catalogRedirectUrl); ?>"
                                data-app-confirm-title="刪除商品"
                                data-app-confirm="確定刪除此商品？這可能會受資料關聯影響。"
                            >
                                <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                                <?php if ($categoryFilter > 0): ?><input type="hidden" name="category_id" value="<?php echo (int)$categoryFilter; ?>"><?php endif; ?>
                                <?php if ($isLowStockMode): ?><input type="hidden" name="filter" value="low_stock"><?php endif; ?>
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="staff-action-btn staff-action-btn-danger">刪除商品</button>
                            </form>
                            <form method="POST" class="staff-inline-form" action="<?php echo htmlspecialchars($catalogRedirectUrl); ?>">
                                <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                                <?php if ($categoryFilter > 0): ?><input type="hidden" name="category_id" value="<?php echo (int)$categoryFilter; ?>"><?php endif; ?>
                                <?php if ($isLowStockMode): ?><input type="hidden" name="filter" value="low_stock"><?php endif; ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                <button type="submit" class="staff-action-btn staff-action-btn-muted"><?php echo $isActive ? '下架' : '上架'; ?></button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php staffPageEnd(); ?>
