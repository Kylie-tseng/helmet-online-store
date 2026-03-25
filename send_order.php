<?php
/**
 * 訂單通知信發送腳本 (自動識別來源變數)
 */

require_once __DIR__ . '/includes/cart_functions.php';

// 1. 變數兼容性處理 (重要：解決不同頁面引入時的變數命名差異)
$mail_order_id = $order_id ?? ($order['id'] ?? 'N/A');
$mail_payment_method = $payment_method ?? ($order['payment_method'] ?? 'unknown');

// 決定要循環的商品陣列 (信用卡頁面使用 $order_items, 確認頁面使用 $cart_items)
$mail_items = !empty($cart_items) ? $cart_items : ($order_items ?? []);

// 決定金額 (優先從 $order_summary 拿，沒有則從資料庫 $order 拿)
$mail_discount = $order_summary['discount'] ?? ((isset($order) && is_array($order)) ? (float)($order['discount_amount'] ?? 0) : 0);
$mail_total = $order_summary['final_total'] ?? ((isset($order) && is_array($order)) ? get_order_payable_amount($order) : 0);

$payment_method_names = [
    'credit_card' => '信用卡',
    'cod' => '貨到付款'
];

// 2. 載入 PHPMailer
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 3. 抓取消費者 Email
try {
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
    $userStmt->execute([':id' => $user_id]);
    $user = $userStmt->fetch();
    $customer_email = $user['email'] ?? '';
} catch (Exception $e) {
    $customer_email = ''; 
}

if (!empty($customer_email) && !empty($mail_items)) {
    $mail = new PHPMailer(true);
    try {
        // --- 伺服器設定 ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'helmetvrsefju@gmail.com';
        $mail->Password   = 'avpwtgymnlgekpyv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'utf-8';

        // --- 收寄件人 ---
        $mail->setFrom('helmetvrsefju@gmail.com', 'HelmetVRse 客服中心');
        $mail->addAddress($customer_email); 

        // --- 郵件內容 ---
        $mail->isHTML(true);
        $mail->Subject = "【HelmetVRse】訂單確認通知 - 單號 #$mail_order_id";

        $item_rows = "";
        foreach ($mail_items as $item) {
            // 這裡自動處理 $item['price'] (購物車) 或 $item['unit_price'] (訂單明細)
            $unit_p = $item['price'] ?? ($item['unit_price'] ?? 0);
            $sub = number_format($unit_p * $item['quantity']);
            $size_label = formatCartSizeForDisplay($item['size'] ?? '');
            $item_rows .= "
                <tr>
                    <td style='border:1px solid #ddd; padding:8px;'>{$item['product_name']}（尺寸：{$size_label}）</td>
                    <td style='border:1px solid #ddd; padding:8px; text-align:center;'>{$item['quantity']}</td>
                    <td style='border:1px solid #ddd; padding:8px; text-align:right;'>NT$ $sub</td>
                </tr>";
        }

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
                <h2 style='color: #2c3e50;'>感謝您的訂購！</h2>
                <p>親愛的顧客您好，我們已收到您的訂單，正在為您準備出貨。</p>
                <p><strong>訂單編號：</strong> #$mail_order_id</p>
                <p><strong>付款方式：</strong> " . ($payment_method_names[$mail_payment_method] ?? $mail_payment_method) . "</p>
                
                <table style='width:100%; border-collapse: collapse; margin-top: 20px;'>
                    <thead>
                        <tr style='background-color: #f8f9fa;'>
                            <th style='border:1px solid #ddd; padding:8px;'>商品</th>
                            <th style='border:1px solid #ddd; padding:8px;'>數量</th>
                            <th style='border:1px solid #ddd; padding:8px;'>小計</th>
                        </tr>
                    </thead>
                    <tbody>
                        $item_rows
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='2' style='border:1px solid #ddd; padding:8px; text-align:right;'>優惠券折扣</td>
                            <td style='border:1px solid #ddd; padding:8px; text-align:right;'>- NT$ " . number_format($mail_discount) . "</td>
                        </tr>
                        <tr>
                            <td colspan='2' style='border:1px solid #ddd; padding:8px; text-align:right;'><strong>最終總價</strong></td>
                            <td style='border:1px solid #ddd; padding:8px; text-align:right; color: #e74c3c;'><strong>NT$ " . number_format($mail_total) . "</strong></td>
                        </tr>
                    </tfoot>
                </table>
                <p style='margin-top: 20px; font-size: 12px; color: #7f8c8d;'>這是系統自動發送的郵件，請勿直接回覆。</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("郵件發送失敗: {$mail->ErrorInfo}");
    }
}