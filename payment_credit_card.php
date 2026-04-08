<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/product_query_helpers.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('payment_credit_card.php'));
    exit;
}

$user_id = $_SESSION['user_id'];

// 檢查是否有待付款的訂單
if (!isset($_SESSION['pending_order_id'])) {
    header('Location: checkout.php');
    exit;
}

$order_id = $_SESSION['pending_order_id'];

// 查詢訂單資料
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id AND user_id = :user_id AND payment_method = 'credit_card' AND status = 'pending'");
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        unset($_SESSION['pending_order_id']);
        header('Location: checkout.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: checkout.php');
    exit;
}

// 查詢訂單明細
try {
    $stmt = $pdo->prepare("SELECT oi.*, p.name AS product_name,
                          " . primaryImageSubquery('p', 'pi') . " AS primary_image
                          FROM order_items oi
                          INNER JOIN products p ON oi.product_id = p.id
                          WHERE oi.order_id = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    $order_items = $stmt->fetchAll();
} catch (PDOException $e) {
    $order_items = [];
}

// 處理付款表單提交
$payment_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
    $card_name = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
    $card_expiry = isset($_POST['card_expiry']) ? trim($_POST['card_expiry']) : '';
    $card_cvv = isset($_POST['card_cvv']) ? trim($_POST['card_cvv']) : '';
    
    $errors = [];
    $card_number_clean = preg_replace('/\s+/', '', $card_number);
    if (!preg_match('/^\d{16}$/', $card_number_clean)) {
        $errors[] = '請輸入有效的16位信用卡號';
    }
    if (empty($card_name)) {
        $errors[] = '請輸入持卡人姓名';
    }
    if (!preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
        $errors[] = '請輸入有效的有效期限（格式：MM/YY）';
    }
    if (!preg_match('/^\d{3}$/', $card_cvv)) {
        $errors[] = '請輸入有效的安全碼（3位數字）';
    }
    
    if (empty($errors)) {
        $payment_updated = false;
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', updated_at = NOW() WHERE id = :order_id AND user_id = :user_id");
            $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
            $payment_updated = true;
        } catch (PDOException $e) {
            $payment_error = '付款處理時發生錯誤：' . $e->getMessage();
        }
        if ($payment_updated) {
            include __DIR__ . '/send_order.php';
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            unset($_SESSION['pending_order_id']);
            unset($_SESSION['checkout_data']);
            if (function_exists('clearAppliedCoupon')) {
                clearAppliedCoupon();
            }
            $success_oid = (int)$order_id;
            echo '<!DOCTYPE html><html lang="zh-TW"><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=order_success.php?order_id=' . $success_oid . '"><script>window.location.href=\'order_success.php?order_id=' . $success_oid . '\';</script></head><body></body></html>';
            exit;
        }
    } else {
        $payment_error = implode('<br>', $errors);
    }
}

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
                            <span class="summary-value">#<?php echo htmlspecialchars($order_id); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">訂單總金額：</span>
                            <span class="summary-value summary-total">NT$ <?php echo number_format(get_order_payable_amount($order), 0); ?></span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="checkout-summary-items-toggle"
                        data-checkout-items-toggle="1"
                        data-checkout-items-target="paymentItemsList"
                        aria-expanded="false"
                    >
                        <span>查看商品清單</span>
                        <svg class="checkout-summary-items-chevron" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M6 9L12 15L18 9" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>

                    <div id="paymentItemsList" class="checkout-summary-items" aria-hidden="true">
                        <div class="checkout-summary-items-inner">
                            <?php foreach ($order_items as $oi): 
                                $oi_subtotal = isset($oi['subtotal']) ? (float)$oi['subtotal'] : ((float)$oi['unit_price'] * (int)$oi['quantity']);
                            ?>
                                <div class="checkout-summary-items-row">
                                    <div class="checkout-summary-items-left">
                                        <div class="checkout-summary-items-name"><?php echo htmlspecialchars($oi['product_name']); ?></div>
                                        <div class="checkout-summary-items-meta">
                                            <?php echo htmlspecialchars(formatCartSizeForDisplay($oi['size'] ?? '')); ?>
                                            &nbsp;|&nbsp; Qty: <?php echo (int)$oi['quantity']; ?>
                                        </div>
                                    </div>
                                    <div class="checkout-summary-items-right">
                                        <div class="checkout-summary-items-amount">NT$ <?php echo number_format($oi_subtotal, 0); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="payment-form-wrapper">
                    <h2 class="section-title">信用卡資訊</h2>
                    <?php if ($payment_error): ?>
                        <div class="error-message"><?php echo $payment_error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="payment_credit_card.php" class="payment-form">
                        <div class="form-group">
                            <label class="form-label">卡號 <span class="required">*</span></label>
                            <input type="text" name="card_number" class="form-input" placeholder="0000 0000 0000 0000" maxlength="19" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">持卡人姓名 <span class="required">*</span></label>
                            <input type="text" name="card_name" class="form-input" placeholder="請輸入持卡人姓名" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">有效期限 <span class="required">*</span></label>
                                <input type="text" name="card_expiry" class="form-input" placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">安全碼 <span class="required">*</span></label>
                                <input type="text" name="card_cvv" class="form-input" placeholder="000" maxlength="3" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="checkout.php" class="btn-secondary">返回修改</a>
                            <button type="submit" name="confirm_payment" class="btn-primary">確認付款</button>
                        </div>
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
        // 自動格式化邏輯保持不變...
        document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formatted;
        });
        document.querySelector('input[name="card_expiry"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) value = value.substring(0, 2) + '/' + value.substring(2, 4);
            e.target.value = value;
        });
        document.querySelector('input[name="card_cvv"]')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });

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