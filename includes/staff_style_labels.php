<?php

/**
 * 店員後台：商品風格選項（staff_style_labels）。
 * 若資料表不存在則嘗試建立；失敗時退回預設三種風格，不影響前台。
 */

if (!function_exists('staff_style_labels_ensure_table')) {
    function staff_style_labels_ensure_table(PDO $pdo): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $ok = false;
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `staff_style_labels` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(64) NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_staff_style_labels_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $n = (int)$pdo->query('SELECT COUNT(*) FROM staff_style_labels')->fetchColumn();
            if ($n === 0) {
                $ins = $pdo->prepare('INSERT IGNORE INTO staff_style_labels (`name`) VALUES (?), (?), (?)');
                $ins->execute(['復古', '通勤', '競速']);
            }
            try {
                $dist = $pdo->query(
                    "SELECT DISTINCT TRIM(`style`) AS s FROM `products`
                     WHERE `style` IS NOT NULL AND TRIM(`style`) <> ''"
                );
                if ($dist) {
                    $insOne = $pdo->prepare('INSERT IGNORE INTO staff_style_labels (`name`) VALUES (?)');
                    foreach ($dist->fetchAll(PDO::FETCH_COLUMN) as $s) {
                        $s = trim((string)$s);
                        if ($s !== '') {
                            $insOne->execute([$s]);
                        }
                    }
                }
            } catch (Throwable $e) {
            }
            $ok = true;
        } catch (Throwable $e) {
            $ok = false;
        }

        return $ok;
    }
}

if (!function_exists('staff_style_labels_rows')) {
    /**
     * @return list<array{id:int,name:string}>
     */
    function staff_style_labels_rows(PDO $pdo): array
    {
        if (!staff_style_labels_ensure_table($pdo)) {
            return [
                ['id' => 0, 'name' => '復古'],
                ['id' => 0, 'name' => '通勤'],
                ['id' => 0, 'name' => '競速'],
            ];
        }
        try {
            $stmt = $pdo->query('SELECT id, name FROM staff_style_labels ORDER BY name ASC');

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('staff_style_labels_names_for_form')) {
    /**
     * @return list<string>
     */
    function staff_style_labels_names_for_form(PDO $pdo): array
    {
        $rows = staff_style_labels_rows($pdo);
        $out = [];
        foreach ($rows as $r) {
            $n = trim((string)($r['name'] ?? ''));
            if ($n !== '' && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }
        if ($out === []) {
            return ['復古', '通勤', '競速'];
        }

        return $out;
    }
}

if (!function_exists('staff_style_labels_usage_counts')) {
    /**
     * @return array<string,int>
     */
    function staff_style_labels_usage_counts(PDO $pdo): array
    {
        $map = [];
        try {
            $stmt = $pdo->query(
                "SELECT TRIM(`style`) AS s, COUNT(*) AS c FROM products
                 WHERE `style` IS NOT NULL AND TRIM(`style`) <> ''
                 GROUP BY TRIM(`style`)"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $s = trim((string)($row['s'] ?? ''));
                if ($s !== '') {
                    $map[$s] = (int)($row['c'] ?? 0);
                }
            }
        } catch (Throwable $e) {
        }

        return $map;
    }
}
