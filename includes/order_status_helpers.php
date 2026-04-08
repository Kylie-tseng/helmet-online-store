<?php

function appOrderStatusMap(): array
{
    return [
        'pending' => '待處理',
        'pending_payment' => '待付款',
        'paid' => '已付款',
        'shipped' => '已出貨',
        'completed' => '已完成',
        'cancelled' => '已取消',
        'progress' => '處理中',
        'done' => '已完成',
        'active' => '上架中',
        'inactive' => '未上架',
    ];
}

function appOrderStatusLabel(string $status): string
{
    $key = strtolower(trim($status));
    $map = appOrderStatusMap();
    return $map[$key] ?? $status;
}

function appStatusBadgeClass(string $status): string
{
    $status = strtolower(trim($status));
    if (in_array($status, ['pending', 'pending_payment', '待處理', '待出貨', '處理中', 'pending_refund', '待退款'], true)) {
        return 'pending';
    }
    if (in_array($status, ['paid', 'shipped', '已出貨', '已付款', 'progress'], true)) {
        return 'progress';
    }
    if (in_array($status, ['completed', '已完成', 'approved', '核准', 'done', 'refunded', '已退款', 'active'], true)) {
        return 'done';
    }
    if (in_array($status, ['cancelled', '取消', 'rejected', '駁回', 'inactive'], true)) {
        return 'danger';
    }
    return 'neutral';
}

function appRefundStatusLabel(string $status): string
{
    $key = strtolower(trim($status));
    $map = [
        'pending_refund' => '待退款',
        'refunded' => '已退款',
    ];
    return $map[$key] ?? ($status !== '' ? $status : '待退款');
}

function get_order_status_key(string $status): string
{
    $key = strtolower(trim($status));
    if ($key === 'pending' || $key === 'shipped' || $key === 'completed' || $key === 'cancelled') {
        return $key;
    }

    // 舊資料兼容：統一映射到前後台共同顯示狀態
    if (in_array($key, ['pending_payment', 'paid', 'processing', 'progress'], true)) {
        return 'pending';
    }
    if (in_array($key, ['done'], true)) {
        return 'completed';
    }

    return 'pending';
}

function get_order_status_text(string $status): string
{
    $map = [
        'pending' => '待處理',
        'shipped' => '已出貨',
        'completed' => '已完成',
        'cancelled' => '已取消',
    ];
    $key = get_order_status_key($status);
    return $map[$key] ?? '待處理';
}

function get_order_status_modifier_class(string $status): string
{
    $key = get_order_status_key($status);
    return 'order-status--' . $key;
}

function get_order_status_class(string $status): string
{
    return 'order-status-badge ' . get_order_status_modifier_class($status);
}

function can_cancel_order_status(string $status): bool
{
    return get_order_status_key($status) === 'pending' && strtolower(trim($status)) === 'pending';
}

function can_cancel_order(array $order): bool
{
    return can_cancel_order_status((string)($order['status'] ?? ''));
}

