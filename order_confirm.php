<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';

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

if ($payment_method !== 'cod') {
    header('Location: checkout.php');
    exit;
}

// 查詢購物車內容
$cart_items = getCartItems($pdo, $user_id);

if (empty($cart_items)) {
    header('Location: checkout.php');
    exit;
}

// 計算金額
$order_amount = calculateOrderAmount($cart_items, $shipping_method);
$coupon_status = getAppliedCouponStatus($pdo, $cart_items);
$order_summary = calculateOrderSummary($cart_items, $shipping_method, $coupon_status['coupon']);

// 處理訂單建立（貨到付款）
$order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    $cod_order_committed = false;
    try {
        $pdo->beginTransaction();
        
        $order_status = 'pending';
        if ($payment_method === 'cod') {
            $order_status = 'pending';
        }
        
        $order_amounts = build_orders_amount_fields($order_summary);
        $order_coupon_id = !empty($coupon_status['coupon']['id']) ? (int)$coupon_status['coupon']['id'] : null;

        $stmt = $pdo->prepare("INSERT INTO orders (user_id, coupon_id, total_amount, discount_amount, final_amount, status, payment_method, shipping_method, shipping_address, pickup_store) 
                               VALUES (:user_id, :coupon_id, :total_amount, :discount_amount, :final_amount, :status, :payment_method, :shipping_method, :shipping_address, :pickup_store)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':coupon_id' => $order_coupon_id,
            ':total_amount' => $order_amounts['total_amount'],
            ':discount_amount' => $order_amounts['discount_amount'],
            ':final_amount' => $order_amounts['final_amount'],
            ':status' => $order_status,
            ':payment_method' => $payment_method,
            ':shipping_method' => $shipping_method,
            ':shipping_address' => $shipping_method === 'home' ? $shipping_address : null,
            ':pickup_store' => $shipping_method === 'pickup' ? $pickup_store : null
        ]);
        
        $order_id = (int)$pdo->lastInsertId();

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

        if ($order_id <= 0) {
            $pdo->rollBack();
            $error_message = '建立訂單失敗，請稍後再試。';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
            $stmt->execute([$order_id]);
            $items_written = (int)$stmt->fetchColumn();
            if ($items_written !== count($cart_items)) {
                $pdo->rollBack();
                $error_message = '訂單明細寫入異常，請稍後再試。';
            } else {
                $pdo->commit();
                $cod_order_committed = true;
            }
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = '建立訂單時發生錯誤：' . $e->getMessage();
    }

    if ($cod_order_committed) {
        include __DIR__ . '/send_order.php';
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        unset($_SESSION['checkout_data']);
        if (function_exists('clearAppliedCoupon')) {
            clearAppliedCoupon();
        }
        unset($_SESSION['coupon_code_prefill']);
        $success_oid = (int)$order_id;
        echo '<!DOCTYPE html><html lang="zh-TW"><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=order_success.php?order_id=' . $success_oid . '"><script>window.location.href=\'order_success.php?order_id=' . $success_oid . '\';</script></head><body></body></html>';
        exit;
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

require_once 'includes/navbar.php';
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
            <h1 class="checkout-page-title">訂單確認</h1>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            <?php if (!isset($error_message)): ?>
                <div class="order-confirm-wrapper">
                    <!-- 商品列表（沿用 checkout.php 同款結構） -->
                    <div class="checkout-order-toggle order-section" role="region" aria-label="查看商品清單">
                        <button
                            type="button"
                            class="checkout-order-toggle-header"
                            data-checkout-items-toggle="1"
                            data-checkout-items-target="orderConfirmItemsList"
                            aria-expanded="false"
                        >
                            <span class="checkout-order-toggle-header-text">查看商品清單</span>
                            <svg class="checkout-order-toggle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M6 9L12 15L18 9" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <div id="orderConfirmItemsList" class="checkout-order-toggle-body" aria-hidden="true">
                            <div class="checkout-summary-items-inner">
                                <?php foreach ($cart_items as $item):
                                    $subtotal = (float)$item['price'] * (int)$item['quantity'];
                                    $item_img_src = resolve_product_card_image_src($item['primary_image'] ?? null);
                                ?>
                                    <div class="checkout-summary-items-row">
                                        <div class="checkout-summary-items-media">
                                            <img
                                                src="<?php echo htmlspecialchars($item_img_src, ENT_QUOTES); ?>"
                                                alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                            >
                                        </div>

                                        <div class="checkout-summary-items-left">
                                            <div class="checkout-summary-items-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                            <div class="checkout-summary-items-meta">
                                                <?php echo htmlspecialchars($item['category_name']); ?>
                                                &nbsp;&nbsp; 尺寸：<?php echo htmlspecialchars(formatCartSizeForDisplay($item['size'] ?? '')); ?>
                                                &nbsp;&nbsp; 數量：<?php echo (int)$item['quantity']; ?>
                                            </div>
                                            <div class="checkout-summary-items-unit-price">
                                                單價 NT$ <?php echo number_format((float)$item['price'], 0); ?>
                                            </div>
                                        </div>

                                        <div class="checkout-summary-items-right">
                                            <div class="checkout-summary-items-amount">
                                                小計 NT$ <?php echo number_format($subtotal, 0); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
                    <form method="POST" action="order_confirm.php" class="order-confirm-form">
                        <input type="hidden" name="confirm_order" value="1">
                        <div class="form-actions">
                            <a href="checkout.php" class="btn-secondary">返回修改</a>
                            <button type="submit" class="btn-primary">送出訂單</button>
                        </div>
                    </form>
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
                        <li><a href="faq.php">常見問題 FAQ</a></li>
                        <li><a href="return.php">退貨政策</a></li>
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

