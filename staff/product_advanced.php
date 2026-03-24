<?php
require_once '../config.php';
require_once __DIR__ . '/includes/staff_layout.php';

staffRequireAuth();

$productId = (int)($_GET['id'] ?? 0);
if ($productId > 0) {
    header('Location: product_form.php?id=' . $productId);
} else {
    header('Location: product_form.php');
}
exit;

