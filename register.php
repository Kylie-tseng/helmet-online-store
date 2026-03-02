<?php
require_once 'config.php';

// 如果已經登入，根據角色導向
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'staff') {
        header('Location: staff/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // 驗證輸入
    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($phone) || empty($address)) {
        $error = '請填寫所有必填欄位';
    } elseif ($password !== $password_confirm) {
        $error = '兩次輸入的密碼不一致';
    } elseif (strlen($password) < 6) {
        $error = '密碼長度至少需要 6 個字元';
    } else {
        try {
            // 檢查 username 是否已存在
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = '此帳號已被使用，請換一個';
            } else {
                // 檢查 email 是否已存在
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = '此電子郵件已被註冊';
                } else {
                    // 建立新帳號
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, name, email, password, phone, address, role) VALUES (?, ?, ?, ?, ?, ?, 'member')");
                    $stmt->execute([$username, $name, $email, $hashed_password, $phone, $address]);
                    
                    header('Location: login.php?success=註冊成功，請登入');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = '註冊失敗，請稍後再試';
            error_log("註冊錯誤: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- 頂部公告橫幅 -->
    <div class="announcement-bar">
        <div class="announcement-content">
            歡迎註冊 HelmetVRse
        </div>
    </div>

    <!-- 導覽列 -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.php">HelmetVRse</a>
            </div>
            <div class="nav-right">
                <a href="index.php" style="color: #FFFFFF; text-decoration: none; font-size: 14px;">← 返回首頁</a>
            </div>
        </div>
    </nav>

    <!-- 註冊表單 -->
    <div class="register-container">
        <div class="register-card">
            <h1 class="register-title">註冊</h1>
            <p class="register-subtitle">填寫以下資訊完成註冊</p>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="username" class="form-label">
                        帳號 <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        placeholder="請輸入帳號（登入使用）"
                        required
                        autofocus
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="name" class="form-label">
                        姓名 <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="form-input" 
                        placeholder="請輸入您的姓名"
                        required
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        電子郵件 <span class="required">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="請輸入您的電子郵件"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        密碼 <span class="required">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="請輸入密碼（至少 6 個字元）"
                        required
                        minlength="6"
                    >
                </div>

                <div class="form-group">
                    <label for="password_confirm" class="form-label">
                        確認密碼 <span class="required">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        class="form-input" 
                        placeholder="請再次輸入密碼"
                        required
                        minlength="6"
                    >
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">
                        電話 <span class="required">*</span>
                    </label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="form-input" 
                        placeholder="請輸入您的電話號碼"
                        required
                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="address" class="form-label">
                        地址 <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="address" 
                        name="address" 
                        class="form-input" 
                        placeholder="請輸入您的地址"
                        required
                        value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>"
                    >
                </div>

                <button type="submit" class="register-btn">註冊</button>
            </form>

            <div class="register-footer">
                <p>已經有帳號？ <a href="login.php">立即登入</a></p>
            </div>
        </div>
    </div>
</body>
</html>
