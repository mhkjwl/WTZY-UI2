/**
 * @file assets/common/toast.js
 * @description 全局通知组件 Notify（规范 §3）：右上角堆叠、五类通知、可关闭、非阻塞
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */
(function () {
    'use strict';

    var TYPES = {
        success: { icon: '✓', cls: 'notify--success' },
        error:   { icon: '✕', cls: 'notify--error' },
        warning: { icon: '!', cls: 'notify--warning' },
        info:    { icon: 'i', cls: 'notify--info' },
        loading: { icon: '⟳', cls: 'notify--loading' }
    };

    // 停留时长（规范 §3.3）：成功 2.5s / 错误 4s / 警告 3.5s / 信息 3s / 加载手动关闭
    var DURATION = { success: 2500, error: 4000, warning: 3500, info: 3000, loading: 0 };
    var MAX_STACK = 3;

    function root() {
        var el = document.getElementById('notify-root');
        if (!el) {
            el = document.createElement('div');
            el.id = 'notify-root';
            el.className = 'notify-root';
            document.body.appendChild(el);
        }
        // 采用 popover 让通知同样进入 top layer：弹窗打开时通知才不会被遮住
        if (el.getAttribute('popover') !== 'manual') {
            el.setAttribute('popover', 'manual');
        }
        return el;
    }

    /** 进入 top layer（不支持 popover 的浏览器静默降级为普通定位） */
    function showLayer(container) {
        try {
            if (typeof container.showPopover === 'function' && !container.matches(':popover-open')) {
                container.showPopover();
            }
        } catch (err) {
            /* popover 不可用时保持原有 z-index 行为 */
        }
    }

    /** 队列清空后退出 top layer，避免空容器拦截页面点击 */
    function hideLayer(container) {
        try {
            if (container.childElementCount === 0
                && typeof container.hidePopover === 'function'
                && container.matches(':popover-open')) {
                container.hidePopover();
            }
        } catch (err) {
            /* 同上，忽略 */
        }
    }

    function buildNode(type, message, options) {
        var meta = TYPES[type] || TYPES.info;
        var item = document.createElement('div');
        item.className = 'notify ' + meta.cls;
        item.setAttribute('role', 'status');

        var icon = document.createElement('span');
        icon.className = 'notify__icon' + (type === 'loading' ? ' notify__icon--spin' : '');
        icon.textContent = meta.icon;

        var text = document.createElement('span');
        text.className = 'notify__text';
        text.textContent = message || '';

        item.appendChild(icon);
        item.appendChild(text);

        var closeBtn = document.createElement('button');
        closeBtn.className = 'notify__close';
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', '关闭');
        closeBtn.textContent = '×';
        item.appendChild(closeBtn);

        return { node: item, closeBtn: closeBtn };
    }

    /**
     * 展示一条通知；opts.duration 覆盖默认停留，opts.sticky 常驻
     * @returns {{close: Function}} 返回句柄，loading 类型需手动调用 close()
     */
    function notify(type, message, options) {
        options = options || {};
        var built = buildNode(type, message, options);
        var container = root();

        // 堆叠上限 3 条：超出替换最早一条
        var existing = container.querySelectorAll('.notify:not(.notify--leaving)');
        if (existing.length >= MAX_STACK) {
            dismiss(existing[0]);
        }

        container.appendChild(built.node);
        showLayer(container);
        // 下一帧加入入场态，触发滑入+淡入动画
        requestAnimationFrame(function () {
            built.node.classList.add('is-in');
        });

        var timer = null;
        var duration = 'duration' in options ? options.duration : DURATION[type];
        if (duration > 0 && !options.sticky) {
            timer = setTimeout(function () { dismiss(built.node); }, duration);
        }

        built.closeBtn.addEventListener('click', function () {
            if (timer) { clearTimeout(timer); }
            dismiss(built.node);
        });

        return {
            close: function () {
                if (timer) { clearTimeout(timer); }
                dismiss(built.node);
            }
        };
    }

    function dismiss(node) {
        if (!node || node.classList.contains('notify--leaving')) {
            return;
        }
        node.classList.add('notify--leaving');
        setTimeout(function () {
            var container = node.parentNode;
            if (container) {
                container.removeChild(node);
                hideLayer(container);
            }
        }, 250);
    }

    window.Notify = {
        success: function (msg, opts) { return notify('success', msg, opts); },
        error: function (msg, opts) { return notify('error', msg, opts); },
        warning: function (msg, opts) { return notify('warning', msg, opts); },
        info: function (msg, opts) { return notify('info', msg, opts); },
        loading: function (msg, opts) { return notify('loading', msg, opts || { sticky: true }); },
        clear: function () {
            root().querySelectorAll('.notify').forEach(dismiss);
        }
    };
    // 统一别名：业务代码 Notify / Toast 任选其一，同一实现
    window.Toast = window.Notify;
})();
