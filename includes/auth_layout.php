<?php
/**
 * 會員驗證頁專用版型
 */

function renderAuthHeader($message = '') {
    ?>
    <header class="auth-header">
        <div class="auth-header-inner">
            <a href="index.php" class="auth-brand">HelmetVRse</a>
            <p class="auth-header-message"><?php echo htmlspecialchars($message); ?></p>
        </div>
    </header>
    <?php
}
