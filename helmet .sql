-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1:3307
-- 產生時間： 2026-03-22 18:38:33
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `helmet`
--

-- --------------------------------------------------------

--
-- 資料表結構 `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` enum('S','M','L','XL','F') NOT NULL DEFAULT 'M',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `size`, `quantity`, `unit_price`, `added_at`) VALUES
(58, 4, 27, 'F', 1, 299.00, '2026-03-22 16:24:16');

-- --------------------------------------------------------

--
-- 資料表結構 `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, '全罩式安全帽', '提供完整頭部保護的全罩式安全帽', '2025-12-07 08:56:15'),
(2, '半罩式安全帽', '輕便舒適的半罩式安全帽', '2025-12-07 08:56:15'),
(3, '3/4罩安全帽', '兼具保護與通風的3/4罩安全帽', '2025-12-07 08:56:15'),
(4, '周邊與配件', '安全帽相關配件與零件', '2025-12-07 08:56:15');

-- --------------------------------------------------------

--
-- 資料表結構 `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `discount_type` enum('percent','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `minimum_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `expire_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `coupons`
--

INSERT INTO `coupons` (`id`, `coupon_code`, `discount_type`, `discount_value`, `minimum_amount`, `start_date`, `expire_date`, `is_active`, `created_at`) VALUES
(1, 'NEW100', 'fixed', 100.00, 500.00, '2025-01-01', '2099-12-31', 1, '2026-03-04 12:36:48'),
(2, 'HELMET10', 'percent', 10.00, 0.00, '2025-01-01', '2099-12-31', 1, '2026-03-04 12:36:48'),
(3, 'SAVE300', 'fixed', 300.00, 2000.00, '2025-01-01', '2099-12-31', 1, '2026-03-04 12:36:48'),
(4, 'RIDER20', 'percent', 20.00, 0.00, '2025-01-01', '2099-12-31', 1, '2026-03-04 12:36:48');

-- --------------------------------------------------------

--
-- 資料表結構 `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','pending_payment','paid','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `shipping_method` enum('pickup','home') DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `pickup_store` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` enum('S','M','L','XL') DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `style` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_addon` tinyint(1) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `style`, `description`, `price`, `status`, `is_addon`, `is_featured`, `created_at`, `updated_at`) VALUES
(1, 1, '復古全罩 1', '復古', '復古風格全罩安全帽', 8500.00, 'active', 0, 1, '2026-03-22 15:32:46', NULL),
(2, 1, '復古全罩 2', '復古', '復古風格全罩安全帽', 8500.00, 'active', 0, 0, '2026-03-22 15:32:46', NULL),
(3, 1, '通勤全罩 1', '通勤', '通勤風格全罩安全帽', 8500.00, 'active', 0, 1, '2026-03-22 15:32:46', NULL),
(4, 1, '通勤全罩 2', '通勤', '通勤風格全罩安全帽', 8500.00, 'active', 0, 0, '2026-03-22 15:32:46', NULL),
(5, 1, '競速全罩 1', '競速', '競速風格全罩安全帽', 8500.00, 'active', 0, 1, '2026-03-22 15:32:46', NULL),
(6, 1, '競速全罩 2', '競速', '競速風格全罩安全帽', 8500.00, 'active', 0, 0, '2026-03-22 15:32:46', NULL),
(7, 1, '女性全罩 1', '女性', '女性風格全罩安全帽', 8500.00, 'active', 0, 1, '2026-03-22 15:32:46', NULL),
(8, 1, '女性全罩 2', '女性', '女性風格全罩安全帽', 8500.00, 'active', 0, 0, '2026-03-22 15:32:46', NULL),
(9, 2, '復古半罩 1', '復古', '復古風格半罩安全帽', 5200.00, 'active', 0, 1, '2026-03-22 15:32:52', NULL),
(10, 2, '復古半罩 2', '復古', '復古風格半罩安全帽', 5200.00, 'active', 0, 0, '2026-03-22 15:32:52', NULL),
(11, 2, '通勤半罩 1', '通勤', '通勤風格半罩安全帽', 5200.00, 'active', 0, 1, '2026-03-22 15:32:52', NULL),
(12, 2, '通勤半罩 2', '通勤', '通勤風格半罩安全帽', 5200.00, 'active', 0, 0, '2026-03-22 15:32:52', NULL),
(13, 2, '競速半罩 1', '競速', '競速風格半罩安全帽', 5200.00, 'active', 0, 0, '2026-03-22 15:32:52', NULL),
(14, 2, '競速半罩 2', '競速', '競速風格半罩安全帽', 5200.00, 'active', 0, 0, '2026-03-22 15:32:52', NULL),
(15, 2, '女性半罩 1', '女性', '女性風格半罩安全帽', 5200.00, 'active', 0, 0, '2026-03-22 15:32:52', NULL),
(16, 2, '女性半罩 2', '女性', '女性風格半罩安全帽', 5200.00, 'active', 0, 0, '2026-03-22 15:32:52', NULL),
(17, 3, '復古3/4罩 1', '復古', '復古風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(18, 3, '復古3/4罩 2', '復古', '復古風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(19, 3, '通勤3/4罩 1', '通勤', '通勤風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(20, 3, '通勤3/4罩 2', '通勤', '通勤風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(21, 3, '競速3/4罩 1', '競速', '競速風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(22, 3, '競速3/4罩 2', '競速', '競速風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(23, 3, '女性3/4罩 1', '女性', '女性風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(24, 3, '女性3/4罩 2', '女性', '女性風格3/4罩安全帽', 6500.00, 'active', 0, 0, '2026-03-22 15:32:59', NULL),
(25, 4, '防霧片', NULL, '安全帽防霧配件', 499.00, 'active', 1, 0, '2026-03-22 15:33:08', NULL),
(26, 4, '藍牙耳機', NULL, '安全帽藍牙通訊配件', 1990.00, 'active', 1, 0, '2026-03-22 15:33:08', NULL),
(27, 4, '安全帽袋', NULL, '安全帽收納配件', 299.00, 'active', 1, 0, '2026-03-22 15:33:08', NULL),
(28, 4, '鏡片清潔組', NULL, '安全帽鏡片清潔配件', 199.00, 'active', 1, 0, '2026-03-22 15:33:08', NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `sort_order`, `is_primary`, `created_at`) VALUES
(1, 1, 'fullface-1-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(2, 1, 'fullface-1-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(3, 1, 'fullface-1-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(4, 1, 'fullface-1-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(5, 1, 'fullface-1-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(6, 1, 'fullface-1-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(7, 2, 'fullface-2-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(8, 2, 'fullface-2-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(9, 2, 'fullface-2-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(10, 2, 'fullface-2-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(11, 2, 'fullface-2-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(12, 2, 'fullface-2-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(13, 3, 'fullface-3-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(14, 3, 'fullface-3-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(15, 3, 'fullface-3-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(16, 3, 'fullface-3-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(17, 3, 'fullface-3-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(18, 3, 'fullface-3-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(19, 4, 'fullface-4-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(20, 4, 'fullface-4-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(21, 4, 'fullface-4-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(22, 4, 'fullface-4-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(23, 4, 'fullface-4-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(24, 4, 'fullface-4-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(25, 5, 'fullface-5-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(26, 5, 'fullface-5-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(27, 5, 'fullface-5-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(28, 5, 'fullface-5-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(29, 5, 'fullface-5-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(30, 5, 'fullface-5-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(31, 6, 'fullface-6-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(32, 6, 'fullface-6-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(33, 6, 'fullface-6-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(34, 6, 'fullface-6-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(35, 6, 'fullface-6-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(36, 6, 'fullface-6-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(37, 7, 'fullface-7-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(38, 7, 'fullface-7-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(39, 7, 'fullface-7-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(40, 7, 'fullface-7-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(41, 7, 'fullface-7-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(42, 7, 'fullface-7-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(43, 8, 'fullface-8-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(44, 8, 'fullface-8-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(45, 8, 'fullface-8-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(46, 8, 'fullface-8-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(47, 8, 'fullface-8-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(48, 8, 'fullface-8-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(49, 9, 'halfface-1-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(50, 9, 'halfface-1-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(51, 9, 'halfface-1-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(52, 9, 'halfface-1-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(53, 9, 'halfface-1-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(54, 9, 'halfface-1-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(55, 10, 'halfface-2-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(56, 10, 'halfface-2-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(57, 10, 'halfface-2-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(58, 10, 'halfface-2-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(59, 10, 'halfface-2-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(60, 10, 'halfface-2-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(61, 11, 'halfface-3-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(62, 11, 'halfface-3-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(63, 11, 'halfface-3-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(64, 11, 'halfface-3-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(65, 11, 'halfface-3-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(66, 11, 'halfface-3-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(67, 12, 'halfface-4-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(68, 12, 'halfface-4-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(69, 12, 'halfface-4-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(70, 12, 'halfface-4-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(71, 12, 'halfface-4-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(72, 12, 'halfface-4-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(73, 13, 'halfface-5-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(74, 13, 'halfface-5-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(75, 13, 'halfface-5-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(76, 13, 'halfface-5-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(77, 13, 'halfface-5-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(78, 13, 'halfface-5-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(79, 14, 'halfface-6-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(80, 14, 'halfface-6-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(81, 14, 'halfface-6-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(82, 14, 'halfface-6-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(83, 14, 'halfface-6-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(84, 14, 'halfface-6-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(85, 15, 'halfface-7-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(86, 15, 'halfface-7-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(87, 15, 'halfface-7-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(88, 15, 'halfface-7-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(89, 15, 'halfface-7-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(90, 15, 'halfface-7-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(91, 16, 'halfface-8-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(92, 16, 'halfface-8-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(93, 16, 'halfface-8-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(94, 16, 'halfface-8-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(95, 16, 'halfface-8-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(96, 16, 'halfface-8-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(97, 17, 'threequarter-1-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(98, 17, 'threequarter-1-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(99, 17, 'threequarter-1-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(100, 17, 'threequarter-1-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(101, 17, 'threequarter-1-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(102, 17, 'threequarter-1-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(103, 18, 'threequarter-2-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(104, 18, 'threequarter-2-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(105, 18, 'threequarter-2-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(106, 18, 'threequarter-2-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(107, 18, 'threequarter-2-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(108, 18, 'threequarter-2-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(109, 19, 'threequarter-3-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(110, 19, 'threequarter-3-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(111, 19, 'threequarter-3-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(112, 19, 'threequarter-3-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(113, 19, 'threequarter-3-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(114, 19, 'threequarter-3-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(115, 20, 'threequarter-4-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(116, 20, 'threequarter-4-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(117, 20, 'threequarter-4-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(118, 20, 'threequarter-4-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(119, 20, 'threequarter-4-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(120, 20, 'threequarter-4-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(121, 21, 'threequarter-5-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(122, 21, 'threequarter-5-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(123, 21, 'threequarter-5-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(124, 21, 'threequarter-5-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(125, 21, 'threequarter-5-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(126, 21, 'threequarter-5-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(127, 22, 'threequarter-6-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(128, 22, 'threequarter-6-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(129, 22, 'threequarter-6-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(130, 22, 'threequarter-6-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(131, 22, 'threequarter-6-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(132, 22, 'threequarter-6-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(133, 23, 'threequarter-7-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(134, 23, 'threequarter-7-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(135, 23, 'threequarter-7-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(136, 23, 'threequarter-7-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(137, 23, 'threequarter-7-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(138, 23, 'threequarter-7-6.jpg', 6, 0, '2026-03-22 16:23:25'),
(139, 24, 'threequarter-8-1.jpg', 1, 1, '2026-03-22 16:23:25'),
(140, 24, 'threequarter-8-2.jpg', 2, 0, '2026-03-22 16:23:25'),
(141, 24, 'threequarter-8-3.jpg', 3, 0, '2026-03-22 16:23:25'),
(142, 24, 'threequarter-8-4.jpg', 4, 0, '2026-03-22 16:23:25'),
(143, 24, 'threequarter-8-5.jpg', 5, 0, '2026-03-22 16:23:25'),
(144, 24, 'threequarter-8-6.jpg', 6, 0, '2026-03-22 16:23:25');

-- --------------------------------------------------------

--
-- 資料表結構 `product_sizes`
--

CREATE TABLE `product_sizes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` enum('S','M','L','XL') NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `product_sizes`
--

INSERT INTO `product_sizes` (`id`, `product_id`, `size`, `stock`, `created_at`, `updated_at`) VALUES
(1, 1, 'S', 10, '2026-03-22 16:23:52', NULL),
(2, 1, 'M', 10, '2026-03-22 16:23:52', NULL),
(3, 1, 'L', 10, '2026-03-22 16:23:52', NULL),
(4, 1, 'XL', 10, '2026-03-22 16:23:52', NULL),
(5, 2, 'S', 10, '2026-03-22 16:23:52', NULL),
(6, 2, 'M', 10, '2026-03-22 16:23:52', NULL),
(7, 2, 'L', 10, '2026-03-22 16:23:52', NULL),
(8, 2, 'XL', 10, '2026-03-22 16:23:52', NULL),
(9, 3, 'S', 10, '2026-03-22 16:23:52', NULL),
(10, 3, 'M', 10, '2026-03-22 16:23:52', NULL),
(11, 3, 'L', 10, '2026-03-22 16:23:52', NULL),
(12, 3, 'XL', 10, '2026-03-22 16:23:52', NULL),
(13, 4, 'S', 10, '2026-03-22 16:23:52', NULL),
(14, 4, 'M', 10, '2026-03-22 16:23:52', NULL),
(15, 4, 'L', 10, '2026-03-22 16:23:52', NULL),
(16, 4, 'XL', 10, '2026-03-22 16:23:52', NULL),
(17, 5, 'S', 10, '2026-03-22 16:23:52', NULL),
(18, 5, 'M', 10, '2026-03-22 16:23:52', NULL),
(19, 5, 'L', 10, '2026-03-22 16:23:52', NULL),
(20, 5, 'XL', 10, '2026-03-22 16:23:52', NULL),
(21, 6, 'S', 10, '2026-03-22 16:23:52', NULL),
(22, 6, 'M', 10, '2026-03-22 16:23:52', NULL),
(23, 6, 'L', 10, '2026-03-22 16:23:52', NULL),
(24, 6, 'XL', 10, '2026-03-22 16:23:52', NULL),
(25, 7, 'S', 10, '2026-03-22 16:23:52', NULL),
(26, 7, 'M', 10, '2026-03-22 16:23:52', NULL),
(27, 7, 'L', 10, '2026-03-22 16:23:52', NULL),
(28, 7, 'XL', 10, '2026-03-22 16:23:52', NULL),
(29, 8, 'S', 10, '2026-03-22 16:23:52', NULL),
(30, 8, 'M', 10, '2026-03-22 16:23:52', NULL),
(31, 8, 'L', 10, '2026-03-22 16:23:52', NULL),
(32, 8, 'XL', 10, '2026-03-22 16:23:52', NULL),
(33, 9, 'S', 10, '2026-03-22 16:23:52', NULL),
(34, 9, 'M', 10, '2026-03-22 16:23:52', NULL),
(35, 9, 'L', 10, '2026-03-22 16:23:52', NULL),
(36, 9, 'XL', 10, '2026-03-22 16:23:52', NULL),
(37, 10, 'S', 10, '2026-03-22 16:23:52', NULL),
(38, 10, 'M', 10, '2026-03-22 16:23:52', NULL),
(39, 10, 'L', 10, '2026-03-22 16:23:52', NULL),
(40, 10, 'XL', 10, '2026-03-22 16:23:52', NULL),
(41, 11, 'S', 10, '2026-03-22 16:23:52', NULL),
(42, 11, 'M', 10, '2026-03-22 16:23:52', NULL),
(43, 11, 'L', 10, '2026-03-22 16:23:52', NULL),
(44, 11, 'XL', 10, '2026-03-22 16:23:52', NULL),
(45, 12, 'S', 10, '2026-03-22 16:23:52', NULL),
(46, 12, 'M', 10, '2026-03-22 16:23:52', NULL),
(47, 12, 'L', 10, '2026-03-22 16:23:52', NULL),
(48, 12, 'XL', 10, '2026-03-22 16:23:52', NULL),
(49, 13, 'S', 10, '2026-03-22 16:23:52', NULL),
(50, 13, 'M', 10, '2026-03-22 16:23:52', NULL),
(51, 13, 'L', 10, '2026-03-22 16:23:52', NULL),
(52, 13, 'XL', 10, '2026-03-22 16:23:52', NULL),
(53, 14, 'S', 10, '2026-03-22 16:23:52', NULL),
(54, 14, 'M', 10, '2026-03-22 16:23:52', NULL),
(55, 14, 'L', 10, '2026-03-22 16:23:52', NULL),
(56, 14, 'XL', 10, '2026-03-22 16:23:52', NULL),
(57, 15, 'S', 10, '2026-03-22 16:23:52', NULL),
(58, 15, 'M', 10, '2026-03-22 16:23:52', NULL),
(59, 15, 'L', 10, '2026-03-22 16:23:52', NULL),
(60, 15, 'XL', 10, '2026-03-22 16:23:52', NULL),
(61, 16, 'S', 10, '2026-03-22 16:23:52', NULL),
(62, 16, 'M', 10, '2026-03-22 16:23:52', NULL),
(63, 16, 'L', 10, '2026-03-22 16:23:52', NULL),
(64, 16, 'XL', 10, '2026-03-22 16:23:52', NULL),
(65, 17, 'S', 10, '2026-03-22 16:23:52', NULL),
(66, 17, 'M', 10, '2026-03-22 16:23:52', NULL),
(67, 17, 'L', 10, '2026-03-22 16:23:52', NULL),
(68, 17, 'XL', 10, '2026-03-22 16:23:52', NULL),
(69, 18, 'S', 10, '2026-03-22 16:23:52', NULL),
(70, 18, 'M', 10, '2026-03-22 16:23:52', NULL),
(71, 18, 'L', 10, '2026-03-22 16:23:52', NULL),
(72, 18, 'XL', 10, '2026-03-22 16:23:52', NULL),
(73, 19, 'S', 10, '2026-03-22 16:23:52', NULL),
(74, 19, 'M', 10, '2026-03-22 16:23:52', NULL),
(75, 19, 'L', 10, '2026-03-22 16:23:52', NULL),
(76, 19, 'XL', 10, '2026-03-22 16:23:52', NULL),
(77, 20, 'S', 10, '2026-03-22 16:23:52', NULL),
(78, 20, 'M', 10, '2026-03-22 16:23:52', NULL),
(79, 20, 'L', 10, '2026-03-22 16:23:52', NULL),
(80, 20, 'XL', 10, '2026-03-22 16:23:52', NULL),
(81, 21, 'S', 10, '2026-03-22 16:23:52', NULL),
(82, 21, 'M', 10, '2026-03-22 16:23:52', NULL),
(83, 21, 'L', 10, '2026-03-22 16:23:52', NULL),
(84, 21, 'XL', 10, '2026-03-22 16:23:52', NULL),
(85, 22, 'S', 10, '2026-03-22 16:23:52', NULL),
(86, 22, 'M', 10, '2026-03-22 16:23:52', NULL),
(87, 22, 'L', 10, '2026-03-22 16:23:52', NULL),
(88, 22, 'XL', 10, '2026-03-22 16:23:52', NULL),
(89, 23, 'S', 10, '2026-03-22 16:23:52', NULL),
(90, 23, 'M', 10, '2026-03-22 16:23:52', NULL),
(91, 23, 'L', 10, '2026-03-22 16:23:52', NULL),
(92, 23, 'XL', 10, '2026-03-22 16:23:52', NULL),
(93, 24, 'S', 10, '2026-03-22 16:23:52', NULL),
(94, 24, 'M', 10, '2026-03-22 16:23:52', NULL),
(95, 24, 'L', 10, '2026-03-22 16:23:52', NULL),
(96, 24, 'XL', 10, '2026-03-22 16:23:52', NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `role` enum('member','staff','admin') NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `phone`, `address`, `role`, `created_at`, `updated_at`, `reset_token`, `reset_expires_at`) VALUES
(2, '系統管理員', 'admin', 'admin@helmetvrse.com', '$2b$12$13SBmvK83f81HMqK1/SalOMiaT43zEI4J2lh.oxta6tsQ5s0Hwz1u', '02-1234-5678', '新北市新莊區中正路510號', 'admin', '2025-12-07 05:33:31', NULL, NULL, NULL),
(3, '店員', 'staff', 'staff@helmetvrse.com', '$2b$12$lFl28stXXYHHP2pNbWqRredB83yYJ0/PgHY2eFnHzBwWduN5AkH6O', '02-1234-5679', '新北市新莊區中正路510號', 'staff', '2025-12-07 05:33:31', NULL, NULL, NULL),
(4, 'kylie', 'kylie', 'kyliem512mk@gmail.com', '$2y$10$qq5a0sjSWv6h5e2Fry2z2OAxmyFS0RxpoIV/lbmB2VNSNmsCa/pmK', '0911111111', '123', 'member', '2025-12-07 05:38:00', '2026-03-20 07:51:23', '9ad2c7e1168ae6ed397448c3de3afa02826d32044c167779d13377700f2692fc', '2026-03-20 16:35:12'),
(5, '黃翊鈞', 'bobby', 'bobby930910@gmail.com', '$2y$10$nuaEQf.tTEUIyot08asjiubcsCaMIGblhgbm/MuDROivfmPRcGGIi', '0900000000', '新北市新莊區中正路520號', 'member', '2026-03-02 11:12:17', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `user_coupons`
--

CREATE TABLE `user_coupons` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `status` enum('unused','used') DEFAULT 'unused',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `user_coupons`
--

INSERT INTO `user_coupons` (`id`, `user_id`, `coupon_id`, `status`, `created_at`) VALUES
(1, 4, 1, 'unused', '2026-03-09 11:20:44');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_admin_logs_user` (`admin_id`);

--
-- 資料表索引 `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`,`size`),
  ADD KEY `fk_cart_product` (`product_id`);

