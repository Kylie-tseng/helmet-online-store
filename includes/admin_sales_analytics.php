<?php

/**
 * 管理者銷售統計頁共用：同一時間區間、兩套口徑（全訂單 vs 有效營收）、退貨摘要。
 * 不讀 session；每次請求皆由資料庫重算。
 */

if (!function_exists('admin_sales_range_filter_orders')) {
    /**
     * 訂單時間篩選（orders 別名 o）
     *
     * @return array{sql:string,params:array<string,mixed>}
     */
    function admin_sales_range_filter_orders(string $range, string $todayYmd, string $todayYm): array
    {
        if ($range === 'today') {
            return [
                'sql' => ' AND DATE(o.created_at) = :sales_ord_d',
                'params' => [':sales_ord_d' => $todayYmd],
            ];
        }
        if ($range === 'month') {
            return [
                'sql' => " AND DATE_FORMAT(o.created_at, '%Y-%m') = :sales_ord_ym",
                'params' => [':sales_ord_ym' => $todayYm],
            ];
        }
        return ['sql' => '', 'params' => []];
    }
}

if (!function_exists('admin_sales_range_filter_orders_no_alias')) {
    /**
     * 訂單時間篩選（表 orders，無別名）
     *
     * @return array{sql:string,params:array<string,mixed>}
     */
    function admin_sales_range_filter_orders_no_alias(string $range, string $todayYmd, string $todayYm): array
    {
        if ($range === 'today') {
            return [
                'sql' => ' AND DATE(created_at) = :sales_ordna_d',
                'params' => [':sales_ordna_d' => $todayYmd],
            ];
        }
        if ($range === 'month') {
            return [
                'sql' => " AND DATE_FORMAT(created_at, '%Y-%m') = :sales_ordna_ym",
                'params' => [':sales_ordna_ym' => $todayYm],
            ];
        }
        return ['sql' => '', 'params' => []];
    }
}

if (!function_exists('admin_sales_range_filter_returns')) {
    /**
     * 退貨申請時間篩選（別名 r）
     *
     * @return array{sql:string,params:array<string,mixed>}
     */
    function admin_sales_range_filter_returns(string $range, string $todayYmd, string $todayYm): array
    {
        if ($range === 'today') {
            return [
                'sql' => ' AND DATE(r.created_at) = :sales_ret_d',
                'params' => [':sales_ret_d' => $todayYmd],
            ];
        }
        if ($range === 'month') {
            return [
                'sql' => " AND DATE_FORMAT(r.created_at, '%Y-%m') = :sales_ret_ym",
                'params' => [':sales_ret_ym' => $todayYm],
            ];
        }
        return ['sql' => '', 'params' => []];
    }
}

if (!function_exists('admin_sales_sql_in_placeholders')) {
    /**
     * @param list<string> $values
     * @param array<string,mixed> $params 以傳址合併綁定值
     * @return list{0:string,1:array<string,mixed>}
     */
    function admin_sales_sql_in_placeholders(array $values, array &$params, string $prefix): array
    {
        $ph = [];
        foreach (array_values($values) as $i => $v) {
            $k = ':' . $prefix . $i;
            $params[$k] = $v;
            $ph[] = $k;
        }
        return [implode(', ', $ph), $params];
    }
}

if (!function_exists('admin_sales_overview_buckets_scoped')) {
    /**
     * 訂單狀態分布（區間內「所有訂單」），分桶規則與 app_orders_compute_overview_buckets 對齊並補上 return_requested。
     *
     * @param list<string> $allowedStatuses
     * @return array<string,int>
     */
    function admin_sales_overview_buckets_scoped(PDO $pdo, array $allowedStatuses, string $range, string $todayYmd, string $todayYm): array
    {
        $buckets = [
            'pending' => 0,
            'return_requested' => 0,
            'paid' => 0,
            'shipped' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        $rf = admin_sales_range_filter_orders_no_alias($range, $todayYmd, $todayYm);
        $dsql = $rf['sql'];
        $dp = $rf['params'];

        try {
            $pendingVals = array_values(array_intersect(
                ['pending', 'pending_payment', 'processing', 'progress'],
                $allowedStatuses
            ));
            if ($pendingVals !== []) {
                $params = $dp;
                [$inSql, $params] = admin_sales_sql_in_placeholders($pendingVals, $params, 'bukp');
                $sql = 'SELECT COUNT(*) FROM orders WHERE (status IN (' . $inSql . ') OR TRIM(COALESCE(status, \'\')) = \'\')' . $dsql;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $buckets['pending'] = (int) $stmt->fetchColumn();
            }
            // 與訂單列表一致：return_requested 獨立分桶（即使 ENUM 清單未列出仍統計實際資料）
            $sql = "SELECT COUNT(*) FROM orders WHERE status = 'return_requested'" . $dsql;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($dp);
            $buckets['return_requested'] = (int) $stmt->fetchColumn();
            if (in_array('paid', $allowedStatuses, true)) {
                $sql = "SELECT COUNT(*) FROM orders WHERE status = 'paid'" . $dsql;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dp);
                $buckets['paid'] = (int) $stmt->fetchColumn();
            }
            if (in_array('shipped', $allowedStatuses, true)) {
                $sql = "SELECT COUNT(*) FROM orders WHERE status = 'shipped'" . $dsql;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dp);
                $buckets['shipped'] = (int) $stmt->fetchColumn();
            }
            $doneVals = array_values(array_intersect(['completed', 'done'], $allowedStatuses));
            if ($doneVals !== []) {
                $params = $dp;
                [$inSql, $params] = admin_sales_sql_in_placeholders($doneVals, $params, 'bukd');
                $sql = 'SELECT COUNT(*) FROM orders WHERE status IN (' . $inSql . ')' . $dsql;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $buckets['completed'] = (int) $stmt->fetchColumn();
            }
            if (in_array('cancelled', $allowedStatuses, true)) {
                $sql = "SELECT COUNT(*) FROM orders WHERE status = 'cancelled'" . $dsql;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dp);
                $buckets['cancelled'] = (int) $stmt->fetchColumn();
            }
        } catch (Throwable $e) {
        }

        return $buckets;
    }
}

