<?php
// 資料庫連線設定
$db_host = '127.0.0.1';   // 用 IP 比 localhost 穩定
$db_name = 'helmet';
$db_port = '3306';        // 你的 MySQL port
$db_user = 'root';
$db_pass = '';

try {
    // 建立 PDO 連線（⚠️ 一定要把 port 寫進 DSN）
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("資料庫連線失敗：" . $e->getMessage());
}

// 開啟 session（如果尚未啟動）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
