<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('profile.php') . '&notice=profile');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'info'; // 預設顯示個人資料分頁

// 處理更改密碼
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = '請填寫所有欄位';
    } elseif ($new_password !== $confirm_password) {
        $error = '新密碼與確認密碼不一致';
    } elseif (strlen($new_password) < 6) {
        $error = '新密碼長度至少需要6個字元';
    } else {
        try {
            // 查詢使用者目前的密碼
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $user_data = $stmt->fetch();
            
            if (!$user_data) {
                $error = '找不到使用者資料';
            } elseif (!password_verify($old_password, $user_data['password'])) {
                $error = '舊密碼錯誤';
            } else {
                // 更新密碼
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id");
                $stmt->execute([
                    ':password' => $hashed_password,
                    ':user_id' => $user_id
                ]);
                
                $success = '密碼已成功更新';
                $active_tab = 'password'; // 保持在更改密碼分頁
            }
        } catch (PDOException $e) {
            $error = '更新密碼時發生錯誤：' . $e->getMessage();
        }
    }
    $active_tab = 'password';
}

// 處理取消訂單
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    if ($order_id > 0) {
        try {
            // 檢查訂單是否屬於該使用者且狀態為未出貨
            $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = :order_id AND user_id = :user_id");
            $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                $error = '找不到此訂單';
            } elseif (!in_array($order['status'], ['pending', 'pending_payment', 'paid'])) {
                $error = '此訂單無法取消';
            } else {
                $pdo->beginTransaction();
                
                // 更新訂單狀態為已取消
                $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :order_id");
                $stmt->execute([':order_id' => $order_id]);
                
                // 還原庫存
                $stmt = $pdo->prepare("SELECT product_id, size, quantity FROM order_items WHERE order_id = :order_id");
                $stmt->execute([':order_id' => $order_id]);
                $order_items = $stmt->fetchAll();
                
                foreach ($order_items as $item) {
                    $stmt = $pdo->prepare("UPDATE product_sizes SET stock = stock + :quantity, updated_at = NOW() 
                                         WHERE product_id = :product_id AND size = :size");
                    $stmt->execute([
                        ':quantity' => $item['quantity'],
                        ':product_id' => $item['product_id'],
                        ':size' => $item['size']
                    ]);
                }
                
                $pdo->commit();
                $success = '訂單已成功取消';
                $active_tab = 'orders';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = '取消訂單時發生錯誤：' . $e->getMessage();
        }
    }
    $active_tab = 'orders';
}

// 處理表單提交（個人資料更新）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // 驗證必填欄位
    if (empty($name) || empty($username) || empty($email) || empty($phone) || empty($address)) {
        $error = '請填寫所有必填欄位';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '請輸入有效的電子郵件地址';
    } else {
        try {
            // 檢查 username 和 email 是否已被其他使用者使用
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':user_id' => $user_id
            ]);
            
            if ($stmt->fetch()) {
                $error = '使用者名稱或電子郵件已被使用';
            } else {
                // 更新使用者資料
                $stmt = $pdo->prepare("UPDATE users SET name = :name, username = :username, email = :email, phone = :phone, address = :address, updated_at = NOW() WHERE id = :user_id");
                $stmt->execute([
                    ':name' => $name,
                    ':username' => $username,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':user_id' => $user_id
                ]);
                
                // 更新 session 中的使用者名稱
                $_SESSION['user_name'] = $name;
                $success = '個人資料已成功更新';
                $active_tab = 'info'; // 更新成功後保持在個人資料分頁
            }
        } catch (PDOException $e) {
            $error = '更新資料時發生錯誤：' . $e->getMessage();
        }
    }
}

// 查詢使用者資料
try {
    $stmt = $pdo->prepare("SELECT id, name, username, email, phone, address FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: login.php?redirect=' . urlencode('profile.php') . '&notice=profile');
        exit;
    }
} catch (PDOException $e) {
    $error = '讀取資料時發生錯誤：' . $e->getMessage();
    $user = null;
}

