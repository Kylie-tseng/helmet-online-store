<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

// 取得商品 ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 查詢商品資料
$product = null;
$error_message = '';

if ($product_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name
                               FROM products p
                               INNER JOIN categories c ON p.category_id = c.id
                               WHERE p.id = :product_id AND p.status = 'active'");
        $stmt->execute([':product_id' => $product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $error_message = '找不到此商品或已下架';
        }
    } catch (PDOException $e) {
        $error_message = '查詢商品時發生錯誤：' . $e->getMessage();
    }
} else {
    $error_message = '找不到此商品或已下架';
}

// 檢查是否已登入
$is_logged_in = isset($_SESSION['user_id']);
$is_favorited = false;
if ($is_logged_in && $product_id > 0) {
    $is_favorited = isProductFavorited($pdo, (int)$_SESSION['user_id'], $product_id);
}

// 查詢所有分類（用於導覽列）
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

// 查詢商品尺寸庫存（包含庫存為0的尺寸）
$product_sizes = [];
if ($product) {
    try {
        $stmt = $pdo->prepare("SELECT size, stock FROM product_sizes WHERE product_id = :product_id ORDER BY FIELD(size, 'S', 'M', 'L', 'XL')");
        $stmt->execute([':product_id' => $product_id]);
        $product_sizes = $stmt->fetchAll();
    } catch (PDOException $e) {
        // 如果查詢失敗，保持為空陣列
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['name']) . ' - ' : ''; ?>商品詳情 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 商品詳情內容 -->
    <div class="product-detail-container">
        <div class="container">
            <?php if ($error_message): ?>
                <div class="product-detail-error">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="products.php" class="btn-primary">返回商品總覽</a>
                </div>
            <?php elseif ($product): ?>
                <div class="product-detail-wrapper">
                    <!-- 左側圖片區 -->
                    <div class="product-detail-image">
                        <?php 
                        // 檢查 image_url 是否為 NULL 或空字串
                        $has_image = !empty($product['image_url']) && trim($product['image_url']) !== '';
                        if ($has_image): 
                        ?>
                            <img src="<?php echo htmlspecialchars($product['image_url'], ENT_QUOTES); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="product-detail-image-placeholder">
                                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="#9A9A9A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <span>無圖片</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 右側資訊區 -->
                    <div class="product-detail-info">
                        <div class="product-detail-header">
                            <span class="product-detail-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            <h1 class="product-detail-name"><?php echo htmlspecialchars($product['name']); ?></h1>
                            <div class="product-detail-price">
                                NT$ <?php echo number_format($product['price'], 0); ?>
                            </div>
                            <form action="api/toggle_favorite.php" method="POST" class="product-favorite-form">
                                <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars('product_detail.php?id=' . (int)$product['id']); ?>">
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

                        <?php if (!empty($product['description'])): ?>
                            <div class="product-detail-description">
                                <h3>商品描述</h3>
                                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- 互動區塊 -->
                        <div class="product-detail-actions">
                            <?php if ($is_logged_in): ?>
                                <?php if (!empty($product_sizes)): ?>
                                    <form class="product-detail-form" id="addToCartForm">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                        
                                        <div class="form-group">
                                            <label class="form-label">尺寸</label>
                                            <select name="size" class="form-input" id="sizeSelect" required>
                                                <?php foreach ($product_sizes as $ps): 
                                                    $is_out_of_stock = $ps['stock'] <= 0;
                                                ?>
                                                    <option value="<?php echo htmlspecialchars($ps['size']); ?>" 
                                                            data-stock="<?php echo htmlspecialchars($ps['stock']); ?>"
                                                            <?php echo $is_out_of_stock ? 'disabled' : ''; ?>>
                                                        <?php 
                                                        if ($is_out_of_stock) {
                                                            echo htmlspecialchars($ps['size']) . '（已售完）';
                                                        } else {
                                                            echo htmlspecialchars($ps['size']) . '（剩餘 ' . htmlspecialchars($ps['stock']) . ' 件）';
                                                        }
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label">數量</label>
                                            <input type="number" 
                                                   name="quantity" 
                                                   class="form-input" 
                                                   id="quantityInput"
                                                   min="1" 
                                                   max="<?php echo htmlspecialchars($product_sizes[0]['stock']); ?>" 
                                                   value="1" 
                                                   required>
                                        </div>

                                        <button type="submit" class="btn-primary product-detail-add-cart">
                                            加入購物車
                                        </button>
                                    </form>
                                    
                                    <!-- Toast 訊息 -->
                                    <div id="cartToast" class="cart-toast" style="display: none;">
                                        <span id="cartToastMessage"></span>
                                    </div>
                                    
                                    <script>
                                        // 當尺寸改變時，更新數量的最大值
                                        document.getElementById('sizeSelect').addEventListener('change', function() {
                                            const selectedOption = this.options[this.selectedIndex];
                                            const maxStock = parseInt(selectedOption.getAttribute('data-stock'));
                                            document.getElementById('quantityInput').max = maxStock;
                                            if (parseInt(document.getElementById('quantityInput').value) > maxStock) {
                                                document.getElementById('quantityInput').value = maxStock;
                                            }
                                        });
                                        
                                        // AJAX 加入購物車
                                        document.getElementById('addToCartForm').addEventListener('submit', function(e) {
                                            e.preventDefault();
                                            
                                            const form = this;
                                            const formData = new FormData(form);
                                            const submitBtn = form.querySelector('button[type="submit"]');
                                            const originalText = submitBtn.textContent;
                                            
                                            // 禁用按鈕
                                            submitBtn.disabled = true;
                                            submitBtn.textContent = '處理中...';
                                            
                                            fetch('api/add_to_cart.php', {
                                                method: 'POST',
                                                body: formData
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    // 即時同步 navbar 購物車 badge
                                                    if (typeof window.updateNavbarBadges === 'function') {
                                                        window.updateNavbarBadges({ cart_count: data.cart_count });
                                                    } else {
                                                        window.dispatchEvent(new CustomEvent('cart:updated', { detail: { cart_count: data.cart_count } }));
                                                    }
                                                    
                                                    // 顯示成功訊息
                                                    showToast(data.message, 'success');
                                                    
                                                    // 重置表單數量
                                                    document.getElementById('quantityInput').value = 1;
                                                } else {
                                                    if (data.redirect) {
                                                        window.location.href = data.redirect;
                                                    } else {
                                                        showToast(data.message, 'error');
                                                    }
                                                }
                                            })
                                            .catch(error => {
                                                showToast('加入購物車時發生錯誤', 'error');
                                                console.error('Error:', error);
                                            })
                                            .finally(() => {
                                                submitBtn.disabled = false;
                                                submitBtn.textContent = originalText;
                                            });
                                        });
                                        
                                        // 顯示 Toast 訊息
                                        function showToast(message, type) {
                                            const toast = document.getElementById('cartToast');
                                            const toastMessage = document.getElementById('cartToastMessage');
                                            
                                            toastMessage.textContent = message;
                                            toast.className = 'cart-toast ' + type;
                                            toast.style.display = 'block';
                                            
                                            setTimeout(() => {
                                                toast.style.display = 'none';
                                            }, 3000);
                                        }
                                    </script>
                                <?php else: ?>
                                    <div class="product-detail-no-size">
                                        <p>此商品尚未設定尺寸庫存，無法加入購物車。</p>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="product-detail-login-prompt">
                                    <p>請先登入才能加入購物車</p>
                                    <a href="login.php?redirect=<?php echo urlencode('product_detail.php?id=' . $product_id); ?>" class="btn-primary">
                                        前往登入
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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

        // 漢堡選單切換
        (function() {
            const navToggle = document.getElementById('navToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (navToggle && navMenu) {
                navToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                    navToggle.classList.toggle('active');
                });
            }
        })();
    </script>
</body>
</html>

