<?php
require_once 'config.php';
require_once 'includes/cart_functions.php';
require_once 'includes/navbar.php';
require_once 'includes/order_status_helpers.php';
require_once __DIR__ . '/includes/reviews_init.php';

// 檢查是否已登入
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('profile.php') . '&notice=profile');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'info'; // 預設顯示個人資料分頁

// 設定頁籤分頁內的篩選條件（使用者端）
$order_q = trim((string)($_GET['order_q'] ?? ''));
$order_status_filter = trim((string)($_GET['order_status'] ?? '')); // processing/paid/shipped/completed/cancelled
$allowedOrderStatusFilters = ['', 'processing', 'paid', 'shipped', 'completed', 'cancelled'];
if ($order_status_filter !== '' && !in_array($order_status_filter, $allowedOrderStatusFilters, true)) {
    $order_status_filter = '';
}

// 我的優惠券分頁篩選：all/unused/used/expired
$coupon_status_filter = trim((string)($_GET['coupon_status'] ?? 'all'));
$allowedCouponStatusFilters = ['all', 'unused', 'used', 'expired'];
if (!in_array($coupon_status_filter, $allowedCouponStatusFilters, true)) {
    $coupon_status_filter = 'all';
}

// 欄位存在性檢查：避免硬寫入不存在的資料欄
$hasBirthdayColumn = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if ((string)$col['Field'] === 'birthday') {
            $hasBirthdayColumn = true;
            break;
        }
    }
} catch (Throwable $e) {
    $hasBirthdayColumn = false;
}

$hasUserCouponsClaimedAtColumn = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM user_coupons");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if ((string)$col['Field'] === 'claimed_at') {
            $hasUserCouponsClaimedAtColumn = true;
            break;
        }
    }
} catch (Throwable $e) {
    $hasUserCouponsClaimedAtColumn = false;
}

// reviews 資料表初始化/補齊（避免欄位缺失導致評價流程壞掉）
$reviewsEnsure = reviewsEnsureTable($pdo);

// 評價（reviews）資料表存在性與欄位偵測：用於使用者提交/顯示自己的評價
$reviewsTableExists = false;
$reviewsHasOrderIdColumn = !empty($reviewsEnsure['has_order_id']);
$reviewsRequiredColumns = [
    'rating' => false,
    'comment' => false,
    'product_id' => false,
    'user_id' => false,
];
$reviewsHiddenColumnName = '';
try {
    $check = $pdo->query("SHOW TABLES LIKE 'reviews'");
    $reviewsTableExists = (bool)$check->fetchColumn();
    if ($reviewsTableExists) {
        $cols = [];
        $cstmt = $pdo->query("SHOW COLUMNS FROM reviews");
        foreach ($cstmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $field = (string)($c['Field'] ?? '');
            $cols[$field] = true;
        }

        foreach (array_keys($reviewsRequiredColumns) as $col) {
            if (!empty($cols[$col])) {
                $reviewsRequiredColumns[$col] = true;
            }
        }

        if (!empty($cols['is_hidden'])) {
            $reviewsHiddenColumnName = 'is_hidden';
        } elseif (!empty($cols['hidden'])) {
            $reviewsHiddenColumnName = 'hidden';
        }
    }
} catch (Throwable $e) {
    $reviewsTableExists = false;
}

$reviewsReadyForUser = $reviewsTableExists
    && $reviewsRequiredColumns['rating']
    && $reviewsRequiredColumns['comment']
    && $reviewsRequiredColumns['product_id']
    && $reviewsRequiredColumns['user_id'];

// 處理更改密碼
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = '請填寫所有欄位';
    } elseif ($new_password !== $confirm_password) {
        $error = '新密碼與確認密碼不一致';
    } elseif (strlen($new_password) < 6) {
        $error = '新密碼長度至少需要6個字元';
    } else {
        try {
            // 查詢使用者目前的密碼
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $user_data = $stmt->fetch();
            
            if (!$user_data) {
                $error = '找不到使用者資料';
            } elseif (!password_verify($old_password, $user_data['password'])) {
                $error = '舊密碼錯誤';
            } else {
                // 更新密碼
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id");
                $stmt->execute([
                    ':password' => $hashed_password,
                    ':user_id' => $user_id
                ]);
                
                $success = '密碼已成功更新';
                $active_tab = 'password'; // 保持在更改密碼分頁
            }
        } catch (PDOException $e) {
            $error = '更新密碼時發生錯誤：' . $e->getMessage();
        }
    }
    $active_tab = 'password';
}

// 處理取消訂單
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    if ($order_id > 0) {
        try {
            // 檢查訂單是否屬於該使用者且狀態為未出貨
            $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = :order_id AND user_id = :user_id");
            $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                $error = '找不到此訂單';
            } elseif (!in_array($order['status'], ['pending', 'pending_payment', 'paid'])) {
                $error = '此訂單無法取消';
            } else {
                $pdo->beginTransaction();
                
                // 更新訂單狀態為已取消
                $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :order_id");
                $stmt->execute([':order_id' => $order_id]);
                
                // 還原庫存
                $stmt = $pdo->prepare("SELECT product_id, size, quantity FROM order_items WHERE order_id = :order_id");
                $stmt->execute([':order_id' => $order_id]);
                $order_items = $stmt->fetchAll();
                
                foreach ($order_items as $item) {
                    $sz = $item['size'] ?? null;
                    if ($sz === null || $sz === '' || $sz === getCartSizeNoneValue() || $sz === 'N') {
                        continue;
                    }
                    $stmt = $pdo->prepare("UPDATE product_sizes SET stock = stock + :quantity, updated_at = NOW() 
                                         WHERE product_id = :product_id AND size = :size");
                    $stmt->execute([
                        ':quantity' => $item['quantity'],
                        ':product_id' => $item['product_id'],
                        ':size' => $sz
                    ]);
                }
                
                $pdo->commit();
                $success = '訂單已成功取消';
                $active_tab = 'orders';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = '取消訂單時發生錯誤：' . $e->getMessage();
        }
    }
    $active_tab = 'orders';
}

