<?php

/**
 * 評價 reviews 資料表初始化/補齊。
 * - 若資料表不存在：建立最基本結構
 * - 若欄位缺失：補齊缺失欄位（優先 is_hidden，兼容 hidden）
 *
 * 注意：這裡的 SQL 以「可成功跑起來」為優先，盡量避免破壞既有資料。
 */
function reviewsEnsureTable(PDO $pdo): array
{
    $result = [
        'table_exists' => false,
        'hidden_column' => '',
        'has_order_id' => false,
    ];

    $tableExists = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'reviews'");
        $tableExists = (bool)$check->fetchColumn();
    } catch (Throwable $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        // 建立一個可用的 reviews 結構
        try {
            $pdo->exec(
                "CREATE TABLE reviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    product_id INT NOT NULL,
                    order_id INT NULL,
                    rating TINYINT NOT NULL,
                    comment TEXT NOT NULL,
                    is_hidden TINYINT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT NOW(),
                    updated_at DATETIME NOT NULL DEFAULT NOW()
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $tableExists = true;
        } catch (Throwable $e) {
            // 若建立失敗，後續程式仍可能能從既有資料繼續運作
            $tableExists = false;
        }
    }

    if (!$tableExists) {
        return $result;
    }

    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM reviews");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $field = (string)($c['Field'] ?? '');
            if ($field !== '') {
                $cols[$field] = true;
            }
        }
    } catch (Throwable $e) {
        return $result;
    }

    $result['table_exists'] = true;

    // hidden 欄位相容：is_hidden / hidden
    if (!empty($cols['is_hidden'])) {
        $result['hidden_column'] = 'is_hidden';
    } elseif (!empty($cols['hidden'])) {
        $result['hidden_column'] = 'hidden';
    } else {
        // 補 is_hidden，讓後續程式能統一處理
        try {
            $pdo->exec("ALTER TABLE reviews ADD COLUMN is_hidden TINYINT NOT NULL DEFAULT 0");
            $result['hidden_column'] = 'is_hidden';
        } catch (Throwable $e) {
            // 若 ALTER 失敗就保持空字串
            $result['hidden_column'] = '';
        }
    }

    // order_id（用於同筆訂單同商品同會員只能一則評價的校驗）
    if (!empty($cols['order_id'])) {
        $result['has_order_id'] = true;
    } else {
        try {
            $pdo->exec("ALTER TABLE reviews ADD COLUMN order_id INT NULL");
            $result['has_order_id'] = true;
        } catch (Throwable $e) {
            $result['has_order_id'] = false;
        }
    }

    // rating/comment/created_at/updated_at 的補齊（若既有資料表缺欄位）
    if (empty($cols['rating'])) {
        try {
            $pdo->exec("ALTER TABLE reviews ADD COLUMN rating TINYINT NOT NULL DEFAULT 5");
        } catch (Throwable $e) {
            // ignore
        }
    }
    if (empty($cols['comment'])) {
        try {
            $pdo->exec("ALTER TABLE reviews ADD COLUMN comment TEXT NOT NULL");
        } catch (Throwable $e) {
            // ignore
        }
    }
    if (empty($cols['created_at'])) {
        try {
            $pdo->exec("ALTER TABLE reviews ADD COLUMN created_at DATETIME NOT NULL DEFAULT NOW()");
        } catch (Throwable $e) {
            // ignore
        }
    }
    if (empty($cols['updated_at'])) {
        try {
            $pdo->exec("ALTER TABLE reviews ADD COLUMN updated_at DATETIME NOT NULL DEFAULT NOW()");
        } catch (Throwable $e) {
            // ignore
        }
    }

    return $result;
}

