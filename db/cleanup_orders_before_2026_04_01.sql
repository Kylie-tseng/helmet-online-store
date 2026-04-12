-- =============================================================================
-- HelmetVRse：清理 2026-04-01 以前的訂單資料（僅 DELETE，不變更結構）
-- 條件：orders.created_at < '2026-04-01 00:00:00'
--
-- 專案內已確認與 orders 有關的表：
--   1) order_items     — FOREIGN KEY order_id → orders(id) ON DELETE CASCADE
--   2) return_requests — order_id 欄位（staff_feature_updates.sql；未必有 FK）
--
-- 未在 schema 中發現：order_logs、payments、shipments 等表（若本機有自增表請自行補 DELETE）。
--
-- 建議：先整段執行「一、確認筆數」，核對無誤後再執行「二、刪除」（可於交易內執行）。
-- =============================================================================

USE `helmet`;

-- =============================================================================
-- 一、確認筆數（安全版本：請先執行此區，確認數字）
-- =============================================================================

-- 將被刪除的訂單筆數
SELECT COUNT(*) AS orders_to_delete
FROM orders
WHERE created_at < '2026-04-01 00:00:00';

-- 將一併刪除的訂單明細筆數
SELECT COUNT(*) AS order_items_to_delete
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
WHERE o.created_at < '2026-04-01 00:00:00';

-- 退貨申請（若無 return_requests 表，此句會報錯，請略過或註解）
SELECT COUNT(*) AS return_requests_to_delete
FROM return_requests rr
INNER JOIN orders o ON o.id = rr.order_id
WHERE o.created_at < '2026-04-01 00:00:00';

-- =============================================================================
-- 二、刪除（請確認上一區筆數後再執行）
-- 順序：return_requests → order_items → orders
-- 說明：order_items 雖為 ON DELETE CASCADE，仍先手動刪除可相容未開 CASCADE 的庫、且語意清楚。
-- =============================================================================

START TRANSACTION;

-- 2a 退貨／退貨申請（無此表請註解掉以下三行）
DELETE rr
FROM return_requests rr
INNER JOIN orders o ON o.id = rr.order_id
WHERE o.created_at < '2026-04-01 00:00:00';

-- 2b 訂單明細
DELETE oi
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
WHERE o.created_at < '2026-04-01 00:00:00';

-- 2c 訂單主檔
DELETE FROM orders
WHERE created_at < '2026-04-01 00:00:00';

-- 若預覽正確請 COMMIT；若要取消請執行 ROLLBACK;
COMMIT;

-- ROLLBACK;
