<?php
/**
 * 商品列表／卡片用圖片路徑（不含商品詳情頁多圖）
 *
 * 規則：
 * 1. primary_image（來自 product_images：sort_order ASC, id ASC 第一張子查詢）→ assets/images/products/ + primary_image
 * 2. 否則 → assets/images/products/default.jpg
 *
 * @param string|null $primary_image 子查詢欄位 primary_image（僅檔名或相對路徑片段）
 */
function resolve_product_card_image_src(?string $primary_image): string
{
    $base = 'assets/images/products/';
    $default = $base . 'default.jpg';

    $p = $primary_image !== null ? trim($primary_image) : '';
    if ($p !== '' && strpos($p, '..') === false) {
        $normalized = ltrim(str_replace('\\', '/', $p), '/');

        $toWebPath = static function (string $relative) use ($base): string {
            $relative = ltrim($relative, '/');
            if (strpos($relative, 'assets/') === 0) {
                return $relative;
            }
            return $base . $relative;
        };

        $candidates = [$normalized];

        // 兼容歷史命名差異：racingfull-face1-1.jpg <-> racingfull-face 1-1.jpg
        if (preg_match('/^(.*?face)(\d+-\d+\.[a-z0-9]+)$/i', $normalized, $m)) {
            $candidates[] = $m[1] . ' ' . $m[2];
        }
        if (preg_match('/^(.*?face)\s+(\d+-\d+\.[a-z0-9]+)$/i', $normalized, $m)) {
            $candidates[] = $m[1] . $m[2];
        }

        foreach (array_unique($candidates) as $candidate) {
            $webPath = $toWebPath($candidate);
            $filePath = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $webPath);
            if (is_file($filePath)) {
                return $webPath;
            }
        }
    }

    return $default;
}
