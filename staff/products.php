<?php

require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';
require_once __DIR__ . '/../includes/staff_style_labels.php';

staffRequireAuth();

$categoryCount = 0;
$styleOptionCount = 0;
$productTotal = 0;
$activeProducts = 0;

try {
    $categoryCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
} catch (Throwable $e) {
    $categoryCount = 0;
}

$styleTableOk = staff_style_labels_ensure_table($pdo);
if ($styleTableOk) {
    try {
        $styleOptionCount = (int)$pdo->query('SELECT COUNT(*) FROM staff_style_labels')->fetchColumn();
    } catch (Throwable $e) {
        $styleOptionCount = 0;
    }
} else {
    try {
        $defaults = ['復古', '通勤', '競速'];
        $stmt = $pdo->query(
            "SELECT DISTINCT TRIM(`style`) AS s FROM products WHERE `style` IS NOT NULL AND TRIM(`style`) <> ''"
        );
        $dist = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $s) {
            $s = trim((string)$s);
            if ($s !== '') {
                $dist[] = $s;
            }
        }
        $styleOptionCount = count(array_unique(array_merge($defaults, $dist)));
    } catch (Throwable $e) {
        $styleOptionCount = 3;
    }
}

try {
    $productTotal = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
} catch (Throwable $e) {
    $productTotal = 0;
}

try {
    $activeProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
} catch (Throwable $e) {
    $activeProducts = 0;
}

staffPageStart($pdo, '商品管理', 'products');
?>

<style>
    .staff-page .staff-products-hub .staff-entry-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    @media (max-width: 768px) {
        .staff-page .staff-products-hub .staff-entry-grid {
            grid-template-columns: 1fr;
        }
    }
    .staff-page .staff-products-hub .staff-entry-card--stack {
        display: flex;
        flex-direction: column;
        min-height: 0;
        padding: 14px 16px;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(17, 24, 39, 0.06);
    }
    .staff-page .staff-products-hub .staff-entry-card--stack h2 {
        margin: 0 0 8px;
        font-size: 17px;
    }
    .staff-page .staff-products-hub .staff-entry-card--stack .staff-entry-desc {
        margin: 0;
        color: #555;
        font-size: 14px;
        line-height: 1.5;
    }
    .staff-page .staff-products-hub .staff-entry-card--stack .staff-entry-meta-line {
        margin: 10px 0 0;
        color: #111;
        font-size: 14px;
        font-weight: 600;
    }
    .staff-page .staff-products-hub .staff-entry-card--stack .staff-btn {
        margin-top: 14px;
        align-self: flex-start;
    }

    /* 後台商品清單卡圖（與 style.css 內 .staff-page .staff-product-* 一致；清單頁依全域 CSS 套用） */
    .staff-page .staff-product-media {
        height: 180px;
        min-height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px 12px;
        box-sizing: border-box;
        background: #f3f4f6;
        overflow: hidden;
    }
    .staff-page .staff-product-media .staff-product-cover {
        width: 100%;
        height: auto;
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        display: block;
    }
</style>

<section class="staff-products-hub staff-dashboard-entry" aria-label="商品管理入口">
    <div class="staff-entry-grid">
        <article class="staff-entry-card staff-entry-card--stack">
            <h2>分類／風格管理</h2>
            <p class="staff-entry-desc">管理商品分類與風格選項，供商品編輯與篩選使用。</p>
            <p class="staff-entry-meta-line">
                商品分類 <?php echo number_format($categoryCount); ?> 個
                ／ 風格選項 <?php echo number_format($styleOptionCount); ?> 個
            </p>
            <a href="category_management.php" class="staff-btn">前往功能</a>
        </article>

        <article class="staff-entry-card staff-entry-card--stack">
            <h2>商品管理</h2>
            <p class="staff-entry-desc">管理商品資料、上下架、圖片與庫存。</p>
            <p class="staff-entry-meta-line">
                商品總數 <?php echo number_format($productTotal); ?>
                ／ 上架中 <?php echo number_format($activeProducts); ?>
            </p>
            <a href="product_catalog.php" class="staff-btn">前往功能</a>
        </article>
    </div>
</section>

<?php staffPageEnd(); ?>