if (!function_exists('admin_sales_fetch_trend_merged')) {
    /**
     * 依區間取得趨勢：每日「全訂單筆數」與「有效訂單營收」同源同一列，避免圖表長度不一致。
     *
     * @param list<string> $validRevenueStatuses
     * @return array{labels:list<string>,revenue:list<float>,order_counts:list<int>}
     */
    function admin_sales_fetch_trend_merged(PDO $pdo, string $range, string $todayYmd, string $todayYm, array $validRevenueStatuses): array
    {
        $labels = [];
        $revenue = [];
        $orderCounts = [];
        $validStatuses = array_values(array_unique(array_map('strval', $validRevenueStatuses)));

        try {
            $params = [];
            [$validIn, $params] = admin_sales_sql_in_placeholders($validStatuses, $params, 'trev');

            if ($range === 'today' || $range === 'month') {
                $rf = admin_sales_range_filter_orders($range, $todayYmd, $todayYm);
                $w = ' WHERE 1=1 ' . $rf['sql'];
                $params = array_merge($params, $rf['params']);
                $sql = 'SELECT DATE(o.created_at) AS order_date,
                               COUNT(*) AS order_count,
                               COALESCE(SUM(CASE WHEN o.status IN (' . $validIn . ') THEN o.final_amount ELSE 0 END), 0) AS sales_amount
                        FROM orders o' . $w . '
                        GROUP BY DATE(o.created_at)
                        ORDER BY order_date ASC';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = 'SELECT order_date, order_count, sales_amount FROM (
                            SELECT DATE(o.created_at) AS order_date,
                                   COUNT(*) AS order_count,
                                   COALESCE(SUM(CASE WHEN o.status IN (' . $validIn . ') THEN o.final_amount ELSE 0 END), 0) AS sales_amount
                            FROM orders o
                            GROUP BY DATE(o.created_at)
                            ORDER BY order_date DESC
                            LIMIT 90
                        ) t
                        ORDER BY order_date ASC';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            foreach ($rows as $tr) {
                $labels[] = (string) ($tr['order_date'] ?? '');
                $orderCounts[] = (int) ($tr['order_count'] ?? 0);
                $revenue[] = round((float) ($tr['sales_amount'] ?? 0), 2);
            }
        } catch (Throwable $e) {
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'order_counts' => $orderCounts,
        ];
    }
}