// 處理退貨申請
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_return') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));

    // 預設訊息
    $active_tab = 'orders';

    // 檢查 return_requests 表是否存在
    $returnsTableExists = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'return_requests'");
        $returnsTableExists = (bool)$check->fetchColumn();
    } catch (Throwable $e) {
        $returnsTableExists = false;
    }

    if (!$returnsTableExists) {
        $error = '系統尚未建立退貨申請資料表，無法送出申請。';
    } elseif ($order_id <= 0 || $reason === '') {
        $error = '請填寫正確的訂單與退貨原因。';
    } else {
        // 檢查訂單是否屬於此使用者
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1");
            $stmt->execute([':id' => $order_id, ':user_id' => $user_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $error = '找不到此訂單';
            } else {
                $allowedReturnOrderStatuses = ['completed', 'shipped'];
                $currentStatus = (string)($order['status'] ?? '');
                if (!in_array($currentStatus, $allowedReturnOrderStatuses, true)) {
                    $error = '此訂單目前不可申請退貨';
                } else {
                    // 檢查 return_requests 欄位存在性，避免硬寫造成 SQL 錯誤
                    $cols = [];
                    try {
                        $cstmt = $pdo->query("SHOW COLUMNS FROM return_requests");
                        foreach ($cstmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                            $field = (string)($c['Field'] ?? '');
                            $cols[$field] = true;
                        }
                    } catch (Throwable $e) {
                        $cols = [];
                    }

                    $hasOrderIdCol = !empty($cols['order_id']);
                    $hasUserIdCol = !empty($cols['user_id']);
                    $hasReasonCol = !empty($cols['reason']);
                    $hasStatusCol = !empty($cols['status']);
                    $hasRefundStatusCol = !empty($cols['refund_status']);
                    $hasCreatedAtCol = !empty($cols['created_at']);
                    $hasUpdatedAtCol = !empty($cols['updated_at']);

                    if (!$hasOrderIdCol || !$hasReasonCol || !$hasStatusCol || !$hasRefundStatusCol) {
                        $error = '退貨申請資料格式不完整，無法送出。';
                    } else {
                        // 防止重複申請
                        $dupWhere = "order_id = :order_id";
                        $dupParams = [':order_id' => $order_id, ':user_id' => $user_id];
                        if ($hasUserIdCol) {
                            $dupWhere .= " AND user_id = :user_id";
                        }

                        $dupSql = "SELECT id FROM return_requests WHERE {$dupWhere} LIMIT 1";
                        $stmt = $pdo->prepare($dupSql);
                        $stmt->execute($dupParams);
                        if ($stmt->fetch()) {
                            $error = '您已對此訂單提交過退貨申請';
                        } else {
                            $insertCols = [];
                            $insertVals = [];
                            $insertParams = [];

                            // order_id
                            $insertCols[] = 'order_id';
                            $insertVals[] = ':order_id';
                            $insertParams[':order_id'] = $order_id;

                            // user_id
                            if ($hasUserIdCol) {
                                $insertCols[] = 'user_id';
                                $insertVals[] = ':user_id';
                                $insertParams[':user_id'] = $user_id;
                            }

                            // reason
                            $insertCols[] = 'reason';
                            $insertVals[] = ':reason';
                            $insertParams[':reason'] = $reason;

                            // status / refund_status
                            $insertCols[] = 'status';
                            $insertVals[] = ':status';
                            $insertParams[':status'] = 'pending';

                            $insertCols[] = 'refund_status';
                            $insertVals[] = ':refund_status';
                            $insertParams[':refund_status'] = 'pending_refund';

                            // timestamps
                            if ($hasCreatedAtCol) {
                                $insertCols[] = 'created_at';
                                $insertVals[] = 'NOW()';
                            }
                            if ($hasUpdatedAtCol) {
                                $insertCols[] = 'updated_at';
                                $insertVals[] = 'NOW()';
                            }

                            $sql = "INSERT INTO return_requests (" . implode(', ', $insertCols) . ")
                                    VALUES (" . implode(', ', $insertVals) . ")";

                            $istmt = $pdo->prepare($sql);
                            $istmt->execute($insertParams);
                            $success = '退貨申請已送出';
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $error = '送出退貨申請時發生錯誤：' . $e->getMessage();
        }
    }
}

// 處理使用者送出評價
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));

    $active_tab = 'orders';

    if (!$reviewsReadyForUser) {
        $error = '目前系統尚未支援評價功能（reviews 資料表或欄位缺失）。';
    } elseif ($order_id <= 0 || $product_id <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
        $error = '請填寫完整的評價資料。';
    } else {
        try {
            // 檢查訂單屬於此使用者且包含該商品
            $stmt = $pdo->prepare("SELECT oi.product_id
                                   FROM orders o
                                   INNER JOIN order_items oi ON oi.order_id = o.id
                                   WHERE o.id = :order_id
                                     AND o.user_id = :user_id
                                     AND o.status = 'completed'
                                     AND oi.product_id = :product_id
                                   LIMIT 1");
            $stmt->execute([
                ':order_id' => $order_id,
                ':user_id' => $user_id,
                ':product_id' => $product_id
            ]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = '找不到符合條件的訂單商品，無法送出評價。';
            } else {
                // 送出評價前，確認該商品是否已被評價過
                if ($reviewsHasOrderIdColumn) {
                    $dupWhere = "user_id = :user_id AND product_id = :product_id AND order_id = :order_id";
                    $dupParams = [
                        ':user_id' => $user_id,
                        ':product_id' => $product_id,
                        ':order_id' => $order_id
                    ];
                } else {
                    $dupWhere = "user_id = :user_id AND product_id = :product_id";
                    $dupParams = [
                        ':user_id' => $user_id,
                        ':product_id' => $product_id
                    ];
                }
                $dupSql = "SELECT id FROM reviews WHERE {$dupWhere} LIMIT 1";
                $dupStmt = $pdo->prepare($dupSql);
                $dupStmt->execute($dupParams);
                if ($dupStmt->fetch(PDO::FETCH_ASSOC)) {
                    $error = '您已對此商品送出過評價。';
                } else {
                    // 寫入評價（動態帶入存在欄位）
                    $cols = [];
                    $vals = [];
                    $params = [];

                    $cols[] = 'user_id';
                    $vals[] = ':user_id';
                    $params[':user_id'] = $user_id;

                    $cols[] = 'product_id';
                    $vals[] = ':product_id';
                    $params[':product_id'] = $product_id;

                    $cols[] = 'rating';
                    $vals[] = ':rating';
                    $params[':rating'] = $rating;

                    $cols[] = 'comment';
                    $vals[] = ':comment';
                    $params[':comment'] = $comment;

                    // 額外欄位：created_at / updated_at / order_id（若存在）
                    try {
                        $cstmt = $pdo->query("SHOW COLUMNS FROM reviews");
                        $colsExist = [];
                        foreach ($cstmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                            $field = (string)($c['Field'] ?? '');
                            $colsExist[$field] = true;
                        }

                        if (!empty($colsExist['order_id'])) {
                            $cols[] = 'order_id';
                            $vals[] = ':order_id';
                            $params[':order_id'] = $order_id;
                        }

                        if (!empty($colsExist['created_at'])) {
                            $cols[] = 'created_at';
                            $vals[] = 'NOW()';
                        }
                        if (!empty($colsExist['updated_at'])) {
                            $cols[] = 'updated_at';
                            $vals[] = 'NOW()';
                        }
                    } catch (Throwable $e) {
                        // ignore
                    }

                    $sql = "INSERT INTO reviews (" . implode(', ', $cols) . ")
                            VALUES (" . implode(', ', $vals) . ")";
                    $istmt = $pdo->prepare($sql);
                    $istmt->execute($params);

                    $success = '評價已送出，謝謝您的回饋。';
                }
            }
        } catch (Throwable $e) {
            $error = '送出評價時發生錯誤：' . $e->getMessage();
        }
    }
}

// 處理使用者更新評價
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_review') {
    $review_id = (int)($_POST['review_id'] ?? 0);
    $order_id = (int)($_POST['order_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));

    $active_tab = 'orders';

    if (!$reviewsReadyForUser) {
        $error = '目前系統尚未支援評價功能（reviews 資料表或欄位缺失）。';
    } elseif ($review_id <= 0 || $order_id <= 0 || $product_id <= 0 || $rating < 1 || $rating > 5 || $comment === '') {
        $error = '請填寫完整的評價資料。';
    } else {
        try {
            // 1) 確認該評論屬於此使用者，且未被隱藏（被隱藏時前台只顯示訊息）
            $orderWhere = $reviewsHasOrderIdColumn ? " AND r.order_id = :order_id" : "";
            $hiddenSelect = $reviewsHiddenColumnName !== '' ? ", r.{$reviewsHiddenColumnName} AS is_hidden_value" : ", 0 AS is_hidden_value";
            $sql = "SELECT r.id
                    FROM reviews r
                    WHERE r.id = :review_id
                      AND r.user_id = :user_id
                      AND r.product_id = :product_id
                      {$orderWhere}
                      {$hiddenSelect}
                    LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':review_id' => $review_id,
                ':user_id' => $user_id,
                ':product_id' => $product_id,
                ':order_id' => $order_id,
            ]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $error = '找不到符合條件的評價，無法更新。';
            } elseif ((int)($row['is_hidden_value'] ?? 0) === 1) {
                $error = '此評論已被管理員隱藏，無法修改。';
            } else {
                // 2) 確認該訂單為 completed 且包含該商品
                $stmt = $pdo->prepare("SELECT oi.product_id
                                       FROM orders o
                                       INNER JOIN order_items oi ON oi.order_id = o.id
                                       WHERE o.id = :order_id
                                         AND o.user_id = :user_id
                                         AND o.status = 'completed'
                                         AND oi.product_id = :product_id
                                       LIMIT 1");
                $stmt->execute([
                    ':order_id' => $order_id,
                    ':user_id' => $user_id,
                    ':product_id' => $product_id
                ]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    $error = '此訂單目前不可更新評價。';
                } else {
                    // 3) 更新評價內容
                    $upSql = "UPDATE reviews
                              SET rating = :rating,
                                  comment = :comment,
                                  updated_at = NOW()
                              WHERE id = :review_id AND user_id = :user_id";
                    $upStmt = $pdo->prepare($upSql);
                    $upStmt->execute([
                        ':rating' => $rating,
                        ':comment' => $comment,
                        ':review_id' => $review_id,
                        ':user_id' => $user_id
                    ]);

                    $success = '評價已更新。';
                }
            }
        } catch (Throwable $e) {
            $error = '更新評價時發生錯誤：' . $e->getMessage();
        }
    }
}

// 處理表單提交（個人資料更新）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birthday = $hasBirthdayColumn ? trim((string)($_POST['birthday'] ?? '')) : '';
    
    // 驗證必填欄位
    if (empty($name) || empty($username) || empty($email) || empty($phone) || empty($address)) {
        $error = '請填寫所有必填欄位';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '請輸入有效的電子郵件地址';
    } else {
        try {
            // 檢查 username 和 email 是否已被其他使用者使用
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':user_id' => $user_id
            ]);
            
            if ($stmt->fetch()) {
                $error = '使用者名稱或電子郵件已被使用';
            } else {
                // 更新使用者資料（依欄位存在性動態調整）
                $params = [
                    ':name' => $name,
                    ':username' => $username,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':user_id' => $user_id
                ];

                $updateSql = "UPDATE users
                              SET name = :name,
                                  username = :username,
                                  email = :email,
                                  phone = :phone,
                                  address = :address";

                if ($hasBirthdayColumn) {
                    $params[':birthday'] = ($birthday !== '' ? $birthday : null);
                    $updateSql .= ", birthday = :birthday";
                }

                $updateSql .= ", updated_at = NOW()
                               WHERE id = :user_id";

                $stmt = $pdo->prepare($updateSql);
                $stmt->execute($params);
                
                // 更新 session 中的使用者名稱
                $_SESSION['user_name'] = $name;
                $success = '個人資料已成功更新';
                $active_tab = 'info'; // 更新成功後保持在個人資料分頁
            }
        } catch (PDOException $e) {
            $error = '更新資料時發生錯誤：' . $e->getMessage();
        }
    }
}

