/**
 * HelmetVRse 全站共用 modal（取代 alert / confirm）
 */
(function (global) {
    'use strict';

    var overlay, dialog, titleEl, bodyEl, okBtn, cancelBtn;
    var mode = 'confirm';
    var finish = null;
    var inited = false;

    function qs() {
        if (!overlay) {
            overlay = document.getElementById('app-modal-overlay');
        }
        if (!dialog) {
            dialog = document.getElementById('app-modal-dialog');
        }
        if (!titleEl) {
            titleEl = document.getElementById('app-modal-title');
        }
        if (!bodyEl) {
            bodyEl = document.getElementById('app-modal-body');
        }
        if (!okBtn) {
            okBtn = document.getElementById('app-modal-ok');
        }
        if (!cancelBtn) {
            cancelBtn = document.getElementById('app-modal-cancel');
        }
    }

    function onKeydown(e) {
        if (!dialog || dialog.hasAttribute('hidden')) {
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            if (mode === 'confirm') {
                close(false);
            } else {
                close(undefined);
            }
        }
    }

    function openUi() {
        document.body.classList.add('app-modal-open');
        overlay.removeAttribute('hidden');
        dialog.removeAttribute('hidden');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function closeUi() {
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('hidden', 'hidden');
        dialog.setAttribute('hidden', 'hidden');
        document.body.classList.remove('app-modal-open');
    }

    function close(result) {
        var cb = finish;
        finish = null;
        closeUi();
        document.removeEventListener('keydown', onKeydown, true);
        if (cb) {
            cb(result);
        }
    }

    function onOverlayClick() {
        if (mode === 'alert') {
            close(undefined);
        } else {
            close(false);
        }
    }

    function initOnce() {
        if (inited) {
            return;
        }
        qs();
        if (!overlay || !dialog || !okBtn || !cancelBtn) {
            return;
        }
        inited = true;

        okBtn.addEventListener('click', function () {
            if (mode === 'alert') {
                close(undefined);
            } else {
                close(true);
            }
        });
        cancelBtn.addEventListener('click', function () {
            close(false);
        });
        overlay.addEventListener('click', onOverlayClick);
        dialog.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        document.addEventListener(
            'submit',
            function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }
                if (form.dataset.appConfirmBypass === '1') {
                    delete form.dataset.appConfirmBypass;
                    return;
                }
                var msg = form.getAttribute('data-app-confirm');
                if (!msg) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var title = form.getAttribute('data-app-confirm-title') || '請確認';
                global.AppModal.confirm({ title: title, message: msg }).then(function (ok) {
                    if (ok) {
                        form.dataset.appConfirmBypass = '1';
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            },
            true
        );
    }

    function ensureInit() {
        initOnce();
    }

    /**
     * 將殘留的 window.alert 導向自訂 modal（無法安全覆寫同步的 window.confirm）。
     * 若頁面未輸出 #app-modal-* 標記，仍呼叫原生 alert。
     */
    if (!global.__helmetAppModalAlertPatched) {
        global.__helmetAppModalAlertPatched = true;
        var nativeAlert = global.alert;
        global.alert = function (message) {
            ensureInit();
            qs();
            if (dialog && titleEl && bodyEl && global.AppModal) {
                global.AppModal.alert({
                    title: '提示',
                    message: String(message == null ? '' : message)
                });
                return;
            }
            nativeAlert.call(global, message);
        };
    }

    global.AppModal = {
        confirm: function (opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                ensureInit();
                qs();
                if (!dialog || !titleEl || !bodyEl) {
                    resolve(false);
                    return;
                }
                mode = 'confirm';
                titleEl.textContent = opts.title || '請確認';
                bodyEl.textContent = opts.message || '';
                okBtn.textContent = opts.okText || '確定';
                cancelBtn.textContent = opts.cancelText || '取消';
                cancelBtn.removeAttribute('hidden');
                cancelBtn.style.display = '';

                finish = function (ok) {
                    resolve(ok === true);
                };

                document.addEventListener('keydown', onKeydown, true);
                openUi();
                okBtn.focus();
            });
        },

        alert: function (opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                ensureInit();
                qs();
                if (!dialog || !titleEl || !bodyEl) {
                    resolve();
                    return;
                }
                mode = 'alert';
                titleEl.textContent = opts.title || '提示';
                bodyEl.textContent = opts.message || '';
                okBtn.textContent = opts.okText || '確定';
                cancelBtn.setAttribute('hidden', 'hidden');
                cancelBtn.style.display = 'none';

                finish = function () {
                    resolve();
                };

                document.addEventListener('keydown', onKeydown, true);
                openUi();
                okBtn.focus();
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureInit);
    } else {
        ensureInit();
    }
})(typeof window !== 'undefined' ? window : this);
