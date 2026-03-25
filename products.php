<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/category_utils.php';
require_once 'includes/product_style_utils.php';
require_once 'includes/navbar.php';
require_once 'includes/product_query_helpers.php';

// 取得 GET 參數（category 可為數字 id 或分類名稱；category_id 為明確 id；style 為風格篩選）
$category_param = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$category_id_get = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search_keyword = trim($_GET['search'] ?? '');
$is_search_mode = ($search_keyword !== '');
$style_param = isset($_GET['style']) ? trim((string)$_GET['style']) : '';
$style_label = resolve_product_list_style($style_param);
$style_param_token = strtolower($style_param);
$legacy_style_tokens = ['retro', 'vintage', 'commuter', 'racing', 'women'];
$use_legacy_style_collection_layout = in_array($style_param_token, $legacy_style_tokens, true);
$style_label_to_token_map = [
    '復古' => 'vintage',
    '通勤' => 'commuter',
    '競速' => 'racing',
    '女性' => 'women',
];
$active_style_token = $use_legacy_style_collection_layout
    ? $style_param_token
    : (($style_label !== null && isset($style_label_to_token_map[$style_label])) ? $style_label_to_token_map[$style_label] : '');
// 舊連結 ?style=retro 仍有效；產生分頁／tab 連結時統一用 style=vintage
if ($active_style_token === 'retro') {
    $active_style_token = 'vintage';
}

[$category_id, $category_name] = resolve_product_list_category($pdo, $category_param, $category_id_get);

// 查詢所有分類（左側列表）
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// 查詢「周邊與配件」的分類 ID
$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    if ($parts_category) {
        $parts_category_id = $parts_category['id'];
    }
} catch (PDOException $e) {
    // 如果查詢失敗，保持為 null
}

// 頁面標題
$page_title = '商品總覽';
if ($category_name !== null && $category_name !== '') {
    $page_title = $category_name;
}
if ($style_label !== null) {
    if ($category_name !== null && $category_name !== '') {
        $page_title = $category_name . ' · ' . $style_label . '風格';
    } else {
        $page_title = $style_label . '風格';
    }
}

