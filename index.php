<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
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
    $stmt = $pdo->query("SELECT id, name, price, image_url FROM products WHERE status = 'active' ORDER BY created_at DESC LIMIT 6");
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
                                        <a href="<?php echo htmlspecialchars($promo_main_offer['link'] ?? 'coupons.php'); ?>" class="promo-btn btn-primary">立即選購</a>
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
                        $resolved = $split_category_lookup[$name] ?? null;
                        $target = $resolved
                            ? 'products.php?category=' . urlencode((string)$resolved['id'])
                            : 'products.php?category=' . urlencode($name);
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

                <?php if (!empty($_SESSION['favorite_message'])): ?>
                    <div class="success-message"><?php echo htmlspecialchars($_SESSION['favorite_message']); ?></div>
                    <?php unset($_SESSION['favorite_message']); ?>
                <?php endif; ?>

                <?php
                $product_tags = ['全罩式', '競賽款', '入門款'];
                ?>

                <?php if (empty($hotProducts)): ?>
                    <div class="empty-message">目前尚未有熱門商品</div>
                <?php else: ?>
                    <div class="featured-products-grid-home">
                        <?php foreach ($hotProducts as $idx => $product): ?>
                            <?php
                            $is_favorited = in_array((int)$product['id'], $favorite_ids, true);
                            $has_image = !empty($product['image_url']) && trim($product['image_url']) !== '';
                            $tag = $product_tags[$idx % count($product_tags)];
                            ?>
                            <article class="featured-product-card">
                                <div class="featured-product-media">
                                    <div class="featured-product-frame">
                                        <?php if ($has_image): ?>
                                            <img class="featured-product-image" src="<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="featured-product-placeholder">
                                                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="#9A9A9A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                    <polyline points="21 15 16 10 5 21"></polyline>
                                                </svg>
                                                <span>無圖片</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="featured-product-body">
                                    <div class="featured-product-meta">
                                        <span class="featured-product-tag"><?php echo htmlspecialchars($tag); ?></span>
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
                    <article class="lifestyle-gallery-item">
                        <img class="lifestyle-gallery-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='%23d8dde3' width='600' height='380'/%3E%3Cpath d='M0 310L130 220L250 265L360 200L470 250L600 190V380H0Z' fill='%23b8c0ca'/%3E%3C/svg%3E" alt="通勤騎乘">
                        <h3 class="lifestyle-gallery-title">通勤騎乘</h3>
                    </article>
                    <article class="lifestyle-gallery-item">
                        <img class="lifestyle-gallery-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='%23d9dfe5' width='600' height='380'/%3E%3Cpath d='M0 292L120 210L220 245L340 195L470 255L600 182V380H0Z' fill='%23b7bec8'/%3E%3C/svg%3E" alt="長途旅行">
                        <h3 class="lifestyle-gallery-title">長途旅行</h3>
                    </article>
                    <article class="lifestyle-gallery-item">
                        <img class="lifestyle-gallery-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='%23d6dce2' width='600' height='380'/%3E%3Cpath d='M0 300L90 240L210 270L325 205L460 248L600 190V380H0Z' fill='%23b1bbc5'/%3E%3C/svg%3E" alt="城市風格">
                        <h3 class="lifestyle-gallery-title">城市風格</h3>
                    </article>
                    <article class="lifestyle-gallery-item">
                        <img class="lifestyle-gallery-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='%23dce1e6' width='600' height='380'/%3E%3Cpath d='M0 305L120 228L250 275L370 210L500 258L600 200V380H0Z' fill='%23bcc4cd'/%3E%3C/svg%3E" alt="競速性能">
                        <h3 class="lifestyle-gallery-title">競速性能</h3>
                    </article>
                    <article class="lifestyle-gallery-item">
                        <img class="lifestyle-gallery-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='%23d7dde2' width='600' height='380'/%3E%3Cpath d='M0 296L128 220L236 258L352 202L462 244L600 187V380H0Z' fill='%23b4bcc5'/%3E%3C/svg%3E" alt="女性精選">
                        <h3 class="lifestyle-gallery-title">女性精選</h3>
                    </article>
                    <article class="lifestyle-gallery-item">
                        <img class="lifestyle-gallery-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='%23d4dae0' width='600' height='380'/%3E%3Cpath d='M0 302L112 226L234 266L350 204L472 256L600 194V380H0Z' fill='%23adb6bf'/%3E%3C/svg%3E" alt="周邊配件">
                        <h3 class="lifestyle-gallery-title">周邊配件</h3>
                    </article>
                    <article class="lifestyle-gallery-item">
                        <img class="lifestyle-gallery-image" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 380'%3E%3Crect fill='%23d8dde1' width='600' height='380'/%3E%3Cpath d='M0 299L122 222L246 268L362 208L478 252L600 196V380H0Z' fill='%23b3bcc4'/%3E%3C/svg%3E" alt="禮物推薦">
                        <h3 class="lifestyle-gallery-title">禮物推薦</h3>
                    </article>
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
