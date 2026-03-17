<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/checkout_steps.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('order_confirm.php'));
    exit;
}

$user_id = $_SESSION['user_id'];

// 檢查是否有結帳資料
if (!isset($_SESSION['checkout_data'])) {
    header('Location: checkout.php');
    exit;
}

$checkout_data = $_SESSION['checkout_data'];
$shipping_method = $checkout_data['shipping_method'];
$payment_method = $checkout_data['payment_method'];
$shipping_address = $checkout_data['shipping_address'] ?? '';
$pickup_store = $checkout_data['pickup_store'] ?? '';

// 查詢購物車內容
$cart_items = getCartItems($pdo, $user_id);

// 如果購物車為空，導回購物車頁面
if (empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

// 計算金額
$order_amount = calculateOrderAmount($cart_items, $shipping_method);
$coupon_status = getAppliedCouponStatus($pdo, $cart_items);
$order_summary = calculateOrderSummary($cart_items, $shipping_method, $coupon_status['coupon']);

// 處理訂單建立（非信用卡付款）
$order_created = false;
$order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    // 如果是信用卡付款，不應該到這裡
    if ($payment_method === 'credit_card') {
        header('Location: checkout.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // 根據付款方式決定訂單狀態
        $order_status = 'pending'; // 預設待處理
        if ($payment_method === 'cod') {
            $order_status = 'pending'; // 貨到付款，待出貨
        }
        
        // 建立訂單
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, shipping_method, shipping_address, pickup_store) 
                               VALUES (:user_id, :total_amount, :status, :payment_method, :shipping_method, :shipping_address, :pickup_store)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':total_amount' => $order_summary['final_total'],
            ':status' => $order_status,
            ':payment_method' => $payment_method,
            ':shipping_method' => $shipping_method,
            ':shipping_address' => $shipping_method === 'home' ? $shipping_address : null,
            ':pickup_store' => $shipping_method === 'pickup' ? $pickup_store : null
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // 建立訂單明細
        foreach ($cart_items as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size, quantity, unit_price, subtotal) 
                                 VALUES (:order_id, :product_id, :size, :quantity, :unit_price, :subtotal)");
            $stmt->execute([
                ':order_id' => $order_id,
                ':product_id' => $item['product_id'],
                ':size' => $item['size'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['price'],
                ':subtotal' => $subtotal
            ]);
        }
        
        // 清空購物車
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        
        // 清除結帳資料
        unset($_SESSION['checkout_data']);
        clearAppliedCoupon();
        
        $pdo->commit();
        $order_created = true;
        // --- 在這裡觸發發信腳本 ---
        // 這樣 send_order.php 就可以直接使用這頁已經算好的 $order_id, $cart_items 等變數
        include 'send_order.php';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = '建立訂單時發生錯誤：' . $e->getMessage();
    }
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

$is_logged_in = isset($_SESSION['user_id']);

// 付款方式顯示名稱
$payment_method_names = [
    'credit_card' => '信用卡',
    'cod' => '貨到付款'
];

// 送貨方式顯示名稱
$shipping_method_names = [
    'pickup' => '超商取貨',
    'home' => '宅配到府'
];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訂單確認 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 訂單確認內容 -->
    <div class="checkout-container">
        <div class="container">
            <?php renderCheckoutSteps(3); ?>
            <h1 class="checkout-page-title">訂單建立成功</h1>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php elseif ($order_created): ?>
                <div class="order-success">
                    <h2>訂單建立成功！</h2>
                    <p>訂單編號：<?php echo htmlspecialchars($order_id); ?></p>
                    <p>感謝您的購買，我們將盡快為您處理訂單。</p>
                    <div class="order-success-actions">
                        <a href="products.php" class="btn-primary">繼續購物</a>
                        <a href="profile.php" class="btn-secondary">查看訂單</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- 訂單內容 -->
                <div class="order-confirm-wrapper">
                    <!-- 商品列表 -->
                    <div class="order-section">
                        <h2 class="section-title">訂單商品</h2>
                        <div class="cart-table-wrapper">
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th class="col-product">商品資料</th>
                                        <th class="col-price">單價</th>
                                        <th class="col-quantity">數量</th>
                                        <th class="col-subtotal">小計</th>
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
                                                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#9A9A9A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
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
                                                <?php echo htmlspecialchars($item['quantity']); ?>
                                            </td>
                                            <td class="col-subtotal">
                                                NT$ <?php echo number_format($subtotal, 0); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 訂單摘要 -->
                    <div class="order-section">
                        <h2 class="section-title">訂單摘要</h2>
                        <div class="order-summary">
                            <div class="summary-row">
                                <span class="summary-label">商品小計：</span>
                                <span class="summary-value">NT$ <?php echo number_format($order_summary['subtotal'], 0); ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">運費：</span>
                                <span class="summary-value">
                                    <?php if ($order_summary['shipping'] == 0): ?>
                                        NT$ 0（免運）
                                    <?php else: ?>
                                        NT$ <?php echo number_format($order_summary['shipping'], 0); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">優惠券折扣：</span>
                                <span class="summary-value">- NT$ <?php echo number_format($order_summary['discount'], 0); ?></span>
                            </div>
                            <div class="summary-row summary-total">
                                <span class="summary-label">最終總價：</span>
                                <span class="summary-value">NT$ <?php echo number_format($order_summary['final_total'], 0); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 送貨資訊 -->
                    <div class="order-section">
                        <h2 class="section-title">送貨資訊</h2>
                        <div class="order-info">
                            <p><strong>送貨方式：</strong><?php echo htmlspecialchars($shipping_method_names[$shipping_method]); ?></p>
                            <?php if ($shipping_method === 'home'): ?>
                                <p><strong>送貨地址：</strong><?php echo nl2br(htmlspecialchars($shipping_address)); ?></p>
                            <?php else: ?>
                                <p><strong>取貨門市：</strong><?php echo htmlspecialchars($pickup_store); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 付款資訊 -->
                    <div class="order-section">
                        <h2 class="section-title">付款資訊</h2>
                        <div class="order-info">
                            <p><strong>付款方式：</strong><?php echo htmlspecialchars($payment_method_names[$payment_method]); ?></p>
                        </div>
                    </div>

                    <!-- 確認按鈕 -->
                    <?php if ($payment_method !== 'credit_card'): ?>
                        <form method="POST" class="order-confirm-form">
                            <input type="hidden" name="confirm_order" value="1">
                            <div class="form-actions">
                                <a href="checkout.php" class="btn-secondary">返回修改</a>
                                <button type="submit" class="btn-primary">送出訂單</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="form-actions">
                            <a href="checkout.php" class="btn-secondary">返回修改</a>
                            <p class="payment-note">您選擇信用卡付款，請在下一步完成付款流程。</p>
                        </div>
                    <?php endif; ?>
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
</body>
</html>

