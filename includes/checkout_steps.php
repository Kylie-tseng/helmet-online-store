<?php
/**
 * 結帳步驟條
 * @param int $current_step 目前步驟 (1=購物車, 2=填寫資料, 3=訂單確認)
 * @param string|array $extra_steps 額外步驟名稱（例如：信用卡繳費、訂單完成）
 */
function renderCheckoutSteps($current_step = 1, $extra_steps = '') {
    $steps = ['購物車', '填寫資料', '訂單確認'];

    if (is_string($extra_steps) && $extra_steps !== '') {
        $extra_steps = [$extra_steps];
    } elseif (!is_array($extra_steps)) {
        $extra_steps = [];
    }

    foreach ($extra_steps as $extra_step) {
        if ($extra_step !== '') {
            $steps[] = $extra_step;
        }
    }

    $total_steps = count($steps);
    ?>
    <div class="checkout-steps steps-count-<?php echo (int)$total_steps; ?>">
        <?php foreach ($steps as $index => $label): ?>
            <?php $step_number = $index + 1; ?>
            <div class="checkout-step <?php echo $current_step == $step_number ? 'active' : ($current_step > $step_number ? 'completed' : ''); ?>">
                <div class="step-circle"><?php echo $step_number; ?></div>
                <div class="step-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php if ($step_number < $total_steps): ?>
                <div class="step-connector <?php echo $current_step >= ($step_number + 1) ? 'active' : ''; ?>"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php
}

