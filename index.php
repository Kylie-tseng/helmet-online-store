<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/category_utils.php';
require_once 'includes/navbar.php';

// 查詢分類資料
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id LIMIT 4");
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

// 查詢熱門商品
try {
    $stmt = $pdo->query("SELECT p.id, p.name, p.price, p.style,
            (
                SELECT pi.image_url
                FROM product_images pi
                WHERE pi.product_id = p.id
                ORDER BY pi.sort_order ASC, pi.id ASC
                LIMIT 1
            ) AS primary_image,
            c.name AS category_name
            FROM products p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
              AND p.is_featured = 1
            ORDER BY p.id ASC
            LIMIT 6");
    $hotProducts = $stmt->fetchAll();
} catch (PDOException $e) {
    $hotProducts = [];
}

$is_logged_in = isset($_SESSION['user_id']);
$favorite_ids = [];
if ($is_logged_in) {
    $favorite_ids = getUserFavoriteProductIds($pdo, (int)$_SESSION['user_id']);
}

$promo_offers = [
    [
        'title' => '新會員優惠',
        'text' => '新會員註冊後即可使用優惠券',
        'coupon' => 'NEW100',
        'highlight' => '滿 500 元折抵 100 元',
        'link' => 'coupon_new_member.php',
        'detail_link' => 'coupon_new_member.php'
    ],
    [
        'title' => '安全帽週年慶',
        'text' => '全館安全帽限時優惠',
        'coupon' => 'HELMET10',
        'highlight' => '全館商品 9 折',
        'link' => 'coupon_anniversary.php',
        'detail_link' => 'coupon_anniversary.php'
    ],
    [
        'title' => '滿額折扣活動',
        'text' => '購物滿額即可使用優惠券',
        'coupon' => 'SAVE300',
        'highlight' => '滿 2000 元折抵 300 元',
        'link' => 'coupon_discount.php',
        'detail_link' => 'coupon_discount.php'
    ],
    [
        'title' => '騎士節活動',
        'text' => '騎士節限定優惠',
        'coupon' => 'RIDER20',
        'highlight' => '全館商品 8 折',
        'link' => 'coupon_rider_day.php',
        'detail_link' => 'coupon_rider_day.php'
    ],
    [
        'title' => '滿三千免運',
        'text' => '全站購物滿額即可享免運優惠',
        'coupon' => '',
        'highlight' => '全站滿 3000 元免運',
        'link' => 'coupon_free_shipping.php',
        'detail_link' => 'coupon_free_shipping.php'
    ],
];

$promo_main_offer = null;
$promo_side_offers = [];
if (is_array($promo_offers) && !empty($promo_offers)) {
    foreach ($promo_offers as $index => $offer) {
        if (isset($offer['title']) && $offer['title'] === '安全帽週年慶') {
            $promo_main_offer = $offer;
            unset($promo_offers[$index]);
            break;
        }
    }

    if ($promo_main_offer === null) {
        $promo_main_offer = reset($promo_offers);
        if ($promo_main_offer !== false) {
            array_shift($promo_offers);
        } else {
            $promo_main_offer = null;
        }
    }

    $promo_side_offers = array_slice(array_values($promo_offers), 0, 4);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelmetVRse - 首頁</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/assets/css/style.css')); ?>">
</head>
<body class="home-page">
    <header class="home-header">
        <?php renderNavbar($pdo, $categories, $parts_category_id, 'home'); ?>
    </header>

    <section class="hero">
        <div class="hero-slider">
            <div class="slides">
                <img src="assets/images/index1.jpg" class="slide active" alt="Helmet Banner 1">
                <img src="assets/images/index2.jpg" class="slide" alt="Helmet Banner 2">
                <img src="assets/images/index3.jpg" class="slide" alt="Helmet Banner 3">
                <img src="assets/images/index4.jpg" class="slide" alt="Helmet Banner 4">
                <img src="assets/images/index5.jpg" class="slide" alt="Helmet Banner 5">
            </div>

            <div class="hero-overlay">
                <h1 class="hero-main-title">ENTER THE VR HELMET MALL</h1>
                <p class="hero-main-subtitle">沉浸式虛擬商場，重新定義智慧安全帽選購體驗</p>
                <a href="products.php" class="hero-cta-btn">前往 VR 商場</a>
            </div>

            <div class="slider-arrow left">&#10094;</div>
            <div class="slider-arrow right">&#10095;</div>
        </div>
    </section>

    <!-- 精選分類區 -->
    <section class="categories-section split-category-section">
        <div class="container">
            <section class="promo-section" aria-label="限時優惠活動">
                <div class="promo-container">
                    <div class="promo-header">
                        <p class="promo-eyebrow">EXCLUSIVE OFFERS</p>
                        <h2 class="promo-title">限時優惠活動</h2>
                        <p class="promo-subtitle">為騎士精選的專屬回饋與限時折扣</p>
                    </div>

                    <div class="promo-layout">
                        <?php if (is_array($promo_main_offer) && !empty($promo_main_offer)): ?>
                            <article class="promo-main-card">
                                <div class="promo-main-bg">
                                    <img src="assets/images/index_helmet.jpg" alt="安全帽主視覺" class="promo-main-bg-image">
                                </div>
                                <div class="promo-main-overlay"></div>
                                <div class="promo-main-content">
                                    <span class="promo-main-label">FEATURED</span>
                                    <h3 class="promo-main-title"><?php echo htmlspecialchars($promo_main_offer['title'] ?? '限時優惠'); ?></h3>
                                    <p class="promo-main-highlight"><?php echo htmlspecialchars($promo_main_offer['highlight'] ?? '優惠進行中'); ?></p>
                                    <p class="promo-main-text"><?php echo htmlspecialchars($promo_main_offer['text'] ?? '精選回饋活動'); ?></p>
                                    <div class="hero-actions">
                                        <a href="products.php" class="promo-btn btn-primary">立即選購</a>
                                        <a href="<?php echo htmlspecialchars($promo_main_offer['detail_link'] ?? $promo_main_offer['link'] ?? 'coupon_anniversary.php'); ?>" class="promo-btn btn-secondary">查看活動</a>
                                    </div>
                                </div>
                            </article>
                        <?php endif; ?>

                        <div class="promo-side-grid">
                            <?php foreach ($promo_side_offers as $offer): ?>
                                <article class="promo-mini-card">
                                    <span class="promo-card-label">LIMITED</span>
                                    <h3 class="promo-card-title"><?php echo htmlspecialchars($offer['title'] ?? '活動資訊'); ?></h3>
                                    <p class="promo-card-desc"><?php echo htmlspecialchars($offer['text'] ?? '限時活動'); ?></p>
                                    <p class="promo-card-offer"><?php echo htmlspecialchars($offer['highlight'] ?? '限時優惠'); ?></p>
                                    <a href="<?php echo htmlspecialchars($offer['link'] ?? 'coupons.php'); ?>" class="promo-btn">查看活動</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
            <div class="split-category-shell">
                <header class="featured-section-header">
                    <p class="featured-section-eyebrow">CURATED CATEGORIES</p>
                    <h2 class="featured-section-title">精選分類</h2>
                    <p class="featured-section-subtitle">以騎乘情境為核心，快速找到最適合的防護選擇</p>
                </header>

                <?php
                $split_category_lookup = [];
                foreach ($categories as $category) {
                    $name = trim((string)($category['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $split_category_lookup[$name] = $category;
                    $norm = normalize_product_category_label($name);
                    if ($norm !== '' && !isset($split_category_lookup[$norm])) {
                        $split_category_lookup[$norm] = $category;
                    }
                }

                $split_categories = [
                    [
                        'name' => '全罩式安全帽',
                        'description' => '完整包覆與穩定防護，適合長途與高速騎乘。',
                        'tone' => 'split-tone-dark-1'
                    ],
                    [
                        'name' => '半罩式安全帽',
                        'description' => '輕量通勤，保留日常穿梭的自由感。',
                        'tone' => 'split-tone-dark-2'
                    ],
                    [
                        'name' => '3/4罩安全帽',
                        'description' => '在保護性與透氣感之間取得平衡。',
                        'tone' => 'split-tone-dark-3'
                    ],
                    [
                        'name' => '周邊與配件',
                        'description' => '補足騎乘細節，完成整體配戴體驗。',
                        'tone' => 'split-tone-light'
                    ]
                ];
                ?>

                <div class="split-category-grid">
                    <?php foreach ($split_categories as $item): ?>
                        <?php
                        $name = $item['name'];
                        $norm = normalize_product_category_label($name);
                        $resolved = $split_category_lookup[$name] ?? $split_category_lookup[$norm] ?? null;
                        $target = $resolved
                            ? 'products.php?category=' . (int)$resolved['id']
                            : 'products.php?category=' . rawurlencode($name);
                        ?>
                        <div class="split-category-item">
                            <h3 class="split-category-title"><?php echo htmlspecialchars($name); ?></h3>
                            <p class="split-category-text"><?php echo htmlspecialchars($item['description']); ?></p>
                            <a href="<?php echo htmlspecialchars($target); ?>" class="split-category-link">前往選購 <span aria-hidden="true">→</span></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- 熱門商品區 -->
    <section class="products-section featured-products-section-home">
        <div class="container">
            <div class="featured-products-shell">
                <header class="featured-section-header">
                    <p class="featured-section-eyebrow">FEATURED PICKS</p>
                    <h2 class="featured-section-title">熱門商品</h2>
                    <p class="featured-section-subtitle">精選推薦，為不同騎乘風格而設計</p>
                </header>

                <?php
                // 首頁商品卡兩個 badge 的來源：
                // 1) 帽型分類：由 categories.name 映射（全罩式/半罩式/3/4罩/配件）
                // 2) 風格英文：由 products.style 映射（VINTAGE/COMMUTER/RACING/...）
                $helmet_type_map = [
                    '全罩式安全帽' => '全罩式',
                    '半罩式安全帽' => '半罩式',
                    '3/4罩安全帽' => '3/4罩',
                    '周邊與配件' => '配件',
                ];

                $style_english_map = [
                    '復古' => 'VINTAGE',
                    '通勤' => 'COMMUTER',
                    // 資料庫可能使用「競速」或「競賽」
                    '競賽' => 'RACING',
                    '競速' => 'RACING',
                    '街頭' => 'STREET',
                    '女性' => 'WOMEN',
                ];
                ?>

                <?php if (empty($hotProducts)): ?>
                    <div class="empty-message">目前尚未有熱門商品</div>
                <?php else: ?>
                    <div class="featured-products-grid-home">
                        <?php foreach ($hotProducts as $product): ?>
                            <?php
                            $is_favorited = in_array((int)$product['id'], $favorite_ids, true);
                            $card_img_src = resolve_product_card_image_src($product['primary_image'] ?? null);

                            $category_name = (string)($product['category_name'] ?? '');
                            $helmet_badge = $helmet_type_map[$category_name] ?? null;
                            // fallback：用字串關鍵字判斷（避免資料名稱略有差異）
                            if ($helmet_badge === null) {
                                if (strpos($category_name, '全罩式') !== false) {
                                    $helmet_badge = '全罩式';
                                } elseif (strpos($category_name, '半罩式') !== false) {
                                    $helmet_badge = '半罩式';
                                } elseif (strpos($category_name, '3/4') !== false) {
                                    $helmet_badge = '3/4罩';
                                } elseif (strpos($category_name, '配件') !== false || strpos($category_name, '周邊') !== false) {
                                    $helmet_badge = '配件';
                                } else {
                                    $helmet_badge = '配件';
                                }
                            }

                            $style_label = (string)($product['style'] ?? '');
                            $style_english_badge = $style_english_map[$style_label] ?? 'OTHER';
                            ?>
                            <article class="featured-product-card">
                                <div class="featured-product-media">
                                    <div class="featured-product-frame">
                                        <img class="featured-product-image" src="<?php echo htmlspecialchars($card_img_src, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </div>
                                </div>

                                <div class="featured-product-body">
                                    <div class="featured-product-meta">
                                        <div class="featured-product-badge-group" aria-label="商品標籤">
                                            <span class="featured-product-helmet-badge"><?php echo htmlspecialchars($helmet_badge); ?></span>
                                            <span class="featured-product-style-badge"><?php echo htmlspecialchars($style_english_badge); ?></span>
                                        </div>
                                        <form action="api/toggle_favorite.php" method="POST" class="product-favorite-inline-form">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                            <input type="hidden" name="redirect" value="index.php">
                                            <button
                                                type="submit"
                                                class="favorite-btn favorite-icon-btn featured-wishlist-btn <?php echo $is_favorited ? 'active' : ''; ?>"
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

                                    <h3 class="featured-product-title"><?php echo htmlspecialchars($product['name']); ?></h3>

                                    <div class="featured-product-footer">
                                        <p class="featured-product-price">NT$ <?php echo number_format($product['price']); ?></p>
                                        <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="featured-product-link">查看詳情 <span aria-hidden="true">→</span></a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 風格圖片導覽 -->
    <section class="lifestyle-gallery-section">
        <div class="container">
            <div class="lifestyle-gallery-shell">
                <header class="lifestyle-gallery-header">
                    <p class="featured-section-eyebrow">STYLE GUIDE</p>
                    <h2 class="featured-section-title">騎乘風格導覽</h2>
                    <p class="featured-section-subtitle">從不同場景出發，探索適合你的裝備搭配</p>
                </header>

                <div class="lifestyle-gallery-track">
                    <?php
                    // 騎乘風格導覽（中文 -> 英文）對應：用 map 方便之後擴充
                    $style_english_map = [
                        '復古' => 'VINTAGE',
                        '通勤' => 'COMMUTER',
                        '競速' => 'RACING',
                        '競賽' => 'RACING',
                        '女性' => 'WOMEN',
                    ];

                    // 首頁風格卡片導向：直接帶入 products.php 的 style 參數
                    // 注意：此區塊只改連結目標，不改卡片外觀/hover。
                    $style_param_map = [
                        '復古' => 'retro',
                        '通勤' => 'commuter',
                        '競速' => 'racing',
                        '女性' => 'women',
                    ];

                    $home_style_cards = [
                        ['label' => '復古', 'svg_rect' => '%23d8dde3', 'svg_path' => '%23b8c0ca', 'path_d' => 'M0 310L130 220L250 265L360 200L470 250L600 190V380H0Z'],
                        ['label' => '通勤', 'svg_rect' => '%23d9dfe5', 'svg_path' => '%23b7bec8', 'path_d' => 'M0 292L120 210L220 245L340 195L470 255L600 182V380H0Z'],
                        ['label' => '競速', 'svg_rect' => '%23dce1e6', 'svg_path' => '%23bcc4cd', 'path_d' => 'M0 305L120 228L250 275L370 210L500 258L600 200V380H0Z'],
                        ['label' => '女性', 'svg_rect' => '%23d7dde2', 'svg_path' => '%23b4bcc5', 'path_d' => 'M0 296L128 220L236 258L352 202L462 244L600 187V380H0Z'],
                    ];
                    foreach ($home_style_cards as $sc):
                        $style_cn = (string)($sc['label'] ?? '');
                        $style_param = $style_param_map[$style_cn] ?? 'retro';
                        $style_href = 'products.php?style=' . rawurlencode($style_param);
                        $style_en = $style_english_map[$style_cn] ?? 'OTHER';
                        $svg_src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='" . $sc['svg_rect'] . "' width='600' height='380'/%3E%3Cpath d='" . $sc['path_d'] . "' fill='" . $sc['svg_path'] . "'/%3E%3C/svg%3E";
                    ?>
                    <a class="lifestyle-gallery-item" href="<?php echo htmlspecialchars($style_href); ?>">
                        <img class="lifestyle-gallery-image" src="<?php echo htmlspecialchars($svg_src, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($style_cn); ?> 風格">
                        <h3 class="lifestyle-gallery-title" aria-label="<?php echo htmlspecialchars($style_cn); ?> / <?php echo htmlspecialchars($style_en); ?>">
                            <span class="lifestyle-style-badge-cn"><?php echo htmlspecialchars($style_cn); ?></span>
                            <span class="lifestyle-style-badge-en"><?php echo htmlspecialchars($style_en); ?></span>
                        </h3>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <button type="button" class="back-to-top-btn" id="backToTopBtn" aria-label="回到最上方">↑</button>

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

                // 滑鼠移入展開
                helmetMenu.addEventListener('mouseenter', function() {
                    if (!isHelmetLocked) {
                        helmetMenu.classList.add('open');
                    }
                });

                // 滑鼠移出收回（若未鎖定）
                helmetMenu.addEventListener('mouseleave', function() {
                    if (!isHelmetLocked) {
                        helmetMenu.classList.remove('open');
                    }
                });

                // 點擊切換鎖定狀態
                helmetMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    isHelmetLocked = !isHelmetLocked;
                    if (isHelmetLocked) {
                        helmetMenu.classList.add('open');
                    } else {
                        helmetMenu.classList.remove('open');
                    }
                });

                // 點擊外部取消鎖定
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

                // 點擊搜尋圖示切換
                searchToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    searchBox.classList.toggle('active');
                    if (searchBox.classList.contains('active')) {
                        searchInput.focus();
                    }
                });

                // 點擊輸入框時保持展開
                searchInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                    searchBox.classList.add('active');
                });

                // 點擊外部或失去焦點時收起
                document.addEventListener('click', function(e) {
                    if (!searchBox.contains(e.target)) {
                        searchBox.classList.remove('active');
                    }
                });

                // 輸入框失去焦點時收起
                searchInput.addEventListener('blur', function() {
                    // 延遲一下再收起，讓點擊事件先執行
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

        // 首頁 Hero 圖片輪播
        (function() {
            try {
                const slides = document.querySelectorAll('.hero-slider .slide');
                const nextArrow = document.querySelector('.hero-slider .slider-arrow.right');
                const prevArrow = document.querySelector('.hero-slider .slider-arrow.left');
                if (!slides.length || !nextArrow || !prevArrow) return;

                let current = 0;

                function showSlide(index) {
                    slides.forEach(function(slide) {
                        slide.classList.remove('active');
                    });
                    slides[index].classList.add('active');
                }

                function nextSlide() {
                    current = (current + 1) % slides.length;
                    showSlide(current);
                }

                function prevSlide() {
                    current = (current - 1 + slides.length) % slides.length;
                    showSlide(current);
                }

                nextArrow.addEventListener('click', nextSlide);
                prevArrow.addEventListener('click', prevSlide);

                setInterval(nextSlide, 5000);
            } catch (error) {
                console.error('Hero 輪播功能錯誤:', error);
            }
        })();

        // 回到最上方按鈕
        (function() {
            const backToTopBtn = document.getElementById('backToTopBtn');
            if (!backToTopBtn) return;

            const toggleVisibility = function() {
                if (window.scrollY > 520) {
                    backToTopBtn.classList.add('is-visible');
                } else {
                    backToTopBtn.classList.remove('is-visible');
                }
            };

            toggleVisibility();
            window.addEventListener('scroll', toggleVisibility, { passive: true });

            backToTopBtn.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();
    </script>
</body>
</html>
