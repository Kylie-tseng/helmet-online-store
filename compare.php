<?php
require_once 'config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/product_query_helpers.php';
require_once 'includes/product_card_image.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('compare.php') . '&notice=favorite');
    exit;
}

if (!isset($_SESSION['compare_list']) || !is_array($_SESSION['compare_list'])) {
    $_SESSION['compare_list'] = [];
}

$compare_flash = isset($_SESSION['compare_flash']) ? trim((string)$_SESSION['compare_flash']) : '';
unset($_SESSION['compare_flash']);
$compare_list = array_values(array_unique(array_map('intval', $_SESSION['compare_list'])));

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

$compare_products = [];
if (!empty($compare_list)) {
    try {
        $productCols = [];
        $colStmt = $pdo->query("SHOW COLUMNS FROM products");
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $field = (string)($col['Field'] ?? '');
            if ($field !== '') {
                $productCols[$field] = true;
            }
        }

        $extraSelect = [];
        foreach (['style', 'weight_g', 'material', 'safety_cert', 'is_addon'] as $field) {
            if (!empty($productCols[$field])) {
                $extraSelect[] = "p.{$field}";
            } else {
                $extraSelect[] = "NULL AS {$field}";
            }
        }

        $placeholders = implode(',', array_fill(0, count($compare_list), '?'));
        $sql = "SELECT p.id, p.name, p.price, " . implode(', ', $extraSelect) . ",
                       " . primaryImageSubquery('p', 'pi') . " AS primary_image,
                       c.name AS category_name
                FROM products p
                INNER JOIN categories c ON p.category_id = c.id
                INNER JOIN favorites f ON f.product_id = p.id AND f.user_id = ?
                WHERE p.status = 'active' AND p.id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([(int)$_SESSION['user_id']], $compare_list));
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        foreach ($compare_list as $id) {
            if (isset($map[$id])) {
                $compare_products[] = $map[$id];
            }
        }

        // 讀取尺寸與庫存（product_sizes），補到每個比較商品
        if (!empty($compare_products)) {
            $validProductIds = array_map(function ($p) {
                return (int)($p['id'] ?? 0);
            }, $compare_products);
            $validProductIds = array_values(array_filter($validProductIds, function ($id) {
                return (int)$id > 0;
            }));

            if (!empty($validProductIds)) {
                $sizeMap = [];
                $stockMap = [];
                $sizePlaceholders = implode(',', array_fill(0, count($validProductIds), '?'));
                $sizeSql = "SELECT product_id, size, stock
                            FROM product_sizes
                            WHERE product_id IN ($sizePlaceholders)
                            ORDER BY product_id ASC, id ASC";
                $sizeStmt = $pdo->prepare($sizeSql);
                $sizeStmt->execute($validProductIds);
                $sizeRows = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($sizeRows as $sr) {
                    $pid = (int)($sr['product_id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    if (!isset($sizeMap[$pid])) {
                        $sizeMap[$pid] = [];
                    }
                    if (!isset($stockMap[$pid])) {
                        $stockMap[$pid] = 0;
                    }
                    $sizeVal = trim((string)($sr['size'] ?? ''));
                    if ($sizeVal !== '' && !in_array($sizeVal, $sizeMap[$pid], true)) {
                        $sizeMap[$pid][] = $sizeVal;
                    }
                    $stockMap[$pid] += (int)($sr['stock'] ?? 0);
                }

                foreach ($compare_products as &$cp) {
                    $pid = (int)($cp['id'] ?? 0);
                    $sizes = $sizeMap[$pid] ?? [];
                    $sizeText = !empty($sizes) ? implode(' / ', $sizes) : '—';
                    $totalStock = (int)($stockMap[$pid] ?? 0);
                    if ($totalStock > 10) {
                        $stockText = '有庫存';
                    } elseif ($totalStock > 0) {
                        $stockText = '剩 ' . $totalStock . ' 件';
                    } else {
                        $stockText = '缺貨';
                    }
                    $cp['size_text'] = $sizeText;
                    $cp['stock_text'] = $stockText;
                }
                unset($cp);
            }
        }

        // compare_list 只保留仍在 favorites 的商品
        $valid_ids = array_map(function ($p) {
            return (int)($p['id'] ?? 0);
        }, $compare_products);
        $_SESSION['compare_list'] = array_values(array_filter($valid_ids, function ($id) {
            return (int)$id > 0;
        }));
    } catch (PDOException $e) {
        $compare_products = [];
    }
}

