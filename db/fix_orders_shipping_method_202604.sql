-- 補齊測試訂單 shipping_method（僅更新 NULL 或空字串，不覆蓋已有值）
-- 日期：created_at 介於 2026-04-01 ~ 2026-04-12（含）
-- 欄位名稱：shipping_method ENUM('pickup','home')
--   credit_card -> home（畫面：宅配到府）
--   cod         -> pickup（畫面：超商取貨）
--
-- 執行前請確認資料庫名稱，必要時加上 USE `your_db`;

UPDATE `orders`
SET
  `shipping_method` = CASE LOWER(TRIM(COALESCE(`payment_method`, '')))
    WHEN 'credit_card' THEN 'home'
    WHEN 'cod' THEN 'pickup'
    ELSE `shipping_method`
  END,
  `updated_at` = NOW()
WHERE
  (`shipping_method` IS NULL OR TRIM(COALESCE(`shipping_method`, '')) = '')
  AND DATE(`created_at`) BETWEEN '2026-04-01' AND '2026-04-12'
  AND LOWER(TRIM(COALESCE(`payment_method`, ''))) IN ('credit_card', 'cod');