if (!function_exists('admin_sales_fetch_top_products')) {
    /**
     * @param list<string> $validRevenueStatuses
     * @return list<array<string,mixed>>
     */
    function admin_sales_fetch_top_products(PDO $pdo, string $range, string $todayYmd, string $todayYm, array $validRevenueStatuses): array
    {
        $rf = admin_sales_range_filter_orders($range, $todayYmd, $todayYm);
        $params = $rf['params'];
        [$inSql, $params] = admin_sales_sql_in_placeholders($validRevenueStatuses, $params, 'tpv');

        try {
            $sql = "SELECT p.name,
                           c.name AS category_name,
                           SUM(oi.quantity) AS sold_qty,
                           SUM(oi.subtotal) AS sold_amount
                    FROM order_items oi
                    INNER JOIN products p ON p.id = oi.product_id
                    LEFT JOIN categories c ON c.id = p.category_id
                    INNER JOIN orders o ON o.id = oi.order_id
                    WHERE o.status IN ({$inSql}) {$rf['sql']}
                    GROUP BY oi.product_id, p.name, c.name
                    ORDER BY sold_qty DESC, sold_amount DESC
                    LIMIT 5";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('admin_sales_fetch_category_sales')) {
    /**
     * @param list<string> $validRevenueStatuses
     * @return list<array<string,mixed>>
     */
    function admin_sales_fetch_category_sales(PDO $pdo, string $range, string $todayYmd, string $todayYm, array $validRevenueStatuses): array
    {
        $rf = admin_sales_range_filter_orders($range, $todayYmd, $todayYm);
        $params = $rf['params'];
        [$inSql, $params] = admin_sales_sql_in_placeholders($validRevenueStatuses, $params, 'csv');

        try {
            $sql = "SELECT c.name AS category_name,
                           COALESCE(SUM(oi.quantity), 0) AS sold_qty,
                           COALESCE(SUM(oi.subtotal), 0) AS sold_amount
                    FROM categories c
                    LEFT JOIN products p ON p.category_id = c.id
                    LEFT JOIN order_items oi ON oi.product_id = p.id
                    LEFT JOIN orders o ON o.id = oi.order_id
                        AND o.status IN ({$inSql}) {$rf['sql']}
                    GROUP BY c.id, c.name
                    HAVING sold_qty > 0 OR sold_amount > 0
                    ORDER BY sold_amount DESC, sold_qty DESC, c.name ASC
                    LIMIT 8";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('admin_sales_fetch_returns_summary')) {
    /**
     * @return array{total:int,pending:int,completed:int,table_exists:bool}
     */
    function admin_sales_fetch_returns_summary(PDO $pdo, string $range, string $todayYmd, string $todayYm): array
    {
        $out = ['total' => 0, 'pending' => 0, 'completed' => 0, 'table_exists' => false];
        try {
            $check = $pdo->query("SHOW TABLES LIKE 'return_requests'");
            if (!$check->fetchColumn()) {
                return $out;
            }
            $out['table_exists'] = true;
            $rf = admin_sales_range_filter_returns($range, $todayYmd, $todayYm);
            $sql = 'SELECT COUNT(*) AS total_count,
                           SUM(CASE WHEN r.status IN (\'pending\',\'pending_payment\') THEN 1 ELSE 0 END) AS pending_count,
                           SUM(CASE WHEN r.status = \'completed\' THEN 1 ELSE 0 END) AS completed_count
                    FROM return_requests r
                    WHERE 1=1 ' . $rf['sql'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($rf['params']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['total'] = (int) ($row['total_count'] ?? 0);
            $out['pending'] = (int) ($row['pending_count'] ?? 0);
            $out['completed'] = (int) ($row['completed_count'] ?? 0);
        } catch (Throwable $e) {
        }

        return $out;
    }
}

if (!function_exists('admin_sales_fetch_member_ranking')) {
    /**
     * 會員消費排行：僅有效營收訂單，並套用與整頁相同之時間區間。
     *
     * @param list<string> $validRevenueStatuses
     * @return list<array<string,mixed>>
     */
    function admin_sales_fetch_member_ranking(PDO $pdo, string $range, string $todayYmd, string $todayYm, array $validRevenueStatuses): array
    {
        $rf = admin_sales_range_filter_orders($range, $todayYmd, $todayYm);
        $params = $rf['params'];
        [$inSql, $params] = admin_sales_sql_in_placeholders($validRevenueStatuses, $params, 'mbr');

        try {
            $sql = "SELECT u.id, u.name, u.username,
                           COUNT(o.id) AS order_cnt,
                           COALESCE(SUM(o.final_amount), 0) AS total_spent,
                           MAX(o.created_at) AS last_order_at
                    FROM users u
                    INNER JOIN orders o ON o.user_id = u.id AND o.status IN ({$inSql}) {$rf['sql']}
                    WHERE u.role = 'member'
                    GROUP BY u.id, u.name, u.username
                    ORDER BY total_spent DESC, order_cnt DESC
                    LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('admin_sales_fetch_payment_rows')) {
    /**
     * 付款方式筆數（區間內「所有訂單」）
     *
     * @return list<array{pm:string,cnt:int}>
     */
    function admin_sales_fetch_payment_rows(PDO $pdo, string $range, string $todayYmd, string $todayYm): array
    {
        $rf = admin_sales_range_filter_orders($range, $todayYmd, $todayYm);
        try {
            $sql = "SELECT LOWER(TRIM(COALESCE(o.payment_method, ''))) AS pm, COUNT(*) AS cnt
                    FROM orders o
                    WHERE 1=1 {$rf['sql']}
                    GROUP BY LOWER(TRIM(COALESCE(o.payment_method, '')))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($rf['params']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('admin_sales_fetch_shipping_rows')) {
    /**
     * 配送方式筆數（區間內「所有訂單」）
     *
     * @return list<array{sm:string,cnt:int}>
     */
    function admin_sales_fetch_shipping_rows(PDO $pdo, string $range, string $todayYmd, string $todayYm): array
    {
        $rf = admin_sales_range_filter_orders($range, $todayYmd, $todayYm);
        try {
            $sql = "SELECT LOWER(TRIM(COALESCE(o.shipping_method, ''))) AS sm, COUNT(*) AS cnt
                    FROM orders o
                    WHERE 1=1 {$rf['sql']}
                    GROUP BY LOWER(TRIM(COALESCE(o.shipping_method, '')))";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($rf['params']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}
