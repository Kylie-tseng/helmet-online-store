<?php
require_once 'config.php';
require_once 'includes/auth_layout.php';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重設密碼 - HelmetVRse</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page auth-reset-password-page">
    <?php renderAuthHeader('設定您的新密碼'); ?>

    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">重設密碼</h1>
            <p class="login-subtitle">請輸入您的新密碼</p>

            <div id="resetPasswordError" class="error-message">
                兩次輸入的密碼不一致，請重新確認。
            </div>

            <form id="resetPasswordForm" action="#" method="POST" novalidate>
                <div class="form-group">
                    <label for="new_password" class="form-label">新密碼</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        class="form-input"
                        placeholder="請輸入新密碼"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">確認密碼</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        class="form-input"
                        placeholder="請再次輸入新密碼"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="login-btn auth-gradient-btn">確認送出</button>
            </form>

            <div class="login-footer">
                <p><a href="login.php">返回登入</a></p>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const form = document.getElementById('resetPasswordForm');
            const passwordInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const errorBox = document.getElementById('resetPasswordError');
            if (!form || !passwordInput || !confirmInput || !errorBox) return;

            function setError(message) {
                errorBox.textContent = message;
                errorBox.style.display = 'block';
            }

            function clearError() {
                errorBox.style.display = 'none';
            }

            function validatePasswords() {
                const password = passwordInput.value;
                const confirm = confirmInput.value;

                if (!password || !confirm) {
                    setError('請完整填寫新密碼與確認密碼');
                    return false;
                }

                if (password.length < 8) {
                    setError('密碼長度至少需要 8 碼');
                    return false;
                }

                if (password !== confirm) {
                    setError('兩次輸入的密碼不一致，請重新確認');
                    return false;
                }

                clearError();
                return true;
            }

            passwordInput.addEventListener('input', validatePasswords);
            confirmInput.addEventListener('input', validatePasswords);

            form.addEventListener('submit', function(e) {
                if (!validatePasswords()) {
                    e.preventDefault();
                    if (!passwordInput.value || passwordInput.value.length < 8) {
                        passwordInput.focus();
                    } else {
                        confirmInput.focus();
                    }
                }
            });
        })();
    </script>
</body>
</html>
