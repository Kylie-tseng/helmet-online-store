<?php
require_once 'config.php';

// 清除所有 session 資料
$_SESSION = array();

// 如果使用 cookie 儲存 session ID，也要清除 cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 銷毀 session
session_destroy();

// 導向首頁
header('Location: index.php');
exit;
?>

