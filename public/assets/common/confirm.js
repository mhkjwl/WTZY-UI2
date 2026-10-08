/**
 * @file assets/common/confirm.js
 * @description 二次确认弹窗组件 Confirm（规范 §4）：L1 普通确认 / L2 勾选确认 / L3 输入确认
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 *
 * 实现说明：使用原生 <dialog showModal()> 而非 z-index 遮罩层。
 * 原生弹窗会被浏览器放入 top layer，层级永远高于页面上任何 z-index 元素；
 * 若用普通定位元素实现，当调用方本身也是 <dialog>（如后台各模块的编辑弹窗）时，
 * 确认弹窗会被源弹窗盖住，因此必须同样进入 top layer。
 */
(function () {
    'use strict';

    /**
     * Confirm.show(options)
     * @param {Object}  options
     * @param {string}  options.title          标题（必填）
     * @param {string}  [options.level=L1]     确认等级 L1/L2/L3
     * @param {string}  options.message        说明文字（必填）
     * @param {string}  [options.confirmText]  确认按钮文字
     * @param {string}  [options.cancelText]   取消按钮文字
     * @param {boolean} [options.danger]       危险操作红色样式
     * @param {boolean} [options.requireCheckbox] L2：必须勾选确认复选框
     * @param {string}  [options.checkboxText] L2 复选框文字
     * @param {string}  [options.requireTyping]   L3：必须输入的确认文字
     * @param {Function} options.onConfirm     确认回调（可返回 Promise，等待期间按钮转加载态）
     * @param {Function} [options.onCancel]    取消回调
     */
    function show(options) {
        if (!options || !options.title || !options.message) {
            throw new Error('Confirm.show 需要 title 与 message');
        }
        var level = options.level || 'L1';
        var requireCheckbox = level === 'L2' || options.requireCheckbox === true;
        var requireTyping = level === 'L3' ? (options.requireTyping || 'DELETE') : (options.requireTyping || null);
        var danger = options.danger !== false;

        var dlg = document.createElement('dialog');
        dlg.className = 'confirm-dialog';
        dlg.innerHTML =
            '<div class="confirm-box" role="document" aria-label="' + esc(options.title) + '">' +
            '  <div class="confirm-box__head">' +
            '    <span class="confirm-box__icon' + (danger ? ' confirm-box__icon--danger' : '') + '">' + (danger ? '⚠' : 'ℹ') + '</span>' +
            '    <span class="confirm-box__title">' + esc(options.title) + '<em class="confirm-box__level">' + level + '</em></span>' +
            '  </div>' +
            '  <div class="confirm-box__body">' +
            '    <p class="confirm-box__message">' + esc(options.message) + '</p>' +
            (requireCheckbox
                ? '<label class="confirm-box__check"><input type="checkbox" id="modal-check"> ' + esc(options.checkboxText || '我已了解此操作的影响') + '</label>'
                : '') +
            (requireTyping
                ? '<p class="confirm-box__typing-hint">请输入 <b>' + esc(requireTyping) + '</b> 以确认执行：</p>' +
                  '<input type="text" class="confirm-box__typing" id="modal-typing" autocomplete="off">'
                : '') +
            '  </div>' +
            '  <div class="confirm-box__foot">' +
            '    <button type="button" class="btn btn--ghost" id="modal-cancel">' + esc(options.cancelText || '取消') + '</button>' +
            '    <button type="button" class="btn ' + (danger ? 'btn--danger' : 'btn--primary') + '" id="modal-confirm" disabled>' +
            esc(options.confirmText || '确认') + '</button>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(dlg);

        var confirmBtn = dlg.querySelector('#modal-confirm');
        var cancelBtn = dlg.querySelector('#modal-cancel');
        var checkbox = dlg.querySelector('#modal-check');
        var typing = dlg.querySelector('#modal-typing');

        // L2/L3：条件满足前禁用确认按钮
        function refreshConfirmState() {
            var ok = true;
            if (requireCheckbox && checkbox && !checkbox.checked) {
                ok = false;
            }
            if (requireTyping && typing && typing.value !== requireTyping) {
                ok = false;
            }
            confirmBtn.disabled = !ok;
        }
        if (checkbox) { checkbox.addEventListener('change', refreshConfirmState); }
        if (typing) { typing.addEventListener('input', refreshConfirmState); }
        // L1 无勾选/输入条件，须显式求值一次，否则确认按钮停留在初始 disabled 状态而无法点击
        refreshConfirmState();

        // closed：防止重复关闭；processing：回调执行期间禁止取消
        // 二者必须分开——若共用一个标志，回调完成后的 close() 会被当成重复关闭而被拦掉，弹窗无法消失
        var closed = false;
        var processing = false;

        function close(onDone) {
            if (closed) { return; }
            closed = true;
            if (dlg.open) { dlg.close(); }
            if (dlg.parentNode) { dlg.parentNode.removeChild(dlg); }
            if (onDone) { onDone(); }
        }

        function onCancel() {
            if (processing) { return; }
            close(typeof options.onCancel === 'function' ? options.onCancel : null);
        }

        function onConfirm() {
            if (confirmBtn.disabled || processing) { return; }
            processing = true;
            confirmBtn.disabled = true;
            confirmBtn.textContent = '处理中…';
            var result;
            try {
                result = typeof options.onConfirm === 'function' ? options.onConfirm() : null;
            } catch (err) {
                processing = false;
                Notify.error(err && err.message ? err.message : '操作失败');
                refreshConfirmState();
                confirmBtn.textContent = options.confirmText || '确认';
                return;
            }
            if (result && typeof result.then === 'function') {
                result.then(function () { close(); })
                      .catch(function (err) {
                          processing = false;
                          Notify.error(err && err.message ? err.message : '操作失败');
                          refreshConfirmState();
                          confirmBtn.textContent = options.confirmText || '确认';
                      });
            } else {
                close();
            }
        }

        cancelBtn.addEventListener('click', onCancel);
        confirmBtn.addEventListener('click', onConfirm);
        // Esc 关闭等同于取消；native dialog 会派发 cancel 事件
        dlg.addEventListener('cancel', function (e) {
            e.preventDefault();
            onCancel();
        });
        // 点击遮罩不关闭（防误触，规范 §4.4）：原生 dialog 点击 backdrop 时 target 为 dialog 本身
        dlg.addEventListener('click', function (e) {
            if (e.target === dlg) { e.preventDefault(); }
        });
        dlg.addEventListener('close', function () {
            if (dlg.parentNode) { dlg.parentNode.removeChild(dlg); }
        });

        dlg.showModal();
        (typing || confirmBtn).focus();
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    window.Confirm = { show: show };
})();
