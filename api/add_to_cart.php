<?php
require_once '../config.php';
require_once '../includes/cart_functions.php';

header('Content-Type: application/json; charset=utf-8');

$cart_size_none = getCartSizeNoneValue();

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => '請先登入或註冊，才可以使用購物車功能',
        'redirect' => 'login.php?notice=cart'
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
    $is_addon = isset($_POST['is_addon']) && $_POST['is_addon'] === '1';
    $unit_price = 0.0;

    if ($product_id <= 0) {
        $error_message = '無效的商品 ID';
    } elseif ($quantity <= 0) {
        $error_message = '數量必須大於 0';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, price, is_addon FROM products WHERE id = :product_id AND status = 'active'");
            $stmt->execute([':product_id' => $product_id]);
            $product = $stmt->fetch();

            if (!$product) {
                $error_message = '找不到此商品或已下架';
            } else {
                $unit_price = (float)$product['price'];

                $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_sizes WHERE product_id = :product_id");
                $cnt_stmt->execute([':product_id' => $product_id]);
                $has_sizes = (int)$cnt_stmt->fetchColumn() > 0;

                // 加價購（購物車區）：配件 is_addon=1、9 折、購物車尺寸一律 F（不依賴 product_sizes）
                if ($is_addon) {
                    if ((int)$product['is_addon'] !== 1) {
                        throw new Exception('此商品不可加價購');
                    }
                    $size = $cart_size_none;
                    $unit_price = round((float)$product['price'] * 0.9, 2);
                } else {
                    // 一般加入購物車（商品詳情等）
                    $must_select_size = ((int)$product['is_addon'] !== 1) && $has_sizes;

                    if ($must_select_size) {
                        if ($size === '') {
                            throw new Exception('請選擇尺寸');
                        }
                    } else {
                        // 配件（is_addon=1）、或無 product_sizes：購物車尺寸使用 F
                        $size = $cart_size_none;
                    }
                }

                // —— 依尺寸與庫存處理（F = 配件／不分尺寸，不走 product_sizes）——
                if ($size === $cart_size_none) {
                    $max_virtual = 9999;
                    if ($quantity > $max_virtual) {
                        $quantity = $max_virtual;
                    }

                    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id AND size = :size");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':product_id' => $product_id,
                        ':size' => $cart_size_none
                    ]);
                    $existing_cart_item = $stmt->fetch();

                    if ($existing_cart_item) {
                        $new_quantity = $existing_cart_item['quantity'] + $quantity;
                        if ($new_quantity > $max_virtual) {
                            $new_quantity = $max_virtual;
                        }
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = :quantity, unit_price = :unit_price WHERE id = :cart_id");
                        $stmt->execute([
                            ':quantity' => $new_quantity,
                            ':unit_price' => $unit_price,
                            ':cart_id' => $existing_cart_item['id']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, size, quantity, unit_price)
                                               VALUES (:user_id, :product_id, :size, :quantity, :unit_price)");
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':product_id' => $product_id,
                            ':size' => $cart_size_none,
                            ':quantity' => $quantity,
                            ':unit_price' => $unit_price
                        ]);
                    }
                    $success_message = $is_addon ? '已成功加入加價購商品（9 折）' : '已成功加入購物車';
                } else {
                    $stmt = $pdo->prepare("SELECT stock FROM product_sizes WHERE product_id = :product_id AND size = :size");
                    $stmt->execute([':product_id' => $product_id, ':size' => $size]);
                    $size_stock = $stmt->fetch();

                    if (!$size_stock) {
                        throw new Exception('此商品沒有此尺寸的庫存資料');
                    }
                    if ((int)$size_stock['stock'] <= 0) {
                        throw new Exception('此尺寸已售完');
                    }

                    $warn = '';
                    if ($quantity > (int)$size_stock['stock']) {
                        $quantity = (int)$size_stock['stock'];
                        $warn = '數量超過庫存，已自動調整為最大可購買數量：' . $quantity;
                    }

                    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = :user_id AND product_id = :product_id AND size = :size");
                    $stmt->execute([':user_id' => $user_id, ':product_id' => $product_id, ':size' => $size]);
                    $existing_cart_item = $stmt->fetch();

                    if ($existing_cart_item) {
                        $new_quantity = $existing_cart_item['quantity'] + $quantity;
                        if ($new_quantity > (int)$size_stock['stock']) {
                            $new_quantity = (int)$size_stock['stock'];
                            $warn = '購物車中已有此商品，總數量超過庫存，已調整為最大可購買數量：' . $new_quantity;
                        }
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = :quantity, unit_price = :unit_price WHERE id = :cart_id");
                        $stmt->execute([
                            ':quantity' => $new_quantity,
                            ':unit_price' => $unit_price,
                            ':cart_id' => $existing_cart_item['id']
                        ]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, size, quantity, unit_price)
                                               VALUES (:user_id, :product_id, :size, :quantity, :unit_price)");
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':product_id' => $product_id,
                            ':size' => $size,
                            ':quantity' => $quantity,
                            ':unit_price' => $unit_price
                        ]);
                    }

                    $success_message = $is_addon ? '已成功加入加價購商品（9 折）' : '已成功加入購物車';
                    if ($warn !== '') {
                        $success_message .= '（' . $warn . '）';
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = '加入購物車時發生錯誤：' . $e->getMessage();
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }

    $cart_count = 0;
    if ($error_message === '') {
        try {
            $cart_count = getCartItemCount($pdo, $user_id);
        } catch (Exception $e) {
            // 忽略
        }
    }

    if ($error_message !== '') {
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
