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
                       COALESCE(cart.unit_price, p.price) AS price,
                       p.price AS original_price,
                       p.name AS product_name, p.image_url,
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
 * @return array ['subtotal' => 商品小計, 'shipping' => 運費, 'total' => 總金額]
 */
function calculateOrderAmount($cart_items, $shipping_method = 'pickup') {
    // 計算商品小計
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // 統一免運門檻：滿 3000 免運
    $shipping = $subtotal >= getFreeShippingThreshold() ? 0 : 60;
    
    $total = $subtotal + $shipping;
    
    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total' => $total
    ];
}

/**
 * 免運門檻
 */
function getFreeShippingThreshold() {
    return 3000;
}

/**
 * 將優惠券代碼標準化
 */
function normalizeCouponCode($coupon_code) {
    return strtoupper(trim((string)$coupon_code));
}

/**
 * 系統允許的優惠券代碼（僅保留四張）
 */
function getAllowedCouponCodes() {
    return ['NEW100', 'HELMET10', 'SAVE300', 'RIDER20'];
}

/**
 * 清除已套用優惠券 session
 */
function clearAppliedCoupon() {
    unset($_SESSION['applied_coupon']);
}

/**
 * 儲存已套用優惠券到 session
 */
function setAppliedCoupon($coupon) {
    $_SESSION['applied_coupon'] = [
        'coupon_id' => (int)$coupon['id'],
        'coupon_code' => (string)$coupon['coupon_code']
    ];
}

/**
 * 查詢優惠券（依代碼）
 */
