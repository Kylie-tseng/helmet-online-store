<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/product_query_helpers.php';
require_once 'includes/credit_card_payment_validate.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('payment_credit_card.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];

unset($_SESSION['pending_order_id']);

$checkout_data = isset($_SESSION['checkout_data']) && is_array($_SESSION['checkout_data']) ? $_SESSION['checkout_data'] : null;
if ($checkout_data === null || trim((string)($checkout_data['payment_method'] ?? '')) !== 'credit_card') {
    header('Location: checkout.php');
    exit;
}

$shipping_method = trim((string)($checkout_data['shipping_method'] ?? ''));
$shipping_address = trim((string)($checkout_data['shipping_address'] ?? ''));
$pickup_store = trim((string)($checkout_data['pickup_store'] ?? ''));

if (!in_array($shipping_method, ['pickup', 'home'], true)) {
    header('Location: checkout.php');
    exit;
}

$shipping_row = [
    'shipping_method' => $shipping_method,
    'shipping_address' => $shipping_address,
    'pickup_store' => $pickup_store,
];
if (!credit_card_order_has_complete_shipping($shipping_row)) {
    header('Location: checkout.php');
    exit;
}

$payment_error = '';
$repop_card_name = '';
$repop_card_expiry = '';

// 注意：前端若用 HTMLFormElement.prototype.submit() 送出，submit 按鈕不會進 POST，必須靠 hidden 的 confirm_payment
$is_payment_post = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['confirm_payment']) || (array_key_exists('card_number', $_POST) && array_key_exists('card_name', $_POST)));

