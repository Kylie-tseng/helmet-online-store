<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('favorites.php') . '&notice=favorite');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    if ($parts_category) {
        $parts_category_id = $parts_category['id'];
    }
} catch (PDOException $e) {
    // ignore
}

$favorites = [];
try {
    $sql = "SELECT p.id, p.name, p.price,
                   (
                       SELECT pi.image_url
                       FROM product_images pi
                       WHERE pi.product_id = p.id
                       ORDER BY pi.sort_order ASC, pi.id ASC
                       LIMIT 1
                   ) AS primary_image,
                   c.name AS category_name
            FROM favorites f
            INNER JOIN products p ON f.product_id = p.id
            INNER JOIN categories c ON p.category_id = c.id
            WHERE f.user_id = :user_id AND p.status = 'active'
            ORDER BY f.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $favorites = $stmt->fetchAll();
} catch (PDOException $e) {
    $favorites = [];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>收藏商品 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h1 class="section-title">收藏商品</h1>
                <p class="section-subtitle">你已收藏的商品都在這裡</p>
            </div>

            <?php if (empty($favorites)): ?>
                <div class="empty-message">目前尚無收藏商品，快去逛逛商品吧。</div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($favorites as $item): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php
                                $fav_img = resolve_product_card_image_src($item['primary_image'] ?? null);
                                ?>
                                <img src="<?php echo htmlspecialchars($fav_img, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            <div class="product-info">
                                <p class="product-category"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                <h3 class="product-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <div class="product-price-row">
                                    <p class="product-price">NT$ <?php echo number_format($item['price'], 0); ?></p>
                                    <form action="api/toggle_favorite.php" method="POST" class="product-favorite-inline-form">
                                        <input type="hidden" name="product_id" value="<?php echo (int)$item['id']; ?>">
                                        <input type="hidden" name="redirect" value="favorites.php">
                                        <button type="submit" class="favorite-btn favorite-icon-btn active" aria-label="取消收藏" title="取消收藏">
                                            <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true">
                                                <path class="heart-outline" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                                <path class="heart-fill" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                                <div class="favorite-actions">
                                    <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>" class="product-btn">查看詳情</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</body>
</html>
