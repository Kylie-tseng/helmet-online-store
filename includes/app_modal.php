<?php
/**
 * 全站共用：自訂確認／提示彈窗（取代瀏覽器 alert / confirm）。
 *
 * 使用方式：
 * - 在 </body> 前呼叫 app_modal_render() 一次（可重複呼叫，僅輸出一次）。
 * - 表單加上 data-app-confirm="訊息" 與選填 data-app-confirm-title="標題"。
 * - JS：AppModal.confirm({ title, message, okText, cancelText }) → Promise<boolean>
 *       AppModal.alert({ title, message, okText }) → Promise<void>
 * - app_modal.js 會將 window.alert 轉成 AppModal.alert（頁面有 modal 標記時）。
 *   window.confirm / prompt 為同步 API，無法完整 polyfill，請用 AppModal.confirm 或表單 data-app-confirm。
 */
declare(strict_types=1);

if (!function_exists('app_modal_script_src')) {
    function app_modal_script_src(): string
    {
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#/(staff|admin)/#', $scriptName)) {
            return '../assets/js/app_modal.js';
        }
        return 'assets/js/app_modal.js';
    }
}

if (!function_exists('app_modal_render')) {
    function app_modal_render(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $src = app_modal_script_src();
        $path = __DIR__ . '/../assets/js/app_modal.js';
        $v = is_file($path) ? (int)filemtime($path) : 1;
        if ($v < 1) {
            $v = 1;
        }
        ?>
<div id="app-modal-overlay" class="app-modal-overlay" hidden></div>
<div
    id="app-modal-dialog"
    class="app-modal-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="app-modal-title"
    aria-describedby="app-modal-body"
    hidden
>
    <div class="app-modal-dialog__panel">
        <h2 id="app-modal-title" class="app-modal-dialog__title"></h2>
        <p id="app-modal-body" class="app-modal-dialog__body"></p>
        <div class="app-modal-dialog__actions">
            <button type="button" class="app-modal-btn app-modal-btn--ghost" id="app-modal-cancel">取消</button>
            <button type="button" class="app-modal-btn app-modal-btn--primary" id="app-modal-ok">確定</button>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo $v; ?>"></script>
        <?php
    }
}
