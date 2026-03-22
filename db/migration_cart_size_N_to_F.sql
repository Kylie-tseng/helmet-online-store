-- 將購物車「無尺寸」由 N 改為 F（若先前已執行過含 N 的 ENUM，請依序執行）
-- 若 cart 尚無 N，可只執行最後一行 ALTER 改為含 F。

-- 1) 暫時允許 N 與 F 並存（若目前 ENUM 已含 N）
-- ALTER TABLE `cart` MODIFY COLUMN `size` ENUM('S','M','L','XL','N','F') NOT NULL DEFAULT 'M';

-- 2) 舊資料 N → F
-- UPDATE `cart` SET `size` = 'F' WHERE `size` = 'N';

-- 3) 最終 ENUM（僅 S/M/L/XL/F）
ALTER TABLE `cart`
  MODIFY COLUMN `size` ENUM('S','M','L','XL','F') NOT NULL DEFAULT 'M';
