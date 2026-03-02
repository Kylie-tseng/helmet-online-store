<?php
require_once 'config.php';

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// 取得表單資料
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// 驗證輸入
if (empty($username) || empty($password)) {
    header('Location: login.php?error=請填寫完整資訊');
    exit;
}

try {
    // 查詢使用者（使用 username）
    $stmt = $pdo->prepare("SELECT id, name, username, email, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 檢查帳號是否存在
    if (!$user) {
        header('Location: login.php?error=not_found');
        exit;
    }

    // 驗證密碼
    if (!password_verify($password, $user['password'])) {
        header('Location: login.php?error=wrong_password');
        exit;
    }

    // 登入成功，設定 session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    // 取得 redirect 參數
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php';
    
    // 根據角色導向不同頁面
    if ($user['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($user['role'] === 'staff') {
        header('Location: staff/dashboard.php');
    } else {
        // 一般會員：如果有 redirect 就導向該頁，否則導向首頁
        header('Location: ' . $redirect);
    }
    exit;

} catch (PDOException $e) {
    // 資料庫錯誤
    error_log("登入錯誤: " . $e->getMessage());
    header('Location: login.php?error=系統錯誤，請稍後再試');
    exit;
}
?>
