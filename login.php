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
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

$success = '';
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// 取得 redirect 參數
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- 頂部公告橫幅 -->
    <div class="announcement-bar">
        <div class="announcement-content">
            歡迎登入 HelmetVRse
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

    <!-- 登入表單 -->
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">登入</h1>
            <p class="login-subtitle">請輸入您的帳號密碼</p>

            <?php if ($error): ?>
                <div class="error-message">
                    <?php 
                    if ($error === 'not_found') {
                        echo '帳號不存在';
                    } elseif ($error === 'wrong_password') {
                        echo '密碼錯誤';
                    } else {
                        echo htmlspecialchars($error);
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form action="authenticate.php" method="POST">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <div class="form-group">
                    <label for="username" class="form-label">帳號</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-input" 
                        placeholder="請輸入您的帳號"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">密碼</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="請輸入您的密碼"
                        required
                    >
                </div>

                <button type="submit" class="login-btn">登入</button>
            </form>

            <div class="login-footer">
                <p>還沒有帳號？ <a href="register.php">立即註冊</a></p>
            </div>
        </div>
    </div>
</body>
</html>
