<?php
/**
 * 信用卡繳費頁：欄位僅檢查是否填寫（不驗證卡號格式／Luhn／效期格式）
 */

/**
 * @return array{
 *   ok: bool,
 *   errors: string[],
 *   card_number_clean: string,
 *   card_name: string,
 *   card_expiry: string
 * }
 */
function validate_credit_card_payment_submission(array $post): array
{
    $errors = [];

    $rawNumber = isset($post['card_number']) ? trim((string)$post['card_number']) : '';
    if ($rawNumber === '') {
        $errors[] = '請輸入信用卡卡號';
    }

    $name = isset($post['card_name']) ? trim((string)$post['card_name']) : '';
    if ($name === '') {
        $errors[] = '請輸入持卡人姓名';
    }

    $exp = isset($post['card_expiry']) ? trim((string)$post['card_expiry']) : '';
    if ($exp === '') {
        $errors[] = '請輸入有效期限';
    }

    $cvv = isset($post['card_cvv']) ? trim((string)$post['card_cvv']) : '';
    if ($cvv === '') {
        $errors[] = '請輸入信用卡安全碼（CVV）';
    }

    $clean = preg_replace('/\s+/', '', $rawNumber);

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'card_number_clean' => $clean,
        'card_name' => $name,
        'card_expiry' => $exp,
    ];
}

/**
 * 訂單列是否具備信用卡付款前應有的配送欄位（與 checkout 驗證一致）
 */
function credit_card_order_has_complete_shipping(array $orderRow): bool
{
    $sm = strtolower(trim((string)($orderRow['shipping_method'] ?? '')));
    if ($sm === 'home') {
        return trim((string)($orderRow['shipping_address'] ?? '')) !== '';
    }
    if ($sm === 'pickup') {
        return trim((string)($orderRow['pickup_store'] ?? '')) !== '';
    }
    return false;
}
