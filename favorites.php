<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/product_query_helpers.php';

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
                   " . primaryImageSubquery('p', 'pi') . " AS primary_image,
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

$favorite_sizes_map = [];
if (!empty($favorites)) {
    $favorite_product_ids = array_values(array_unique(array_map(static function ($row) {
        return (int)($row['id'] ?? 0);
    }, $favorites)));
    $favorite_product_ids = array_values(array_filter($favorite_product_ids, static function ($id) {
        return $id > 0;
    }));

    if (!empty($favorite_product_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($favorite_product_ids), '?'));
            $sql = "SELECT product_id, size, stock
                    FROM product_sizes
                    WHERE product_id IN ($placeholders)
                    ORDER BY product_id ASC, FIELD(size, 'S', 'M', 'L', 'XL'), size ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($favorite_product_ids);
            $size_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($size_rows as $size_row) {
                $pid = (int)($size_row['product_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                if (!isset($favorite_sizes_map[$pid])) {
                    $favorite_sizes_map[$pid] = [];
                }
                $favorite_sizes_map[$pid][] = [
                    'size' => (string)($size_row['size'] ?? ''),
                    'stock' => (int)($size_row['stock'] ?? 0),
                ];
            }
        } catch (PDOException $e) {
            $favorite_sizes_map = [];
        }
    }
}

