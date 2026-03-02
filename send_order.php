<?php

$payment_method_names = [
    'credit_card' => '信用卡',
    'cod' => '貨到付款'
];

// 因為是用 include 引入，這裡不需要重新連接資料庫 config.php
// 只需要載入 PHPMailer 的檔案
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. 抓取消費者的 Email (從資料庫抓取目前登入者的 Email)
try {
    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id");
    $userStmt->execute([':id' => $user_id]);
    $user = $userStmt->fetch();
    $customer_email = $user['email'] ?? '';
} catch (Exception $e) {
    $customer_email = ''; 
}

// 2. 如果有抓到 Email 才執行發信
if (!empty($customer_email)) {
    $mail = new PHPMailer(true);

    try {
        // --- 伺服器設定 ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'bobby930910@gmail.com';         // [修改] 你的 Gmail
        $mail->Password   = 'xhxitqgddgzvpxba';          // [修改] 16位應用程式密碼
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'utf-8';

        // --- 收寄件人 ---
        $mail->setFrom('bobby930910@gmail.com', 'HelmetVRse 客服中心');
        $mail->addAddress($customer_email); 

        // --- 郵件內容 ---
        $mail->isHTML(true);
        $mail->Subject = "【HelmetVRse】訂單確認通知 - 單號 #$order_id";

        // 動態生成訂單明細表格
        $item_rows = "";
        foreach ($cart_items as $item) {
            $sub = number_format($item['price'] * $item['quantity']);
            $item_rows .= "
                <tr>
                    <td style='border:1px solid #ddd; padding:8px;'>{$item['product_name']} ({$item['size']})</td>
                    <td style='border:1px solid #ddd; padding:8px; text-align:center;'>{$item['quantity']}</td>
                    <td style='border:1px solid #ddd; padding:8px; text-align:right;'>NT$ $sub</td>
                </tr>";
        }

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto;'>
                <h2 style='color: #2c3e50;'>感謝您的訂購！</h2>
                <p>親愛的顧客您好，我們已收到您的訂單，正在為您準備出貨。</p>
                <p><strong>訂單編號：</strong> #$order_id</p>
                <p><strong>付款方式：</strong> " . $payment_method_names[$payment_method] . "</p>
                
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
                            <td colspan='2' style='border:1px solid #ddd; padding:8px; text-align:right;'><strong>總計</strong></td>
                            <td style='border:1px solid #ddd; padding:8px; text-align:right; color: #e74c3c;'><strong>NT$ " . number_format($order_amount['total']) . "</strong></td>
                        </tr>
                    </tfoot>
                </table>
                <p style='margin-top: 20px; font-size: 12px; color: #7f8c8d;'>這是系統自動發送的郵件，請勿直接回覆。</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        // 如果發信失敗，我們悄悄記錄在伺服器，不要打斷使用者的畫面
        error_log("郵件發送失敗: {$mail->ErrorInfo}");
    }
}