// 查詢訂單資料（訂單管理分頁）
$orders = [];
if ($active_tab === 'orders') {
    try {
        $stmt = $pdo->prepare("SELECT id, total_amount, status, payment_method, shipping_method, shipping_address, pickup_store, created_at, updated_at 
                              FROM orders 
                              WHERE user_id = :user_id 
                              ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $orders = $stmt->fetchAll();
        
        // 為每個訂單查詢明細（包含尺寸）
        foreach ($orders as &$order) {
            $stmt = $pdo->prepare("SELECT oi.id, oi.quantity, oi.unit_price, oi.subtotal, oi.size, p.name AS product_name
                                   FROM order_items oi
                                   INNER JOIN products p ON oi.product_id = p.id
                                   WHERE oi.order_id = :order_id");
            $stmt->execute([':order_id' => $order['id']]);
            $order['items'] = $stmt->fetchAll();
        }
        unset($order);
    } catch (PDOException $e) {
        $error = '讀取訂單資料時發生錯誤：' . $e->getMessage();
    }
}

// 查詢會員優惠券（我的優惠券分頁）
$user_coupons = [];
if ($active_tab === 'coupons') {
    try {
        $stmt = $pdo->prepare("SELECT uc.id, c.coupon_code, uc.status, uc.created_at
                               FROM user_coupons uc
                               INNER JOIN coupons c ON uc.coupon_id = c.id
                               WHERE uc.user_id = :user_id
                               ORDER BY uc.created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $user_coupons = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = '讀取優惠券資料時發生錯誤：' . $e->getMessage();
    }
}

// 訂單狀態中文對照
$status_map = [
    'pending' => '未出貨',
    'pending_payment' => '待信用卡付款',
    'paid' => '已付款',
    'shipped' => '已出貨',
    'completed' => '已完成',
    'cancelled' => '已取消'
];

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

// 取得購物車數量
$cart_count = getCartItemCount($pdo, $user_id);
// 導覽列資料
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    if ($parts_category) {
        $parts_category_id = $parts_category['id'];
    }
} catch (PDOException $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人檔案 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 個人檔案內容 -->
    <div class="dashboard-container">
        <div class="dashboard-content">
            <!-- 標題區 -->
            <div class="dashboard-header">
                <h1 class="dashboard-title">個人檔案</h1>
                <p class="dashboard-subtitle">管理您的個人資訊與訂單記錄</p>
            </div>

            <!-- 分頁切換 -->
            <div class="profile-tabs">
                <a href="profile.php?tab=info" class="profile-tab <?php echo $active_tab === 'info' ? 'active' : ''; ?>">
                    個人資料
                </a>
                <a href="profile.php?tab=password" class="profile-tab <?php echo $active_tab === 'password' ? 'active' : ''; ?>">
                    更改密碼
                </a>
                <a href="profile.php?tab=orders" class="profile-tab <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">
                    訂單管理
                </a>
                <a href="profile.php?tab=coupons" class="profile-tab <?php echo $active_tab === 'coupons' ? 'active' : ''; ?>">
                    我的優惠券
                </a>
            </div>

            <!-- 分頁內容 -->
            <div class="profile-tab-content">
                <?php if ($active_tab === 'info'): ?>
                    <!-- 個人資料分頁 -->
                    <div class="profile-card">
                        <h2 class="card-title">編輯個人資料</h2>
                        
                        <?php if ($error): ?>
                            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <?php if ($user): ?>
                            <form method="POST" action="profile.php?tab=info" class="profile-form">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-group">
                                    <label class="form-label">姓名 <span class="required">*</span></label>
                                    <input type="text" 
                                           name="name" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">帳號名稱 <span class="required">*</span></label>
                                    <input type="text" 
                                           name="username" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">電子郵件 <span class="required">*</span></label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">電話 <span class="required">*</span></label>
                                    <input type="text" 
                                           name="phone" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                           required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">地址 <span class="required">*</span></label>
                                    <input type="text" 
                                           name="address" 
                                           class="form-input" 
                                           value="<?php echo htmlspecialchars($user['address']); ?>" 
                                           required>
                                </div>

                                <button type="submit" class="btn">儲存變更</button>
                            </form>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'password'): ?>
                    <!-- 更改密碼分頁 -->
                    <div class="profile-card">
                        <h2 class="card-title">更改密碼</h2>
                        
                        <?php if ($error && strpos($error, '密碼') !== false): ?>
                            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($success && strpos($success, '密碼') !== false): ?>
                            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php?tab=password" class="profile-form">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label class="form-label">舊密碼 <span class="required">*</span></label>
                                <input type="password" 
                                       name="old_password" 
                                       class="form-input" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">新密碼 <span class="required">*</span></label>
                                <input type="password" 
                                       name="new_password" 
                                       class="form-input" 
                                       minlength="6"
                                       required>
                                <small class="form-hint">密碼長度至少需要6個字元</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">確認新密碼 <span class="required">*</span></label>
                                <input type="password" 
                                       name="confirm_password" 
                                       class="form-input" 
                                       minlength="6"
                                       required>
                            </div>

                            <button type="submit" class="btn">更新密碼</button>
                        </form>
                    </div>

                <?php elseif ($active_tab === 'orders'): ?>
                    <!-- 訂單管理分頁 -->
                    <div class="profile-card">
                        <h2 class="card-title">訂單管理</h2>
                        
                        <?php if ($error && strpos($error, '訂單') !== false): ?>
                            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if (empty($orders)): ?>
                            <div class="empty-message">目前尚未有任何訂單。</div>
                        <?php else: ?>
                            <div class="orders-list">
                                <?php foreach ($orders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)">
                                            <div class="order-info">
                                                <div class="order-id">訂單編號：#<?php echo htmlspecialchars($order['id']); ?></div>
                                                <div class="order-date"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></div>
                                            </div>
                                            <div class="order-status-wrapper">
                                                <span class="order-status status-<?php echo htmlspecialchars($order['status']); ?>">
                                                    <?php echo htmlspecialchars($status_map[$order['status']] ?? $order['status']); ?>
                                                </span>
                                                <span class="order-amount">NT$ <?php echo number_format($order['total_amount'], 0); ?></span>
                                                <?php if (in_array($order['status'], ['pending', 'pending_payment', 'paid'])): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('確定要取消此訂單嗎？');">
                                                        <input type="hidden" name="action" value="cancel_order">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <button type="submit" class="btn-cancel-order">取消訂單</button>
                                                    </form>
                                                <?php endif; ?>
                                                <span class="order-toggle-icon" id="toggle-icon-<?php echo $order['id']; ?>">▼</span>
                                            </div>
                                        </div>
                                        <div class="order-details" id="order-details-<?php echo $order['id']; ?>" style="display: none;">
                                            <div class="order-details-content">
                                                <div class="order-meta">
                                                    <?php if ($order['payment_method']): ?>
                                                        <p><strong>付款方式：</strong><?php echo htmlspecialchars($payment_method_names[$order['payment_method']] ?? $order['payment_method']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($order['shipping_method']): ?>
                                                        <p><strong>送貨方式：</strong><?php echo htmlspecialchars($shipping_method_names[$order['shipping_method']] ?? $order['shipping_method']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($order['shipping_address']): ?>
                                                        <p><strong>配送地址：</strong><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($order['pickup_store']): ?>
                                                        <p><strong>取貨門市：</strong><?php echo htmlspecialchars($order['pickup_store']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <table class="order-items-table">
                                                    <thead>
                                                        <tr>
                                                            <th>商品名稱</th>
                                                            <th>尺寸</th>
                                                            <th>數量</th>
                                                            <th>單價</th>
                                                            <th>小計</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($order['items'] as $item): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($item['size'] ?? '-'); ?></td>
                                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                                                <td>NT$ <?php echo number_format($item['unit_price'], 0); ?></td>
                                                                <td>NT$ <?php echo number_format($item['subtotal'], 0); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td colspan="4" class="text-right"><strong>總計：</strong></td>
                                                            <td><strong>NT$ <?php echo number_format($order['total_amount'], 0); ?></strong></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($active_tab === 'coupons'): ?>
                    <!-- 我的優惠券分頁 -->
                    <div class="profile-card">
                        <h2 class="card-title">我的優惠券</h2>

                        <?php if (empty($user_coupons)): ?>
                            <div class="empty-message">目前尚未領取任何優惠券。</div>
                        <?php else: ?>
                            <div class="cart-table-wrapper">
                                <table class="cart-table">
                                    <thead>
                                        <tr>
                                            <th>優惠名稱</th>
                                            <th>優惠內容</th>
                                            <th>狀態</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_coupons as $coupon): ?>
                                            <?php $meta = getCouponActivityMeta($coupon['coupon_code']); ?>
                                            <tr class="cart-table-row">
                                                <td><?php echo htmlspecialchars($meta['name']); ?></td>
                                                <td><?php echo htmlspecialchars($meta['content']); ?></td>
                                                <td><?php echo $coupon['status'] === 'unused' ? '可使用' : '已使用'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
        // 搜尋框展開/收起功能
        (function() {
            try {
                const searchToggle = document.getElementById('searchToggle');
                const searchInput = document.getElementById('searchInput');
                const searchBox = document.querySelector('.search-box');

                if (!searchToggle || !searchInput || !searchBox) return;

                searchToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    searchBox.classList.toggle('active');
                    if (searchBox.classList.contains('active')) {
                        searchInput.focus();
                    }
                });

                searchInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                    searchBox.classList.add('active');
                });

                document.addEventListener('click', function(e) {
                    if (!searchBox.contains(e.target)) {
                        searchBox.classList.remove('active');
                    }
                });

                searchInput.addEventListener('blur', function() {
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

        function toggleOrderDetails(orderId) {
            const details = document.getElementById('order-details-' + orderId);
            const icon = document.getElementById('toggle-icon-' + orderId);
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                icon.textContent = '▲';
            } else {
                details.style.display = 'none';
                icon.textContent = '▼';
            }
        }

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
