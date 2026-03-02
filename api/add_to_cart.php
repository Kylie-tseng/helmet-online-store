<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => '請先登入',
        'redirect' => 'login.php'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// 處理 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $size = isset($_POST['size']) ? trim($_POST['size']) : '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    // 驗證輸入
    if ($product_id <= 0) {
        $error_message = '無效的商品 ID';
    } elseif (!in_array($size, ['S', 'M', 'L', 'XL'])) {
        $error_message = '無效的尺寸';
    } elseif ($quantity <= 0) {
        $error_message = '數量必須大於 0';
    } else {
        try {
            // 檢查商品是否存在且為 active
            $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = :product_id AND status = 'active'");
            $stmt->execute([':product_id' => $product_id]);
            $product = $stmt->fetch();

            if (!$product) {
                $error_message = '找不到此商品或已下架';
            } else {
                // 檢查該商品 + 尺寸是否存在於尺寸庫存表中
                $stmt = $pdo->prepare("SELECT stock FROM product_sizes WHERE product_id = :product_id AND size = :size");
                $stmt->execute([':product_id' => $product_id, ':size' => $size]);
                $size_stock = $stmt->fetch();

                if (!$size_stock) {
                    $error_message = '此商品沒有此尺寸的庫存資料';
                } elseif ($size_stock['stock'] <= 0) {
                    $error_message = '此尺寸已售完';
                } elseif ($quantity > $size_stock['stock']) {
                    // 限制數量為庫存上限
                    $quantity = $size_stock['stock'];
                    $error_message = '數量超過庫存，已自動調整為最大可購買數量：' . $quantity;
                } else {
                    // 檢查購物車中是否已有同商品同尺寸
                    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id AND size = :size");
                    $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id, ':size' => $size]);
                    $existing_cart_item = $stmt->fetch();

                    if ($existing_cart_item) {
                        // 更新數量
                        $new_quantity = $existing_cart_item['quantity'] + $quantity;
                        
                        // 檢查總數量是否超過庫存
                        if ($new_quantity > $size_stock['stock']) {
                            $new_quantity = $size_stock['stock'];
                            $error_message = '購物車中已有此商品，總數量超過庫存，已調整為最大可購買數量：' . $new_quantity;
                        }

                        $stmt = $pdo->prepare("UPDATE cart SET quantity = :quantity WHERE id = :cart_id");
                        $stmt->execute([':quantity' => $new_quantity, ':cart_id' => $existing_cart_item['id']]);
                    } else {
                        // 新增購物車項目
                        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, size, quantity) VALUES (:user_id, :product_id, :size, :quantity)");
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':product_id' => $product_id,
                            ':size' => $size,
                            ':quantity' => $quantity
                        ]);
                    }

                    // 如果沒有錯誤訊息，表示成功
                    if (empty($error_message)) {
                        $success_message = '已成功加入購物車';
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = '加入購物車時發生錯誤：' . $e->getMessage();
        }
    }
    
    // 取得購物車總數
    $cart_count = 0;
    if (empty($error_message)) {
        try {
            require_once '../includes/cart_functions.php';
            $cart_count = getCartItemCount($pdo, $user_id);
        } catch (Exception $e) {
            // 忽略錯誤
        }
    }
    
    // 回傳 JSON
    if (!empty($error_message)) {
        echo json_encode([
            'success' => false,
            'message' => $error_message,
            'cart_count' => $cart_count
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'cart_count' => $cart_count
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => '無效的請求方法'
    ]);
}

