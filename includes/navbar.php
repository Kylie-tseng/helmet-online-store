<?php
/**
 * 導覽列共用函數
 * 使用前需先 require_once 'includes/cart_functions.php'
 */

function renderNavbar($pdo, $categories, $parts_category_id, $current_page = '') {
    require_once __DIR__ . '/category_utils.php';
    $is_logged_in = isset($_SESSION['user_id']);
    $user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
    $cart_count = getCartItemCount($pdo, $user_id);
    $favorite_count = getFavoriteCount($pdo, $user_id);
    $is_home = ($current_page === 'home');
    $nav_class = $is_home ? 'navbar home-navbar unified-navbar' : 'navbar unified-navbar';
    $nav_id = $is_home ? ' id="homeNavbar"' : '';
    $favorites_link = $is_logged_in ? 'favorites.php' : 'login.php?redirect=' . urlencode('favorites.php') . '&notice=favorite';
    $cart_link = $is_logged_in ? 'cart.php' : 'login.php?redirect=' . urlencode('cart.php') . '&notice=cart';
    $profile_link = $is_logged_in ? 'profile.php' : 'login.php';
    ?>
    <nav class="<?php echo $nav_class; ?>"<?php echo $nav_id; ?>>
        <div class="nav-container">
            <div class="nav-logo home-navbar-left">
                <a href="index.php">HelmetVRse</a>
            </div>
            <ul class="nav-menu home-navbar-center">
                <li class="helmet-menu nav-item has-mega-menu">
                    <a href="products.php?category=全部商品" id="helmetMenuToggle">安全帽 <span class="dropdown-arrow">▾</span></a>
                    <ul class="helmet-submenu">
                        <li><a href="products.php?category=全部商品">全部安全帽</a></li>
                        <li><a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '全罩式安全帽')); ?>">全罩式安全帽</a></li>
                        <li><a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '半罩式安全帽')); ?>">半罩式安全帽</a></li>
                        <li><a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '3/4罩安全帽')); ?>">3/4罩安全帽</a></li>
                        <li><a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '周邊與配件')); ?>">周邊與配件</a></li>
                    </ul>
                    <div class="mega-menu">
                        <div class="mega-links">
                            <div class="mega-column">
                                <h4>商品分類</h4>
                                <a href="products.php?category=全部商品">全部安全帽</a>
                                <a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '全罩式安全帽')); ?>">全罩式安全帽</a>
                                <a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '半罩式安全帽')); ?>">半罩式安全帽</a>
                                <a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '3/4罩安全帽')); ?>">3/4罩安全帽</a>
                                <a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '周邊與配件')); ?>">周邊與配件</a>
                            </div>
                            <div class="mega-column">
                                <h4>更多資訊</h4>
                                <a href="helmet_knowledge.php">安全帽知識</a>
                                <a href="head_measure.php">頭圍量測教學</a>
                                <a href="helmet_care.php">安全帽保養教學</a>
                                <a href="faq.php">常見問題 FAQ</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="<?php echo htmlspecialchars(products_category_list_url_by_name($categories, '周邊與配件')); ?>">周邊與配件</a>
                </li>
                <li class="helmet-menu nav-item has-mega-menu">
                    <a href="guide.php" id="guideMenuToggle">購物指南 <span class="dropdown-arrow">▾</span></a>
                    <ul class="helmet-submenu">
                        <li><a href="guide.php">購物指南總覽</a></li>
                        <li><a href="about.php">關於我們</a></li>
                        <li><a href="coupons.php">優惠券專區</a></li>
                        <li><a href="return_policy.php">退貨政策</a></li>
                        <li><a href="helmet_knowledge.php">安全帽知識</a></li>
                        <li><a href="head_measure.php">頭圍量測教學</a></li>
                        <li><a href="helmet_care.php">安全帽保養教學</a></li>
                        <li><a href="faq.php">常見問題 FAQ</a></li>
                    </ul>
                    <div class="mega-menu">
                        <div class="mega-links">
                            <div class="mega-column">
                                <h4>購物指南</h4>
                                <a href="guide.php">購物指南總覽</a>
                                <a href="about.php">關於我們</a>
                                <a href="coupons.php">優惠券專區</a>
                                <a href="return_policy.php">退貨政策</a>
                            </div>
                            <div class="mega-column">
                                <h4>更多資訊</h4>
                                <a href="helmet_knowledge.php">安全帽知識</a>
                                <a href="head_measure.php">頭圍量測教學</a>
                                <a href="helmet_care.php">安全帽保養教學</a>
                                <a href="faq.php">常見問題 FAQ</a>
                            </div>
                        </div>
                    </div>
                </li>
            </ul>
            <div class="nav-right home-navbar-right">
                <?php if ($is_logged_in): ?>
                    <span class="user-greeting">Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <?php endif; ?>

                <a href="<?php echo htmlspecialchars($favorites_link); ?>" class="nav-action-link nav-icon-link" aria-label="收藏商品">
                    <span class="nav-action-icon">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </span>
                    <span id="favoriteBadge" class="nav-action-badge<?php echo $favorite_count > 0 ? '' : ' is-empty'; ?>"><?php echo (int)$favorite_count; ?></span>
                </a>

                <a href="<?php echo htmlspecialchars($cart_link); ?>" class="nav-action-link nav-icon-link" id="cartLink" aria-label="購物車">
                    <span class="nav-action-icon">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h7.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                    </span>
                    <span id="cartBadge" class="nav-action-badge<?php echo $cart_count > 0 ? '' : ' is-empty'; ?>"><?php echo (int)$cart_count; ?></span>
                </a>

                <a href="<?php echo htmlspecialchars($profile_link); ?>" class="nav-action-link nav-icon-link" aria-label="個人檔案">
                    <span class="nav-action-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                </a>

                <?php if ($is_logged_in): ?>
                    <a href="logout.php" class="nav-action-link nav-icon-link" aria-label="登出">
                        <span class="nav-action-icon">
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </span>
                    </a>
                <?php endif; ?>

            </div>
        </div>
    </nav>
    <script>
        (function() {
            try {
                const bindMegaMenuBehavior = function(toggleId) {
                    const menuLink = document.getElementById(toggleId);
                    const menuItem = menuLink ? menuLink.closest('.helmet-menu') : null;
                    if (!menuLink || !menuItem) return null;

                    let armedForNavigation = false;

                    menuItem.addEventListener('mouseenter', function() {
                        menuItem.classList.add('open');
                    });

                    menuItem.addEventListener('mouseleave', function() {
                        if (!armedForNavigation) {
                            menuItem.classList.remove('open');
                        }
                    });

                    menuLink.addEventListener('click', function(e) {
                        // First click opens dropdown; second click follows link
                        if (!menuItem.classList.contains('open') || !armedForNavigation) {
                            e.preventDefault();
                            menuItem.classList.add('open');
                            armedForNavigation = true;
                            return;
                        }

                        armedForNavigation = false;
                    });

                    return {
                        element: menuItem,
                        reset: function() {
                            menuItem.classList.remove('open');
                            armedForNavigation = false;
                        }
                    };
                };

                const managedMenus = [
                    bindMegaMenuBehavior('helmetMenuToggle'),
                    bindMegaMenuBehavior('guideMenuToggle')
                ].filter(Boolean);

                if (managedMenus.length === 0) return;

                document.addEventListener('click', function(e) {
                    managedMenus.forEach(function(menuState) {
                        if (!menuState.element.contains(e.target)) {
                            menuState.reset();
                        }
                    });
                });
            } catch (error) {
                console.error('下拉選單功能錯誤:', error);
            }
        })();

        (function() {
            const navbar = document.getElementById('homeNavbar');
            if (!navbar) return;

            const updateNavbarState = function() {
                if (window.scrollY > 0) {
                    navbar.classList.add('is-scrolled');
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('is-scrolled');
                    navbar.classList.remove('scrolled');
                }
            };

            updateNavbarState();
            window.addEventListener('scroll', updateNavbarState, { passive: true });
            window.addEventListener('pageshow', updateNavbarState);
        })();

        (function() {
            const SCROLL_KEY = 'favoriteToggleScrollY';
            const PATH_KEY = 'favoriteTogglePath';

            document.addEventListener('submit', function(e) {
                const form = e.target.closest('form[action*="api/toggle_favorite.php"]');
                if (!form) return;

                try {
                    sessionStorage.setItem(SCROLL_KEY, String(window.scrollY || window.pageYOffset || 0));
                    sessionStorage.setItem(PATH_KEY, window.location.pathname + window.location.search);
                } catch (error) {
                    // ignore storage failure
                }
            });

            const restoreScroll = function() {
                try {
                    const savedY = sessionStorage.getItem(SCROLL_KEY);
                    const savedPath = sessionStorage.getItem(PATH_KEY);
                    const currentPath = window.location.pathname + window.location.search;

                    if (!savedY || savedPath !== currentPath) return;

                    const y = parseInt(savedY, 10);
                    if (!Number.isNaN(y)) {
                        window.scrollTo(0, y);
                    }

                    sessionStorage.removeItem(SCROLL_KEY);
                    sessionStorage.removeItem(PATH_KEY);
                } catch (error) {
                    // ignore storage failure
                }
            };

            window.addEventListener('DOMContentLoaded', restoreScroll, { once: true });
            window.addEventListener('pageshow', restoreScroll, { once: true });
        })();

        (function() {
            const setBadge = function(el, count) {
                if (!el) return;
                const value = Number.isFinite(Number(count)) ? Math.max(0, parseInt(count, 10)) : 0;
                el.textContent = String(value);
                el.classList.toggle('is-empty', value <= 0);
            };

            window.updateNavbarBadges = function(payload) {
                if (!payload || typeof payload !== 'object') return;
                if (Object.prototype.hasOwnProperty.call(payload, 'cart_count')) {
                    setBadge(document.getElementById('cartBadge'), payload.cart_count);
                }
                if (Object.prototype.hasOwnProperty.call(payload, 'favorite_count')) {
                    setBadge(document.getElementById('favoriteBadge'), payload.favorite_count);
                }
            };

            window.addEventListener('cart:updated', function(e) {
                window.updateNavbarBadges(e.detail || {});
            });
        })();
    </script>
    <?php
}

