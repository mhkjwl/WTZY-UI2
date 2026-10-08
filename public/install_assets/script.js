/**
 * @file install/assets/script.js
 * @description 安装向导交互：自定义确认弹窗（禁用原生 confirm）与按钮防重复提交
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */
(function () {
    'use strict';

    /**
     * 轻量确认弹窗（自绘遮罩 + 卡片，Esc 取消，遮罩点击不关闭防误触）
     * @param {string} message 确认文案
     * @returns {Promise<boolean>} 是否确认
     */
    window.confirm2 = function (message) {
        return new Promise(function (resolve) {
            var mask = document.createElement('div');
            mask.className = 'mini-mask';
            mask.innerHTML =
                '<div class="mini-modal" role="dialog" aria-modal="true">' +
                '  <p class="mini-text"></p>' +
                '  <div class="mini-actions">' +
                '    <button type="button" class="btn btn--ghost mini-cancel">取消</button>' +
                '    <button type="button" class="btn btn--primary mini-ok">确定</button>' +
                '  </div>' +
                '</div>';
            mask.querySelector('.mini-text').textContent = message;
            document.body.appendChild(mask);
            requestAnimationFrame(function () { mask.classList.add('is-in'); });

            function close(result) {
                mask.classList.remove('is-in');
                setTimeout(function () {
                    if (mask.parentNode) { mask.parentNode.removeChild(mask); }
                    document.removeEventListener('keydown', onKey);
                }, 150);
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') { close(false); }
            }

            mask.querySelector('.mini-ok').addEventListener('click', function () { close(true); });
            mask.querySelector('.mini-cancel').addEventListener('click', function () { close(false); });
            document.addEventListener('keydown', onKey);
            mask.querySelector('.mini-ok').focus();
        });
    };

    // 「开始安装」等带 data-confirm 的按钮：确认后提交并锁定按钮防重复安装
    document.querySelectorAll('button[data-confirm]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            confirm2(btn.getAttribute('data-confirm')).then(function (ok) {
                if (!ok) { return; }
                btn.disabled = true;
                btn.textContent = '安装中，请勿关闭页面…';
                btn.form.submit();
            });
        });
    });
})();