// 查詢商品（根據分類和搜尋條件）
try {
    $sql = "SELECT p.id, p.name, p.description, p.price, p.status,
                   " . primaryImageSubquery('p', 'pi') . " AS primary_image,
                   c.name AS category_name, c.id AS category_id
            FROM products p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'";
    
    $params = [];
    
    // 分類過濾（以 category_id，與 products.category_id 一致）
    if ($category_id) {
        $sql .= " AND p.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }

    // 風格過濾（products.style，可與分類並用為 AND）
    if ($style_label !== null) {
        $sql .= " AND p.style = :style_filter";
        $params[':style_filter'] = $style_label;
    }

    // 關鍵字搜尋（商品名稱與描述）
    if ($is_search_mode) {
        $sql .= " AND (p.name LIKE :search_keyword_name OR p.description LIKE :search_keyword_desc)";
        $params[':search_keyword_name'] = '%' . $search_keyword . '%';
        $params[':search_keyword_desc'] = '%' . $search_keyword . '%';
    }

    // 排序：全部商品（無分類、無搜尋）依分類固定順序 → 同分類內 id 升冪；單一分類頁依 id 升冪；搜尋維持建立時間新到舊
    if ($is_search_mode) {
        $sql .= " ORDER BY p.created_at DESC";
    } elseif ($category_id) {
        $sql .= " ORDER BY p.id ASC";
    } else {
        $ordered_cat_ids = get_category_ids_sorted_for_product_list($categories);
        if (!empty($ordered_cat_ids)) {
            $field_list = implode(',', array_map('intval', $ordered_cat_ids));
            $sql .= " ORDER BY FIELD(p.category_id, {$field_list}), p.id ASC";
        } else {
            $sql .= " ORDER BY p.category_id ASC, p.id ASC";
        }
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $error_message = "查詢商品時發生錯誤：" . $e->getMessage();
}

// 更新頁面標題（僅搜尋時）
if ($is_search_mode) {
    if ($category_name) {
        $page_title = $category_name . ' - 搜尋：「' . htmlspecialchars($search_keyword) . '」';
    } elseif ($style_label !== null) {
        $page_title = $style_label . ' 風格 - 搜尋：「' . htmlspecialchars($search_keyword) . '」';
    } else {
        $page_title = '搜尋：「' . htmlspecialchars($search_keyword) . '」';
    }
}

// 檢查是否已登入
$is_logged_in = isset($_SESSION['user_id']);
$favorite_ids = [];
if ($is_logged_in) {
    $favorite_ids = getUserFavoriteProductIds($pdo, (int)$_SESSION['user_id']);
}

// 風格館頁：products.php?style=...（查詢邏輯仍沿用，但版型將統一成商品總覽頁）
$is_style_collection_page = $style_label !== null;
$style_order_category_labels = $is_style_collection_page ? get_product_list_category_order_labels() : [];

// 風格頁 tab 不顯示「周邊與配件」
if ($is_style_collection_page) {
    $excluded_nk = normalize_product_category_label('周邊與配件');
    $style_order_category_labels = array_values(array_filter(
        $style_order_category_labels,
        function ($label) use ($excluded_nk) {
            $nk = normalize_product_category_label((string)$label);
            return $nk !== $excluded_nk;
        }
    ));
}

// 用於「橫向分類 pill」的 category_id 對應
$style_category_id_map = [];
if ($is_style_collection_page) {
    foreach ($categories as $cat) {
        $cat_name = (string)($cat['name'] ?? '');
        $style_category_id_map[normalize_product_category_label($cat_name)] = (int)($cat['id'] ?? 0);
    }
}

// 風格館：版面僅重新排版（不改查詢邏輯）
$products_for_grid = $is_style_collection_page ? $products : $products;

// 風格館主標中文 -> 英文（用於 header 主標旁顯示）
$style_english_title_map = [
    '復古' => 'VINTAGE',
    '通勤' => 'COMMUTER',
    '競速' => 'RACING',
    '女性' => 'WOMEN',
];
$style_english_title = $is_style_collection_page ? ($style_english_title_map[$style_label] ?? '') : '';

// 風格館 header 描述文案（依風格對應）
$style_desc_map = [
    '復古' => '把經典輪廓與懷舊質感穿進每一次出發，騎出不退流行的風格態度。',
    '通勤' => '從日常移動出發，找到兼顧舒適、實用與俐落外型的通勤裝備選擇。',
    '競速' => '以速度感與性能語彙為靈感，探索更銳利、更有侵略感的騎乘風格配置。',
    '女性' => '在俐落輪廓與細節質感之間，找到更貼近日常穿搭與個人風格的騎乘選擇。',
];
$style_header_desc = $is_style_collection_page ? ($style_desc_map[$style_label] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品總覽 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="products-overview-page">
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 商品總覽頁面內容 -->
    <?php if ($is_style_collection_page && $use_legacy_style_collection_layout): ?>
        <div class="style-collection-page-container">
            <div class="style-collection-page-wrapper">
                <!-- 標題區（置中，不用大圖 hero） -->
                <header class="style-collection-header">
                    <div class="style-collection-kicker">STYLE ARCHIVE</div>
                    <h1 class="style-collection-header-title">
                        <span class="style-collection-header-title-cn"><?php echo htmlspecialchars((string)$style_label); ?>風格</span>
                        <?php if ($style_english_title !== ''): ?>
                            <span class="style-collection-header-title-en"><?php echo htmlspecialchars($style_english_title); ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="style-collection-header-desc">
                        <?php echo htmlspecialchars($style_header_desc); ?>
                    </p>
                </header>

                <!-- 分類 tab（橫向 chips） -->
                <nav class="style-collection-tabs" aria-label="風格分類">
                    <?php
                        $all_is_active = ($category_id === null);
                        $all_href = 'products.php?style=' . rawurlencode((string)$active_style_token);
                    ?>
                    <a href="<?php echo htmlspecialchars($all_href); ?>" class="style-collection-tab <?php echo $all_is_active ? 'active' : ''; ?>">
                        全部商品
                    </a>

                    <?php foreach ($style_order_category_labels as $label): ?>
                        <?php
                            $nk = normalize_product_category_label($label);
                            $tab_cat_id = isset($style_category_id_map[$nk]) ? (int)$style_category_id_map[$nk] : 0;
                            $tab_href = $tab_cat_id > 0
                                ? ('products.php?category=' . $tab_cat_id . '&style=' . rawurlencode((string)$active_style_token))
                                : '#';
                            $tab_active = ($category_id !== null && $tab_cat_id > 0 && (int)$category_id === $tab_cat_id);
                        ?>
                        <?php if ($tab_cat_id > 0): ?>
                            <a href="<?php echo htmlspecialchars($tab_href); ?>" class="style-collection-tab <?php echo $tab_active ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>

                <!-- 商品 grid -->
                <?php if (isset($error_message)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php elseif (empty($products_for_grid)): ?>
                    <div class="empty-products-message">
                        <?php if ($is_search_mode): ?>
                            找不到符合「<?php echo htmlspecialchars($search_keyword); ?>」的商品。
                        <?php else: ?>
                            目前此條件下沒有商品。
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <section class="style-collection-grid-section">
                        <div class="style-collection-grid">
                            <?php foreach ($products_for_grid as $product): ?>
                                <?php $is_favorited = in_array((int)$product['id'], $favorite_ids, true); ?>
                                <div class="product-card-page">
                                    <div class="product-image-page">
                                        <?php $list_img = resolve_product_card_image_src($product['primary_image'] ?? null); ?>
                                        <img src="<?php echo htmlspecialchars($list_img, ENT_QUOTES); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </div>
                                    <div class="product-info-page">
                                        <h3 class="product-name-page"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <div class="product-price-row">
                                            <p class="product-price-page">NT$ <?php echo number_format($product['price'], 0); ?></p>
                                            <form action="api/toggle_favorite.php" method="POST" class="product-favorite-inline-form">
                                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'products.php'); ?>">
                                                <button
                                                    type="submit"
                                                    class="favorite-btn favorite-icon-btn <?php echo $is_favorited ? 'active' : ''; ?>"
                                                    aria-label="<?php echo $is_favorited ? '取消收藏' : '加入收藏'; ?>"
                                                    title="<?php echo $is_favorited ? '取消收藏' : '加入收藏'; ?>"
                                                >
                                                    <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path class="heart-outline" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                                        <path class="heart-fill" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="product-card-actions">
                                            <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="btn-primary product-btn-page">
                                                查看詳情
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="products-page-container">
            <div class="products-page-wrapper">
                <!-- 左側分類列表 -->
                <aside class="products-sidebar products-sidebar-panel">
                    <div class="sidebar-text-nav-header">
                        <div class="sidebar-title">商品分類</div>
                    </div>

                    <ul class="category-list">
                        <li>
                            <?php
                                // 只負責「商品分類」：點「全部商品」要清掉 category 條件，並且不保留 style 參數
                                $all_href = 'products.php?category=全部商品';
                                $all_active = ($category_id === null && ($category_param === '' || $category_param === '全部商品'));
                            ?>
                            <a href="<?php echo htmlspecialchars($all_href); ?>" class="category-link <?php echo $all_active ? 'active' : ''; ?>">
                                全部商品
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                            <?php
                                $cid = (int)$cat['id'];
                                // 只負責「商品分類」：切分類時不混入 style 條件
                                $cat_link = 'products.php?category=' . $cid;
                                $is_active = ($category_id !== null && (int)$category_id === $cid);
                            ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($cat_link); ?>"
                                   class="category-link <?php echo $is_active ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </aside>

                <!-- 右側商品列表 -->
                <main class="products-main">
                    <!-- 標題 -->
                    <div class="products-header">
                        <div class="products-header-row">
                            <h1 class="products-page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                            <form action="products.php" method="GET" class="products-search-form" role="search">
                                <?php if ($category_id !== null): ?>
                                    <input type="hidden" name="category" value="<?php echo (int)$category_id; ?>">
                                <?php elseif ($category_param !== '' && $category_param !== '全部商品'): ?>
                                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_param); ?>">
                                <?php endif; ?>
                                <?php if ($style_label !== null): ?>
                                    <input type="hidden" name="style" value="<?php echo htmlspecialchars($style_label); ?>">
                                <?php endif; ?>
                                <input
                                    type="text"
                                    name="search"
                                    id="productSearchInput"
                                    class="products-search-input"
                                    placeholder="找商品"
                                    value="<?php echo htmlspecialchars($search_keyword); ?>"
                                >
                                <button type="submit" class="products-search-btn">搜尋</button>
                            </form>
                        </div>
                    </div>

                    <!-- 商品卡片網格 -->
                    <?php if (isset($error_message)): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php elseif (empty($products)): ?>
                        <div class="empty-products-message">
                            <?php if ($is_search_mode): ?>
                                找不到符合「<?php echo htmlspecialchars($search_keyword); ?>」的商品。
                            <?php else: ?>
                                目前此條件下沒有商品。
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="products-grid-page">
                            <?php foreach ($products as $product): ?>
                                <?php $is_favorited = in_array((int)$product['id'], $favorite_ids, true); ?>
                                <div class="product-card-page">
                                    <div class="product-image-page">
                                        <?php
                                        $list_img = resolve_product_card_image_src($product['primary_image'] ?? null);
                                        ?>
                                        <img src="<?php echo htmlspecialchars($list_img, ENT_QUOTES); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </div>
                                    <div class="product-info-page">
                                        <h3 class="product-name-page"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <div class="product-price-row">
                                            <p class="product-price-page">NT$ <?php echo number_format($product['price'], 0); ?></p>
                                            <form action="api/toggle_favorite.php" method="POST" class="product-favorite-inline-form">
                                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'products.php'); ?>">
                                                <button
                                                    type="submit"
                                                    class="favorite-btn favorite-icon-btn <?php echo $is_favorited ? 'active' : ''; ?>"
                                                    aria-label="<?php echo $is_favorited ? '取消收藏' : '加入收藏'; ?>"
                                                    title="<?php echo $is_favorited ? '取消收藏' : '加入收藏'; ?>"
                                                >
                                                    <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path class="heart-outline" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                                        <path class="heart-fill" d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="product-card-actions">
                                            <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="btn-primary product-btn-page">
                                                查看詳情
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3 class="footer-title">關於我們</h3>
                    <ul class="footer-links">
                        <li><a href="about.php">公司簡介</a></li>
                        <li><a href="about.php#history">發展歷程</a></li>
                        <li><a href="about.php#mission">經營理念</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">顧客服務</h3>
                    <ul class="footer-links">
                        <li><a href="guide.php">購物指南</a></li>
                        <li><a href="faq.php">常見問題</a></li>
                        <li><a href="return.php">退換貨政策</a></li>
                        <li><a href="shipping.php">運送說明</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">聯絡我們</h3>
                    <ul class="footer-links">
                        <li>電話：02-2905-2000</li>
                        <li>Email：helmetvrsefju@gmail.com</li>
                        <li>地址：新北市新莊區中正路510號</li>
                        <li class="social-links">
                            <a href="#" class="social-icon">Facebook</a>
                            <a href="#" class="social-icon">Instagram</a>
                            <a href="#" class="social-icon">Line</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>Powered by HelmetVRse</p>
            </div>
        </div>
    </footer>

    <script>
// 安全帽側邊欄互動功能
        (function() {
            try {
                const helmetMenu = document.querySelector('.helmet-menu');
                const helmetMenuToggle = document.getElementById('helmetMenuToggle');
                if (!helmetMenu || !helmetMenuToggle) return;

                let isHelmetLocked = false;

                helmetMenu.addEventListener('mouseenter', function() {
                    if (!isHelmetLocked) {
                        helmetMenu.classList.add('open');
                    }
                });

                helmetMenu.addEventListener('mouseleave', function() {
                    if (!isHelmetLocked) {
                        helmetMenu.classList.remove('open');
                    }
                });

                helmetMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    isHelmetLocked = !isHelmetLocked;
                    if (isHelmetLocked) {
                        helmetMenu.classList.add('open');
                    } else {
                        helmetMenu.classList.remove('open');
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!helmetMenu.contains(e.target) && isHelmetLocked) {
                        isHelmetLocked = false;
                        helmetMenu.classList.remove('open');
                    }
                });
            } catch (error) {
                console.error('安全帽選單功能錯誤:', error);
            }
        })();

        // 搜尋框展開/收起功能
        (function() {
            try {
                const searchToggle = document.getElementById('searchToggle');
                const searchInput = document.getElementById('searchInput');
                const searchBox = document.querySelector('.search-box');

                if (!searchToggle || !searchInput || !searchBox) return;

                searchToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    searchBox.classList.toggle('active');
                    if (searchBox.classList.contains('active')) {
                        searchInput.focus();
                    }
                });

                searchInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                    searchBox.classList.add('active');
                });

                document.addEventListener('click', function(e) {
                    if (!searchBox.contains(e.target)) {
                        searchBox.classList.remove('active');
                    }
                });

                searchInput.addEventListener('blur', function() {
                    setTimeout(function() {
                        if (document.activeElement !== searchInput) {
                            searchBox.classList.remove('active');
                        }
                    }, 200);
                });

                // 搜尋表單提交
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const keyword = searchInput.value.trim();
                        if (keyword) {
                            window.location.href = 'products.php?search=' + encodeURIComponent(keyword);
                        } else {
                            window.location.href = 'products.php';
                        }
                    }
                });
            } catch (error) {
                console.error('搜尋框功能錯誤:', error);
            }
        })();

        // 商品頁搜尋欄：清空時移除 search 參數並回到原本總覽（保留其他參數）
        (function() {
            try {
                const productSearchInput = document.getElementById('productSearchInput');
                if (!productSearchInput) return;

                productSearchInput.addEventListener('input', function() {
                    const currentValue = productSearchInput.value.trim();
                    if (currentValue !== '') return;

                    const url = new URL(window.location.href);
                    if (!url.searchParams.has('search')) return;

                    url.searchParams.delete('search');
                    const nextQuery = url.searchParams.toString();
                    const nextUrl = url.pathname + (nextQuery ? ('?' + nextQuery) : '') + url.hash;

                    if (nextUrl !== (window.location.pathname + window.location.search + window.location.hash)) {
                        window.location.assign(nextUrl);
                    }
                });
            } catch (error) {
                console.error('商品搜尋清空導頁功能錯誤:', error);
            }
        })();
    </script>
</body>
</html>

