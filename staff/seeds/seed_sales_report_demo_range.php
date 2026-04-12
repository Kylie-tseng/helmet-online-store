<?php

/**
 * 店員銷售頁：示範訂單資料（可重跑）
 *
 * 範圍：2025-09-01 ～ 2026-06-30（示範單僅 paid／shipped／completed，與 sales_report 日曆營收統計一致）
 * 標記：orders.pickup_store = '__SALES_SEED__'，重跑會先刪除同標記訂單與明細。
 *
 * 執行（專案根目錄或 staff/seeds 下）：
 *   php staff/seeds/seed_sales_report_demo_range.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "請使用 CLI 執行。\n");
    exit(1);
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.php';

const SEED_MARKER = '__SALES_SEED__';

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "無法取得 PDO。\n");
    exit(1);
}

$userId = (int)$pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
$pids = $pdo->query('SELECT id FROM products ORDER BY id ASC LIMIT 8')->fetchAll(PDO::FETCH_COLUMN);
if ($userId < 1 || $pids === []) {
    fwrite(STDERR, "需要至少一筆 users 與 products。\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE oi FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.pickup_store = " . $pdo->quote(SEED_MARKER));
    $pdo->exec('DELETE FROM orders WHERE pickup_store = ' . $pdo->quote(SEED_MARKER));

    $insOrder = $pdo->prepare('INSERT INTO orders (user_id, coupon_id, total_amount, discount_amount, final_amount, status, payment_method, shipping_method, shipping_address, pickup_store, created_at, updated_at)
        VALUES (:uid, NULL, :total, 0, :final, :st, :pm, :sm, NULL, ' . $pdo->quote(SEED_MARKER) . ', :created, NOW())');
    $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, size, quantity, unit_price, subtotal) VALUES (:oid, :pid, :sz, :qty, :up, :sub)');

    $start = new DateTimeImmutable('2025-09-01');
    $end = new DateTimeImmutable('2026-06-30');
    $orderCount = 0;

    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
        $ymd = $d->format('Y-m-d');

        $h = crc32($ymd);
        // 略過部分日期，避免「天天有單」
        $skip = ($h % 17 === 0) || ($h % 23 === 0);
        if ($skip) {
            continue;
        }

        $nOrders = 1 + ($h % 3);
        $isWeekend = in_array((int)$d->format('w'), [0, 6], true);
        if ($isWeekend && ($h % 5 === 0)) {
            $nOrders = min(3, $nOrders + 1);
        }

        for ($oi = 0; $oi < $nOrders; $oi++) {
            $stRoll = ($h + $oi * 7) % 10;
            // 日曆／月總結只計 paid, shipped, completed；避免僅 pending 造成畫面全 0
            if ($stRoll < 3) {
                $status = 'paid';
            } elseif ($stRoll < 6) {
                $status = 'shipped';
            } else {
                $status = 'completed';
            }

            $base = 1800 + (($h + $oi * 131) % 47) * 170;
            $qty = 1 + (($h + $oi) % 4);
            $pid = (int)$pids[($h + $oi) % count($pids)];
            $unit = (int)round($base / max(1, $qty));
            $sub = $unit * $qty;
            $hour = 9 + (($h + $oi * 3) % 9);
            $min = ($h % 50);
            $created = $ymd . sprintf(' %02d:%02d:00', $hour, $min);

            $pm = (($h + $oi) % 2 === 0) ? 'cod' : 'credit_card';
            $sm = (($h + $oi) % 3 === 0) ? 'pickup' : 'home';

            $insOrder->execute([
                ':uid' => $userId,
                ':total' => $sub,
                ':final' => $sub,
                ':st' => $status,
                ':pm' => $pm,
                ':sm' => $sm,
                ':created' => $created,
            ]);
            $oid = (int)$pdo->lastInsertId();
            $insItem->execute([
                ':oid' => $oid,
                ':pid' => $pid,
                ':sz' => 'M',
                ':qty' => $qty,
                ':up' => $unit,
                ':sub' => $sub,
            ]);
            $orderCount++;
        }
    }

    $pdo->commit();
    fwrite(STDOUT, "完成：寫入示範訂單筆數（orders）約 {$orderCount}（含明細）。\n");
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '失敗：' . $e->getMessage() . "\n");
    exit(1);
}
