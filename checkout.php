<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('checkout.php'));
    exit;
}

$user_id = $_SESSION['user_id'];

// 如果從信用卡付款頁返回（有 pending_order_id），恢復購物車
if (isset($_SESSION['pending_order_id']) && isset($_GET['return_from_payment'])) {
    $pending_order_id = $_SESSION['pending_order_id'];
    
    try {
        $pdo->beginTransaction();
        
        // 查詢訂單明細
        $stmt = $pdo->prepare("SELECT product_id, size, quantity, unit_price FROM order_items WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $pending_order_id]);
        $order_items = $stmt->fetchAll();
        
        // 恢復購物車
        foreach ($order_items as $item) {
            $cart_restore_size = ($item['size'] === null || $item['size'] === '')
                ? getCartSizeNoneValue()
                : $item['size'];
            // 檢查購物車中是否已存在相同商品+尺寸
            $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id AND size = :size");
            $stmt->execute([
                ':user_id' => $user_id,
                ':product_id' => $item['product_id'],
                ':size' => $cart_restore_size
            ]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // 更新數量
                $stmt = $pdo->prepare("UPDATE cart SET quantity = :quantity, unit_price = :unit_price, updated_at = NOW() WHERE id = :cart_id");
                $stmt->execute([
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':cart_id' => $existing['id']
                ]);
            } else {
                // 新增到購物車
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, size, quantity, unit_price)
                                       VALUES (:user_id, :product_id, :size, :quantity, :unit_price)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':product_id' => $item['product_id'],
                    ':size' => $cart_restore_size,
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price']
                ]);
            }
        }
        
        // 刪除待付款訂單（因為用戶要返回修改）
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = :order_id AND user_id = :user_id AND status = 'pending_payment'");
        $stmt->execute([':order_id' => $pending_order_id, ':user_id' => $user_id]);
        
        // 清除 session
        unset($_SESSION['pending_order_id']);
        
        $pdo->commit();
        
        // 重新導向到 checkout.php（不帶參數），避免重複處理
        header('Location: checkout.php');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        // 如果恢復失敗，繼續執行，讓後續邏輯處理
    }
}

// 查詢購物車內容
$cart_items = getCartItems($pdo, $user_id);

// 如果購物車為空，導回購物車頁面
if (empty($cart_items)) {
    header('Location: cart.php');
    exit;
}

