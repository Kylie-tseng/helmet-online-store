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

    // 兼容：允許用英文 token 直接指定 style（供首頁卡片導向）
    // 目標：把英文值轉成 DB/系統實際使用的中文風格標籤
    $raw_lc = mb_strtolower($raw, 'UTF-8');
    $english_to_cn = [
        'retro' => '復古',
        'vintage' => '復古',
        'commuter' => '通勤',
        'racing' => '競速',
        'women' => '女性',
    ];
    if (isset($english_to_cn[$raw_lc])) {
        return $english_to_cn[$raw_lc];
    }

    foreach (get_product_style_labels() as $label) {
        if ($raw === $label) {
            return $label;
        }
    }
    return null;
}