--
-- 資料表索引 `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_coupon_code` (`coupon_code`);

--
-- 資料表索引 `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_favorite` (`user_id`,`product_id`),
  ADD KEY `fk_favorites_product` (`product_id`);

--
-- 資料表索引 `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orders_user` (`user_id`),
  ADD KEY `fk_orders_coupon` (`coupon_id`);

--
-- 資料表索引 `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`);

--
-- 資料表索引 `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_products_category` (`category_id`);

--
-- 資料表索引 `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- 資料表索引 `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_size` (`product_id`,`size`),
  ADD KEY `fk_product_sizes_product` (`product_id`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- 資料表索引 `user_coupons`
--
ALTER TABLE `user_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_coupon` (`user_id`,`coupon_id`),
  ADD KEY `fk_user_coupons_coupon` (`coupon_id`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `product_sizes`
--
ALTER TABLE `product_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `user_coupons`
--
ALTER TABLE `user_coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `fk_admin_logs_user` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- 資料表的限制式 `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `fk_favorites_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- 資料表的限制式 `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE;

--
-- 資料表的限制式 `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- 資料表的限制式 `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD CONSTRAINT `fk_product_sizes_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 資料表的限制式 `user_coupons`
--
ALTER TABLE `user_coupons`
  ADD CONSTRAINT `fk_user_coupons_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_coupons_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