$coupon_status = getAppliedCouponStatus($pdo, $cart_items);
$coupon_notice = !empty($coupon_status['message']) ? $coupon_status['message'] : '';

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_method = isset($_POST['shipping_method']) ? trim($_POST['shipping_method']) : '';
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $shipping_address = isset($_POST['shipping_address']) ? trim($_POST['shipping_address']) : '';
    $pickup_store = isset($_POST['pickup_store']) ? trim($_POST['pickup_store']) : '';
    
    // 驗證
    $errors = [];
    if ($coupon_notice !== '') {
        $errors[] = $coupon_notice;
    }
    
    if (!in_array($shipping_method, ['pickup', 'home'])) {
        $errors[] = '請選擇送貨方式';
    }
    
    if (!in_array($payment_method, ['credit_card', 'cod'])) {
        $errors[] = '請選擇付款方式';
    }
    
    if ($shipping_method === 'home' && empty($shipping_address)) {
        $errors[] = '請填寫宅配地址';
    }
    
    if ($shipping_method === 'pickup' && empty($pickup_store)) {
        $errors[] = '請填寫取貨門市';
    }
    
    if (empty($errors)) {
        // 儲存到 session
        $_SESSION['checkout_data'] = [
            'shipping_method' => $shipping_method,
            'payment_method' => $payment_method,
            'shipping_address' => $shipping_address,
            'pickup_store' => $pickup_store,
            'coupon_code' => !empty($coupon_status['coupon']) ? $coupon_status['coupon']['coupon_code'] : null
        ];
        
        // 如果選擇信用卡，先建立訂單再導向信用卡繳費頁
        if ($payment_method === 'credit_card') {
            // 計算金額
            $order_summary = calculateOrderSummary($cart_items, $shipping_method, $coupon_status['coupon']);
            $order_amounts = build_orders_amount_fields($order_summary);
            $order_coupon_id = !empty($coupon_status['coupon']['id']) ? (int)$coupon_status['coupon']['id'] : null;
            
            try {
                $pdo->beginTransaction();
                
                // 建立訂單（狀態為 pending_payment）
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, coupon_id, total_amount, discount_amount, final_amount, status, payment_method, shipping_method, shipping_address, pickup_store) 
                                     VALUES (:user_id, :coupon_id, :total_amount, :discount_amount, :final_amount, 'pending_payment', :payment_method, :shipping_method, :shipping_address, :pickup_store)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':coupon_id' => $order_coupon_id,
                    ':total_amount' => $order_amounts['total_amount'],
                    ':discount_amount' => $order_amounts['discount_amount'],
                    ':final_amount' => $order_amounts['final_amount'],
                    ':payment_method' => $payment_method,
                    ':shipping_method' => $shipping_method,
                    ':shipping_address' => $shipping_method === 'home' ? $shipping_address : null,
                    ':pickup_store' => $shipping_method === 'pickup' ? $pickup_store : null
                ]);
                
                $order_id = $pdo->lastInsertId();
                
                // 建立訂單明細
                foreach ($cart_items as $item) {
                    $subtotal = $item['price'] * $item['quantity'];
                    $cs = (string)($item['size'] ?? '');
                    $order_item_size = ($cs === '' || $cs === getCartSizeNoneValue() || $cs === 'N') ? null : $item['size'];
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size, quantity, unit_price, subtotal) 
                                         VALUES (:order_id, :product_id, :size, :quantity, :unit_price, :subtotal)");
                    $stmt->execute([
                        ':order_id' => $order_id,
                        ':product_id' => $item['product_id'],
                        ':size' => $order_item_size,
                        ':quantity' => $item['quantity'],
                        ':unit_price' => $item['price'],
                        ':subtotal' => $subtotal
                    ]);
                }
                
                // 清空購物車
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $user_id]);
                
                $pdo->commit();
                
                // 儲存訂單 ID 到 session
                $_SESSION['pending_order_id'] = $order_id;
                
                // 導向信用卡繳費頁
                header('Location: payment_credit_card.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = '建立訂單時發生錯誤：' . $e->getMessage();
            }
        } else {
            // 其他付款方式，導向訂單確認頁
            header('Location: order_confirm.php');
            exit;
        }
    }
} else {
    // 從 session 讀取已填寫的資料（如果有）
    $shipping_method = isset($_SESSION['checkout_data']['shipping_method']) ? $_SESSION['checkout_data']['shipping_method'] : 'pickup';
    $payment_method = isset($_SESSION['checkout_data']['payment_method']) ? $_SESSION['checkout_data']['payment_method'] : '';
    $shipping_address = isset($_SESSION['checkout_data']['shipping_address']) ? $_SESSION['checkout_data']['shipping_address'] : '';
    $pickup_store = isset($_SESSION['checkout_data']['pickup_store']) ? $_SESSION['checkout_data']['pickup_store'] : '';
    $errors = [];
    if ($coupon_notice !== '') {
        $errors[] = $coupon_notice;
    }
    
    // 如果沒有填寫過地址，自動帶入會員資料中的地址
    if (empty($shipping_address)) {
        try {
            $stmt = $pdo->prepare("SELECT address FROM users WHERE id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $user_data = $stmt->fetch();
            if ($user_data && !empty($user_data['address'])) {
                $shipping_address = $user_data['address'];
            }
        } catch (PDOException $e) {
            // 忽略錯誤，使用空字串
        }
    }
}

// 計算金額
$order_amount = calculateOrderAmount($cart_items, $shipping_method);
$order_summary = calculateOrderSummary($cart_items, $shipping_method, $coupon_status['coupon']);

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
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>填寫資料 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260323-checkout-toggle">
</head>
<body>
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 填寫資料內容 -->
    <div class="checkout-container">
        <div class="container">
            <h1 class="checkout-page-title">選擇送貨及付款方式</h1>

            <!-- 查看商品清單（收合/展開）-->
            <div class="checkout-order-toggle" role="region" aria-label="查看商品清單">
                <button
                    type="button"
                    class="checkout-order-toggle-header"
                    data-checkout-items-toggle="1"
                    data-checkout-items-target="checkoutItemsList"
                    aria-expanded="false"
                >
                    <span class="checkout-order-toggle-header-text">查看商品清單</span>
                    <svg class="checkout-order-toggle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M6 9L12 15L18 9" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div id="checkoutItemsList" class="checkout-order-toggle-body" aria-hidden="true">
                    <div class="checkout-summary-items-inner">
                        <?php foreach ($cart_items as $ci): 
                            $ci_subtotal = (float)$ci['price'] * (int)$ci['quantity'];
                            $ci_img_src = resolve_product_card_image_src($ci['primary_image'] ?? null);
                        ?>
                            <div class="checkout-summary-items-row">
                                <div class="checkout-summary-items-media">
                                    <img
                                        src="<?php echo htmlspecialchars($ci_img_src, ENT_QUOTES); ?>"
                                        alt="<?php echo htmlspecialchars($ci['product_name']); ?>"
                                    >
                                </div>

                                <div class="checkout-summary-items-left">
                                    <div class="checkout-summary-items-name"><?php echo htmlspecialchars($ci['product_name']); ?></div>
                                    <div class="checkout-summary-items-meta">
                                        <?php echo htmlspecialchars($ci['category_name']); ?>
                                        &nbsp;&nbsp; 尺寸：<?php echo htmlspecialchars(formatCartSizeForDisplay($ci['size'] ?? '')); ?>
                                    </div>
                                    <div class="checkout-summary-items-unit-price">
                                        單價 NT$ <?php echo number_format((float)$ci['price'], 0); ?>
                                    </div>
                                </div>

                                <div class="checkout-summary-items-right">
                                    <div class="checkout-summary-items-amount">
                                        小計 NT$ <?php echo number_format($ci_subtotal, 0); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="checkout-form-wrapper">
                <form method="POST" id="checkoutForm" class="checkout-form">
                    <div class="checkout-page-layout">
                        <!-- 左欄：送貨方式 / 付款方式 -->
                        <div class="checkout-main">
                            <div class="form-section">
                                <h2 class="form-section-title">送貨方式</h2>
                                <div class="shipping-methods">
                                    <label class="shipping-method-option">
                                        <input type="radio" name="shipping_method" value="pickup" 
                                               <?php echo $shipping_method === 'pickup' ? 'checked' : ''; ?>
                                               onchange="updateShippingMethod()">
                                        <span class="method-label">超商取貨</span>
                                        <span class="method-fee"><?php echo $order_amount['shipping'] == 0 ? '免運費' : '運費 60 元'; ?></span>
                                    </label>
                                    <label class="shipping-method-option">
                                        <input type="radio" name="shipping_method" value="home"
                                               <?php echo $shipping_method === 'home' ? 'checked' : ''; ?>
                                               onchange="updateShippingMethod()">
                                        <span class="method-label">宅配到府</span>
                                        <span class="method-fee"><?php echo $order_amount['shipping'] == 0 ? '免運費' : '運費 60 元'; ?></span>
                                    </label>
                                </div>
                                
                                <!-- 超商取貨欄位 -->
                                <div id="pickupFields" class="shipping-fields" style="display: <?php echo $shipping_method === 'pickup' ? 'block' : 'none'; ?>;">
                                    <div class="form-group">
                                        <label class="form-label">超商門市代碼 <span class="required">*</span></label>
                                        <div class="form-input-group">
                                            <input type="text" name="pickup_store" class="form-input" 
                                                   value="<?php echo htmlspecialchars($pickup_store); ?>"
                                                   placeholder="請輸入門市代碼">
                                            <a href="https://emap.pcsc.com.tw/" 
                                               target="_blank" 
                                               class="btn-store-lookup">超商代碼查詢</a>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 宅配地址欄位 -->
                                <div id="homeFields" class="shipping-fields" style="display: <?php echo $shipping_method === 'home' ? 'block' : 'none'; ?>;">
                                    <div class="form-group">
                                        <label class="form-label">送貨地址 <span class="required">*</span></label>
                                        <textarea name="shipping_address" class="form-input" rows="3" 
                                                  placeholder="請輸入完整地址"><?php echo htmlspecialchars($shipping_address); ?></textarea>
                                        <small class="form-hint">地址已自動帶入您的會員資料，您仍可手動修改</small>
                                    </div>
                                </div>
                            </div>

                            <!-- 付款方式 -->
                            <div class="form-section">
                                <h2 class="form-section-title">付款方式</h2>
                                <div class="payment-methods">
                                    <label class="payment-method-option">
                                        <input type="radio" name="payment_method" value="credit_card"
                                               <?php echo $payment_method === 'credit_card' ? 'checked' : ''; ?>>
                                        <span class="method-label">信用卡</span>
                                    </label>
                                    <label class="payment-method-option">
                                        <input type="radio" name="payment_method" value="cod"
                                               <?php echo $payment_method === 'cod' ? 'checked' : ''; ?>>
                                        <span class="method-label">貨到付款</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 右欄：付款明細 / 訂單摘要 + 按鈕 -->
                        <aside class="checkout-sidebar">
                            <div class="checkout-sidebar-stack">
                                <div class="checkout-summary-panel">
                                    <h3 class="checkout-summary-title">小計明細</h3>
                                    <div class="order-summary">
                                        <div class="summary-row">
                                            <span class="summary-label">商品小計：</span>
                                            <span class="summary-value">NT$ <?php echo number_format($order_summary['subtotal'], 0); ?></span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">運費：</span>
                                            <span class="summary-value" id="shippingFee">
                                                <?php if ($order_summary['shipping'] == 0): ?>
                                                    免運費
                                                <?php else: ?>
                                                    運費 60 元
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">優惠券折扣：</span>
                                            <span class="summary-value" id="couponDiscount">- NT$ <?php echo number_format($order_summary['discount'], 0); ?></span>
                                        </div>
                                        <div class="summary-row summary-total">
                                            <span class="summary-label">最終總價：</span>
                                            <span class="summary-value" id="totalAmount">NT$ <?php echo number_format($order_summary['final_total'], 0); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions checkout-submit-actions">
                                    <a href="cart.php" class="btn-secondary">返回購物車</a>
                                    <button type="submit" class="btn-primary">訂單確認</button>
                                </div>
                            </div>
                        </aside>
                    </div>
                </form>
            </div>
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
        // 更新送貨方式
        function updateShippingMethod() {
            const form = document.getElementById('checkoutForm');
            const shippingMethod = form.querySelector('input[name="shipping_method"]:checked').value;
            
            // 顯示/隱藏對應欄位
            document.getElementById('pickupFields').style.display = shippingMethod === 'pickup' ? 'block' : 'none';
            document.getElementById('homeFields').style.display = shippingMethod === 'home' ? 'block' : 'none';
            
            // 重新計算運費（使用 AJAX 或直接計算）
            const subtotal = <?php echo (float)$order_summary['subtotal']; ?>;
            const couponDiscount = <?php echo (float)$order_summary['discount']; ?>;
            let shipping = subtotal >= 3000 ? 0 : 60;
            
            const total = Math.max(0, subtotal + shipping - couponDiscount);
            
            // 更新顯示
            document.getElementById('shippingFee').textContent = shipping === 0 ? '免運費' : '運費 60 元';
            document.getElementById('totalAmount').textContent = 'NT$ ' + total.toLocaleString();
            document.getElementById('couponDiscount').textContent = '- NT$ ' + couponDiscount.toLocaleString();
        }

        // 摘要：查看商品清單（收合/展開）
        (function () {
            const toggles = document.querySelectorAll('[data-checkout-items-toggle="1"]');
            toggles.forEach((btn) => {
                btn.addEventListener('click', function () {
                    const targetId = btn.getAttribute('data-checkout-items-target');
                    const target = document.getElementById(targetId);
                    if (!target) return;

                    const isOpen = target.classList.toggle('is-open');
                    btn.classList.toggle('is-open', isOpen);
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    target.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                });
            });
        })();
    </script>
</body>
</html>
