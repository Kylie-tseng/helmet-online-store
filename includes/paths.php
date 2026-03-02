<?php
/**
 * 路徑常數定義
 * 用於統一管理專案中的路徑引用
 */

// 根目錄路徑（相對於當前檔案）
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// 基礎 URL 路徑（用於 HTML 連結）
define('BASE_URL', '/專題/');

// 資料夾路徑
define('USER_PATH', BASE_URL . 'user/');
define('ADMIN_PATH', BASE_URL . 'admin/');
define('STAFF_PATH', BASE_URL . 'staff/');
define('API_PATH', BASE_URL . 'api/');
define('INCLUDES_PATH', BASE_URL . 'includes/');
define('ASSETS_PATH', BASE_URL . 'assets/');

