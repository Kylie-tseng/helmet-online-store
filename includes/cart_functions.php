<?php
/**
 * 購物車相關共用函數
 */

/**
 * 取得使用者購物車商品總數
 */
function getCartItemCount($pdo, $user_id) {
    if (!$user_id) {
        return 0;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch();
        return $result['total'] ? (int)$result['total'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * 取得購物車內容
 */
function getCartItems($pdo, $user_id) {
    if (!$user_id) {
        return [];
    }
    
    try {
        $sql = "SELECT cart.id AS cart_id, cart.product_id, cart.size, cart.quantity,
                       p.name AS product_name, p.price, p.image_url,
                       cat.name AS category_name
                FROM cart
                INNER JOIN products p ON cart.product_id = p.id
                INNER JOIN categories cat ON p.category_id = cat.id
                WHERE cart.user_id = :user_id
                ORDER BY cart.added_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * 計算訂單金額（含運費）
 * @param array $cart_items 購物車項目
 * @param string $shipping_method 送貨方式 ('pickup' 超商取貨, 'home' 宅配)
 * @return array ['subtotal' => 商品小計, 'shipping' => 運費, 'total' => 總金額]
 */
function calculateOrderAmount($cart_items, $shipping_method = 'pickup') {
    // 計算商品小計
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // 計算運費
    $shipping = 60; // 預設運費
    
    if ($shipping_method === 'pickup') {
        // 超商取貨：滿 199 免運
        if ($subtotal >= 199) {
            $shipping = 0;
        }
    } elseif ($shipping_method === 'home') {
        // 宅配：滿 490 免運
        if ($subtotal >= 490) {
            $shipping = 0;
        }
    }
    
    $total = $subtotal + $shipping;
    
    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total' => $total
    ];
}

