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

// 查詢「周邊與零件」的分類 ID
$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與零件' LIMIT 1");
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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelmetVRse - 首頁</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- 頂部公告橫幅（自動輪播） -->
    <div class="announcement-bar">
        <div class="announcement-content" id="announcementText">
            商品庫存變動快速，請多利用客服功能
        </div>
    </div>

    <!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="hero-content">
            <h1 class="hero-title">HelmetVRse</h1>
            <p class="hero-subtitle">結合 VR 展場體驗，讓你看得更清楚、買得更安心</p>
            <a href="products.php" class="hero-btn">VR展場</a>
        </div>
    </section>

    <!-- 分類區 -->
    <section class="categories-section">
        <div class="container">
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
            <div class="products-grid">
                <?php if (empty($hotProducts)): ?>
                    <div class="empty-message">目前尚未有熱門商品</div>
                <?php else: ?>
                    <?php foreach ($hotProducts as $product): ?>
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
                                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#8B96A9" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
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
                                <p class="product-price">NT$ <?php echo number_format($product['price']); ?></p>
                                <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="product-btn">查看詳情</a>
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
                        <li><a href="guide.php">購物須知</a></li>
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
        // 公告條自動輪播功能
        (function() {
            try {
                const announcementText = document.getElementById('announcementText');
                if (!announcementText) return;

                const messages = [
                    '商品庫存變動快速，請多利用客服功能',
                    '超取滿199、宅配滿490 享免運優惠'
                ];

                let currentIndex = 0;

                function rotateAnnouncement() {
                    currentIndex = (currentIndex + 1) % messages.length;
                    announcementText.textContent = messages[currentIndex];
                }

                // 每 4 秒切換一次
                setInterval(rotateAnnouncement, 4000);
            } catch (error) {
                console.error('公告輪播功能錯誤:', error);
            }
        })();

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
    </script>
</body>
</html>
