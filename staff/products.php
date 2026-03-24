<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/product_card_image.php';
require_once __DIR__ . '/../includes/product_query_helpers.php';

staffRequireAuth();

$search = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
$filter = trim($_GET['filter'] ?? '');
$isLowStockMode = ($filter === 'low_stock');
$flashMessage = '';
$products = [];

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
    }
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
        <a href="product_form.php" class="staff-btn staff-btn-soft">新增商品</a>
    </form>

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
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars((string)$product['name']); ?>" class="staff-product-cover">
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
                            <a href="product_form.php?id=<?php echo (int)$product['id']; ?>" class="staff-action-btn staff-action-btn-primary">編輯商品</a>
                            <form method="POST" class="staff-inline-form">
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