if (!isset($_SESSION['compare_list']) || !is_array($_SESSION['compare_list'])) {
    $_SESSION['compare_list'] = [];
}
$compare_list = array_values(array_unique(array_map('intval', $_SESSION['compare_list'])));
$compare_count = count($compare_list);
$compare_flash = isset($_SESSION['compare_flash']) ? trim((string)$_SESSION['compare_flash']) : '';
unset($_SESSION['compare_flash']);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>收藏商品 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/style.css')); ?>">
</head>
<body class="favorites-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <main class="favorites-main">
        <section class="favorites-shell">
            <header class="favorites-header">
                <div class="favorites-header-top">
                    <h1 class="favorites-title">我的收藏</h1>
                    <div class="favorites-actions">
                        <div class="favorites-pill favorites-pill-static">收藏 <?php echo (int)count($favorites); ?></div>
                        <?php if ((int)$compare_count > 0): ?>
                            <a href="compare.php" class="favorites-pill favorites-pill-link">前往比較</a>
                        <?php else: ?>
                            <span class="favorites-pill favorites-pill-link is-disabled">前往比較</span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="favorites-subtitle">收藏喜歡的商品，之後可以快速比較與加入購物車</p>
            </header>

            <?php if (empty($favorites)): ?>
                <section class="favorites-empty-state" aria-label="收藏清單為空">
                    <div class="favorites-empty-icon-wrap" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="favorites-empty-icon">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </div>
                    <h2 class="favorites-empty-title">目前還沒有收藏商品</h2>
                    <p class="favorites-empty-desc">先去逛逛全罩式、半罩式或配件，找到喜歡的再收藏吧。</p>
                    <div class="favorites-empty-actions">
                        <a href="products.php" class="favorites-cta-primary">開始逛逛</a>
                        <a href="products.php?sort=popular" class="favorites-cta-secondary">熱門商品</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="favorites-grid" aria-label="收藏商品清單">
                    <?php foreach ($favorites as $item): ?>
                        <?php $is_in_compare = in_array((int)$item['id'], $compare_list, true); ?>
                        <article class="favorite-card">
                            <?php if ($is_in_compare): ?>
                                <span class="compare-badge">比較中</span>
                            <?php endif; ?>
                            <a class="favorite-card-image-link" href="product_detail.php?id=<?php echo (int)$item['id']; ?>">
                                <?php $fav_img = resolve_product_card_image_src($item['primary_image'] ?? null); ?>
                                <img class="favorite-card-image" src="<?php echo htmlspecialchars($fav_img, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </a>

                            <div class="favorite-card-body">
                                <p class="favorite-card-meta"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                <h3 class="favorite-card-name">
                                    <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a>
                                </h3>

                                <div class="favorite-card-price-row">
                                    <p class="favorite-card-price">NT$ <?php echo number_format($item['price'], 0); ?></p>
                                    <form action="api/toggle_favorite.php" method="POST" class="favorite-card-heart-form">
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

                                <div class="favorite-card-actions">
                                    <?php
                                    $size_options = $favorite_sizes_map[(int)$item['id']] ?? [];
                                    $requires_size = !empty($size_options);
                                    ?>
                                    <div
                                        class="favorite-size-row"
                                        data-selected-size="<?php echo $requires_size ? '' : '__NONE__'; ?>"
                                        data-requires-size="<?php echo $requires_size ? '1' : '0'; ?>"
                                    >
                                        <?php if ($requires_size): ?>
                                            <?php foreach ($size_options as $opt): ?>
                                                <?php
                                                $size_label = trim((string)($opt['size'] ?? ''));
                                                $is_disabled = ((int)($opt['stock'] ?? 0) <= 0);
                                                if ($size_label === '') {
                                                    continue;
                                                }
                                                ?>
                                                <button
                                                    type="button"
                                                    class="favorite-size-btn <?php echo $is_disabled ? 'is-disabled' : ''; ?>"
                                                    data-size="<?php echo htmlspecialchars($size_label); ?>"
                                                    <?php echo $is_disabled ? 'disabled' : ''; ?>
                                                >
                                                    <?php echo htmlspecialchars($size_label); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <button type="button" class="favorite-size-btn is-selected" data-size="__NONE__">統一尺寸</button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="favorite-action-row">
                                        <button type="button" class="btn-add-cart btn-primary" data-id="<?php echo (int)$item['id']; ?>">加入購物車</button>
                                        <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>" class="btn-secondary">查看詳情</a>
                                    </div>
                                </div>
                                <form action="compare_actions.php" method="POST" class="favorite-compare-form favorite-compare-row">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$item['id']; ?>">
                                    <input type="hidden" name="redirect" value="favorites.php">
                                    <button
                                        type="submit"
                                        name="action"
                                        value="<?php echo $is_in_compare ? 'remove' : 'add'; ?>"
                                        class="favorite-compare-btn btn-compare <?php echo $is_in_compare ? 'favorite-compare-active' : ''; ?>"
                                    >
                                        <?php echo $is_in_compare ? '已加入比較' : '加入比較'; ?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </section>
    </main>
    <?php if ($compare_flash !== ''): ?>
    <div id="compare-toast" class="compare-toast"><?php echo htmlspecialchars($compare_flash); ?></div>
    <?php endif; ?>
    <script>
    (function () {
        function updateCartCount(count) {
            if (typeof window.updateNavbarBadges === 'function') {
                window.updateNavbarBadges({ cart_count: count });
                return;
            }
            var badge = document.getElementById('cartBadge');
            if (!badge) return;
            var n = parseInt(count, 10) || 0;
            badge.textContent = String(n);
            if (n > 0) {
                badge.classList.remove('is-empty');
            } else {
                badge.classList.add('is-empty');
            }
        }

        function showToast(text) {
            var old = document.querySelector('.favorites-page .toast');
            if (old) old.remove();
            var t = document.createElement('div');
            t.innerText = text;
            t.className = 'toast';
            document.body.appendChild(t);
            setTimeout(function () { t.remove(); }, 2000);
        }

        document.querySelectorAll('.favorite-size-row').forEach(function (row) {
            row.querySelectorAll('.favorite-size-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (btn.classList.contains('is-disabled') || btn.disabled) return;
                    row.querySelectorAll('.favorite-size-btn').forEach(function (peer) {
                        peer.classList.remove('is-selected');
                    });
                    btn.classList.add('is-selected');
                    row.dataset.selectedSize = btn.getAttribute('data-size') || '';
                });
            });
        });

        document.querySelectorAll('.btn-add-cart').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.dataset.id;
                var card = this.closest('.favorite-card');
                var sizeRow = card ? card.querySelector('.favorite-size-row') : null;
                var selectedSize = sizeRow ? String(sizeRow.dataset.selectedSize || '') : '';
                var requiresSize = sizeRow ? String(sizeRow.dataset.requiresSize || '0') === '1' : false;
                if (requiresSize && !selectedSize) {
                    showToast('請選擇尺寸');
                    return;
                }
                var fd = new FormData();
                fd.append('product_id', id);
                fd.append('quantity', '1');
                if (selectedSize && selectedSize !== '__NONE__') {
                    fd.append('size', selectedSize);
                }
                fetch('api/add_to_cart.php', {
                    method: 'POST',
                    body: fd
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        updateCartCount(data.cart_count || 0);
                        showToast('已加入購物車');
                    } else if (data && data.message) {
                        showToast(data.message);
                    } else {
                        showToast('加入購物車失敗');
                    }
                })
                .catch(function () {
                    showToast('加入購物車時發生錯誤');
                });
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            var toast = document.getElementById('compare-toast');
            if (!toast) return;
            setTimeout(function () {
                toast.classList.add('hide');
                setTimeout(function () {
                    toast.remove();
                }, 250);
            }, 2000);
        });
    })();
    </script>
</body>
</html>
