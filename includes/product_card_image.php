<?php
/**
 * 商品列表／卡片用圖片路徑（不含商品詳情頁多圖）
 *
 * 規則：
 * 1. primary_image（來自 product_images 第一張子查詢）→ assets/images/products/ + primary_image
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
        return $base . ltrim(str_replace('\\', '/', $p), '/');
    }

    return $default;
}
