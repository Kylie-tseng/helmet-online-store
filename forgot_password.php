<?php
require_once 'config.php';
require_once 'includes/auth_layout.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘記密碼 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page auth-forgot-password-page">
    <?php renderAuthHeader('找回您的 HelmetVRse 帳號密碼'); ?>

    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">忘記密碼</h1>
            <p class="login-subtitle">請輸入您註冊時使用的電子郵件，我們將寄送重設密碼連結給您</p>

            <div id="forgotPasswordError" class="error-message">
                找不到此電子郵件對應的帳號，請確認後再試一次。
            </div>

            <form id="forgotPasswordForm" action="process_forgot_password.php" method="POST" novalidate>
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        placeholder="請輸入您的電子郵件"
                        required
                        autofocus
                    >
                </div>

                <button type="submit" class="login-btn auth-gradient-btn">寄送重設連結</button>
            </form>

            <div class="login-footer">
                <p>想起密碼了？ <a href="login.php">返回登入</a></p>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const form = document.getElementById('forgotPasswordForm');
            const emailInput = document.getElementById('email');
            const errorBox = document.getElementById('forgotPasswordError');
            if (!form || !emailInput || !errorBox) return;

            function setError(message) {
                errorBox.textContent = message;
                errorBox.style.display = 'block';
            }

            function clearError() {
                errorBox.style.display = 'none';
            }

            function validateEmail() {
                const value = emailInput.value.trim();

                if (!value) {
                    setError('請輸入電子郵件');
                    return false;
                }

                if (emailInput.validity.typeMismatch) {
                    setError('請輸入有效的電子郵件格式');
                    return false;
                }

                clearError();
                return true;
            }

            emailInput.addEventListener('input', validateEmail);

            form.addEventListener('submit', function(e) {
                if (!validateEmail()) {
                    e.preventDefault();
                    emailInput.focus();
                }
            });
        })();
    </script>
</body>
</html>
