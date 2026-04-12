-- 既有資料庫補欄位（若已存在請略過）
ALTER TABLE `users`
  ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '會員帳號：1可使用 0已停用（管理者會員管理）' AFTER `role`;
