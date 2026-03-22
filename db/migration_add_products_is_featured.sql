-- 既有資料庫：為 products 增加首頁熱門精選欄位
-- 執行一次即可；若欄位已存在請略過或手動調整

ALTER TABLE `products`
  ADD COLUMN `is_featured` tinyint(1) NOT NULL DEFAULT 0 COMMENT '首頁熱門區精選：1 顯示' AFTER `style`;

-- 範例：將指定商品設為熱門（請依 id 調整）
-- UPDATE `products` SET `is_featured` = 1 WHERE `id` IN (1, 2, 3);
