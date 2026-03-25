<?php
/**
 * 商品分類名稱正規化（比對 nav / DB 時避免全形斜線、多餘空白等造成對不到）
 */
function normalize_product_category_label($s) {
    $s = trim((string)$s);
    if ($s === '') {
        return '';
    }
    // 全形斜線、分數斜線 → 半形 /
    $s = str_replace(['／', '⁄', '∕'], '/', $s);
    // 全形空白、不換行空白 → 一般空白
    $s = str_replace(["　", "\xc2\xa0"], ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

/**
 * 依 GET 參數解析出 category_id（與 products.category_id 對應）
 *
 * @return array{0: ?int, 1: ?string} [category_id, category_name]
 */
function resolve_product_list_category($pdo, $category_param_raw, $category_id_get = 0) {
    $category_param = trim((string)$category_param_raw);
    $category_id = null;
    $category_name = null;

    if ($category_id_get > 0) {
        try {
            $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $category_id_get]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return [(int)$row['id'], (string)$row['name']];
            }
        } catch (PDOException $e) {
            return [null, null];
        }
        return [null, null];
    }

    if ($category_param === '' || $category_param === '全部商品') {
        return [null, null];
    }

    if (ctype_digit($category_param)) {
        $id = (int)$category_param;
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return [(int)$row['id'], (string)$row['name']];
                }
            } catch (PDOException $e) {
                return [null, null];
            }
        }
        return [null, null];
    }

    $want = normalize_product_category_label($category_param);
    try {
        $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $dbNorm = normalize_product_category_label($row['name'] ?? '');
            if ($dbNorm === $want || (string)($row['name'] ?? '') === $category_param) {
                return [(int)$row['id'], (string)$row['name']];
            }
        }
    } catch (PDOException $e) {
        return [null, null];
    }

    return [null, null];
}

/**
 * 側欄／導覽用：依分類「顯示名稱」產生列表頁連結（優先使用數字 id，避免網址編碼與名稱不一致）
 */
function products_category_list_url_by_name(array $categories, $label) {
    $want = normalize_product_category_label($label);
    foreach ($categories as $c) {
        $cn = normalize_product_category_label((string)($c['name'] ?? ''));
        if ($cn === $want) {
            return 'products.php?category=' . (int)$c['id'];
        }
    }
    return 'products.php?category=' . rawurlencode((string)$label);
}

/**
 * 商品總覽「全部商品」固定分類順序（與左側分類／資料庫 categories.name 對應）
 *
 * @return string[]
 */
function get_product_list_category_order_labels() {
    return ['全罩式安全帽', '半罩式安全帽', '3/4罩安全帽', '周邊與配件'];
}

/**
 * 依目前 categories 查詢結果，產出 FIELD(p.category_id, ...) 用的 id 順序（正規化名稱比對）
 *
 * @return int[]
 */
function get_category_ids_sorted_for_product_list(array $categories) {
    $labels = get_product_list_category_order_labels();
    $by_norm = [];
    foreach ($categories as $cat) {
        $nid = (int)($cat['id'] ?? 0);
        if ($nid <= 0) {
            continue;
        }
        $by_norm[normalize_product_category_label($cat['name'] ?? '')] = $nid;
    }
    $ids = [];
    foreach ($labels as $label) {
        $nk = normalize_product_category_label($label);
        if (isset($by_norm[$nk])) {
            $ids[] = $by_norm[$nk];
        }
    }
    return $ids;
}
