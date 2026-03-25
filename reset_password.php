<?php
require_once 'config.php';
require_once 'includes/auth_layout.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;
$token_valid = true;
$submitted = $_SERVER["REQUEST_METHOD"] === "POST";

// --- 第一步：驗證 Token 是否有效 ---
if (empty($token)) {
    $token_valid = false;
    $error = '無效的請求，缺少重設資訊。';
}

if ($token_valid) {
    // 查詢 Token 是否存在且尚未過期
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $token_valid = false;
        $error = '此連結已失效或已過期，請重新申請「忘記密碼」。';
    }
}

// --- 第二步：處理密碼更新表單提交 ---
if ($submitted) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$token_valid) {
        // 若 token 已失效，維持前面錯誤訊息
    } elseif (strlen($new_password) < 8) {
        $error = '密碼長度至少需要 8 碼';
    } elseif ($new_password !== $confirm_password) {
        $error = '兩次輸入的密碼不一致';
    } else {
        // 加密新密碼
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 更新資料庫並清除 Token
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE reset_token = ?");
        $updateStmt->execute([$hashed_password, $token]);

        $success = true;
    }
}
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

    <main class="auth-main">
        <div class="login-container">
            <div class="login-card">
            <h1 class="login-title">重設密碼</h1>
            
            <?php if ($success): ?>
                <div class="success-message" style="display: block; color: #28a745; text-align: center; margin-bottom: 20px;">
                    密碼重設成功！您現在可以使用新密碼登入了。
                </div>
                <div class="login-footer">
                    <a href="login.php" class="login-btn auth-gradient-btn" style="text-decoration: none; display: block; text-align: center; line-height: 40px;">前往登入</a>
                </div>
            <?php else: ?>
                <p class="login-subtitle">請輸入您的新密碼</p>

                <?php if ($error !== ''): ?>
                    <div id="resetPasswordError" class="error-message" style="display:block;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php else: ?>
                    <div id="resetPasswordError" class="error-message" style="display:none;"></div>
                <?php endif; ?>

                <?php if ($token_valid): ?>
                <form id="resetPasswordForm" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" novalidate>
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
                <?php endif; ?>

                <div class="login-footer">
                    <p><a href="login.php">返回登入</a></p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        (function() {
            const form = document.getElementById('resetPasswordForm');
            if (!form) return; // 如果成功就不需要 JS 邏輯

            const passwordInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const errorBox = document.getElementById('resetPasswordError');
            let hasSubmitted = <?php echo $submitted ? 'true' : 'false'; ?>;

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

                if (!hasSubmitted && !password && !confirm) {
                    clearError();
                    return true;
                }

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
                hasSubmitted = true;
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