<?php

/**
 * 統一商品主圖子查詢：優先 is_primary，再依 sort_order、id。
 */
function primaryImageSubquery(string $productAlias = 'p', string $imageAlias = 'pi'): string
{
    $productAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $productAlias) ?: 'p';
    $imageAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $imageAlias) ?: 'pi';

    return "(SELECT {$imageAlias}.image_url
            FROM product_images {$imageAlias}
            WHERE {$imageAlias}.product_id = {$productAlias}.id
            ORDER BY {$imageAlias}.is_primary DESC, {$imageAlias}.sort_order ASC, {$imageAlias}.id ASC
            LIMIT 1)";
}

/**
 * 商品詳情多圖排序規則。
 */
function productImageOrderClause(string $imageAlias = ''): string
{
    $imageAlias = trim($imageAlias);
    if ($imageAlias !== '') {
        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $imageAlias);
        if ($safeAlias !== '') {
            return "ORDER BY {$safeAlias}.is_primary DESC, {$safeAlias}.sort_order ASC, {$safeAlias}.id ASC";
        }
    }
    return "ORDER BY is_primary DESC, sort_order ASC, id ASC";
}

