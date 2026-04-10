<?php
/**
 * 購物車相關共用函數
 */
require_once __DIR__ . '/product_card_image.php';
require_once __DIR__ . '/product_query_helpers.php';

/**
 * 購物車 cart.size：配件／不需選尺寸時固定為 F（須與資料庫 ENUM 一致）
 */
function getCartSizeNoneValue() {
    return 'F';
}

/**
 * 購物車／訂單／列表顯示用尺寸文字。
 * 配件代號 F、舊資料 N、NULL／空字串：顯示「統一尺寸」（不顯示字母 F）。
 * 安全帽維持 S／M／L／XL 原樣。
 */
function formatCartSizeForDisplay($size) {
    $size = (string)($size ?? '');
    if ($size === '' || $size === getCartSizeNoneValue() || $size === 'N') {
        return '統一尺寸';
    }
    return $size;
}

/**
 * 購物車品項「尺寸」顯示：配件／無尺寸／周邊與配件分類 → F（統一尺寸）；其餘維持 S/M/L/XL 等。
 */
function formatCartLineSizeDisplay($size, $category_id, $parts_category_id) {
    $cid = (int)$category_id;
    $pid = $parts_category_id !== null ? (int)$parts_category_id : 0;
    if ($pid > 0 && $cid === $pid) {
        return 'F（統一尺寸）';
    }
    $s = trim((string)($size ?? ''));
    $none = getCartSizeNoneValue();
    if ($s === '' || strtoupper($s) === 'N' || $s === $none) {
        return 'F（統一尺寸）';
    }
    return formatCartSizeForDisplay($s);
}

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
                       p.name AS product_name,
                       p.category_id AS category_id,
                       " . primaryImageSubquery('p', 'pi') . " AS primary_image,
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
    $default = 3000;
    try {
        global $pdo;
        if ($pdo instanceof PDO) {
            $check = $pdo->query("SHOW TABLES LIKE 'settings'");
            if ($check && $check->fetchColumn()) {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1");
                $stmt->execute([':k' => 'free_shipping_threshold']);
                $val = $stmt->fetchColumn();
                if ($val !== false && is_numeric((string)$val)) {
                    $num = (float)$val;
                    if ($num >= 0) {
                        return $num;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // fallback to default
    }
    return $default;
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
 * 寫入 orders 的 total_amount / discount_amount / final_amount（不依賴資料表預設值）
 *
 * - total_amount：商品小計 + 運費（折前合計，等同 calculateOrderSummary 的 original_total）
 * - discount_amount：優惠券折扣；null、空字串或非數字視為 0
 * - final_amount：應付金額（等同 calculateOrderSummary 的 final_total，即 total_amount - 折扣後四捨五入）
 */
function build_orders_amount_fields(array $order_summary) {
    $total_amount = round((float)($order_summary['original_total'] ?? 0), 2);

    $raw_discount = $order_summary['discount'] ?? 0;
    if ($raw_discount === null || $raw_discount === '' || !is_numeric($raw_discount)) {
        $discount_amount = 0.0;
    } else {
        $discount_amount = round((float)$raw_discount, 2);
    }
    if (!is_finite($discount_amount) || $discount_amount < 0) {
        $discount_amount = 0.0;
    }

    $final_amount = round((float)($order_summary['final_total'] ?? ($total_amount - $discount_amount)), 2);
    if ($final_amount < 0) {
        $final_amount = 0.0;
    }

    return [
        'total_amount' => $total_amount,
        'discount_amount' => $discount_amount,
        'final_amount' => $final_amount,
    ];
}

/**
 * 訂單應付金額（相容舊資料：僅寫入折後 total_amount 且 final_amount、discount_amount 皆為 0 時）
 */
function get_order_payable_amount(array $order) {
    $final = isset($order['final_amount']) ? (float)$order['final_amount'] : 0.0;
    $discount = isset($order['discount_amount']) ? (float)$order['discount_amount'] : 0.0;
    $total = isset($order['total_amount']) ? (float)$order['total_amount'] : 0.0;

    if ($discount > 0.00001 || $final > 0.00001) {
        return round($final, 2);
    }

    return round($total, 2);
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
 * 購物車／列表用：依 coupons 資料列產生折扣說明一行（滿額 + 折價或％）
 */
function describeCouponOfferLine($coupon) {
    if (!$coupon) {
        return '';
    }
    $min = (float)$coupon['minimum_amount'];
    $minPart = '滿 NT$' . number_format($min, 0);
    $type = (string)($coupon['discount_type'] ?? '');
    $val = (float)$coupon['discount_value'];
    if ($type === 'percent') {
        $pct = fmod($val, 1.0) === 0.0 ? (string)(int)$val : (string)$val;
        return $minPart . ' 享 ' . $pct . '% 折扣';
    }
    if ($type === 'fixed') {
        return $minPart . ' 折 NT$' . number_format($val, 0);
    }
    return $minPart;
}

/**
 * 目前購物車小計下，會員可立即套用的優惠券（已領取、未在「完成態」訂單耗用、券有效、達門檻）
 *
 * @return array<int, array{name: string, offer_line: string, coupon_code: string, coupon: array}>
 */
function getCartApplicableCouponsForUser($pdo, $user_id, $cart_subtotal) {
    if ((int)$user_id <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("SELECT c.id, c.coupon_code, c.discount_type, c.discount_value, c.minimum_amount,
                                      c.start_date, c.expire_date, c.is_active
                               FROM user_coupons uc
                               INNER JOIN coupons c ON uc.coupon_id = c.id
                               WHERE uc.user_id = :user_id");
        $stmt->execute([':user_id' => (int)$user_id]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }

    $subtotal = (float)$cart_subtotal;
    $out = [];

    foreach ($rows as $row) {
        $code = normalizeCouponCode((string)($row['coupon_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        $coupon = getCouponByCode($pdo, $code);
        if (!$coupon) {
            continue;
        }
        $ownership = validateUserCouponOwnership($pdo, (int)$user_id, $code);
        if (!$ownership['valid']) {
            continue;
        }
        $validation = validateCoupon($coupon, $subtotal);
        if (!$validation['valid']) {
            continue;
        }
        $meta = getCouponActivityMeta($code);
        $out[] = [
            'name' => (string)($meta['name'] ?? $code),
            'offer_line' => describeCouponOfferLine($coupon),
            'coupon_code' => $code,
            'coupon' => $coupon
        ];
    }

    usort($out, static function ($a, $b) {
        return (float)$a['coupon']['minimum_amount'] <=> (float)$b['coupon']['minimum_amount'];
    });

    return $out;
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
 * 訂單狀態：視為「優惠券已在實際流程中耗用」（與 pending／待付款區隔）
 */
function orderStatusesThatConsumeUserCoupon(): array {
    return ['paid', 'shipped', 'completed', 'done'];
}

/**
 * 會員是否已在「完成態」訂單中使用過此 coupon_id（以 orders 為準）
 */
function userHasConsumedCouponInCompletedOrder($pdo, $user_id, $coupon_id) {
    $user_id = (int)$user_id;
    $coupon_id = (int)$coupon_id;
    if ($user_id <= 0 || $coupon_id <= 0) {
        return false;
    }

    $statuses = orderStatusesThatConsumeUserCoupon();
    try {
        $placeholders = [];
        $params = [':uid' => $user_id, ':cid' => $coupon_id];
        foreach ($statuses as $i => $st) {
            $k = ':st' . $i;
            $placeholders[] = $k;
            $params[$k] = $st;
        }
        $in = implode(', ', $placeholders);
        $stmt = $pdo->prepare("SELECT 1 FROM orders
                               WHERE user_id = :uid AND coupon_id = :cid
                                 AND LOWER(TRIM(status)) IN ($in)
                               LIMIT 1");
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 會員各張已耗用優惠券的「使用日期」（取訂單 updated_at/created_at 較新者），coupon_id => Y-m-d
 *
 * @return array<int, string>
 */
function getUserCouponConsumedDatesMap($pdo, $user_id) {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return [];
    }

    $statuses = orderStatusesThatConsumeUserCoupon();
    try {
        $placeholders = [];
        $params = [':uid' => $user_id];
        foreach ($statuses as $i => $st) {
            $k = ':st' . $i;
            $placeholders[] = $k;
            $params[$k] = $st;
        }
        $in = implode(', ', $placeholders);
        $stmt = $pdo->prepare("SELECT coupon_id,
                                      MAX(COALESCE(updated_at, created_at)) AS used_at
                               FROM orders
                               WHERE user_id = :uid
                                 AND coupon_id IS NOT NULL
                                 AND coupon_id > 0
                                 AND LOWER(TRIM(status)) IN ($in)
                               GROUP BY coupon_id");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $map = [];
    foreach ($rows as $row) {
        $cid = (int)($row['coupon_id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $ts = strtotime((string)($row['used_at'] ?? ''));
        $map[$cid] = $ts ? date('Y-m-d', $ts) : '';
    }
    return $map;
}

/**
 * 訂單狀態變更為「已耗用優惠券」之狀態時，同步 user_coupons（與列表邏輯一致）
 */
function markUserCouponUsedAfterOrderStatusChange($pdo, $order_id, $new_status) {
    $order_id = (int)$order_id;
    $new_status = strtolower(trim((string)$new_status));
    if ($order_id <= 0 || !in_array($new_status, orderStatusesThatConsumeUserCoupon(), true)) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, coupon_id FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $order_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $uid = (int)($row['user_id'] ?? 0);
        $cid = (int)($row['coupon_id'] ?? 0);
        if ($uid <= 0 || $cid <= 0) {
            return;
        }
        $coupon = getCouponById($pdo, $cid);
        if (!$coupon) {
            return;
        }
        markUserCouponUsed($pdo, $uid, (string)$coupon['coupon_code']);
    } catch (PDOException $e) {
        // ignore
    }
}

/**
 * 檢查會員是否可使用指定優惠券（已領取，且尚未在「完成態」訂單中耗用）
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
        $stmt = $pdo->prepare("SELECT id
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
        if (userHasConsumedCouponInCompletedOrder($pdo, (int)$user_id, (int)$coupon['id'])) {
            return ['valid' => false, 'message' => '此優惠券已使用'];
        }

        return ['valid' => true, 'message' => '可使用'];
    } catch (PDOException $e) {
        return ['valid' => false, 'message' => '驗證會員優惠券時發生錯誤'];
    }
}

/**
 * 將會員優惠券標記為已使用（應於訂單進入 paid/shipped/completed/done 時呼叫，與 orders 一致）
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
 * 會員購物車套用優惠券（與 cart.php POST 一致：驗證擁有權、條件，成功則寫入 $_SESSION['applied_coupon']；不寫入 user_coupons used）
 *
 * @return array{ok: bool, message: string, type: string, reason: ?string} reason: already_applied|empty_cart|zero_discount|below_minimum|ownership|empty|validation|exception
 */
function applyMemberCouponFromCode($pdo, $user_id, $coupon_code) {
    $normalized = normalizeCouponCode($coupon_code);
    if ($normalized === '') {
        return ['ok' => false, 'message' => '請輸入優惠券代碼', 'type' => 'error', 'reason' => 'empty'];
    }

    if (!empty($_SESSION['applied_coupon']['coupon_code'])
        && normalizeCouponCode((string)$_SESSION['applied_coupon']['coupon_code']) === $normalized) {
        return ['ok' => true, 'message' => '', 'type' => 'success', 'reason' => 'already_applied'];
    }

    $cart_items = getCartItems($pdo, $user_id);
    $subtotal = 0.0;
    foreach ($cart_items as $item) {
        $subtotal += (float)$item['price'] * (int)$item['quantity'];
    }

    if ($cart_items === [] || $subtotal <= 0) {
        clearAppliedCoupon();
        $_SESSION['coupon_code_prefill'] = $normalized;
        return [
            'ok' => false,
            'message' => '購物車目前沒有商品，請先加入商品後再使用優惠券。',
            'type' => 'warning',
            'reason' => 'empty_cart'
        ];
    }

    try {
        $ownership = validateUserCouponOwnership($pdo, $user_id, $normalized);
        if (!$ownership['valid']) {
            clearAppliedCoupon();
            unset($_SESSION['coupon_code_prefill']);
            return [
                'ok' => false,
                'message' => $ownership['message'],
                'type' => 'error',
                'reason' => 'ownership'
            ];
        }

        $coupon = getCouponByCode($pdo, $normalized);
        $validation = validateCoupon($coupon, $subtotal);

        if ($validation['valid']) {
            $discount_amount = calculateCouponDiscount($coupon, $subtotal);
            if ($discount_amount <= 0) {
                clearAppliedCoupon();
                unset($_SESSION['coupon_code_prefill']);
                return [
                    'ok' => false,
                    'message' => '目前購物車金額無法產生折扣，請確認商品小計是否符合此優惠券使用條件。',
                    'type' => 'warning',
                    'reason' => 'zero_discount'
                ];
            }
            setAppliedCoupon($coupon);
            unset($_SESSION['coupon_code_prefill']);
            return [
                'ok' => true,
                'message' => '優惠券套用成功，折扣 NT$ ' . number_format($discount_amount, 0),
                'type' => 'success',
                'reason' => null
            ];
        }

        clearAppliedCoupon();
        $reason = 'validation';
        if ($coupon && (float)$subtotal < (float)$coupon['minimum_amount']) {
            $reason = 'below_minimum';
        } else {
            unset($_SESSION['coupon_code_prefill']);
        }

        return [
            'ok' => false,
            'message' => $validation['message'],
            'type' => 'error',
            'reason' => $reason
        ];
    } catch (PDOException $e) {
        unset($_SESSION['coupon_code_prefill']);
        return [
            'ok' => false,
            'message' => '套用優惠券時發生錯誤，請稍後再試',
            'type' => 'error',
            'reason' => 'exception'
        ];
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

