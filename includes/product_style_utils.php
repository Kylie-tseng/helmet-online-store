<?php
/**
 * 商品「風格」標籤（與 products.style 欄位值一致）
 *
 * @return string[]
 */
function get_product_style_labels() {
    return ['復古', '通勤', '競速', '女性'];
}

/**
 * 驗證 GET style 參數，通過則回傳標準字串，否則 null
 */
function resolve_product_list_style($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    foreach (get_product_style_labels() as $label) {
        if ($raw === $label) {
            return $label;
        }
    }
    return null;
}
