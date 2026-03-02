<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/checkout_steps.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('cart.php'));
    exit;
}

$user_id = $_SESSION['user_id'];

// 取得訊息（從 session）
$cart_message = '';
$cart_message_type = '';
if (isset($_SESSION['cart_message'])) {
    $cart_message = $_SESSION['cart_message'];
    $cart_message_type = $_SESSION['cart_message_type'];
    unset($_SESSION['cart_message']);
    unset($_SESSION['cart_message_type']);
}

// 處理更新數量
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_quantity') {
        $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

        if ($cart_id > 0 && $quantity >= 0) {
            try {
                // 取得購物車項目資訊
                $stmt = $pdo->prepare("SELECT c.product_id, c.size FROM cart c WHERE c.id = :cart_id AND c.user_id = :user_id");
                $stmt->execute([':cart_id' => $cart_id, ':user_id' => $user_id]);
                $cart_item = $stmt->fetch();

                if ($cart_item) {
                    // 檢查庫存
                    $stmt = $pdo->prepare("SELECT stock FROM product_sizes WHERE product_id = :product_id AND size = :size");
                    $stmt->execute([':product_id' => $cart_item['product_id'], ':size' => $cart_item['size']]);
                    $size_stock = $stmt->fetch();

                    if ($size_stock && $quantity > 0) {
                        if ($quantity > $size_stock['stock']) {
                            $quantity = $size_stock['stock'];
                            $_SESSION['cart_message'] = '數量超過庫存，已調整為最大可購買數量：' . $quantity;
                            $_SESSION['cart_message_type'] = 'warning';
                        }
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = :quantity WHERE id = :cart_id AND user_id = :user_id");
                        $stmt->execute([':quantity' => $quantity, ':cart_id' => $cart_id, ':user_id' => $user_id]);
                    } elseif ($quantity == 0) {
                        // 刪除項目
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id");
                        $stmt->execute([':cart_id' => $cart_id]);
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['cart_message'] = '更新購物車時發生錯誤：' . $e->getMessage();
                $_SESSION['cart_message_type'] = 'error';
            }
        }
    } elseif ($_POST['action'] === 'delete_item') {
        $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;

        if ($cart_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id");
                $stmt->execute([':cart_id' => $cart_id, ':user_id' => $user_id]);
            } catch (PDOException $e) {
                $_SESSION['cart_message'] = '刪除項目時發生錯誤：' . $e->getMessage();
                $_SESSION['cart_message_type'] = 'error';
            }
        }
    }

    // 重新導向以清除 POST 資料
    header('Location: cart.php');
    exit;
}

// 查詢購物車內容
$cart_items = getCartItems($pdo, $user_id);

// 計算金額（使用共用函數）
$order_amount = calculateOrderAmount($cart_items, 'pickup'); // 預設超商取貨

// 查詢所有分類（用於導覽列）
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
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

