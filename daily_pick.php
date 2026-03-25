<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/product_card_image.php';
require_once 'includes/product_query_helpers.php';
require_once 'includes/destiny_helper.php';

try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($parts) {
        $parts_category_id = (int)$parts['id'];
    }
} catch (PDOException $e) {
    $parts_category_id = null;
}

$products = [];
$error_message = null;
try {
    $stmt = $pdo->query("
        SELECT
            p.id,
            p.name,
            p.price,
            p.style,
            c.name AS category_name,
            " . primaryImageSubquery('p', 'pi') . " AS primary_image
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.status = 'active'
        ORDER BY p.id ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $products[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'price' => (int)$row['price'],
            'style' => (string)($row['style'] ?? ''),
            'category_name' => (string)($row['category_name'] ?? '未分類'),
            'image_url' => resolve_product_card_image_src($row['primary_image'] ?? null),
            'detail_url' => 'product_detail.php?id=' . urlencode((string)$row['id']),
        ];
    }
} catch (PDOException $e) {
    $error_message = '目前無法抽籤，請稍後再試。';
}

$destiny_mapping = destiny_get_copy_mapping();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>今日命定帽款 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/style.css')); ?>">
</head>
<body class="daily-oracle-modal-page">
<?php renderNavbar($pdo, $categories, $parts_category_id); ?>

<main class="destiny-modal-shell">
    <header class="destiny-header">
        <h1>今日命定帽款</h1>
        <p>抽一支籤，看看今天與你有緣的是哪一頂帽</p>
    </header>

    <?php if ($error_message !== null): ?>
        <section class="destiny-empty-state"><?php echo htmlspecialchars($error_message); ?></section>
    <?php elseif (empty($products)): ?>
        <section class="destiny-empty-state">目前沒有可抽取的上架商品，請稍後再試。</section>
    <?php else: ?>
        <section class="destiny-stage">
            <article id="destinyShrine" class="destiny-shrine">
                <p class="shrine-top">今日籤運</p>
                <p id="rollingText" class="shrine-main">靜心凝神，待籤文顯現</p>
                <p id="rollingSubText" class="shrine-sub">按下開始抽籤，迎接今日命定之物</p>
            </article>
            <button id="startDrawBtn" type="button" class="destiny-draw-btn">開始抽籤</button>
        </section>
    <?php endif; ?>
</main>

<?php if ($error_message === null && !empty($products)): ?>
<div id="destinyOverlay" class="destiny-overlay" aria-hidden="true">
    <section id="destinyModalCard" class="destiny-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button id="closeModalBtn" type="button" class="destiny-close-btn" aria-label="關閉">×</button>

        <header class="modal-card-head">
            <p id="modalTitle" class="modal-main-title">今日命定帽款</p>
            <p id="modalSlipNo" class="modal-slip-no">第 000 籤</p>
            <span id="modalTier" class="modal-tier">中籤</span>
        </header>

        <div class="modal-card-divider"></div>

        <section class="modal-poem">
            <p id="modalPoem1">天意今朝自有憑</p>
            <p id="modalPoem2">有緣裝備已相逢</p>
            <p id="modalPoem3">穩中帶勁行無礙</p>
            <p id="modalPoem4">平安順遂伴君行</p>
        </section>

        <section class="modal-meaning">
            <p class="modal-label">解籤</p>
            <p id="modalMeaningText">今日宜選穩定貼合的帽款，能讓你在路上更安心從容。</p>
        </section>

        <section class="modal-product">
            <div class="modal-product-image-wrap">
                <img id="modalProductImage" class="modal-product-image" src="assets/images/products/default.jpg" alt="命定商品圖片">
            </div>
            <p id="modalProductName" class="modal-product-name">命定帽款待揭曉</p>
            <p id="modalProductCategory" class="modal-product-meta">分類：—</p>
            <p id="modalProductPrice" class="modal-product-meta">價格：NT$ —</p>
        </section>

        <footer class="modal-actions">
            <a id="viewProductBtn" href="products.php" class="modal-view-btn">查看商品</a>
            <button id="drawAgainBtn" type="button" class="modal-again-btn">再抽一次</button>
            <button id="closeFooterBtn" type="button" class="modal-close-footer-btn">關閉</button>
        </footer>
    </section>
</div>

