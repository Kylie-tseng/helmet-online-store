<?php
require_once '../config.php';

// 只允許 admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

header('Location: dashboard.php');
exit;