$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購物車 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- 頂部公告橫幅 -->
    <div class="announcement-bar">
        <div class="announcement-content" id="announcementText">
            商品庫存變動快速，請多利用客服功能
        </div>
    </div>

    <!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 購物車內容 -->
    <div class="cart-container">
        <div class="container">
            <?php renderCheckoutSteps(1); ?>
            <h1 class="cart-page-title">購物車</h1>

            <!-- 訊息顯示 -->
            <?php if (!empty($cart_message)): ?>
                <div class="cart-message <?php echo htmlspecialchars($cart_message_type); ?>">
                    <?php echo htmlspecialchars($cart_message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <!-- 空購物車 -->
                <div class="cart-empty">
                    <div class="cart-empty-icon">
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#8B96A9" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2h12"></path>
                            <path d="M3 6h18l-2 14H5L3 6z"></path>
                        </svg>
                    </div>
                    <h2>購物車目前是空的</h2>
                    <p>快去選購您喜歡的商品吧！</p>
                    <a href="products.php" class="btn-primary">前往商品總覽</a>
                </div>
            <?php else: ?>
                <!-- 購物車項目表格 -->
                <div class="cart-table-wrapper">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th class="col-product">商品資料</th>
                                <th class="col-price">單價</th>
                                <th class="col-quantity">數量</th>
                                <th class="col-subtotal">小計</th>
                                <th class="col-action">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): 
                                $subtotal = $item['price'] * $item['quantity'];
                                $has_image = !empty($item['image_url']) && trim($item['image_url']) !== '';
                            ?>
                                <tr class="cart-table-row">
                                    <td class="col-product">
                                        <div class="cart-product-info">
                                            <div class="cart-item-image">
                                                <?php if ($has_image): ?>
                                                    <img src="<?php echo htmlspecialchars($item['image_url'], ENT_QUOTES); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                <?php else: ?>
                                                    <div class="product-image-placeholder">
                                                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#8B96A9" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                            <polyline points="21 15 16 10 5 21"></polyline>
                                                        </svg>
                                                        <span>無圖片</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="cart-item-info">
                                                <h3 class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                                <p class="cart-item-meta">
                                                    <span class="cart-item-category"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                    <span class="cart-item-size">尺寸：<?php echo htmlspecialchars($item['size']); ?></span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="col-price">
                                        NT$ <?php echo number_format($item['price'], 0); ?>
                                    </td>
                                    <td class="col-quantity">
                                        <div class="quantity-controls">
                                            <button type="button" class="quantity-btn minus" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, -1)">-</button>
                                            <input type="number" 
                                                   class="quantity-input" 
                                                   id="quantity_<?php echo $item['cart_id']; ?>"
                                                   value="<?php echo htmlspecialchars($item['quantity']); ?>" 
                                                   min="1"
                                                   onchange="submitQuantity(<?php echo $item['cart_id']; ?>, this.value)">
                                            <button type="button" class="quantity-btn plus" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, 1)">+</button>
                                        </div>
                                    </td>
                                    <td class="col-subtotal">
                                        NT$ <?php echo number_format($subtotal, 0); ?>
                                    </td>
                                    <td class="col-action">
                                        <form method="POST" onsubmit="return confirm('確定要刪除此商品嗎？');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="cart_id" value="<?php echo htmlspecialchars($item['cart_id']); ?>">
                                            <button type="submit" class="btn-delete">刪除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 購物車總計 -->
                <div class="cart-summary">
                    <div class="cart-summary-content">
                        <div class="summary-row">
                            <span class="summary-label">商品小計：</span>
                            <span class="summary-value">NT$ <?php echo number_format($order_amount['subtotal'], 0); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">運費：</span>
                            <span class="summary-value">
                                <?php if ($order_amount['shipping'] == 0): ?>
                                    NT$ 0（免運）
                                <?php else: ?>
                                    NT$ <?php echo number_format($order_amount['shipping'], 0); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="summary-row summary-total">
                            <span class="summary-label">訂單總金額：</span>
                            <span class="summary-value">NT$ <?php echo number_format($order_amount['total'], 0); ?></span>
                        </div>
                    </div>
                    <div class="cart-actions">
                        <a href="products.php" class="btn-secondary">繼續購物</a>
                        <a href="checkout.php" class="btn-primary">下一步：選擇送貨及付款方式</a>
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
        // 更新數量
        function updateQuantity(cartId, change) {
            const input = document.getElementById('quantity_' + cartId);
            if (!input) return;
            let newQuantity = parseInt(input.value) + change;
            if (newQuantity < 1) newQuantity = 1;
            input.value = newQuantity;
            submitQuantity(cartId, newQuantity);
        }

        function submitQuantity(cartId, quantity) {
            if (quantity < 1) {
                if (confirm('確定要刪除此商品嗎？')) {
                    quantity = 0;
                } else {
                    const input = document.getElementById('quantity_' + cartId);
                    if (input) input.value = 1;
                    return;
                }
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_quantity">
                <input type="hidden" name="cart_id" value="${cartId}">
                <input type="hidden" name="quantity" value="${quantity}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // 搜尋功能
        (function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
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
            }
        })();
    </script>
</body>
</html>