$compare_count = count($compare_products);
$compare_labels = ['價格', '商品分類', '風格', '尺寸', '庫存', '加價購'];
function compareDisplayValue(array $p, string $label): string
{
    if ($label === '價格') {
        return 'NT$ ' . number_format((float)($p['price'] ?? 0), 0);
    }
    if ($label === '商品分類') {
        return (string)($p['category_name'] ?? '—');
    }
    if ($label === '風格') {
        $v = isset($p['style']) ? trim((string)$p['style']) : '';
        return $v !== '' ? $v : '—';
    }
    if ($label === '尺寸') {
        return (string)($p['size_text'] ?? '—');
    }
    if ($label === '庫存') {
        return (string)($p['stock_text'] ?? '缺貨');
    }
    if ($label === '加價購') {
        return (int)($p['is_addon'] ?? 0) === 1 ? '✔ 支援' : '—';
    }
    return '—';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品比較 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/style.css')); ?>">
</head>
<body class="compare-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

<main class="compare-main">
    <section class="compare-shell">
        <header class="compare-header">
            <div class="compare-header-top">
                <div class="compare-header-text">
                    <h1 class="compare-title">商品比較</h1>
                    <p class="compare-subtitle">比較不同商品的價格、分類與規格差異，幫助你快速做出選擇</p>
                </div>
                <div class="compare-header-actions">
                    <a href="favorites.php" class="compare-pill compare-pill-link">返回收藏</a>
                </div>
            </div>
        </header>

        <?php if ($compare_flash !== ''): ?>
            <div class="compare-flash"><?php echo htmlspecialchars($compare_flash); ?></div>
        <?php endif; ?>

        <?php if ($compare_count === 0): ?>
            <section class="compare-empty-card">
                <div class="compare-empty-icon">◌</div>
                <h2>目前尚未加入比較商品</h2>
                <p>先到收藏頁或商品列表挑選商品加入比較，再回來查看差異</p>
                <div class="compare-empty-actions">
                    <a href="favorites.php" class="compare-btn-primary">前往收藏</a>
                    <a href="products.php" class="compare-btn-secondary">繼續逛逛</a>
                </div>
            </section>
        <?php elseif ($compare_count === 1): ?>
            <?php $item = $compare_products[0]; ?>
            <section class="compare-single-wrap">
                <article class="compare-single-card">
                    <a class="compare-single-image-link" href="product_detail.php?id=<?php echo (int)$item['id']; ?>">
                        <?php $img_src = resolve_product_card_image_src($item['primary_image'] ?? null); ?>
                        <img class="compare-single-image" src="<?php echo htmlspecialchars($img_src, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string)$item['name']); ?>">
                    </a>
                    <div class="compare-single-body">
                        <p class="compare-summary-category"><?php echo htmlspecialchars((string)$item['category_name']); ?></p>
                        <h3 class="compare-summary-name"><?php echo htmlspecialchars((string)$item['name']); ?></h3>
                        <p class="compare-summary-price">NT$ <?php echo number_format((float)$item['price'], 0); ?></p>
                        <div class="compare-summary-actions">
                            <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>" class="compare-btn-secondary">查看詳情</a>
                            <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>" class="compare-btn-primary">加入購物車</a>
                            <form action="compare_actions.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo (int)$item['id']; ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="redirect" value="compare.php">
                                <button type="submit" class="compare-remove-btn">移除比較</button>
                            </form>
                        </div>
                    </div>
                </article>
                <p class="compare-single-note">再加入 1 件商品即可開始比較差異。</p>
            </section>
        <?php else: ?>
            <section class="compare-table-wrap">
                <div class="compare-table" style="--compare-cols: <?php echo (int)$compare_count; ?>;">
                    <div class="compare-table-row compare-table-head">
                        <div class="compare-label-cell">商品</div>
                        <?php foreach ($compare_products as $item): ?>
                            <div class="compare-product-head">
                                <a class="compare-product-image-link" href="product_detail.php?id=<?php echo (int)$item['id']; ?>">
                                    <?php $img_src = resolve_product_card_image_src($item['primary_image'] ?? null); ?>
                                    <img class="compare-product-image" src="<?php echo htmlspecialchars($img_src, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars((string)$item['name']); ?>">
                                </a>
                                <h3 class="compare-head-name"><?php echo htmlspecialchars((string)$item['name']); ?></h3>
                                <p class="compare-head-price">NT$ <?php echo number_format((float)$item['price'], 0); ?></p>
                                <div class="compare-product-actions">
                                    <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>" class="compare-btn-secondary">查看詳情</a>
                                    <a href="product_detail.php?id=<?php echo (int)$item['id']; ?>" class="compare-btn-primary">加入購物車</a>
                                    <form action="compare_actions.php" method="POST">
                                        <input type="hidden" name="product_id" value="<?php echo (int)$item['id']; ?>">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="redirect" value="compare.php">
                                        <button type="submit" class="compare-remove-btn">移除比較</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($compare_labels as $label): ?>
                        <div class="compare-table-row">
                            <div class="compare-label-cell"><?php echo htmlspecialchars($label); ?></div>
                            <?php foreach ($compare_products as $item): ?>
                                <div class="compare-value-cell">
                                    <?php
                                    $value = compareDisplayValue($item, $label);
                                    $value_text = trim((string)$value);
                                    echo htmlspecialchars($value_text !== '' ? $value_text : '—');
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