<script>
    (function () {
        const products = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const destinyMapping = <?php echo json_encode($destiny_mapping, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        const shrine = document.getElementById('destinyShrine');
        const rollingText = document.getElementById('rollingText');
        const rollingSubText = document.getElementById('rollingSubText');
        const startBtn = document.getElementById('startDrawBtn');
        const overlay = document.getElementById('destinyOverlay');
        const modalCard = document.getElementById('destinyModalCard');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const closeFooterBtn = document.getElementById('closeFooterBtn');
        const drawAgainBtn = document.getElementById('drawAgainBtn');
        const viewProductBtn = document.getElementById('viewProductBtn');

        const modalSlipNo = document.getElementById('modalSlipNo');
        const modalTier = document.getElementById('modalTier');
        const modalPoem1 = document.getElementById('modalPoem1');
        const modalPoem2 = document.getElementById('modalPoem2');
        const modalPoem3 = document.getElementById('modalPoem3');
        const modalPoem4 = document.getElementById('modalPoem4');
        const modalMeaningText = document.getElementById('modalMeaningText');
        const modalProductImage = document.getElementById('modalProductImage');
        const modalProductName = document.getElementById('modalProductName');
        const modalProductCategory = document.getElementById('modalProductCategory');
        const modalProductPrice = document.getElementById('modalProductPrice');

        if (!Array.isArray(products) || products.length === 0 || !startBtn) return;

        let isDrawing = false;
        let rollingTimer = null;
        let lastProductId = 0;

        const normalizeCategory = function (name) {
            const raw = String(name || '').trim();
            if (raw === '全罩安全帽') return '全罩式安全帽';
            if (raw === '半罩安全帽') return '半罩式安全帽';
            return raw;
        };

        const randomPick = function (list) {
            return list[Math.floor(Math.random() * list.length)];
        };

        const pickProduct = function (excludeId) {
            if (products.length === 1) return products[0];
            let picked = randomPick(products);
            let guard = 0;
            while (picked && picked.id === excludeId && guard < 20) {
                picked = randomPick(products);
                guard += 1;
            }
            return picked;
        };

        const pickDestinyCopy = function (product) {
            const styleMap = destinyMapping.style || {};
            const categoryMap = destinyMapping.category || {};
            const defaults = destinyMapping.default || [];
            const styleKey = String(product.style || '').trim();
            const categoryKey = normalizeCategory(product.category_name);
            let pool = [];

            if (styleKey && Array.isArray(styleMap[styleKey])) {
                pool = styleMap[styleKey];
            } else if (categoryKey && Array.isArray(categoryMap[categoryKey])) {
                pool = categoryMap[categoryKey];
            } else {
                pool = defaults;
            }

            if (!Array.isArray(pool) || pool.length === 0) {
                return {
                    tier: '中籤',
                    poem: ['天意今朝自有憑', '有緣裝備已相逢', '穩中帶勁行無礙', '平安順遂伴君行'],
                    interpretation: '今日宜選穩定貼合的帽款，能讓你在路上更安心從容。'
                };
            }
            return randomPick(pool);
        };

        const setDrawingState = function (drawing) {
            isDrawing = drawing;
            startBtn.disabled = drawing;
            drawAgainBtn.disabled = drawing;
            shrine.classList.toggle('is-drawing', drawing);
            startBtn.classList.toggle('is-drawing', drawing);
        };

        const openModal = function () {
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('oracle-modal-open');
            window.requestAnimationFrame(function () {
                modalCard.classList.add('is-open');
            });
        };

        const closeModal = function () {
            modalCard.classList.remove('is-open');
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('oracle-modal-open');
        };

        const renderModalContent = function (product) {
            const copy = pickDestinyCopy(product);
            const poem = Array.isArray(copy.poem) ? copy.poem : [];
            const slipNo = String(product.id || 0).padStart(3, '0');
            const tier = String(copy.tier || '中籤');

            modalSlipNo.textContent = '第 ' + slipNo + ' 籤';
            modalTier.textContent = tier;
            modalTier.className = 'modal-tier tier-' + tier;

            modalPoem1.textContent = poem[0] || '天意今朝自有憑';
            modalPoem2.textContent = poem[1] || '有緣裝備已相逢';
            modalPoem3.textContent = poem[2] || '穩中帶勁行無礙';
            modalPoem4.textContent = poem[3] || '平安順遂伴君行';
            modalMeaningText.textContent = copy.interpretation || '今日宜選穩定貼合的帽款，能讓你在路上更安心從容。';

            modalProductImage.src = product.image_url || 'assets/images/products/default.jpg';
            modalProductImage.alt = (product.name || '命定商品') + ' 圖片';
            modalProductName.textContent = product.name || '未命名商品';
            modalProductCategory.textContent = '分類：' + normalizeCategory(product.category_name || '未分類');
            modalProductPrice.textContent = '價格：NT$ ' + Number(product.price || 0).toLocaleString('zh-TW');
            viewProductBtn.href = product.detail_url || 'products.php';
        };

        const runDraw = function () {
            if (isDrawing) return;
            setDrawingState(true);
            const duration = 1650;
            const interval = 100;
            const startedAt = Date.now();
            let finalProduct = pickProduct(lastProductId);

            rollingTimer = window.setInterval(function () {
                const current = pickProduct(lastProductId);
                rollingText.textContent = current.name || '命定之物正在顯現';
                rollingSubText.textContent = normalizeCategory(current.category_name || '未分類');
                finalProduct = current;

                if (Date.now() - startedAt >= duration) {
                    window.clearInterval(rollingTimer);
                    rollingTimer = null;
                    lastProductId = Number(finalProduct.id || 0);
                    renderModalContent(finalProduct);
                    setDrawingState(false);
                    openModal();
                    rollingText.textContent = '籤文已現';
                    rollingSubText.textContent = '今日有緣之物已為你揭示';
                }
            }, interval);
        };

        startBtn.addEventListener('click', runDraw);
        drawAgainBtn.addEventListener('click', runDraw);
        closeModalBtn.addEventListener('click', closeModal);
        closeFooterBtn.addEventListener('click', closeModal);

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay.classList.contains('is-open')) {
                closeModal();
            }
        });
    })();
</script>
<?php endif; ?>
</body>
</html>
