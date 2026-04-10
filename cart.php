<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/product_query_helpers.php';

// 檢查是否已登入（未登入時保留 cart.php?coupon= 以便登入後繼續帶入）
if (!isset($_SESSION['user_id'])) {
    $redirect_cart = 'cart.php';
    if (!empty($_GET['coupon']) && trim((string)$_GET['coupon']) !== '') {
        $redirect_cart = 'cart.php?coupon=' . rawurlencode(trim((string)$_GET['coupon']));
    }
    header('Location: login.php?redirect=' . rawurlencode($redirect_cart) . '&notice=cart');
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

// 優惠券相關改以浮動 toast 顯示（與商品詳情加入購物車通知同風格）
$cart_coupon_toast_flash = null;
if (!empty($_SESSION['cart_coupon_toast']) && is_array($_SESSION['cart_coupon_toast'])) {
    $rawToast = $_SESSION['cart_coupon_toast'];
    unset($_SESSION['cart_coupon_toast']);
    $toastMsg = trim((string)($rawToast['message'] ?? ''));
    if ($toastMsg !== '') {
        $toastType = strtolower((string)($rawToast['type'] ?? 'success'));
        if (!in_array($toastType, ['success', 'error', 'warning'], true)) {
            $toastType = 'success';
        }
        $cart_coupon_toast_flash = ['message' => $toastMsg, 'type' => $toastType];
    }
}

// URL 帶入優惠券：cart.php?coupon=CODE（驗證後自動套用，或僅寫入輸入欄預填）
if ($_SERVER['REQUEST_METHOD'] === 'GET' && array_key_exists('coupon', $_GET)) {
    $incoming_coupon = trim((string)$_GET['coupon']);
    if ($incoming_coupon !== '') {
        $result = applyMemberCouponFromCode($pdo, $user_id, $incoming_coupon);
        if (($result['reason'] ?? '') === 'already_applied') {
            // 已套用相同券，不重複提示
        } elseif ($result['ok'] && ($result['reason'] ?? '') === null) {
            $_SESSION['cart_coupon_toast'] = ['message' => '優惠券套用成功，已更新折扣', 'type' => 'success'];
        } elseif (!$result['ok'] && ($result['reason'] ?? '') === 'empty_cart') {
            $_SESSION['cart_coupon_toast'] = ['message' => $result['message'], 'type' => $result['type']];
        } elseif (!$result['ok'] && ($result['reason'] ?? '') === 'below_minimum') {
            $_SESSION['coupon_code_prefill'] = normalizeCouponCode($incoming_coupon);
            $_SESSION['cart_coupon_toast'] = ['message' => '已帶入優惠券代碼。' . $result['message'], 'type' => 'warning'];
        } elseif ($result['message'] !== '') {
            $_SESSION['cart_coupon_toast'] = ['message' => $result['message'], 'type' => $result['type']];
        }
    }
    header('Location: cart.php');
    exit;
}

// 處理更新數量
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'apply_coupon') {
        $coupon_code = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';

        if ($coupon_code === '') {
            $_SESSION['cart_coupon_toast'] = ['message' => '請輸入優惠券代碼', 'type' => 'error'];
        } else {
            $result = applyMemberCouponFromCode($pdo, $user_id, $coupon_code);
            if (($result['reason'] ?? '') === 'already_applied') {
                // 已為目前套用之券，不額外跳訊息
            } elseif ($result['ok'] && ($result['reason'] ?? '') === null) {
                $_SESSION['cart_coupon_toast'] = ['message' => '優惠券套用成功，已更新折扣', 'type' => 'success'];
            } elseif (!$result['ok'] && ($result['reason'] ?? '') === 'empty_cart') {
                $_SESSION['cart_coupon_toast'] = ['message' => $result['message'], 'type' => $result['type']];
            } elseif (!$result['ok'] && ($result['reason'] ?? '') === 'below_minimum') {
                $_SESSION['coupon_code_prefill'] = normalizeCouponCode($coupon_code);
                $_SESSION['cart_coupon_toast'] = ['message' => '已帶入優惠券代碼。' . $result['message'], 'type' => 'warning'];
            } else {
                $_SESSION['cart_coupon_toast'] = ['message' => $result['message'], 'type' => $result['type']];
            }
        }
    } elseif ($_POST['action'] === 'remove_coupon') {
        clearAppliedCoupon();
        unset($_SESSION['coupon_code_prefill']);
        $_SESSION['cart_coupon_toast'] = ['message' => '已移除優惠券', 'type' => 'success'];
    } elseif ($_POST['action'] === 'update_quantity') {
        $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
        $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

        if ($cart_id > 0 && $quantity >= 0) {
            try {
                // 取得購物車項目資訊
                $stmt = $pdo->prepare("SELECT c.product_id, c.size FROM cart c WHERE c.id = :cart_id AND c.user_id = :user_id");
                $stmt->execute([':cart_id' => $cart_id, ':user_id' => $user_id]);
                $cart_item = $stmt->fetch();

                if ($cart_item) {
                    $size_none = getCartSizeNoneValue();
                    $cart_size = (string)($cart_item['size'] ?? '');

                    // 配件尺寸 F（舊資料可能為 N）：不對 product_sizes 驗證庫存
                    if ($cart_size === $size_none || $cart_size === 'N') {
                        if ($quantity == 0) {
                            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id");
                            $stmt->execute([':cart_id' => $cart_id, ':user_id' => $user_id]);
                        } elseif ($quantity > 0) {
                            if ($quantity > 9999) {
                                $quantity = 9999;
                                $_SESSION['cart_message'] = '數量已調整為上限 9999';
                                $_SESSION['cart_message_type'] = 'warning';
                            }
                            $stmt = $pdo->prepare("UPDATE cart SET quantity = :quantity WHERE id = :cart_id AND user_id = :user_id");
                            $stmt->execute([':quantity' => $quantity, ':cart_id' => $cart_id, ':user_id' => $user_id]);
                        }
                    } else {
                        // 檢查庫存（安全帽尺寸）
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
                            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = :cart_id AND user_id = :user_id");
                            $stmt->execute([':cart_id' => $cart_id, ':user_id' => $user_id]);
                        }
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
$coupon_status = getAppliedCouponStatus($pdo, $cart_items);
$order_summary = calculateOrderSummary($cart_items, 'pickup', $coupon_status['coupon']);
$free_shipping_threshold = getFreeShippingThreshold();
$remaining_for_free_shipping = max(0, $free_shipping_threshold - $order_summary['subtotal']);
// 優惠券 toast：redirect flash 優先；否則本頁 getAppliedCouponStatus 失效訊息
$cart_coupon_toast = $cart_coupon_toast_flash;
if ($cart_coupon_toast === null && !empty($coupon_status['message'])) {
    $cart_coupon_toast = [
        'message' => (string)$coupon_status['message'],
        'type' => 'warning'
    ];
}

// 舊版若仍寫入 cart_message 的優惠券文案，改走 toast、不顯示頁面大區塊
if ($cart_message !== '' && (strpos($cart_message, '優惠券') !== false || strpos($cart_message, '最低消費') !== false)) {
    if ($cart_coupon_toast === null) {
        $tm = in_array($cart_message_type, ['success', 'error', 'warning'], true) ? $cart_message_type : 'warning';
        $cart_coupon_toast = ['message' => $cart_message, 'type' => $tm];
    }
    $cart_message = '';
    $cart_message_type = '';
}

$coupon_input_value = '';
if (!empty($coupon_status['coupon']) && !empty($coupon_status['coupon']['coupon_code'])) {
    $coupon_input_value = (string)$coupon_status['coupon']['coupon_code'];
} elseif (!empty($_SESSION['coupon_code_prefill'])) {
    $coupon_input_value = (string)$_SESSION['coupon_code_prefill'];
}

$cart_applicable_coupons = [];
if (!empty($cart_items) && empty($coupon_status['coupon'])) {
    $cart_applicable_coupons = getCartApplicableCouponsForUser($pdo, $user_id, $order_summary['subtotal']);
}

// 加價購商品（僅購物車有商品時顯示；配件購物車尺寸固定為 F，不需選 S/M/L/XL）
$addon_products = [];
if (!empty($cart_items)) {
    try {
        $stmt = $pdo->query("SELECT p.id, p.name, p.price,
                             " . primaryImageSubquery('p', 'pi') . " AS primary_image
                             FROM products p
                             WHERE p.status = 'active' AND p.is_addon = 1
                             ORDER BY p.created_at DESC
                             LIMIT 8");
        $addon_products = $stmt->fetchAll();
    } catch (PDOException $e) {
        $addon_products = [];
    }
}

// 查詢所有分類（用於導覽列；並解析「周邊與配件」ID 供購物車尺寸顯示）
$parts_category_id = null;
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
    foreach ($categories as $cat_row) {
        if (($cat_row['name'] ?? '') === '周邊與配件') {
            $parts_category_id = (int)$cat_row['id'];
            break;
        }
    }
} catch (PDOException $e) {
    $categories = [];
}

$is_logged_in = isset($_SESSION['user_id']);

$cart_coupon_toast_json = 'null';
if (!empty($cart_coupon_toast) && !empty($cart_coupon_toast['message'])) {
    $cart_coupon_toast_json = json_encode($cart_coupon_toast, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購物車 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css?v=20260410-cart-toast">
</head>
<body class="cart-page">
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <div id="cart-page-toast" class="toast cart-page-cart-toast hidden" role="status" aria-live="polite"><span id="cart-page-toast-message"></span></div>

    <!-- 購物車內容 -->
    <div class="cart-container">
        <div class="container">
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
                        <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#9A9A9A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2h12"></path>
                            <path d="M3 6h18l-2 14H5L3 6z"></path>
                        </svg>
                    </div>
                    <h2>購物車目前是空的</h2>
                    <p>快去選購您喜歡的商品吧！</p>
                    <a href="products.php" class="btn-primary">前往商品總覽</a>
                </div>
            <?php else: ?>
                <div class="cart-page-layout">
                    <!-- 左欄：商品清單 + 加價購（約 65%～70%） -->
                    <div class="cart-main">
                        <section class="cart-items-section">
                            <div class="cart-item-list">
                                <?php foreach ($cart_items as $item): 
                                    $subtotal = $item['price'] * $item['quantity'];
                                    $cart_line_img = resolve_product_card_image_src($item['primary_image'] ?? null);
                                ?>
                                    <article class="cart-item-card">
                                        <div class="cart-item-media">
                                            <img src="<?php echo htmlspecialchars($cart_line_img, ENT_QUOTES); ?>"
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        </div>

                                        <div class="cart-item-body">
                                            <h3 class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                            <p class="cart-item-meta">
                                                <span class="cart-item-category"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                <span class="cart-item-size">尺寸：<?php echo htmlspecialchars(formatCartLineSizeDisplay($item['size'] ?? '', (int)($item['category_id'] ?? 0), $parts_category_id)); ?></span>
                                            </p>
                                            <div class="cart-item-unit-row">
                                                <span class="cart-item-unit-label">單價</span>
                                                <span class="cart-item-unit-price">NT$ <?php echo number_format($item['price'], 0); ?></span>
                                            </div>
                                        </div>

                                        <div class="cart-item-controls">
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

                                            <div class="cart-item-subtotal-row">
                                                <span class="cart-item-subtotal-label">小計</span>
                                                <span class="cart-item-subtotal-value">NT$ <?php echo number_format($subtotal, 0); ?></span>
                                            </div>

                                            <form method="POST" onsubmit="return confirm('確定要刪除此商品嗎？');" class="cart-item-delete-form">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="cart_id" value="<?php echo htmlspecialchars($item['cart_id']); ?>">
                                                <button type="submit" class="btn-delete">刪除</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <!-- 加價購：橫向小卡列表（保留 carousel/滑動邏輯） -->
                        <?php if (!empty($addon_products)): ?>
                            <section class="cart-addon-section">
                                <section class="addon-section">
                                    <div class="addon-header">
                                        <h2 class="addon-title">推薦加購</h2>
                                        <p class="addon-hint">加購享 9 折</p>
                                    </div>
                                    <button type="button" class="addon-nav-btn addon-nav-left" id="addonNavLeft" aria-label="向左滑動">‹</button>
                                    <button type="button" class="addon-nav-btn addon-nav-right" id="addonNavRight" aria-label="向右滑動">›</button>

                                    <div class="addon-scroll" id="addonScroll">
                                        <div class="addon-track">
                                            <?php foreach ($addon_products as $addon): ?>
                                                <?php
                                                    $addon_original_price = (float)$addon['price'];
                                                    $addon_price = round($addon_original_price * 0.9, 2);
                                                    $addon_img_src = resolve_product_card_image_src($addon['primary_image'] ?? null);
                                                ?>
                                                <article class="addon-card" data-product-id="<?php echo (int)$addon['id']; ?>">
                                                    <a href="product_detail.php?id=<?php echo (int)$addon['id']; ?>" class="addon-image-link">
                                                        <div class="addon-image-wrap">
                                                            <img src="<?php echo htmlspecialchars($addon_img_src, ENT_QUOTES); ?>"
                                                                 alt="<?php echo htmlspecialchars($addon['name']); ?>"
                                                                 class="addon-image">
                                                        </div>
                                                    </a>

                                                    <div class="addon-info">
                                                        <a href="product_detail.php?id=<?php echo (int)$addon['id']; ?>" class="addon-name-link">
                                                            <h3 class="addon-name"><?php echo htmlspecialchars($addon['name']); ?></h3>
                                                        </a>

                                                        <div class="addon-price-row">
                                                            <span class="addon-original-price">原價 NT$ <?php echo number_format($addon_original_price, 0); ?></span>
                                                            <span class="addon-price">加購價 NT$ <?php echo number_format($addon_price, 0); ?></span>
                                                        </div>

                                                        <p class="addon-size-error" id="addonError_<?php echo (int)$addon['id']; ?>"></p>
                                                        <button type="button"
                                                                class="addon-cta-btn addon-add-btn"
                                                                data-product-id="<?php echo (int)$addon['id']; ?>">
                                                            加入購物車
                                                        </button>
                                                    </div>
                                                </article>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </section>
                            </section>
                        <?php endif; ?>
                    </div>

                    <!-- 右欄：優惠券 + 摘要 + 結帳按鈕（約 30%～35%） -->
                    <aside class="cart-sidebar">
                        <div class="cart-sidebar-stack">
                            <section class="cart-coupon-module">
                                <div class="coupon-panel">
                                    <div class="cart-summary-content">
                                        <label class="summary-label coupon-label">Coupon Code：</label>
                                        <div class="coupon-form-row">
                                            <form method="POST" class="coupon-apply-form">
                                                <input type="hidden" name="action" value="<?php echo !empty($coupon_status['coupon']) ? 'remove_coupon' : 'apply_coupon'; ?>">
                                                <input type="text" name="coupon_code" class="form-input coupon-input" placeholder="輸入優惠券代碼"
                                                       value="<?php echo htmlspecialchars($coupon_input_value); ?>">
                                                <button type="submit" class="<?php echo !empty($coupon_status['coupon']) ? 'btn-secondary' : 'btn-primary'; ?>">
                                                    <?php echo !empty($coupon_status['coupon']) ? '移除優惠券' : '套用優惠券'; ?>
                                                </button>
                                            </form>
                                        </div>

                                        <?php if (!empty($cart_applicable_coupons)): ?>
                                            <div class="cart-available-coupons" aria-label="可用優惠券">
                                                <div class="cart-available-coupons-title">可用優惠券</div>
                                                <ul class="cart-available-coupons-list">
                                                    <?php foreach ($cart_applicable_coupons as $ac): ?>
                                                        <li class="cart-available-coupon-card">
                                                            <div class="cart-available-coupon-name"><?php echo htmlspecialchars($ac['name']); ?></div>
                                                            <div class="cart-available-coupon-offer"><?php echo htmlspecialchars($ac['offer_line']); ?></div>
                                                            <div class="cart-available-coupon-code"><?php echo htmlspecialchars($ac['coupon_code']); ?></div>
                                                            <form method="POST" class="cart-available-coupon-form">
                                                                <input type="hidden" name="action" value="apply_coupon">
                                                                <input type="hidden" name="coupon_code" value="<?php echo htmlspecialchars($ac['coupon_code']); ?>">
                                                                <button type="submit" class="cart-available-coupon-btn">套用</button>
                                                            </form>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </section>

                            <section class="cart-summary-module">
                                <div class="summary-card">
                                    <div class="cart-summary-content">
                                        <h3 class="cart-subtotal-breakdown-title">小計明細</h3>
                                        <div class="summary-row">
                                            <span class="summary-label">商品小計：</span>
                                            <span class="summary-value">NT$ <?php echo number_format($order_summary['subtotal'], 0); ?></span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">運費：</span>
                                            <span class="summary-value">
                                                <?php if ($order_summary['shipping'] == 0): ?>
                                                    免運費
                                                <?php else: ?>
                                                    NT$ 60
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($remaining_for_free_shipping > 0): ?>
                                            <div class="summary-row summary-hint">
                                                <span class="summary-label">免運提醒：</span>
                                                <span class="summary-value">再購買 <?php echo number_format($remaining_for_free_shipping, 0); ?> 元即可享免運</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="summary-row">
                                            <span class="summary-label">優惠券折扣：</span>
                                            <span class="summary-value">- NT$ <?php echo number_format($order_summary['discount'], 0); ?></span>
                                        </div>
                                        <div class="summary-divider"></div>
                                        <div class="summary-row summary-total">
                                            <span class="summary-label">最終總價：</span>
                                            <span class="summary-value">NT$ <?php echo number_format($order_summary['final_total'], 0); ?></span>
                                        </div>
                                    </div>

                                    <div class="cart-checkout-actions">
                                        <a href="products.php" class="btn-secondary cart-shopping-btn">繼續購物</a>
                                        <a href="checkout.php" class="btn-primary cart-checkout-btn">前往結帳</a>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </aside>
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
        (function () {
            var cartPageToastPayload = <?php echo $cart_coupon_toast_json; ?>;
            var cartPageToastHideTimer = null;
            function showCartPageCouponToast(message, type) {
                var toast = document.getElementById('cart-page-toast');
                var toastMessage = document.getElementById('cart-page-toast-message');
                if (!toast || !toastMessage || !message) return;
                if (cartPageToastHideTimer) {
                    clearTimeout(cartPageToastHideTimer);
                    cartPageToastHideTimer = null;
                }
                toastMessage.textContent = message;
                toast.classList.remove('hidden', 'is-hiding', 'is-error');
                if (type === 'error') {
                    toast.classList.add('is-error');
                }
                void toast.offsetWidth;
                toast.classList.add('is-visible');
                cartPageToastHideTimer = setTimeout(function () {
                    toast.classList.remove('is-visible');
                    toast.classList.add('is-hiding');
                    setTimeout(function () {
                        toast.classList.add('hidden');
                        toast.classList.remove('is-hiding', 'is-error');
                        cartPageToastHideTimer = null;
                    }, 280);
                }, 3000);
            }
            if (cartPageToastPayload && cartPageToastPayload.message) {
                showCartPageCouponToast(cartPageToastPayload.message, cartPageToastPayload.type || 'success');
            }
        })();

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

        // 加價購：直接加入購物車（使用 9 折）
        (function() {
            const addonScroll = document.getElementById('addonScroll');
            const navLeft = document.getElementById('addonNavLeft');
            const navRight = document.getElementById('addonNavRight');
            const addonTrack = addonScroll ? addonScroll.querySelector('.addon-track') : null;

            function getScrollStep() {
                if (!addonTrack) return 376;
                const firstCard = addonTrack.querySelector('.addon-card');
                if (!firstCard) return 376;
                const gap = parseFloat(window.getComputedStyle(addonTrack).columnGap || window.getComputedStyle(addonTrack).gap || '16');
                return firstCard.getBoundingClientRect().width + gap;
            }

            function updateNavState() {
                if (!addonScroll || !navLeft || !navRight) return;
                const maxScrollLeft = addonScroll.scrollWidth - addonScroll.clientWidth;
                navLeft.disabled = addonScroll.scrollLeft <= 2;
                navRight.disabled = addonScroll.scrollLeft >= (maxScrollLeft - 2);
            }

            if (addonScroll && navLeft && navRight) {
                navLeft.addEventListener('click', function() {
                    addonScroll.scrollBy({ left: -getScrollStep(), behavior: 'smooth' });
                });

                navRight.addEventListener('click', function() {
                    addonScroll.scrollBy({ left: getScrollStep(), behavior: 'smooth' });
                });

                addonScroll.addEventListener('scroll', updateNavState, { passive: true });
                window.addEventListener('resize', updateNavState);

                addonScroll.addEventListener('wheel', function(e) {
                    if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                        e.preventDefault();
                        addonScroll.scrollLeft += e.deltaY;
                    }
                }, { passive: false });

                updateNavState();
            }

            const buttons = document.querySelectorAll('.addon-add-btn');
            if (!buttons.length) return;

            buttons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const productId = btn.getAttribute('data-product-id');
                    if (!productId) return;

                    const formData = new FormData();
                    const errorEl = document.getElementById('addonError_' + productId);

                    formData.append('product_id', productId);
                    formData.append('quantity', '1');
                    formData.append('is_addon', '1');

                    btn.disabled = true;
                    const originalText = btn.textContent;
                    btn.textContent = '加入中...';

                    fetch('api/add_to_cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else if (errorEl) {
                            errorEl.textContent = data.message || '加入失敗';
                        }
                    })
                    .catch(() => {
                        if (errorEl) errorEl.textContent = '加入購物車時發生錯誤';
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    });
                });
            });
        })();

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

