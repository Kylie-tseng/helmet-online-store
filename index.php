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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelmetVRse - 首頁</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* 只在首頁生效：活動快捷卡強制版 */
        .home-promotions {
            display: flex !important;
            flex-wrap: nowrap !important;
            justify-content: space-between !important;
            align-items: stretch !important;
            gap: 16px !important;
            margin: 30px 0 !important;
        }

        .home-promotions .promo-card {
            flex: 0 0 calc((100% - 64px) / 5) !important;
            max-width: calc((100% - 64px) / 5) !important;
            min-width: 0 !important;
            background: #fff !important;
            padding: 16px 12px !important;
            border-radius: 12px !important;
            text-align: center !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05) !important;
            transition: transform 0.2s ease !important;
            text-decoration: none !important;
            color: inherit !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
        }

        .home-promotions .promo-card:hover {
            transform: translateY(-3px) !important;
        }

        .home-promotions .promo-shortcut-icon {
            display: block;
            font-size: 20px;
            margin-bottom: 8px;
            line-height: 1;
        }

        .home-promotions .promo-shortcut-title {
            font-size: 15px;
            color: #333333;
            font-weight: 700;
            margin-bottom: 6px;
            line-height: 1.35;
        }

        .home-promotions .promo-shortcut-desc {
            font-size: 13px;
            color: #666666;
            line-height: 1.4;
        }

        @media (max-width: 991.98px) {
            .home-promotions {
                justify-content: flex-start !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 8px;
            }

            .home-promotions .promo-card {
                flex: 0 0 190px !important;
                max-width: none !important;
            }
        }
    </style>
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

    <!-- 分類區 -->
    <section class="categories-section">
        <div class="container">
            <div class="home-promotions">
                <a href="coupon_new_member.php" class="promo-card">
                    <span class="promo-shortcut-icon">🎉</span>
                    <h3 class="promo-shortcut-title">新會員優惠</h3>
                    <p class="promo-shortcut-desc">滿 500 元折抵 100 元</p>
                </a>
                <a href="coupon_anniversary.php" class="promo-card">
                    <span class="promo-shortcut-icon">🪖</span>
                    <h3 class="promo-shortcut-title">安全帽週年慶</h3>
                    <p class="promo-shortcut-desc">全館商品 9 折</p>
                </a>
                <a href="coupon_discount.php" class="promo-card">
                    <span class="promo-shortcut-icon">💰</span>
                    <h3 class="promo-shortcut-title">滿額折扣活動</h3>
                    <p class="promo-shortcut-desc">滿 2000 元折抵 300 元</p>
                </a>
                <a href="coupon_rider_day.php" class="promo-card">
                    <span class="promo-shortcut-icon">🏍</span>
                    <h3 class="promo-shortcut-title">騎士節活動</h3>
                    <p class="promo-shortcut-desc">全館商品 8 折</p>
                </a>
                <a href="coupon_free_shipping.php" class="promo-card">
                    <span class="promo-shortcut-icon">🚚</span>
                    <h3 class="promo-shortcut-title">滿三千免運</h3>
                    <p class="promo-shortcut-desc">全站滿 NT$3000 免運</p>
                </a>
            </div>
            <div class="section-header">
                <h2 class="section-title">商品分類</h2>
                <p class="section-subtitle">探索我們的精選分類</p>
            </div>
            <div class="categories-grid">
                <?php if (empty($categories)): ?>
                    <div class="empty-message">目前尚未設定分類</div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <a href="products.php?category=<?php echo htmlspecialchars($category['id']); ?>" class="category-card">
                            <div class="category-content">
                                <h3 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h3>
                                <p class="category-description">
                                    <?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '探索此分類的優質商品'; ?>
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 熱門商品區 -->
    <section class="products-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">熱門商品</h2>
                <p class="section-subtitle">精選推薦商品</p>
            </div>
            <?php if (!empty($_SESSION['favorite_message'])): ?>
                <div class="success-message"><?php echo htmlspecialchars($_SESSION['favorite_message']); ?></div>
                <?php unset($_SESSION['favorite_message']); ?>
            <?php endif; ?>
            <div class="products-grid">
                <?php if (empty($hotProducts)): ?>
                    <div class="empty-message">目前尚未有熱門商品</div>
                <?php else: ?>
                    <?php foreach ($hotProducts as $product): ?>
                        <?php $is_favorited = in_array((int)$product['id'], $favorite_ids, true); ?>
                        <div class="product-card">
                            <div class="product-image">
                                <?php 
                                // 檢查 image_url 是否為 NULL 或空字串
                                $has_image = !empty($product['image_url']) && trim($product['image_url']) !== '';
                                if ($has_image): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
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
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-price-row">
                                    <p class="product-price">NT$ <?php echo number_format($product['price']); ?></p>
                                    <form action="api/toggle_favorite.php" method="POST" class="product-favorite-inline-form">
                                        <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                        <input type="hidden" name="redirect" value="index.php">
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
                                    <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="product-btn">查看詳情</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

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
                        <li>Email：service@helmetvr.com</li>
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
    </script>
</body>
</html>
