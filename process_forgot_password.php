<?php
require_once 'config.php';
date_default_timezone_set('Asia/Taipei');

// 1. 手動載入 PHPMailer (參考你跑得動的那份路徑)
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // 2. 檢查資料庫
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 生成 Token 與時間
        $token = bin2hex(random_bytes(32)); 
        $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

        // 更新資料庫
        $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?");
        $updateStmt->execute([$token, $expires, $email]);

        // 3. 開始發信
        $mail = new PHPMailer(true);
        try {
            // --- 伺服器設定 (沿用你跑得動的設定) ---
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'helmetvrsefju@gmail.com'; // 你的 Gmail
            $mail->Password   = 'avpwtgymnlgekpyv';      // 你的應用程式密碼
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'utf-8';

            // 收寄件人
            $mail->setFrom('helmetvrsefju@gmail.com', 'HelmetVRse 客服中心');
            $mail->addAddress($email); 

            // 重設連結 (請確保路徑正確)
            $resetLink = "http://localhost/dashboard/helmetvrse/helmet-online-store/reset_password.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = "【HelmetVRse】重設您的帳號密碼";
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; line-height: 1.6;'>
                    <h2 style='color: #2c3e50;'>您好！</h2>
                    <p>我們收到了重設您 HelmetVRse 帳號密碼的請求。</p>
                    <p>請點選下方的按鈕來重新設定密碼（連結將於 30 分鐘後失效）：</p>
                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' style='background-color: #3498db; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>立即重設密碼</a>
                    </p>
                    <p>如果按鈕無法點擊，請複製以下網址：<br>
                    <a href='{$resetLink}'>{$resetLink}</a></p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #7f8c8d;'>如果您並未要求重設密碼，請忽略此郵件。</p>
                </div>
            ";

            $mail->send();
            echo "<script>alert('重設連結已寄出，請檢查您的信箱！'); window.location.href='login.php';</script>";

        } catch (Exception $e) {
            error_log("郵件發送失敗: {$mail->ErrorInfo}");
            echo "<script>alert('發送失敗，請稍後再試。'); history.back();</script>";
        }
    } else {
        echo "<script>alert('找不到此電子郵件對應的帳號。'); history.back();</script>";
    }
}