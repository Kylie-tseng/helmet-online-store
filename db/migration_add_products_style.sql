-- 為商品新增風格欄位（首頁風格導覽 / products.php?style= 篩選用）
-- 若欄位已存在可略過。

ALTER TABLE `products`
  ADD COLUMN `style` varchar(32) DEFAULT NULL COMMENT '商品風格：復古、通勤、競速、女性等' AFTER `is_addon`;

ALTER TABLE `products`
  ADD INDEX `idx_products_style` (`style`);

-- 範例：依需求為既有商品標記風格（請依實際調整）
-- UPDATE products SET style = '復古' WHERE id IN (1,2,3);
-- UPDATE products SET style = '通勤' WHERE id IN (4,5);
