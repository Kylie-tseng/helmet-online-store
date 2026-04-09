<?php

/**
 * 統一商品主圖子查詢：依 sort_order、id 取第一張（新版 product_images 無 is_primary）。
 */
function primaryImageSubquery(string $productAlias = 'p', string $imageAlias = 'pi'): string
{
    $productAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $productAlias) ?: 'p';
    $imageAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $imageAlias) ?: 'pi';

    return "(SELECT {$imageAlias}.image_url
            FROM product_images {$imageAlias}
            WHERE {$imageAlias}.product_id = {$productAlias}.id
            ORDER BY {$imageAlias}.sort_order ASC, {$imageAlias}.id ASC
            LIMIT 1)";
}

/**
 * 商品詳情多圖排序（與主圖子查詢一致）。
 */
function productImageOrderClause(string $imageAlias = ''): string
{
    $imageAlias = trim($imageAlias);
    if ($imageAlias !== '') {
        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $imageAlias);
        if ($safeAlias !== '') {
            return "ORDER BY {$safeAlias}.sort_order ASC, {$safeAlias}.id ASC";
        }
    }
    return "ORDER BY sort_order ASC, id ASC";
}