// 查詢使用者資料
try {
    $birthdaySelect = $hasBirthdayColumn ? ", birthday" : "";
    $stmt = $pdo->prepare("SELECT id, name, username, email, phone, address{$birthdaySelect} FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: login.php?redirect=' . urlencode('profile.php') . '&notice=profile');
        exit;
    }
} catch (PDOException $e) {
    $error = '讀取資料時發生錯誤：' . $e->getMessage();
    $user = null;
}

// 查詢訂單資料（訂單管理分頁）
$orders = [];
if ($active_tab === 'orders') {
    try {
        $sql = "SELECT id, coupon_id, total_amount, discount_amount, final_amount, status, payment_method, shipping_method, shipping_address, pickup_store, created_at, updated_at
                FROM orders
                WHERE user_id = :user_id";

        $params = [':user_id' => $user_id];

        // 訂單狀態篩選（依系統狀態群組顯示）
        if ($order_status_filter !== '') {
            if ($order_status_filter === 'processing') {
                $sql .= " AND status IN ('pending', 'pending_payment')";
            } else {
                $map = [
                    'paid' => 'paid',
                    'shipped' => 'shipped',
                    'completed' => 'completed',
                    'cancelled' => 'cancelled'
                ];
                if (isset($map[$order_status_filter])) {
                    $sql .= " AND status = :status";
                    $params[':status'] = $map[$order_status_filter];
                }
            }
        }

        // 訂單編號搜尋
        if ($order_q !== '') {
            $sql .= " AND CAST(id AS CHAR) LIKE :q";
            $params[':q'] = '%' . $order_q . '%';
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // 為每個訂單查詢明細（包含尺寸）
        foreach ($orders as &$order) {
            $stmt = $pdo->prepare("SELECT oi.id, oi.product_id, oi.quantity, oi.unit_price, oi.subtotal, oi.size, p.name AS product_name
                                   FROM order_items oi
                                   INNER JOIN products p ON oi.product_id = p.id
                                   WHERE oi.order_id = :order_id");
            $stmt->execute([':order_id' => $order['id']]);
            $order['items'] = $stmt->fetchAll();
            $order['return_requests'] = [];

            try {
                $returnStmt = $pdo->prepare("SELECT id, status, refund_status, reason, created_at, updated_at
                                             FROM return_requests
                                             WHERE order_id = :order_id
                                             ORDER BY created_at DESC");
                $returnStmt->execute([':order_id' => $order['id']]);
                $order['return_requests'] = $returnStmt->fetchAll();
            } catch (PDOException $e) {
                $order['return_requests'] = [];
            }
        }
        unset($order);
    } catch (PDOException $e) {
        $error = '讀取訂單資料時發生錯誤：' . $e->getMessage();
    }
}

// 查詢會員優惠券（我的優惠券分頁）
$user_coupons = [];
if ($active_tab === 'coupons') {
    try {
        // 若資料庫沒有 claimed_at，則以 NULL 回傳（避免在 SQL 裡重複使用 AS claimed_at）
        $claimedAtSelect = $hasUserCouponsClaimedAtColumn ? "uc.claimed_at" : "NULL";

        $stmt = $pdo->prepare("SELECT uc.id,
                                       c.coupon_code,
                                       uc.status,
                                       uc.created_at,
                                       {$claimedAtSelect} AS claimed_at,
                                       c.expire_date,
                                       c.minimum_amount
                               FROM user_coupons uc
                               INNER JOIN coupons c ON uc.coupon_id = c.id
                               WHERE uc.user_id = :user_id
                               ORDER BY uc.created_at DESC");
        $stmt->execute([':user_id' => $user_id]);
        $rows = $stmt->fetchAll();

        $nowDate = date('Y-m-d');
        $couponStatusLabels = [
            'unused' => '可使用',
            'used' => '已使用',
            'expired' => '已過期'
        ];

        // 把資料轉成「可顯示」的狀態與 badge class，同時套用篩選
        $user_coupons = [];
        foreach ($rows as $row) {
            $rawStatus = (string)($row['status'] ?? '');
            $expireDate = (string)($row['expire_date'] ?? '');

            if ($rawStatus === 'used') {
                $effectiveStatus = 'used';
            } else {
                $effectiveStatus = ($expireDate !== '' && $nowDate > $expireDate) ? 'expired' : 'unused';
            }

            if ($coupon_status_filter !== 'all' && $effectiveStatus !== $coupon_status_filter) {
                continue;
            }

            $badgeKey = 'progress';
            if ($effectiveStatus === 'used') {
                $badgeKey = 'done';
            } elseif ($effectiveStatus === 'expired') {
                $badgeKey = 'danger';
            }

            $displayDate = '';
            if ($effectiveStatus === 'used') {
                $claimedAt = $row['claimed_at'] ?? null;
                $ts = $claimedAt ? strtotime((string)$claimedAt) : (strtotime((string)($row['created_at'] ?? '')) ?: null);
                $displayDate = $ts ? date('Y-m-d', $ts) : '';
            } else {
                $ts = $expireDate ? strtotime($expireDate) : null;
                $displayDate = $ts ? date('Y-m-d', $ts) : '';
            }

            $row['effective_status'] = $effectiveStatus;
            $row['effective_badge_class'] = $badgeKey;
            $row['effective_badge_label'] = $couponStatusLabels[$effectiveStatus] ?? $effectiveStatus;
            $row['effective_display_date'] = $displayDate;

            $user_coupons[] = $row;
        }
    } catch (PDOException $e) {
        $error = '讀取優惠券資料時發生錯誤：' . $e->getMessage();
    }
}

// 付款方式顯示名稱
$payment_method_names = [
    'credit_card' => '信用卡',
    'cod' => '貨到付款'
];

// 送貨方式顯示名稱
$shipping_method_names = [
    'pickup' => '超商取貨',
    'home' => '宅配到府'
];

// 取得購物車數量
$cart_count = getCartItemCount($pdo, $user_id);
// 導覽列資料
try {
    $stmt = $pdo->query("SELECT id, name, description FROM categories ORDER BY id");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$parts_category_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = '周邊與配件' LIMIT 1");
    $stmt->execute();
    $parts_category = $stmt->fetch();
    if ($parts_category) {
        $parts_category_id = $parts_category['id'];
    }
} catch (PDOException $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人檔案 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- 導覽列 -->
    <?php renderNavbar($pdo, $categories, $parts_category_id); ?>

    <!-- 個人檔案內容 -->
    <div class="dashboard-container">
        <div class="dashboard-content">
            <!-- 頁面標題區（縮小） -->
            <div class="member-page-header">
                <h1 class="member-page-title">個人檔案</h1>
                <p class="member-page-subtitle">管理您的個人資訊、密碼、訂單與優惠券</p>
            </div>

            <!-- 功能分頁列（膠囊按鈕） -->
            <nav class="member-tabs" aria-label="會員中心功能分頁">
                <a href="profile.php?tab=info" class="member-tab <?php echo $active_tab === 'info' ? 'active' : ''; ?>">
                    個人資料
                </a>
                <a href="profile.php?tab=password" class="member-tab <?php echo $active_tab === 'password' ? 'active' : ''; ?>">
                    修改密碼
                </a>
                <a href="profile.php?tab=orders" class="member-tab <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">
                    訂單管理
                </a>
                <a href="profile.php?tab=coupons" class="member-tab <?php echo $active_tab === 'coupons' ? 'active' : ''; ?>">
                    我的優惠券
                </a>
            </nav>

            <!-- 分頁內容 -->
            <div class="member-main-card">
                <?php if ($active_tab === 'info'): ?>
                    <!-- 個人資料分頁 -->
                    <div class="member-section">
                        <div class="member-panel-toolbar">
                            <div class="member-toolbar-left">
                                <div class="member-toolbar-title">個人資料</div>
                                <div class="member-toolbar-desc">更新您的基本會員資訊</div>
                            </div>
                            <div class="member-toolbar-actions">
                                <a href="profile.php?tab=info" class="member-btn member-btn--soft member-btn--toolbar" role="button" aria-label="取消">
                                    取消
                                </a>
                                <button type="submit" form="member-profile-form" class="member-btn member-btn--primary member-btn--toolbar">
                                    儲存變更
                                </button>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="member-feedback member-feedback--error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="member-feedback member-feedback--success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <?php if ($user): ?>
                            <form method="POST" action="profile.php?tab=info" id="member-profile-form" class="member-form">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="member-form-grid">
                                    <div class="member-field">
                                        <label class="member-form-label">姓名 <span class="member-required">*</span></label>
                                        <input type="text" name="name" class="member-input" value="<?php echo htmlspecialchars((string)$user['name']); ?>" required>
                                    </div>

                                    <div class="member-field">
                                        <label class="member-form-label">帳號名稱 <span class="member-required">*</span></label>
                                        <input type="text" name="username" class="member-input" value="<?php echo htmlspecialchars((string)$user['username']); ?>" required>
                                    </div>

                                    <div class="member-field">
                                        <label class="member-form-label">電子郵件 <span class="member-required">*</span></label>
                                        <input type="email" name="email" class="member-input" value="<?php echo htmlspecialchars((string)$user['email']); ?>" required>
                                    </div>

                                    <div class="member-field">
                                        <label class="member-form-label">手機 <span class="member-required">*</span></label>
                                        <input type="text" name="phone" class="member-input" value="<?php echo htmlspecialchars((string)$user['phone']); ?>" required>
                                    </div>

                                    <?php if ($hasBirthdayColumn): ?>
                                        <div class="member-field">
                                            <label class="member-form-label">生日</label>
                                            <input type="date" name="birthday" class="member-input" value="<?php echo htmlspecialchars((string)($user['birthday'] ?? '')); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="member-field member-span-2">
                                        <label class="member-form-label">地址 <span class="member-required">*</span></label>
                                        <input type="text" name="address" class="member-input" value="<?php echo htmlspecialchars((string)$user['address']); ?>" required>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab === 'password'): ?>
                    <!-- 更改密碼分頁 -->
                    <div class="member-section">
                        <div class="member-panel-toolbar">
                            <div class="member-toolbar-left">
                                <div class="member-toolbar-title">修改密碼</div>
                                <div class="member-toolbar-desc">為了帳號安全，請定期更新密碼</div>
                            </div>
                            <div class="member-toolbar-actions">
                                <a href="profile.php?tab=password" class="member-btn member-btn--soft member-btn--toolbar" role="button" aria-label="取消">
                                    取消
                                </a>
                                <button type="submit" form="member-password-form" class="member-btn member-btn--primary member-btn--toolbar">
                                    更新密碼
                                </button>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="member-feedback member-feedback--error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="member-feedback member-feedback--success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="profile.php?tab=password" id="member-password-form" class="member-form">
                            <input type="hidden" name="action" value="change_password">

                            <div class="member-form-grid member-form-grid--single">
                                <div class="member-field">
                                    <label class="member-form-label">舊密碼 <span class="member-required">*</span></label>
                                    <input type="password" name="old_password" class="member-input" required>
                                </div>

                                <div class="member-field">
                                    <label class="member-form-label">新密碼 <span class="member-required">*</span></label>
                                    <input type="password" name="new_password" class="member-input" minlength="6" required>
                                    <div class="member-form-hint">密碼長度至少 6 碼，建議包含英文與數字</div>
                                </div>

                                <div class="member-field">
                                    <label class="member-form-label">確認新密碼 <span class="member-required">*</span></label>
                                    <input type="password" name="confirm_password" class="member-input" minlength="6" required>
                                </div>
                            </div>
                        </form>
                    </div>

                <?php elseif ($active_tab === 'orders'): ?>
                    <!-- 訂單管理分頁 -->
                    <div class="member-section">
                        <div class="member-panel-toolbar member-orders-toolbar">
                            <div class="member-toolbar-left">
                                <div class="member-toolbar-title">訂單管理</div>
                                <div class="member-toolbar-desc">查看您的歷史訂單與目前訂單狀態</div>
                            </div>

                            <div class="member-toolbar-right">
                                <form method="GET" action="profile.php" class="member-filter-form">
                                    <input type="hidden" name="tab" value="orders">

                                    <div class="member-filter-row">
                                        <input
                                            type="text"
                                            name="order_q"
                                            class="member-filter-input"
                                            placeholder="搜尋訂單編號"
                                            value="<?php echo htmlspecialchars((string)$order_q); ?>"
                                        >

                                        <select name="order_status" class="member-filter-select">
                                            <option value="" <?php echo $order_status_filter === '' ? 'selected' : ''; ?>>全部狀態</option>
                                            <option value="processing" <?php echo $order_status_filter === 'processing' ? 'selected' : ''; ?>>處理中</option>
                                            <option value="paid" <?php echo $order_status_filter === 'paid' ? 'selected' : ''; ?>>已付款</option>
                                            <option value="shipped" <?php echo $order_status_filter === 'shipped' ? 'selected' : ''; ?>>已出貨</option>
                                            <option value="completed" <?php echo $order_status_filter === 'completed' ? 'selected' : ''; ?>>已完成</option>
                                            <option value="cancelled" <?php echo $order_status_filter === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                                        </select>

                                        <button type="submit" class="member-btn member-btn--primary member-btn--toolbar member-btn--toolbar-small">
                                            套用篩選
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="member-feedback member-feedback--error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success)): ?>
                            <div class="member-feedback member-feedback--success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <div class="member-list-card">
                            <?php if (empty($orders)): ?>
                                <div class="member-empty-message">目前尚未有任何訂單。</div>
                            <?php else: ?>
                                <div class="member-orders-list">
                                    <?php foreach ($orders as $order): ?>
                                        <?php
                                            $orderId = (int)$order['id'];
                                            $badgeKey = appStatusBadgeClass((string)($order['status'] ?? ''));
                                            $badgeLabel = appOrderStatusLabel((string)($order['status'] ?? ''));
                                            $cancelable = in_array((string)($order['status'] ?? ''), ['pending', 'pending_payment', 'paid'], true);
                                        ?>

                                        <div class="member-order-card">
                                            <div class="member-order-summary">
                                                <div class="member-order-left">
                                                    <div class="member-order-id">訂單編號：#<?php echo $orderId; ?></div>
                                                    <div class="member-order-date"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$order['created_at']))); ?></div>
                                                </div>

                                                <div class="member-order-right">
                                                    <span class="member-badge <?php echo htmlspecialchars($badgeKey); ?>">
                                                        <?php echo htmlspecialchars($badgeLabel); ?>
                                                    </span>
                                                    <div class="member-order-amount">NT$ <?php echo number_format(get_order_payable_amount($order), 0); ?></div>
                                                </div>
                                            </div>

                                            <div class="member-order-actions">
                                                <button
                                                    type="button"
                                                    class="member-btn member-btn--soft member-btn--small"
                                                    onclick="toggleOrderDetails(<?php echo $orderId; ?>)"
                                                >
                                                    <span class="member-order-toggle-icon" id="member-order-toggle-icon-<?php echo $orderId; ?>">▼</span>
                                                    <span id="member-order-toggle-text-<?php echo $orderId; ?>">查看明細</span>
                                                </button>

                                                <?php if ($cancelable): ?>
                                                    <form method="POST" class="member-inline-form" onsubmit="return confirm('確定要取消此訂單嗎？');">
                                                        <input type="hidden" name="action" value="cancel_order">
                                                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                                        <button type="submit" class="member-btn member-btn--danger-soft member-btn--small">
                                                            取消訂單
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>

                                            <div
                                                id="member-order-details-<?php echo $orderId; ?>"
                                                class="member-order-details member-order-details--hidden"
                                            >
                                                <div class="member-order-details-content">
                                                    <div class="member-order-meta">
                                                        <?php if (!empty($order['payment_method'])): ?>
                                                            <p><strong>付款方式：</strong><?php echo htmlspecialchars($payment_method_names[$order['payment_method']] ?? $order['payment_method']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($order['shipping_method'])): ?>
                                                            <p><strong>送貨方式：</strong><?php echo htmlspecialchars($shipping_method_names[$order['shipping_method']] ?? $order['shipping_method']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($order['shipping_address'])): ?>
                                                            <p><strong>配送地址：</strong><?php echo htmlspecialchars((string)$order['shipping_address']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($order['pickup_store'])): ?>
                                                            <p><strong>取貨門市：</strong><?php echo htmlspecialchars((string)$order['pickup_store']); ?></p>
                                                        <?php endif; ?>
                                                    </div>

                                                    <table class="member-order-items-table">
                                                        <thead>
                                                            <tr>
                                                                <th>商品名稱</th>
                                                                <th>尺寸</th>
                                                                <th>數量</th>
                                                                <th>單價</th>
                                                                <th>小計</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($order['items'] as $item): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars((string)$item['product_name']); ?></td>
                                                                    <td><?php echo htmlspecialchars(formatCartSizeForDisplay((string)($item['size'] ?? ''))); ?></td>
                                                                    <td><?php echo htmlspecialchars((string)$item['quantity']); ?></td>
                                                                    <td>NT$ <?php echo number_format((float)$item['unit_price'], 0); ?></td>
                                                                    <td>NT$ <?php echo number_format((float)$item['subtotal'], 0); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr>
                                                                <td colspan="4" class="member-text-right"><strong>總計：</strong></td>
                                                                <td><strong>NT$ <?php echo number_format(get_order_payable_amount($order), 0); ?></strong></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>

                                                    <?php if ($reviewsReadyForUser && (string)($order['status'] ?? '') === 'completed'): ?>
                                                        <div class="member-order-review-section">
                                                            <div class="member-order-meta-title"><strong>商品評價</strong></div>

                                                            <?php foreach ($order['items'] as $item): ?>
                                                                <?php
                                                                    $productIdForReview = (int)($item['product_id'] ?? 0);
                                                                    $reviewExists = false;
                                                                    $reviewId = 0;
                                                                    $reviewRating = null;
                                                                    $reviewComment = null;
                                                                    $reviewHidden = false;

                                                                    if ($productIdForReview > 0) {
                                                                        $where = "r.user_id = :user_id AND r.product_id = :product_id";
                                                                        $paramsLocal = [
                                                                            ':user_id' => $user_id,
                                                                            ':product_id' => $productIdForReview,
                                                                        ];
                                                                        if ($reviewsHasOrderIdColumn) {
                                                                            $where .= " AND r.order_id = :order_id";
                                                                            $paramsLocal[':order_id'] = (int)($order['id'] ?? 0);
                                                                        }

                                                                        $hiddenSelect = $reviewsHiddenColumnName !== '' ? ", r.{$reviewsHiddenColumnName} AS is_hidden_value" : "";
                                                                        $sql = "SELECT r.id AS review_id, r.rating, r.comment{$hiddenSelect}
                                                                                FROM reviews r
                                                                                WHERE {$where}
                                                                                LIMIT 1";
                                                                        $st = $pdo->prepare($sql);
                                                                        $st->execute($paramsLocal);
                                                                        $reviewRow = $st->fetch(PDO::FETCH_ASSOC);

                                                                        if ($reviewRow) {
                                                                            $reviewExists = true;
                                                                            $reviewId = (int)($reviewRow['review_id'] ?? 0);
                                                                            $reviewRating = (int)($reviewRow['rating'] ?? 0);
                                                                            $reviewComment = (string)($reviewRow['comment'] ?? '');
                                                                            if ($reviewsHiddenColumnName !== '') {
                                                                                $reviewHidden = ((int)($reviewRow['is_hidden_value'] ?? 0) === 1);
                                                                            }
                                                                        }
                                                                    }
                                                                ?>

                                                                <div class="member-review-item-card">
                                                                    <div class="member-review-item-top">
                                                                        <div class="member-review-item-name">
                                                                            <?php echo htmlspecialchars((string)($item['product_name'] ?? '')); ?>
                                                                        </div>
                                                                    </div>

                                                                    <?php if ($reviewExists && $reviewHidden === false): ?>
                                                                        <div class="member-review-view">
                                                                            <div class="member-review-stars"><?php echo number_format((int)$reviewRating); ?>/5</div>
                                                                            <?php if ($reviewComment !== ''): ?>
                                                                                <div class="member-review-comment"><?php echo htmlspecialchars($reviewComment); ?></div>
                                                                            <?php endif; ?>
                                                                        </div>

                                                                        <form method="POST" action="profile.php?tab=orders" class="member-review-form">
                                                                            <input type="hidden" name="action" value="update_review">
                                                                            <input type="hidden" name="review_id" value="<?php echo (int)$reviewId; ?>">
                                                                            <input type="hidden" name="order_id" value="<?php echo (int)($order['id'] ?? 0); ?>">
                                                                            <input type="hidden" name="product_id" value="<?php echo (int)$productIdForReview; ?>">

                                                                            <div class="member-field" style="margin-top: 10px;">
                                                                                <label class="member-form-label">修改評分 <span class="member-required">*</span></label>
                                                                                <select name="rating" class="member-input" required>
                                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                        <option value="<?php echo $i; ?>" <?php echo ((int)$reviewRating === $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                                                                    <?php endfor; ?>
                                                                                </select>
                                                                            </div>

                                                                            <div class="member-field">
                                                                                <label class="member-form-label">修改評論 <span class="member-required">*</span></label>
                                                                                <textarea name="comment" class="member-input" rows="3" required><?php echo htmlspecialchars((string)$reviewComment); ?></textarea>
                                                                            </div>

                                                                            <div class="member-return-apply-actions">
                                                                                <button type="submit" class="member-btn member-btn--primary member-btn--small">更新評價</button>
                                                                            </div>
                                                                        </form>
                                                                    <?php elseif ($reviewExists && $reviewHidden === true): ?>
                                                                        <div class="member-empty-message">此評論已被管理員隱藏。</div>
                                                                    <?php else: ?>
                                                                        <form method="POST" action="profile.php?tab=orders" class="member-review-form">
                                                                            <input type="hidden" name="action" value="submit_review">
                                                                            <input type="hidden" name="order_id" value="<?php echo (int)($order['id'] ?? 0); ?>">
                                                                            <input type="hidden" name="product_id" value="<?php echo (int)$productIdForReview; ?>">

                                                                            <div class="member-field">
                                                                                <label class="member-form-label">評分 <span class="member-required">*</span></label>
                                                                                <select name="rating" class="member-input" required>
                                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                                                                    <?php endfor; ?>
                                                                                </select>
                                                                            </div>

                                                                            <div class="member-field">
                                                                                <label class="member-form-label">評論 <span class="member-required">*</span></label>
                                                                                <textarea name="comment" class="member-input" rows="3" required></textarea>
                                                                            </div>

                                                                            <div class="member-return-apply-actions">
                                                                                <button type="submit" class="member-btn member-btn--primary member-btn--small">送出評價</button>
                                                                            </div>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </div>

                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($order['return_requests'])): ?>
                                                        <div class="member-order-return-section">
                                                            <div class="member-order-meta-title"><strong>退貨/退款進度：</strong></div>
                                                            <?php foreach ($order['return_requests'] as $request): ?>
                                                                <p class="member-order-return-line">
                                                                    申請 #<?php echo (int)$request['id']; ?>｜
                                                                    退貨狀態：<?php echo htmlspecialchars(appOrderStatusLabel((string)($request['status'] ?? 'pending'))); ?>｜
                                                                    退款狀態：<?php echo htmlspecialchars(appRefundStatusLabel((string)($request['refund_status'] ?? 'pending_refund'))); ?>
                                                                    <?php if (!empty($request['updated_at'])): ?>
                                                                        ｜更新時間：<?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string)$request['updated_at']))); ?>
                                                                    <?php endif; ?>
                                                                </p>
                                                                <?php if (!empty($request['reason'])): ?>
                                                                    <p class="member-order-return-reason">原因：<?php echo htmlspecialchars((string)$request['reason']); ?></p>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php $canApplyReturn = in_array((string)($order['status'] ?? ''), ['completed', 'shipped'], true); ?>
                                                        <?php if ($canApplyReturn): ?>
                                                            <form method="POST" action="profile.php?tab=orders" class="member-return-apply-form">
                                                                <input type="hidden" name="action" value="request_return">
                                                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">

                                                                <div class="member-order-meta-title">
                                                                    <strong>申請退貨 / 退款</strong>
                                                                </div>

                                                                <div class="member-field">
                                                                    <label class="member-form-label">退貨原因 <span class="member-required">*</span></label>
                                                                    <textarea name="reason" class="member-input" rows="3" required></textarea>
                                                                </div>

                                                                <div class="member-return-apply-actions">
                                                                    <button type="submit" class="member-btn member-btn--primary member-btn--small">送出申請</button>
                                                                </div>
                                                            </form>
                                                        <?php else: ?>
                                                            <div class="member-empty-message">目前訂單尚不可申請退貨。</div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($active_tab === 'coupons'): ?>
                    <!-- 我的優惠券分頁 -->
                    <div class="member-section">
                        <div class="member-panel-toolbar member-coupons-toolbar">
                            <div class="member-toolbar-left">
                                <div class="member-toolbar-title">我的優惠券</div>
                                <div class="member-toolbar-desc">查看可使用與已使用的優惠券</div>
                            </div>

                            <div class="member-toolbar-right">
                                <form method="GET" action="profile.php" class="member-filter-form">
                                    <input type="hidden" name="tab" value="coupons">

                                    <div class="member-filter-row">
                                        <select name="coupon_status" class="member-filter-select">
                                            <option value="all" <?php echo $coupon_status_filter === 'all' ? 'selected' : ''; ?>>全部</option>
                                            <option value="unused" <?php echo $coupon_status_filter === 'unused' ? 'selected' : ''; ?>>可使用</option>
                                            <option value="used" <?php echo $coupon_status_filter === 'used' ? 'selected' : ''; ?>>已使用</option>
                                            <option value="expired" <?php echo $coupon_status_filter === 'expired' ? 'selected' : ''; ?>>已過期</option>
                                        </select>

                                        <button type="submit" class="member-btn member-btn--primary member-btn--toolbar member-btn--toolbar-small">
                                            套用篩選
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="member-feedback member-feedback--error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <div class="member-list-card">
                            <?php if (empty($user_coupons)): ?>
                                <div class="member-empty-message">目前尚未領取任何優惠券。</div>
                            <?php else: ?>
                                <div class="member-coupons-grid">
                                    <?php foreach ($user_coupons as $coupon): ?>
                                        <?php
                                            $meta = getCouponActivityMeta((string)($coupon['coupon_code'] ?? ''));
                                            $badgeLabel = (string)($coupon['effective_badge_label'] ?? '');
                                            $badgeKey = (string)($coupon['effective_badge_class'] ?? 'progress');
                                            $displayDate = (string)($coupon['effective_display_date'] ?? '');
                                            $effectiveStatus = (string)($coupon['effective_status'] ?? '');
                                            $minAmount = (float)($coupon['minimum_amount'] ?? 0);
                                        ?>

                                        <div class="member-coupon-card">
                                            <div class="member-coupon-head">
                                                <div class="member-coupon-title">
                                                    <?php echo htmlspecialchars((string)($meta['name'] ?? ($coupon['coupon_code'] ?? ''))); ?>
                                                    <span class="member-coupon-code"><?php echo htmlspecialchars((string)($coupon['coupon_code'] ?? '')); ?></span>
                                                </div>
                                                <span class="member-badge <?php echo htmlspecialchars($badgeKey); ?>">
                                                    <?php echo htmlspecialchars($badgeLabel); ?>
                                                </span>
                                            </div>

                                            <div class="member-coupon-content">
                                                <?php echo htmlspecialchars((string)($meta['content'] ?? '')); ?>
                                            </div>

                                            <div class="member-coupon-meta">
                                                <?php if ($effectiveStatus === 'used'): ?>
                                                    <div class="member-coupon-date">使用日期：<?php echo htmlspecialchars($displayDate); ?></div>
                                                <?php else: ?>
                                                    <div class="member-coupon-date">到期日：<?php echo htmlspecialchars($displayDate); ?></div>
                                                <?php endif; ?>

                                                <?php if ($minAmount > 0): ?>
                                                    <div class="member-coupon-threshold">門檻：單筆滿 NT$ <?php echo number_format($minAmount, 0); ?> 才可使用</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3 class="footer-title">關於我們</h3>
                    <ul class="footer-links">
                        <li><a href="about.php">公司簡介</a></li>
                        <li><a href="about.php#history">發展歷程</a></li>
                        <li><a href="about.php#mission">經營理念</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">顧客服務</h3>
                    <ul class="footer-links">
                        <li><a href="guide.php">購物指南</a></li>
                        <li><a href="faq.php">常見問題</a></li>
                        <li><a href="return.php">退換貨政策</a></li>
                        <li><a href="shipping.php">運送說明</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3 class="footer-title">聯絡我們</h3>
                    <ul class="footer-links">
                        <li>電話：02-2905-2000</li>
                        <li>Email：helmetvrsefju@gmail.com</li>
                        <li>地址：新北市新莊區中正路510號</li>
                        <li class="social-links">
                            <a href="#" class="social-icon">Facebook</a>
                            <a href="#" class="social-icon">Instagram</a>
                            <a href="#" class="social-icon">Line</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>Powered by HelmetVRse</p>
            </div>
        </div>
    </footer>

    <script>
        // 搜尋框展開/收起功能
        (function() {
            try {
                const searchToggle = document.getElementById('searchToggle');
                const searchInput = document.getElementById('searchInput');
                const searchBox = document.querySelector('.search-box');

                if (!searchToggle || !searchInput || !searchBox) return;

                searchToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    searchBox.classList.toggle('active');
                    if (searchBox.classList.contains('active')) {
                        searchInput.focus();
                    }
                });

                searchInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                    searchBox.classList.add('active');
                });

                document.addEventListener('click', function(e) {
                    if (!searchBox.contains(e.target)) {
                        searchBox.classList.remove('active');
                    }
                });

                searchInput.addEventListener('blur', function() {
                    setTimeout(function() {
                        if (document.activeElement !== searchInput) {
                            searchBox.classList.remove('active');
                        }
                    }, 200);
                });

                // 搜尋表單提交
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const keyword = searchInput.value.trim();
                        if (keyword) {
                            window.location.href = 'products.php?search=' + encodeURIComponent(keyword);
                        } else {
                            window.location.href = 'products.php';
                        }
                    }
                });
            } catch (error) {
                console.error('搜尋框功能錯誤:', error);
            }
        })();

        function toggleOrderDetails(orderId) {
            const details = document.getElementById('member-order-details-' + orderId);
            const icon = document.getElementById('member-order-toggle-icon-' + orderId);
            const text = document.getElementById('member-order-toggle-text-' + orderId);

            if (!details) return;

            const isHidden = details.classList.contains('member-order-details--hidden');
            if (isHidden) {
                details.classList.remove('member-order-details--hidden');
                details.classList.add('member-order-details--shown');
                if (icon) icon.textContent = '▲';
                if (text) text.textContent = '收合明細';
            } else {
                details.classList.add('member-order-details--hidden');
                details.classList.remove('member-order-details--shown');
                if (icon) icon.textContent = '▼';
                if (text) text.textContent = '查看明細';
            }
        }

        // 漢堡選單切換
        (function() {
            const navToggle = document.getElementById('navToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (navToggle && navMenu) {
                navToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                    navToggle.classList.toggle('active');
                });
            }
        })();
    </script>
</body>
</html>
