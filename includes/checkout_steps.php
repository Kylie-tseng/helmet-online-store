<?php
/**
 * 結帳步驟條
 * @param int $current_step 目前步驟 (1=購物車, 2=填寫資料, 3=訂單確認)
 */
function renderCheckoutSteps($current_step = 1) {
    ?>
    <div class="checkout-steps">
        <div class="checkout-step <?php echo $current_step == 1 ? 'active' : ($current_step > 1 ? 'completed' : ''); ?>">
            <div class="step-circle">1</div>
            <div class="step-label">購物車</div>
        </div>
        <div class="step-connector <?php echo $current_step >= 2 ? 'active' : ''; ?>"></div>
        <div class="checkout-step <?php echo $current_step == 2 ? 'active' : ($current_step > 2 ? 'completed' : ''); ?>">
            <div class="step-circle">2</div>
            <div class="step-label">填寫資料</div>
        </div>
        <div class="step-connector <?php echo $current_step >= 3 ? 'active' : ''; ?>"></div>
        <div class="checkout-step <?php echo $current_step == 3 ? 'active' : ''; ?>">
            <div class="step-circle">3</div>
            <div class="step-label">訂單確認</div>
        </div>
    </div>
    <?php
}