function getCouponByCode($pdo, $coupon_code) {
    $normalized_code = normalizeCouponCode($coupon_code);
    if ($normalized_code === '') {
        return null;
    }
    if (!in_array($normalized_code, getAllowedCouponCodes(), true)) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, coupon_code, discount_type, discount_value, minimum_amount, start_date, expire_date, is_active
                               FROM coupons
                               WHERE coupon_code = :coupon_code
                               LIMIT 1");
        $stmt->execute([':coupon_code' => $normalized_code]);
        $coupon = $stmt->fetch();
        return $coupon ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * 查詢優惠券（依 ID）
 */
function getCouponById($pdo, $coupon_id) {
    if ((int)$coupon_id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, coupon_code, discount_type, discount_value, minimum_amount, start_date, expire_date, is_active
                               FROM coupons
                               WHERE id = :id
                               LIMIT 1");
        $stmt->execute([':id' => (int)$coupon_id]);
        $coupon = $stmt->fetch();
        if (!$coupon) {
            return null;
        }
        if (!in_array($coupon['coupon_code'], getAllowedCouponCodes(), true)) {
            return null;
        }
        return $coupon;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * 驗證優惠券是否可用
 */
function validateCoupon($coupon, $subtotal) {
    if (!$coupon) {
        return ['valid' => false, 'message' => '優惠券不存在'];
    }

    if ((int)$coupon['is_active'] !== 1) {
        return ['valid' => false, 'message' => '此優惠券目前已停用'];
    }

    $now = date('Y-m-d');
    if ($now < $coupon['start_date']) {
        return ['valid' => false, 'message' => '此優惠券尚未開始'];
    }

    if ($now > $coupon['expire_date']) {
        return ['valid' => false, 'message' => '此優惠券已過期'];
    }

    if ((float)$subtotal < (float)$coupon['minimum_amount']) {
        return [
            'valid' => false,
            'message' => '未達最低消費門檻 NT$ ' . number_format((float)$coupon['minimum_amount'], 0)
        ];
    }

    if (!in_array($coupon['discount_type'], ['percent', 'fixed'], true)) {
        return ['valid' => false, 'message' => '優惠券折扣類型錯誤'];
    }

    if ((float)$coupon['discount_value'] <= 0) {
        return ['valid' => false, 'message' => '優惠券折扣數值錯誤'];
    }

    return ['valid' => true, 'message' => '優惠券可使用'];
}

/**
 * 計算優惠券折扣金額（折扣只作用於商品小計）
 */
function calculateCouponDiscount($coupon, $subtotal) {
    if (!$coupon) {
        return 0.0;
    }

    $subtotal = (float)$subtotal;
    $discount_value = (float)$coupon['discount_value'];
    $discount = 0.0;

    if ($coupon['discount_type'] === 'percent') {
        $discount = $subtotal * ($discount_value / 100);
    } else {
        $discount = $discount_value;
    }

    if ($discount > $subtotal) {
        $discount = $subtotal;
    }

    return round($discount, 2);
}

/**
 * 取得目前套用優惠券狀態（含重新驗證）
 */
function getAppliedCouponStatus($pdo, $cart_items) {
    $status = [
        'coupon' => null,
        'discount' => 0.0,
        'message' => ''
    ];

    if (empty($_SESSION['applied_coupon'])) {
        return $status;
    }

    $session_coupon = $_SESSION['applied_coupon'];
    $coupon = null;

    if (!empty($session_coupon['coupon_id'])) {
        $coupon = getCouponById($pdo, (int)$session_coupon['coupon_id']);
    }
    if (!$coupon && !empty($session_coupon['coupon_code'])) {
        $coupon = getCouponByCode($pdo, $session_coupon['coupon_code']);
    }

    $subtotal = 0.0;
    foreach ($cart_items as $item) {
        $subtotal += (float)$item['price'] * (int)$item['quantity'];
    }

    $validation = validateCoupon($coupon, $subtotal);
    if (!$validation['valid']) {
        clearAppliedCoupon();
        $status['message'] = '優惠券已失效：' . $validation['message'];
        return $status;
    }

    $status['coupon'] = $coupon;
    $status['discount'] = calculateCouponDiscount($coupon, $subtotal);
    return $status;
}

/**
 * 計算含優惠券的最終金額
 */
function calculateOrderSummary($cart_items, $shipping_method = 'pickup', $coupon = null) {
    $base_amount = calculateOrderAmount($cart_items, $shipping_method);
    $discount = 0.0;

    if ($coupon) {
        $discount = calculateCouponDiscount($coupon, $base_amount['subtotal']);
    }

    $final_total = (float)$base_amount['total'] - $discount;
    if ($final_total < 0) {
        $final_total = 0;
    }

    return [
        'subtotal' => (float)$base_amount['subtotal'],
        'shipping' => (float)$base_amount['shipping'],
        'original_total' => (float)$base_amount['total'],
        'discount' => $discount,
        'final_total' => round($final_total, 2)
    ];
}

/**
 * 優惠活動對照（僅保留四檔）
 */
function getCouponActivityMap() {
    return [
        'NEW100' => [
            'name' => '新會員優惠',
            'content' => '單筆滿 NT$500 折 NT$100'
        ],
        'HELMET10' => [
            'name' => '安全帽週年慶',
            'content' => '全站商品享 9 折優惠'
        ],
        'SAVE300' => [
            'name' => '滿額折扣',
            'content' => '單筆滿 NT$2000 折 NT$300'
        ],
        'RIDER20' => [
            'name' => '騎士節活動',
            'content' => '指定活動享 8 折優惠'
        ]
    ];
}

/**
 * 依優惠券代碼取得活動資訊
 */
function getCouponActivityMeta($coupon_code) {
    $coupon_code = normalizeCouponCode($coupon_code);
    $map = getCouponActivityMap();
    return $map[$coupon_code] ?? [
        'name' => $coupon_code,
        'content' => '優惠活動'
    ];
}

/**
 * 會員是否已領取特定優惠券
 */
function hasUserCoupon($pdo, $user_id, $coupon_code) {
    if ((int)$user_id <= 0) {
        return false;
    }

    $coupon_code = normalizeCouponCode($coupon_code);
    if ($coupon_code === '') {
        return false;
    }

    $coupon = getCouponByCode($pdo, $coupon_code);
    if (!$coupon) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT id
                               FROM user_coupons
                               WHERE user_id = :user_id
                                 AND coupon_id = :coupon_id
                               LIMIT 1");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':coupon_id' => (int)$coupon['id']
        ]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 取得會員領取指定優惠券時間（若可用）
 */
function getUserCouponClaimedAt($pdo, $user_id, $coupon_code) {
    if ((int)$user_id <= 0) {
        return null;
    }

    $coupon_code = normalizeCouponCode($coupon_code);
    if ($coupon_code === '') {
        return null;
    }

    $coupon = getCouponByCode($pdo, $coupon_code);
    if (!$coupon) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT claimed_at, created_at
                               FROM user_coupons
                               WHERE user_id = :user_id
                                 AND coupon_id = :coupon_id
                               ORDER BY id DESC
                               LIMIT 1");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':coupon_id' => (int)$coupon['id']
        ]);
        $row = $stmt->fetch();
        if ($row) {
            $claimed_at = $row['claimed_at'] ?? null;
            $created_at = $row['created_at'] ?? null;
            return $claimed_at ?: ($created_at ?: null);
        }
    } catch (PDOException $e) {
        // fallback below
    }

    try {
        $stmt = $pdo->prepare("SELECT created_at
                               FROM user_coupons
                               WHERE user_id = :user_id
                                 AND coupon_id = :coupon_id
                               ORDER BY id DESC
                               LIMIT 1");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':coupon_id' => (int)$coupon['id']
        ]);
        $row = $stmt->fetch();
        if ($row && !empty($row['created_at'])) {
            return $row['created_at'];
        }
    } catch (PDOException $e) {
        return null;
    }

    return null;
}