if ($is_payment_post) {
    $repop_card_name = isset($_POST['card_name']) ? trim((string)$_POST['card_name']) : '';
    $repop_card_expiry = isset($_POST['card_expiry']) ? trim((string)$_POST['card_expiry']) : '';

    $cart_items_post = getCartItems($pdo, $user_id);
    $coupon_status_post = getAppliedCouponStatus($pdo, $cart_items_post);
    $coupon_notice_post = !empty($coupon_status_post['message']) ? (string)$coupon_status_post['message'] : '';

    if ($cart_items_post === []) {
        $payment_error = '購物車是空的，無法建立訂單。';
    } elseif ($coupon_notice_post !== '') {
        $payment_error = '無法完成付款：' . htmlspecialchars($coupon_notice_post, ENT_QUOTES, 'UTF-8');
    } elseif (!credit_card_order_has_complete_shipping($shipping_row)) {
        $payment_error = '配送資料不完整，請返回結帳頁補齊。';
    } else {
        $fieldCheck = validate_credit_card_payment_submission($_POST);
        if (!$fieldCheck['ok']) {
            $payment_error = implode('<br>', $fieldCheck['errors']);
        } else {
            $order_summary_post = calculateOrderSummary($cart_items_post, $shipping_method, $coupon_status_post['coupon']);
            if ((float)($order_summary_post['final_total'] ?? 0) <= 0) {
                $payment_error = '訂單金額異常，無法建立訂單。';
            } else {
                $order_amounts = build_orders_amount_fields($order_summary_post);
                $order_coupon_id = !empty($coupon_status_post['coupon']['id']) ? (int)$coupon_status_post['coupon']['id'] : null;

                try {
                    error_log('payment_credit_card: step1 begin_transaction');
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO orders (user_id, coupon_id, total_amount, discount_amount, final_amount, status, payment_method, shipping_method, shipping_address, pickup_store)
                         VALUES (:user_id, :coupon_id, :total_amount, :discount_amount, :final_amount, 'paid', 'credit_card', :shipping_method, :shipping_address, :pickup_store)");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':coupon_id' => $order_coupon_id,
                        ':total_amount' => $order_amounts['total_amount'],
                        ':discount_amount' => $order_amounts['discount_amount'],
                        ':final_amount' => $order_amounts['final_amount'],
                        ':shipping_method' => $shipping_method,
                        ':shipping_address' => $shipping_method === 'home' ? $shipping_address : null,
                        ':pickup_store' => $shipping_method === 'pickup' ? $pickup_store : null,
                    ]);

                    $order_id = (int)$pdo->lastInsertId();
                    error_log('payment_credit_card: step2 insert_orders id=' . $order_id);
                    if ($order_id <= 0) {
                        $pdo->rollBack();
                        $payment_error = '建立訂單失敗，請稍後再試。';
                    } else {
                        foreach ($cart_items_post as $item) {
                            $line_subtotal = (float)$item['price'] * (int)$item['quantity'];
                            $order_item_size = normalizeOrderItemSizeForDb($item['size'] ?? '');
                            error_log('order item size: ' . var_export($item['size'] ?? null, true) . ' => ' . var_export($order_item_size, true));
                            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, size, quantity, unit_price, subtotal)
                                                 VALUES (:order_id, :product_id, :size, :quantity, :unit_price, :subtotal)");
                            $stmt->execute([
                                ':order_id' => $order_id,
                                ':product_id' => $item['product_id'],
                                ':size' => $order_item_size,
                                ':quantity' => $item['quantity'],
                                ':unit_price' => $item['price'],
                                ':subtotal' => $line_subtotal,
                            ]);
                        }
                        error_log('payment_credit_card: step3 order_items written count=' . count($cart_items_post));

                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id = ?');
                        $stmt->execute([$order_id]);
                        $items_written = (int)$stmt->fetchColumn();
                        if ($items_written !== count($cart_items_post)) {
                            $pdo->rollBack();
                            $payment_error = '訂單明細寫入異常，訂單未成立。';
                        } else {
                            $stmt = $pdo->prepare('DELETE FROM cart WHERE user_id = :uid');
                            $stmt->execute([':uid' => $user_id]);
                            $pdo->commit();
                            error_log('payment_credit_card: step4 commit ok');

                            unset($_SESSION['checkout_data'], $_SESSION['pending_order_id']);
                            if (function_exists('clearAppliedCoupon')) {
                                clearAppliedCoupon();
                            }
                            unset($_SESSION['coupon_code_prefill']);

                            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :oid AND user_id = :uid LIMIT 1');
                            $stmt->execute([':oid' => $order_id, ':uid' => $user_id]);
                            $order = $stmt->fetch(PDO::FETCH_ASSOC);

                            $stmt = $pdo->prepare("SELECT oi.*,
                              COALESCE(NULLIF(p.name, ''), CONCAT('商品 #', oi.product_id, '（可能已下架）')) AS product_name,
                              " . primaryImageSubquery('p', 'pi') . " AS primary_image
                              FROM order_items oi
                              LEFT JOIN products p ON oi.product_id = p.id
                              WHERE oi.order_id = :order_id");
                            $stmt->execute([':order_id' => $order_id]);
                            $order_items = $stmt->fetchAll();

                            error_log('payment_credit_card: step5 before send_order');
                            try {
                                include __DIR__ . '/send_order.php';
                            } catch (Throwable $mailEx) {
                                error_log('payment_credit_card: send_order mail error — ' . $mailEx->getMessage());
                            }
                            error_log('payment_credit_card: step6 after send_order, redirect');

                            session_write_close();
                            header('Location: order_success.php?order_id=' . (int)$order_id, true, 303);
                            exit;
                        }
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('payment_credit_card Throwable: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
                    $detail = htmlspecialchars(substr($e->getMessage(), 0, 400), ENT_QUOTES, 'UTF-8');
                    $payment_error = '付款處理時發生錯誤，請稍後再試。若問題持續，請聯絡客服。<br><small style="color:#64748b;">（' . $detail . '）</small>';
                }
            }
        }
    }
}

$cart_items = getCartItems($pdo, $user_id);
if ($cart_items === []) {
    header('Location: cart.php');
    exit;
}

$coupon_status = getAppliedCouponStatus($pdo, $cart_items);
$coupon_notice = !empty($coupon_status['message']) ? (string)$coupon_status['message'] : '';
$order_summary = calculateOrderSummary($cart_items, $shipping_method, $coupon_status['coupon']);
$order_ready_for_payment = ($coupon_notice === '');

