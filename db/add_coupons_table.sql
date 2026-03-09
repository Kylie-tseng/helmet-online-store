USE helmet;

CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_code` varchar(50) NOT NULL,
  `discount_type` enum('percent','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `expire_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coupon_code` (`coupon_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 加價購標記欄位
ALTER TABLE `products`
ADD COLUMN IF NOT EXISTS `is_addon_product` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;

-- 購物車可覆蓋單價（加價購 9 折）
ALTER TABLE `cart`
ADD COLUMN IF NOT EXISTS `unit_price` decimal(10,2) DEFAULT NULL AFTER `quantity`;

-- 只保留四張指定優惠券
DELETE FROM `coupons` WHERE `coupon_code` NOT IN ('NEW100', 'HELMET10', 'SAVE300', 'RIDER20');

INSERT INTO `coupons` (`coupon_code`, `discount_type`, `discount_value`, `minimum_amount`, `start_date`, `expire_date`, `is_active`)
VALUES
('NEW100', 'fixed', 100, 500, '2025-01-01', '2099-12-31', 1),
('HELMET10', 'percent', 10, 0, '2025-01-01', '2099-12-31', 1),
('SAVE300', 'fixed', 300, 2000, '2025-01-01', '2099-12-31', 1),
('RIDER20', 'percent', 20, 0, '2025-01-01', '2099-12-31', 1)
ON DUPLICATE KEY UPDATE
`discount_type` = VALUES(`discount_type`),
`discount_value` = VALUES(`discount_value`),
`minimum_amount` = VALUES(`minimum_amount`),
`start_date` = VALUES(`start_date`),
`expire_date` = VALUES(`expire_date`),
`is_active` = VALUES(`is_active`);

CREATE TABLE IF NOT EXISTS `user_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `status` enum('unused','used') NOT NULL DEFAULT 'unused',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_coupon` (`user_id`, `coupon_code`),
  KEY `idx_user_coupons_user_id` (`user_id`),
  CONSTRAINT `fk_user_coupons_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
