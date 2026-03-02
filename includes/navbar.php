<?php
/**
 * 導覽列共用函數
 * 使用前需先 require_once 'includes/cart_functions.php'
 */

function renderNavbar($pdo, $categories, $parts_category_id, $current_page = '') {
    $is_logged_in = isset($_SESSION['user_id']);
    $user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
    $cart_count = getCartItemCount($pdo, $user_id);
    ?>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.php">HelmetVRse</a>
            </div>
            <ul class="nav-menu">
                <li><a href="products.php">商品總覽</a></li>
                <li class="helmet-menu">
                    <a href="products.php" id="helmetMenuToggle">安全帽 <span class="dropdown-arrow">▾</span></a>
                    <ul class="helmet-submenu">
                        <?php 
                        $helmet_categories = array_filter($categories, function($cat) {
                            return $cat['name'] !== '周邊與零件';
                        });
                        foreach ($helmet_categories as $cat): 
                        ?>
                            <li><a href="products.php?category=<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <li><a href="products.php<?php echo $parts_category_id ? '?category=' . htmlspecialchars($parts_category_id) : ''; ?>">周邊與零件</a></li>
                <li><a href="guide.php">購物須知</a></li>
            </ul>
            <div class="nav-right">
                <!-- 搜尋 -->
                <div class="search-box">
                    <a href="#search" class="nav-action-link" id="searchToggle">
                        <span class="nav-action-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <span class="nav-action-text">找商品</span>
                    </a>
                    <input type="text" class="search-input" id="searchInput" placeholder="找商品">
                </div>

                <!-- 購物車 -->
                <a href="cart.php" class="nav-action-link" id="cartLink">
                    <span class="nav-action-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2h12"></path>
                            <path d="M3 6h18l-2 14H5L3 6z"></path>
                        </svg>
                    </span>
                    <span class="nav-action-text" id="cartCount">購物車(<?php echo $cart_count; ?>)</span>
                </a>

                <!-- 登入/註冊 -->
                <div class="auth-links">
                    <?php if ($is_logged_in): ?>
                        <span class="user-greeting">Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <a href="profile.php" class="nav-action-link">
                            <span class="nav-action-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </span>
                            <span class="nav-action-text">個人檔案</span>
                        </a>
                        <a href="logout.php" class="nav-action-link">
                            <span class="nav-action-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                            </span>
                            <span class="nav-action-text">登出</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="nav-action-link">
                            <span class="nav-action-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </span>
                            <span class="nav-action-text">登入</span>
                        </a>
                        <a href="register.php" class="nav-action-link">
                            <span class="nav-action-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                            </span>
                            <span class="nav-action-text">註冊</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <?php
}