$order_items = $cart_items;
$order_items_count = count($order_items);

// 導覽列分類查詢
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    $parts_category_id = $parts_category ? $parts_category['id'] : null;
} catch (PDOException $e) {
    $categories = [];
    $parts_category_id = null;
}

require_once 'includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>信用卡繳費 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <div class="checkout-container">
        <div class="container">
            <h1 class="checkout-page-title">信用卡繳費</h1>
            
            <div class="payment-summary">
                    <h2 class="section-title">訂單摘要</h2>
                    <div class="order-summary">
                        <div class="summary-row">
                            <span class="summary-label">訂單編號：</span>
                            <span class="summary-value">付款完成後成立</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">應付總額：</span>
                            <span class="summary-value summary-total">NT$ <?php echo number_format((float)$order_summary['final_total'], 0); ?></span>
                        </div>
                    </div>

                    <!-- 與 checkout.php「查看商品清單」同結構 / 同 class（僅顯示，資料仍為 $order_items） -->
                    <div class="checkout-order-toggle" role="region" aria-label="查看商品清單">
                        <button
                            type="button"
                            class="checkout-order-toggle-header"
                            data-checkout-items-toggle="1"
                            data-checkout-items-target="paymentItemsList"
                            aria-expanded="false"
                        >
                            <span class="checkout-order-toggle-header-text">查看商品清單</span>
                            <svg class="checkout-order-toggle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M6 9L12 15L18 9" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <div id="paymentItemsList" class="checkout-order-toggle-body" aria-hidden="true">
                            <div class="checkout-summary-items-inner">
                                <?php foreach ($order_items as $oi):
                                    $line_unit = (float)($oi['price'] ?? $oi['unit_price'] ?? 0);
                                    $line_qty = (int)($oi['quantity'] ?? 0);
                                    $oi_subtotal = isset($oi['subtotal']) ? (float)$oi['subtotal'] : ($line_unit * $line_qty);
                                    $oi_img_src = resolve_product_card_image_src($oi['primary_image'] ?? null);
                                ?>
                                    <div class="checkout-summary-items-row">
                                        <div class="checkout-summary-items-media">
                                            <img
                                                src="<?php echo htmlspecialchars($oi_img_src, ENT_QUOTES); ?>"
                                                alt="<?php echo htmlspecialchars((string)($oi['product_name'] ?? '')); ?>"
                                            >
                                        </div>

                                        <div class="checkout-summary-items-left">
                                            <div class="checkout-summary-items-name"><?php echo htmlspecialchars((string)($oi['product_name'] ?? '')); ?></div>
                                            <div class="checkout-summary-items-meta">
                                                <?php echo htmlspecialchars((string)($oi['category_name'] ?? '')); ?>
                                                &nbsp;&nbsp; 尺寸：<?php echo htmlspecialchars(formatCartSizeForDisplay($oi['size'] ?? '')); ?>
                                            </div>
                                            <div class="checkout-summary-items-unit-price">
                                                單價 NT$ <?php echo number_format($line_unit, 0); ?>
                                            </div>
                                        </div>

                                        <div class="checkout-summary-items-right">
                                            <div class="checkout-summary-items-amount">
                                                小計 NT$ <?php echo number_format($oi_subtotal, 0); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="payment-form-wrapper credit-card-box">
                    <h2 class="section-title">信用卡資訊</h2>
                    <?php if (!$order_ready_for_payment): ?>
                        <div class="error-message" role="alert">目前無法進行信用卡付款（例如優惠券狀態異常）。請返回結帳頁確認後再試。</div>
                    <?php endif; ?>
                    <?php if ($payment_error !== ''): ?>
                        <div class="error-message" role="alert"><?php echo $payment_error; ?></div>
                    <?php endif; ?>
                    <div id="payment-client-error" class="error-message" style="display: none;" role="alert"></div>

                    <form method="POST" action="payment_credit_card.php" class="payment-form payment-card-form" id="creditCardPaymentForm" novalidate>
                        <?php /* 放在 fieldset 外：disabled fieldset 內的欄位不會被 POST；prototype.submit() 也不會帶到 submit 按鈕 */ ?>
                        <input type="hidden" name="confirm_payment" value="1">
                        <fieldset class="payment-form-fieldset" <?php echo $order_ready_for_payment ? '' : 'disabled'; ?>>
                        <div class="form-group">
                            <label class="form-label" for="cc_card_number">卡號 <span class="required">*</span></label>
                            <input type="text" name="card_number" id="cc_card_number" class="form-input" placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric" autocomplete="cc-number">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="cc_card_name">持卡人姓名 <span class="required">*</span></label>
                            <input type="text" name="card_name" id="cc_card_name" class="form-input" placeholder="請輸入持卡人姓名" autocomplete="cc-name" value="<?php echo htmlspecialchars($repop_card_name, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="cc_card_expiry">有效期限 <span class="required">*</span></label>
                                <input type="text" name="card_expiry" id="cc_card_expiry" class="form-input" placeholder="MM/YY" autocomplete="cc-exp" value="<?php echo htmlspecialchars($repop_card_expiry, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cc_card_cvv">安全碼 <span class="required">*</span></label>
                                <input type="text" name="card_cvv" id="cc_card_cvv" class="form-input" placeholder="000" autocomplete="cc-csc">
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="checkout.php" class="btn-secondary">返回修改</a>
                            <button type="submit" name="confirm_payment" value="1" class="btn-primary">確認付款</button>
                        </div>
                        </fieldset>
                    </form>
                </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>Powered by HelmetVRse</p>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            var form = document.getElementById('creditCardPaymentForm');
            var errBox = document.getElementById('payment-client-error');
            var cardNumber = document.getElementById('cc_card_number');
            var cardName = document.getElementById('cc_card_name');
            var cardExpiry = document.getElementById('cc_card_expiry');
            var cardCvv = document.getElementById('cc_card_cvv');

            function showClientErr(msg) {
                if (!errBox) return;
                errBox.textContent = msg;
                errBox.style.display = msg ? 'block' : 'none';
            }

            function formatCardNumberInput(el) {
                if (!el) return;
                var digits = String(el.value).replace(/\D/g, '').slice(0, 16);
                el.value = digits.length ? digits.match(/.{1,4}/g).join(' ') : '';
            }

            cardNumber?.addEventListener('input', function (e) {
                formatCardNumberInput(e.target);
            });
            cardNumber?.addEventListener('paste', function (e) {
                e.preventDefault();
                var t = (e.clipboardData || window.clipboardData).getData('text') || '';
                var el = cardNumber;
                var start = typeof el.selectionStart === 'number' ? el.selectionStart : el.value.length;
                var end = typeof el.selectionEnd === 'number' ? el.selectionEnd : el.value.length;
                el.value = el.value.slice(0, start) + t + el.value.slice(end);
                formatCardNumberInput(el);
            });
            cardExpiry?.addEventListener('input', function (e) {
                var value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) value = value.substring(0, 2) + '/' + value.substring(2, 4);
                e.target.value = value;
            });
            form?.addEventListener('submit', function (e) {
                e.preventDefault();
                var fs = form.querySelector('fieldset.payment-form-fieldset');
                if (fs && fs.disabled) {
                    return;
                }
                showClientErr('');
                if (!String(cardNumber?.value || '').trim()) {
                    showClientErr('請輸入信用卡卡號');
                    return;
                }
                if (!String(cardName?.value || '').trim()) {
                    showClientErr('請輸入持卡人姓名');
                    return;
                }
                if (!String(cardExpiry?.value || '').trim()) {
                    showClientErr('請輸入有效期限');
                    return;
                }
                if (!String(cardCvv?.value || '').trim()) {
                    showClientErr('請輸入信用卡安全碼（CVV）');
                    return;
                }
                HTMLFormElement.prototype.submit.call(form);
            });
        })();

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