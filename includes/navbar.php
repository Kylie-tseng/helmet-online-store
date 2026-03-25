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
    $all_helmets_url = 'products.php?category=全部商品';
    $parts_url = products_category_list_url_by_name($categories, '周邊與配件');
    $guide_url = 'guide.php';

    // 首頁 mega menu 需要的分類/連結（若資料庫排序裁切導致缺少，則 fallback 到總覽頁）
    $fullface_url = (string)products_category_list_url_by_name($categories, '全罩式安全帽');
    $halfface_url = (string)products_category_list_url_by_name($categories, '半罩式安全帽');
    $threequarter_url = (string)products_category_list_url_by_name($categories, '3/4罩安全帽');
    if ($fullface_url === '') $fullface_url = $all_helmets_url;
    if ($halfface_url === '') $halfface_url = $all_helmets_url;
    if ($threequarter_url === '') $threequarter_url = $all_helmets_url;

    $daily_pick_url = 'daily_pick.php';
    $coupon_url = 'coupons.php';
    $faq_url = 'faq.php';
    $about_url = 'about.php';
    $return_policy_url = 'return_policy.php';
    $helmet_knowledge_url = 'helmet_knowledge.php';
    $head_measure_url = 'head_measure.php';
    $helmet_care_url = 'helmet_care.php';

    // 預覽視覺（使用首頁現有圖片資源）
    $preview_helmets_img = 'assets/images/index_helmet.jpg';
    $preview_accessories_img = 'assets/images/index5.jpg';
    $preview_guide_img = 'assets/images/index4.jpg';
    ?>
    <?php if ($is_home): ?>
        <nav class="<?php echo $nav_class; ?>"<?php echo $nav_id; ?> aria-label="主要導覽">
            <div class="nav-container home-navbar-grid">
                <div class="home-navbar-left">
                    <button
                        type="button"
                        class="home-navbar-burger"
                        id="homeMegaToggle"
                        aria-controls="homeSidebarDrawer"
                        aria-expanded="false"
                        aria-label="開啟導覽"
                    >
                        <span class="home-navbar-burger-icon" aria-hidden="true">☰</span>
                    </button>

                    <div class="home-navbar-main-links" role="navigation" aria-label="主選單">
                        <a href="<?php echo htmlspecialchars($all_helmets_url); ?>" class="home-navbar-main-link">安全帽</a>
                        <a href="<?php echo htmlspecialchars($parts_url); ?>" class="home-navbar-main-link">周邊與配件</a>
                        <a href="<?php echo htmlspecialchars($guide_url); ?>" class="home-navbar-main-link">購物指南</a>
                    </div>
                </div>

                <div class="home-navbar-center">
                    <a href="index.php" class="home-brand-link" aria-label="HelmetVRse 首頁">HelmetVRse</a>
                </div>

                <div class="home-navbar-right nav-right">
                    <a href="<?php echo htmlspecialchars($favorites_link); ?>" class="nav-action-link nav-icon-link home-navbar-favorite" aria-label="收藏商品">
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

                    <a href="<?php echo htmlspecialchars($profile_link); ?>" class="nav-action-link nav-icon-link" aria-label="會員">
                        <span class="nav-action-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                    </a>

                    <?php if ($is_logged_in): ?>
                        <a href="logout.php" class="nav-action-link nav-icon-link home-navbar-logout" aria-label="登出">
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
        <div class="home-sidebar-overlay" id="homeSidebarOverlay" aria-hidden="true"></div>

        <?php
        // Sidebar 右側內容（Bell-like：左主分類 / 右內容區）
        // 若分類名稱在資料庫不存在，則 fallback 到對應總覽頁（避免破壞導向功能）
        $bluetooth_url = (string)products_category_list_url_by_name($categories, '藍牙耳機');
        if ($bluetooth_url === '') $bluetooth_url = $parts_url;

        $visor_url = (string)products_category_list_url_by_name($categories, '鏡片');
        if ($visor_url === '') $visor_url = $parts_url;

        $anti_fog_url = (string)products_category_list_url_by_name($categories, '防霧配件');
        if ($anti_fog_url === '') $anti_fog_url = $parts_url;

        $gloves_url = (string)products_category_list_url_by_name($categories, '手套 / 護具');
        if ($gloves_url === '') $gloves_url = $parts_url;
        ?>

        <!-- 左側抽屜 Sidebar / Drawer（兩區塊結構：左主分類 + 右內容區） -->
        <aside
            class="home-sidebar-drawer custom-megamenu"
            id="homeSidebarDrawer"
            role="dialog"
            aria-modal="true"
            aria-hidden="true"
            aria-label="側邊選單"
        >
            <div class="home-sidebar-layout">
                <div class="home-sidebar-left">
                    <nav class="home-sidebar-primary-nav" aria-label="主分類">
                        <ul class="home-sidebar-primary">
                            <li>
                                <a href="<?php echo htmlspecialchars($all_helmets_url); ?>" class="home-sidebar-primary-item" data-home-sidebar-key="helmets">
                                    <span class="home-sidebar-left-text">
                                        <span class="home-sidebar-item-cn">安全帽</span>
                                        <span class="home-sidebar-item-en">Helmet</span>
                                    </span>
                                    <span class="home-sidebar-left-arrow" aria-hidden="true">→</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo htmlspecialchars($parts_url); ?>" class="home-sidebar-primary-item" data-home-sidebar-key="accessories">
                                    <span class="home-sidebar-left-text">
                                        <span class="home-sidebar-item-cn">周邊與配件</span>
                                        <span class="home-sidebar-item-en">Accessories</span>
                                    </span>
                                    <span class="home-sidebar-left-arrow" aria-hidden="true">→</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo htmlspecialchars('products.php?style=vintage'); ?>" class="home-sidebar-primary-item" data-home-sidebar-key="styles">
                                    <span class="home-sidebar-left-text">
                                        <span class="home-sidebar-item-cn">風格</span>
                                        <span class="home-sidebar-item-en">STYLE</span>
                                    </span>
                                    <span class="home-sidebar-left-arrow" aria-hidden="true">→</span>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo htmlspecialchars($guide_url); ?>" class="home-sidebar-primary-item" data-home-sidebar-key="guide">
                                    <span class="home-sidebar-left-text">
                                        <span class="home-sidebar-item-cn">購物指南</span>
                                        <span class="home-sidebar-item-en">Guide</span>
                                    </span>
                                    <span class="home-sidebar-left-arrow" aria-hidden="true">→</span>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <!-- 手機版：主分類手風琴 -->
                    <div class="home-sidebar-mobile-accordion" role="presentation">
                        <div class="home-sidebar-mobile-section" data-home-sidebar-key="helmets">
                            <button type="button" class="home-sidebar-mobile-btn" data-home-sidebar-key="helmets" aria-expanded="false">
                                <span class="home-sidebar-item-cn">安全帽</span>
                                <span class="home-sidebar-item-en">Helmet</span>
                                <span class="home-sidebar-mobile-chevron" aria-hidden="true">⌄</span>
                            </button>
                            <div class="home-sidebar-mobile-content" aria-hidden="true">
                                <a href="<?php echo htmlspecialchars($all_helmets_url); ?>" class="home-sidebar-mobile-link">商品總覽 <span class="sub">All Products</span></a>
                                <a href="<?php echo htmlspecialchars($all_helmets_url); ?>" class="home-sidebar-mobile-link">熱銷商品 <span class="sub">Best Sellers</span></a>
                                <a href="<?php echo htmlspecialchars($daily_pick_url); ?>" class="home-sidebar-mobile-link">新品上架 <span class="sub">New Arrivals</span></a>
                                <a href="<?php echo htmlspecialchars($coupon_url); ?>" class="home-sidebar-mobile-link">限時優惠 <span class="sub">Sale</span></a>
                                <a href="<?php echo htmlspecialchars($fullface_url); ?>" class="home-sidebar-mobile-link">全罩式 <span class="sub">Full Face</span></a>
                                <a href="<?php echo htmlspecialchars($halfface_url); ?>" class="home-sidebar-mobile-link">半罩式 <span class="sub">Half Face</span></a>
                                <a href="<?php echo htmlspecialchars($threequarter_url); ?>" class="home-sidebar-mobile-link">3/4 罩 <span class="sub">Three Quarter</span></a>
                            </div>
                        </div>

                        <div class="home-sidebar-mobile-section" data-home-sidebar-key="accessories">
                            <button type="button" class="home-sidebar-mobile-btn" data-home-sidebar-key="accessories" aria-expanded="false">
                                <span class="home-sidebar-item-cn">周邊與配件</span>
                                <span class="home-sidebar-item-en">Accessories</span>
                                <span class="home-sidebar-mobile-chevron" aria-hidden="true">⌄</span>
                            </button>
                            <div class="home-sidebar-mobile-content" aria-hidden="true">
                                <a href="<?php echo htmlspecialchars($bluetooth_url); ?>" class="home-sidebar-mobile-link">藍牙耳機 <span class="sub">Bluetooth</span></a>
                                <a href="<?php echo htmlspecialchars($visor_url); ?>" class="home-sidebar-mobile-link">鏡片 <span class="sub">Visor</span></a>
                                <a href="<?php echo htmlspecialchars($anti_fog_url); ?>" class="home-sidebar-mobile-link">防霧 <span class="sub">Anti Fog</span></a>
                                <a href="<?php echo htmlspecialchars($gloves_url); ?>" class="home-sidebar-mobile-link">手套 <span class="sub">Gloves</span></a>
                                <a href="<?php echo htmlspecialchars($parts_url); ?>" class="home-sidebar-mobile-link">其他配件 <span class="sub">Accessories</span></a>
                            </div>
                        </div>

                        <div class="home-sidebar-mobile-section" data-home-sidebar-key="styles">
                            <button type="button" class="home-sidebar-mobile-btn" data-home-sidebar-key="styles" aria-expanded="false">
                                <span class="home-sidebar-item-cn">風格</span>
                                <span class="home-sidebar-item-en">STYLE</span>
                                <span class="home-sidebar-mobile-chevron" aria-hidden="true">⌄</span>
                            </button>
                            <div class="home-sidebar-mobile-content" aria-hidden="true">
                                <a href="<?php echo htmlspecialchars('products.php?style=vintage'); ?>" class="home-sidebar-mobile-link">復古 <span class="sub">Vintage</span></a>
                                <a href="<?php echo htmlspecialchars('products.php?style=commuter'); ?>" class="home-sidebar-mobile-link">通勤 <span class="sub">Commuter</span></a>
                                <a href="<?php echo htmlspecialchars('products.php?style=racing'); ?>" class="home-sidebar-mobile-link">競速 <span class="sub">Racing</span></a>
                                <a href="<?php echo htmlspecialchars('products.php?style=women'); ?>" class="home-sidebar-mobile-link">女性 <span class="sub">Women</span></a>
                            </div>
                        </div>

                        <div class="home-sidebar-mobile-section" data-home-sidebar-key="guide">
                            <button type="button" class="home-sidebar-mobile-btn" data-home-sidebar-key="guide" aria-expanded="false">
                                <span class="home-sidebar-item-cn">購物指南</span>
                                <span class="home-sidebar-item-en">Guide</span>
                                <span class="home-sidebar-mobile-chevron" aria-hidden="true">⌄</span>
                            </button>
                            <div class="home-sidebar-mobile-content" aria-hidden="true">
                                <a href="<?php echo htmlspecialchars($profile_link); ?>" class="home-sidebar-mobile-link">會員中心 <span class="sub">Account</span></a>
                                <a href="<?php echo htmlspecialchars($coupon_url); ?>" class="home-sidebar-mobile-link">優惠券 <span class="sub">Coupons</span></a>
                                <a href="<?php echo htmlspecialchars($faq_url); ?>" class="home-sidebar-mobile-link">FAQ</a>
                                <a href="<?php echo htmlspecialchars($about_url); ?>" class="home-sidebar-mobile-link">關於我們 <span class="sub">About</span></a>
                                <a href="<?php echo htmlspecialchars($return_policy_url); ?>" class="home-sidebar-mobile-link">退貨政策 <span class="sub">Return Policy</span></a>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-footer-links" role="navigation" aria-label="帳戶與客服">
                        <a href="<?php echo htmlspecialchars($profile_link); ?>" class="sidebar-footer-item">
                            <span class="zh">登入</span>
                            <span class="en">Login</span>
                        </a>
                        <a href="#" class="sidebar-footer-item">
                            <span class="zh">電子報訂閱</span>
                            <span class="en">Email Signup</span>
                        </a>
                        <a href="<?php echo htmlspecialchars($faq_url); ?>" class="sidebar-footer-item">
                            <span class="zh">客服中心</span>
                            <span class="en">Help Center</span>
                        </a>
                    </div>
                </div>

                <!-- 桌機右側內容區 -->
                <div class="home-sidebar-right">
                    <div class="home-sidebar-content-set" data-home-sidebar-content="helmets">
                        <div class="home-sidebar-panel">
                            <div class="home-sidebar-panel-links home-sidebar-panel-links--helmets-promo">
                                <a href="<?php echo htmlspecialchars($all_helmets_url); ?>" class="home-sidebar-panel-link">商品總覽 All Products</a>
                                <a href="<?php echo htmlspecialchars($all_helmets_url); ?>" class="home-sidebar-panel-link">熱銷商品 Best Sellers</a>
                                <a href="<?php echo htmlspecialchars($daily_pick_url); ?>" class="home-sidebar-panel-link">新品上架 New Arrivals</a>
                                <a href="<?php echo htmlspecialchars($coupon_url); ?>" class="home-sidebar-panel-link">限時優惠 Sale</a>
                            </div>
                            <div class="home-sidebar-panel-list">
                                <a href="<?php echo htmlspecialchars($fullface_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_helmets_img); ?>" alt="全罩式 Full Face" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">全罩式</span> <span class="en">Full Face</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars($halfface_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_helmets_img); ?>" alt="半罩式 Half Face" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">半罩式</span> <span class="en">Half Face</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars($threequarter_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_helmets_img); ?>" alt="3/4 罩 Three Quarter" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">3/4 罩</span> <span class="en">Three Quarter</span></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="home-sidebar-content-set" data-home-sidebar-content="accessories">
                        <div class="home-sidebar-panel">
                            <div class="home-sidebar-panel-list">
                                <a href="<?php echo htmlspecialchars($bluetooth_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_accessories_img); ?>" alt="藍牙耳機 Bluetooth" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">藍牙耳機</span> <span class="en">Bluetooth</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars($visor_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_accessories_img); ?>" alt="鏡片 Visor" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">鏡片</span> <span class="en">Visor</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars($anti_fog_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_accessories_img); ?>" alt="防霧 Anti Fog" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">防霧</span> <span class="en">Anti Fog</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars($gloves_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_accessories_img); ?>" alt="手套 Gloves" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">手套</span> <span class="en">Gloves</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars($parts_url); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_accessories_img); ?>" alt="其他配件 Accessories" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">其他配件</span> <span class="en">Accessories</span></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="home-sidebar-content-set" data-home-sidebar-content="styles">
                        <div class="home-sidebar-panel">
                            <div class="home-sidebar-panel-list">
                                <a href="<?php echo htmlspecialchars('products.php?style=vintage'); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_helmets_img); ?>" alt="復古 Vintage" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">復古</span> <span class="en">Vintage</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars('products.php?style=commuter'); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_helmets_img); ?>" alt="通勤 Commuter" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">通勤</span> <span class="en">Commuter</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars('products.php?style=racing'); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_helmets_img); ?>" alt="競速 Racing" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">競速</span> <span class="en">Racing</span></span>
                                </a>
                                <a href="<?php echo htmlspecialchars('products.php?style=women'); ?>" class="home-sidebar-panel-item">
                                    <img src="<?php echo htmlspecialchars($preview_helmets_img); ?>" alt="女性 Women" class="home-sidebar-panel-item-img" width="72" height="72">
                                    <span class="home-sidebar-panel-item-text"><span class="cn">女性</span> <span class="en">Women</span></span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="home-sidebar-content-set" data-home-sidebar-content="guide">
                        <div class="home-sidebar-panel">
                            <div class="home-sidebar-panel-list home-sidebar-panel-list--guide">
                                <div class="home-sidebar-guide-grid" aria-label="購物指南選單">
                                    <div class="home-sidebar-guide-col" aria-label="購物指南">
                                        <div class="home-sidebar-guide-title">購物指南</div>
                                        <a href="<?php echo htmlspecialchars($guide_url); ?>" class="home-sidebar-guide-link">購物指南總覽</a>
                                        <a href="<?php echo htmlspecialchars($about_url); ?>" class="home-sidebar-guide-link">關於我們</a>
                                        <a href="<?php echo htmlspecialchars($coupon_url); ?>" class="home-sidebar-guide-link">優惠券專區</a>
                                        <a href="<?php echo htmlspecialchars($return_policy_url); ?>" class="home-sidebar-guide-link">退貨政策</a>
                                    </div>
                                    <div class="home-sidebar-guide-col" aria-label="更多資訊">
                                        <div class="home-sidebar-guide-title">更多資訊</div>
                                        <a href="<?php echo htmlspecialchars($helmet_knowledge_url); ?>" class="home-sidebar-guide-link">安全帽知識</a>
                                        <a href="<?php echo htmlspecialchars($head_measure_url); ?>" class="home-sidebar-guide-link">頭圍量測教學</a>
                                        <a href="<?php echo htmlspecialchars($helmet_care_url); ?>" class="home-sidebar-guide-link">安全帽保養教學</a>
                                        <a href="<?php echo htmlspecialchars($faq_url); ?>" class="home-sidebar-guide-link">常見問題 FAQ</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <script>
            (function() {
                try {
                    const toggleBtn = document.getElementById('homeMegaToggle');
                    const overlay = document.getElementById('homeSidebarOverlay');
                    const drawer = document.getElementById('homeSidebarDrawer');

                    const primaryItems = drawer.querySelectorAll('.home-sidebar-primary-item[data-home-sidebar-key]');
                    const contentSets = drawer.querySelectorAll('.home-sidebar-content-set[data-home-sidebar-content]');

                    const clearContent = function() {
                        primaryItems.forEach(function(item) {
                            item.classList.remove('is-active');
                        });
                        contentSets.forEach(function(set) {
                            set.classList.remove('is-active');
                        });
                    };

                    const setContent = function(key) {
                        primaryItems.forEach(function(item) {
                            item.classList.toggle('is-active', item.getAttribute('data-home-sidebar-key') === key);
                        });
                        contentSets.forEach(function(set) {
                            set.classList.toggle('is-active', set.getAttribute('data-home-sidebar-content') === key);
                        });
                    };

                    if (!toggleBtn || !overlay || !drawer) return;

                    let isOpen = false;
                    let prevOverflow = '';

                    const setOpenState = function(open) {
                        isOpen = open;

                        if (open) {
                            prevOverflow = document.body.style.overflow || '';
                            document.body.style.overflow = 'hidden';

                            overlay.classList.add('is-open');
                            drawer.classList.add('is-open');
                            drawer.classList.remove('expanded'); // 預設只顯示左欄

                            overlay.setAttribute('aria-hidden', 'false');
                            drawer.setAttribute('aria-hidden', 'false');

                            toggleBtn.classList.add('is-open');
                            toggleBtn.setAttribute('aria-expanded', 'true');

                            // 預設不顯示任何中間欄
                            clearContent();

                            toggleBtn.focus({ preventScroll: true });
                        } else {
                            overlay.classList.remove('is-open');
                            drawer.classList.remove('is-open');
                            drawer.classList.remove('expanded');
                            clearContent();

                            overlay.setAttribute('aria-hidden', 'true');
                            drawer.setAttribute('aria-hidden', 'true');

                            toggleBtn.classList.remove('is-open');
                            toggleBtn.setAttribute('aria-expanded', 'false');

                            document.body.style.overflow = prevOverflow;
                        }
                    };

                    const close = function() {
                        setOpenState(false);
                    };

                    toggleBtn.addEventListener('click', function() {
                        setOpenState(!isOpen);
                    });

                    overlay.addEventListener('click', close);

                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape' && isOpen) {
                            close();
                        }
                    });

                    // 桌機：左側主分類只用「點擊」顯示中間欄（不導向、不自動展開）
                    primaryItems.forEach(function(item) {
                        const key = item.getAttribute('data-home-sidebar-key');
                        if (!key) return;
                        item.addEventListener('click', function(e) {
                            if (!isOpen) return;
                            if (!window.matchMedia('(min-width: 769px)').matches) return;
                            e.preventDefault(); // 左側分類只做觸發器
                            e.stopPropagation(); // 不觸發抽屜內的關閉邏輯

                            const isSame = item.classList.contains('is-active');
                            const isExpanded = drawer.classList.contains('expanded');

                            // 點擊同一個項目可收起（toggle）
                            if (isExpanded && isSame) {
                                drawer.classList.remove('expanded');
                                clearContent();
                                return;
                            }

                            drawer.classList.add('expanded');
                            setContent(key);
                        });
                    });

                    // 手機：手風琴
                    const mobileSections = drawer.querySelectorAll('.home-sidebar-mobile-section[data-home-sidebar-key]');
                    mobileSections.forEach(function(section) {
                        const btn = section.querySelector('.home-sidebar-mobile-btn');
                        if (!btn) return;
                        btn.addEventListener('click', function() {
                            const isMobile = window.matchMedia('(max-width: 768px)').matches;
                            if (!isMobile) return;

                            const openNow = !section.classList.contains('is-open');
                            mobileSections.forEach(function(s) {
                                s.classList.remove('is-open');
                                const b = s.querySelector('.home-sidebar-mobile-btn');
                                if (b) b.setAttribute('aria-expanded', 'false');
                                const c = s.querySelector('.home-sidebar-mobile-content');
                                if (c) c.setAttribute('aria-hidden', 'true');
                            });

                            if (openNow) {
                                section.classList.add('is-open');
                                btn.setAttribute('aria-expanded', 'true');
                                const content = section.querySelector('.home-sidebar-mobile-content');
                                if (content) content.setAttribute('aria-hidden', 'false');
                            }
                        });
                    });

                    // 點擊連結後關閉抽屜（不改變 href）
                    drawer.addEventListener('click', function(e) {
                        const a = e.target && e.target.closest ? e.target.closest('a') : null;
                        if (a) close();
                    });
                } catch (error) {
                    console.error('Home sidebar 互動錯誤:', error);
                }
            })();
        </script>
    <?php else: ?>
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
                <li class="nav-item daily-pick-nav-item">
                    <a href="daily_pick.php" class="daily-pick-nav-link">今日命定帽款</a>
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
    <?php endif; ?>
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

