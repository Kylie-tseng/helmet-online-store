<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

// 取得 GET 參數
$category_param = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$category_id = null;
$category_name = null;
$category_keyword = null;
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

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

// 查詢選中分類的名稱（用於標題顯示）
$page_title = '商品總覽';
if ($category_param !== '') {
    $preset_keyword_map = [
        '全罩式安全帽' => '全罩式安全帽',
        '半罩式安全帽' => '半罩式安全帽',
        '3/4罩安全帽' => '3/4罩安全帽'
    ];

    if ($category_param === '全部商品') {
        $category_id = null;
        $category_name = null;
        $category_keyword = null;
    } elseif (isset($preset_keyword_map[$category_param])) {
        $category_name = $category_param;
        $category_keyword = $preset_keyword_map[$category_param];
    } elseif (ctype_digit($category_param)) {
        $category_id = (int)$category_param;
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE name = :category_name LIMIT 1");
            $stmt->execute([':category_name' => $category_param]);
            $matched_category = $stmt->fetch();
            if ($matched_category) {
                $category_id = (int)$matched_category['id'];
                $category_name = $matched_category['name'];
            }
        } catch (PDOException $e) {
            // ignore
        }
    }
}

if ($category_id && $category_name === null) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = :category_id");
        $stmt->execute([':category_id' => $category_id]);
        $category = $stmt->fetch();
        if ($category) {
            $category_name = $category['name'];
        }
    } catch (PDOException $e) {
        // ignore
    }
}

if ($category_name !== null) {
    $page_title = $category_name;
}

// 查詢商品（根據分類和搜尋條件）
try {
    $sql = "SELECT p.id, p.name, p.description, p.price, p.status, p.image_url, 
                   c.name AS category_name, c.id AS category_id
            FROM products p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'";
    
    $params = [];
    
    // 分類過濾（ID）
    if ($category_id) {
        $sql .= " AND p.category_id = :category_id";
        $params[':category_id'] = $category_id;
    }

    // 分類過濾（指定分類關鍵字）
    if (!empty($category_keyword)) {
        $sql .= " AND (p.name LIKE :category_keyword_name OR p.description LIKE :category_keyword_desc)";
        $params[':category_keyword_name'] = '%' . $category_keyword . '%';
        $params[':category_keyword_desc'] = '%' . $category_keyword . '%';
    }
    
    // 關鍵字搜尋（商品名稱與描述）
    if (!empty($search_keyword)) {
        $sql .= " AND (p.name LIKE :search_keyword_name OR p.description LIKE :search_keyword_desc)";
        $params[':search_keyword_name'] = '%' . $search_keyword . '%';
        $params[':search_keyword_desc'] = '%' . $search_keyword . '%';
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $error_message = "查詢商品時發生錯誤：" . $e->getMessage();
}

// 更新頁面標題（僅搜尋時）
if (!empty($search_keyword)) {
    if ($category_name) {
        $page_title = $category_name . ' - 搜尋：「' . htmlspecialchars($search_keyword) . '」';
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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品總覽 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 商品總覽頁面內容 -->
    <div class="products-page-container">
        <div class="products-page-wrapper">
            <!-- 左側分類列表 -->
            <aside class="products-sidebar">
                <h3 class="sidebar-title">商品分類</h3>
                <ul class="category-list">
                    <li>
                        <a href="products.php?category=全部商品" class="category-link <?php echo ($category_param === '' || $category_param === '全部商品') ? 'active' : ''; ?>">
                            全部商品
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                        <?php
                            $is_parts_category = ($cat['name'] === '周邊與配件');
                            $cat_link = $is_parts_category
                                ? 'products.php?category=周邊與配件'
                                : ('products.php?category=' . urlencode((string)$cat['name']));
                            $is_active = $is_parts_category
                                ? ($category_name === '周邊與配件' || $category_param === '周邊與配件')
                                : ($category_param === (string)$cat['name'] || $category_name === (string)$cat['name']);
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
                            <?php if ($category_param !== ''): ?>
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_param); ?>">
                            <?php endif; ?>
                            <input
                                type="text"
                                name="search"
                                class="products-search-input"
                                placeholder="找商品"
                                value="<?php echo htmlspecialchars($search_keyword); ?>"
                            >
                            <button type="submit" class="products-search-btn">搜尋</button>
                        </form>
                    </div>
                </div>

                <!-- 商品卡片網格 -->
                <?php if (!empty($_SESSION['favorite_message'])): ?>
                    <div class="success-message"><?php echo htmlspecialchars($_SESSION['favorite_message']); ?></div>
                    <?php unset($_SESSION['favorite_message']); ?>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php elseif (empty($products)): ?>
                    <div class="empty-products-message">
                        <?php if (!empty($search_keyword)): ?>
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
                                    // 檢查 image_url 是否為 NULL 或空字串
                                    $has_image = !empty($product['image_url']) && trim($product['image_url']) !== '';
                                    if ($has_image): 
                                    ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#9A9A9A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                <polyline points="21 15 16 10 5 21"></polyline>
                                            </svg>
                                            <span>無圖片</span>
                                        </div>
                                    <?php endif; ?>
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
    </script>
</body>
</html>