/**
 * 領取會員優惠券
 */
function claimUserCoupon($pdo, $user_id, $coupon_code) {
    $coupon_code = normalizeCouponCode($coupon_code);
    if ((int)$user_id <= 0 || $coupon_code === '') {
        return ['success' => false, 'message' => '資料不完整'];
    }

    $coupon = getCouponByCode($pdo, $coupon_code);
    if (!$coupon || !in_array($coupon_code, getAllowedCouponCodes(), true)) {
        return ['success' => false, 'message' => '此優惠券不可領取'];
    }

    try {
        if (hasUserCoupon($pdo, $user_id, $coupon_code)) {
            return ['success' => false, 'message' => '您已領取過此優惠券'];
        }

        $stmt = $pdo->prepare("INSERT INTO user_coupons (user_id, coupon_id, status)
                               VALUES (:user_id, :coupon_id, 'unused')");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':coupon_id' => (int)$coupon['id']
        ]);
        return ['success' => true, 'message' => '優惠券領取成功'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => '領取失敗，請稍後再試'];
    }
}

/**
 * 檢查會員是否可使用指定優惠券（必須為 unused）
 */
function validateUserCouponOwnership($pdo, $user_id, $coupon_code) {
    $coupon_code = normalizeCouponCode($coupon_code);
    if ((int)$user_id <= 0 || $coupon_code === '') {
        return ['valid' => false, 'message' => '請先登入會員'];
    }

    $coupon = getCouponByCode($pdo, $coupon_code);
    if (!$coupon) {
        return ['valid' => false, 'message' => '此優惠券不存在'];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, status
                               FROM user_coupons
                               WHERE user_id = :user_id
                                 AND coupon_id = :coupon_id
                               LIMIT 1");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':coupon_id' => (int)$coupon['id']
        ]);
        $user_coupon = $stmt->fetch();

        if (!$user_coupon) {
            return ['valid' => false, 'message' => '此優惠券尚未領取'];
        }
        if ($user_coupon['status'] !== 'unused') {
            return ['valid' => false, 'message' => '此優惠券已使用'];
        }

        return ['valid' => true, 'message' => '可使用'];
    } catch (PDOException $e) {
        return ['valid' => false, 'message' => '驗證會員優惠券時發生錯誤'];
    }
}

/**
 * 將會員優惠券標記為已使用
 */
function markUserCouponUsed($pdo, $user_id, $coupon_code) {
    $coupon_code = normalizeCouponCode($coupon_code);
    if ((int)$user_id <= 0 || $coupon_code === '') {
        return false;
    }

    $coupon = getCouponByCode($pdo, $coupon_code);
    if (!$coupon) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("UPDATE user_coupons
                               SET status = 'used'
                               WHERE user_id = :user_id
                                 AND coupon_id = :coupon_id
                                 AND status = 'unused'");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':coupon_id' => (int)$coupon['id']
        ]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 取得會員收藏商品 ID 清單
 */
function getUserFavoriteProductIds($pdo, $user_id) {
    if ((int)$user_id <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT product_id FROM favorites WHERE user_id = :user_id");
        $stmt->execute([':user_id' => (int)$user_id]);
        $rows = $stmt->fetchAll();
        return array_map('intval', array_column($rows, 'product_id'));
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * 判斷會員是否已收藏商品
 */
function isProductFavorited($pdo, $user_id, $product_id) {
    if ((int)$user_id <= 0 || (int)$product_id <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = :user_id AND product_id = :product_id LIMIT 1");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':product_id' => (int)$product_id
        ]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 收藏商品
 */
function addFavorite($pdo, $user_id, $product_id) {
    if ((int)$user_id <= 0 || (int)$product_id <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (:user_id, :product_id)");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':product_id' => (int)$product_id
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 取消收藏商品
 */
function removeFavorite($pdo, $user_id, $product_id) {
    if ((int)$user_id <= 0 || (int)$product_id <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->execute([
            ':user_id' => (int)$user_id,
            ':product_id' => (int)$product_id
        ]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 切換收藏狀態，回傳最新狀態
 */
function toggleFavorite($pdo, $user_id, $product_id) {
    $currently_favorited = isProductFavorited($pdo, $user_id, $product_id);
    if ($currently_favorited) {
        removeFavorite($pdo, $user_id, $product_id);
        return false;
    }

    addFavorite($pdo, $user_id, $product_id);
    return true;
}

/**
 * 取得收藏總數
 */
function getFavoriteCount($pdo, $user_id) {
    if ((int)$user_id <= 0) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM favorites WHERE user_id = :user_id");
        $stmt->execute([':user_id' => (int)$user_id]);
        $row = $stmt->fetch();
        return isset($row['total']) ? (int)$row['total'] : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

