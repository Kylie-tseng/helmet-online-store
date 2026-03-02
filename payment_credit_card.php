<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/checkout_steps.php';

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
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id AND user_id = :user_id AND status = 'pending_payment'");
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
    $stmt = $pdo->prepare("SELECT oi.*, p.name AS product_name, p.image_url
                          FROM order_items oi
                          INNER JOIN products p ON oi.product_id = p.id
                          WHERE oi.order_id = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    $order_items = $stmt->fetchAll();
} catch (PDOException $e) {
    $order_items = [];
}

// 處理付款表單提交
$payment_success = false;
$payment_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
    $card_name = isset($_POST['card_name']) ? trim($_POST['card_name']) : '';
    $card_expiry = isset($_POST['card_expiry']) ? trim($_POST['card_expiry']) : '';
    $card_cvv = isset($_POST['card_cvv']) ? trim($_POST['card_cvv']) : '';
    
    // 基本格式驗證
    $errors = [];
    
    // 卡號驗證（移除空格後檢查是否為16位數字）
    $card_number_clean = preg_replace('/\s+/', '', $card_number);
    if (!preg_match('/^\d{16}$/', $card_number_clean)) {
        $errors[] = '請輸入有效的16位信用卡號';
    }
    
    if (empty($card_name)) {
        $errors[] = '請輸入持卡人姓名';
    }
    
    // 有效期限驗證（格式：MM/YY）
    if (!preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
        $errors[] = '請輸入有效的有效期限（格式：MM/YY）';
    }
    
    // CVV 驗證（3位數字）
    if (!preg_match('/^\d{3}$/', $card_cvv)) {
        $errors[] = '請輸入有效的安全碼（3位數字）';
    }
    
    if (empty($errors)) {
        try {
            // 更新訂單狀態為已付款
            $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', updated_at = NOW() WHERE id = :order_id AND user_id = :user_id");
            $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
            
            // 清除 session
            unset($_SESSION['pending_order_id']);
            unset($_SESSION['checkout_data']);
            
            $payment_success = true;
        } catch (PDOException $e) {
            $payment_error = '付款處理時發生錯誤：' . $e->getMessage();
        }
    } else {
        $payment_error = implode('<br>', $errors);
    }
}

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
    <title>信用卡繳費 - HelmetVRse</title>
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

    <!-- 信用卡繳費內容 -->
    <div class="checkout-container">
        <div class="container">
            <?php renderCheckoutSteps(3); ?>
            <h1 class="checkout-page-title">信用卡繳費</h1>
            
            <?php if ($payment_success): ?>
                <div class="order-success">
                    <h2>付款成功！</h2>
                    <p>訂單編號：<?php echo htmlspecialchars($order_id); ?></p>
                    <p>感謝您的購買，我們將盡快為您處理訂單。</p>
                    <div class="order-success-actions">
                        <a href="products.php" class="btn-primary">繼續購物</a>
                        <a href="profile.php?tab=orders" class="btn-secondary">查看訂單</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- 訂單摘要 -->
                <div class="payment-summary">
                    <h2 class="section-title">訂單摘要</h2>
                    <div class="order-summary">
                        <div class="summary-row">
                            <span class="summary-label">訂單編號：</span>
                            <span class="summary-value">#<?php echo htmlspecialchars($order_id); ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">訂單總金額：</span>
                            <span class="summary-value summary-total">NT$ <?php echo number_format($order['total_amount'], 0); ?></span>
                        </div>
                    </div>
                </div>

                <!-- 信用卡表單 -->
                <div class="payment-form-wrapper">
                    <h2 class="section-title">信用卡資訊</h2>
                    
                    <?php if ($payment_error): ?>
                        <div class="error-message">
                            <?php echo $payment_error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="payment-form">
                        <div class="form-group">
                            <label class="form-label">卡號 <span class="required">*</span></label>
                            <input type="text" 
                                   name="card_number" 
                                   class="form-input" 
                                   placeholder="0000 0000 0000 0000"
                                   maxlength="19"
                                   pattern="[0-9\s]{13,19}"
                                   required>
                            <small class="form-hint">請輸入16位信用卡號碼</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">持卡人姓名 <span class="required">*</span></label>
                            <input type="text" 
                                   name="card_name" 
                                   class="form-input" 
                                   placeholder="請輸入持卡人姓名"
                                   required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">有效期限 <span class="required">*</span></label>
                                <input type="text" 
                                       name="card_expiry" 
                                       class="form-input" 
                                       placeholder="MM/YY"
                                       maxlength="5"
                                       pattern="\d{2}/\d{2}"
                                       required>
                                <small class="form-hint">格式：MM/YY</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">安全碼 <span class="required">*</span></label>
                                <input type="text" 
                                       name="card_cvv" 
                                       class="form-input" 
                                       placeholder="000"
                                       maxlength="3"
                                       pattern="\d{3}"
                                       required>
                                <small class="form-hint">卡片背面3位數字</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="checkout.php?return_from_payment=1" class="btn-secondary">返回修改</a>
                            <button type="submit" name="confirm_payment" class="btn-primary">確認付款</button>
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
        // 卡號自動格式化（每4位加空格）
        document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            if (formatted.length <= 19) {
                e.target.value = formatted;
            }
        });

        // 有效期限自動格式化
        document.querySelector('input[name="card_expiry"]')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // CVV 只允許數字
        document.querySelector('input[name="card_cvv"]')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>

