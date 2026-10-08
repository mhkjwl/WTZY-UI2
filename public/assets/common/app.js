/**
 * @file assets/common/app.js
 * @description 前端公共助手：统一 Ajax 封装（JSON + CSRF 头）与响应反馈、图片放大预览、
 *              侧边栏待办徽标同步（菜单分组展开与移动端抽屉由模板脚本与 assets/ui/shell.js 负责）
 * @author AI
 * @version 0.6
 * @date 2026-10-08
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    async function request(method, url, data) {
        var options = {
            method: method,
            headers: {
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'fetch'
            }
        };
        if (data !== undefined && method !== 'GET') {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data || {});
        }
        var res;
        try {
            res = await fetch(url, options);
        } catch (e) {
            // 断网/超时/CORS：统一返回失败响应，调用方无需各自捕获（规范 §5.3 禁止静默失败）
            return { success: false, message: '网络异常，请检查网络后重试' };
        }
        var body = null;
        try {
            body = await res.json();
        } catch (e) {
            body = { success: false, message: '响应格式异常（HTTP ' + res.status + '）' };
        }
        return body;
    }

    /**
     * 图片放大预览：在页面内弹层看原图，替代新窗口打开
     * 视图只需给缩略图加 data-zoom="原图地址"，点击由下方事件委托统一接管
     */
    function openImagePreview(src) {
        if (!src) { return; }
        var dlg = document.getElementById('img-preview');
        if (!dlg) {
            dlg = document.createElement('dialog');
            dlg.id = 'img-preview';
            dlg.className = 'img-preview';
            dlg.innerHTML = '<img alt="图片预览"><button type="button" class="img-preview__close" aria-label="关闭">×</button>';
            document.body.appendChild(dlg);
            // 点击图片以外的区域即关闭，符合看图的顺手习惯
            dlg.addEventListener('click', function (e) {
                if (e.target === dlg) { dlg.close(); }
            });
            dlg.querySelector('.img-preview__close').addEventListener('click', function () { dlg.close(); });
        }
        dlg.querySelector('img').src = src;
        dlg.showModal();
    }

    // 事件委托：全站图片共用，动态追加的图片同样生效，无需各自绑定
    document.addEventListener('click', function (e) {
        var img = e.target && e.target.closest ? e.target.closest('img[data-zoom]') : null;
        if (!img) { return; }
        e.preventDefault();
        openImagePreview(img.getAttribute('data-zoom') || img.getAttribute('src'));
    });

    window.App = {
        csrf: csrfToken,
        get: function (url) { return request('GET', url); },
        post: function (url, data) { return request('POST', url, data || {}); },
        put: function (url, data) { return request('PUT', url, data || {}); },
        delete: function (url, data) { return request('DELETE', url, data || {}); },

        /** 图片放大预览（视图用 data-zoom 触发，也可直接调用） */
        previewImage: openImagePreview,

        /**
         * 统一响应反馈：success 弹成功提示，否则弹错误提示；失败时页面保持原状（规范 §2.5）
         * 成功后顺带刷新侧边栏待办徽标，处理完的事项数字随之消失
         * @returns {Object} 原始响应体，便于业务继续读取 data
         */
        handle: function (res, successMsg) {
            if (res && res.success) {
                Notify.success(successMsg || res.message || '操作成功');
                refreshTodoBadges();
            } else {
                Notify.error((res && res.message) || '操作失败，请稍后重试');
                // 账号被停用或会话失效：提示后引导回登录页（属页面间真实跳转，非操作后刷新列表）
                if (res && res.data && res.data.redirect) {
                    setTimeout(function () { location.href = res.data.redirect; }, 1200);
                }
            }
            return res;
        }
    };

    /**
     * 刷新侧边栏待办数量：页面渲染时已带初始值，此处用于操作后与定时同步
     * 数量归零的项直接移除徽标，实现「处理以后消失」
     */
    window.refreshTodoBadges = async function () {
        var url = document.body ? document.body.dataset.todoUrl : '';
        if (!url) { return; }
        try {
            var res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
            var data = await res.json();
            if (!data || !data.success) { return; }
            document.querySelectorAll('[data-badge-key]').forEach(function (el) {
                var n = parseInt(data.data[el.dataset.badgeKey], 10) || 0;
                if (n > 0) {
                    el.textContent = n > 99 ? '99+' : String(n);
                    el.hidden = false;
                } else {
                    el.hidden = true;
                }
            });
            syncBlockBadges(document.getElementById('side-nav'));
        } catch (err) {
            // 待办刷新失败不影响主流程
        }
    };

    // 定时同步（60 秒）与页面切回前台时同步，保证数字与后台数据一致
    if (document.body && document.body.dataset.todoUrl) {
        setInterval(refreshTodoBadges, 60000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { refreshTodoBadges(); }
        });
    }

    /**
     * 待办数量汇总到一级分组：分组收起时也能看到该分组内有多少事项待处理
     * 菜单结构见 partials/side_nav.php：分组长为 .nav-item-has-subnav，汇总徽标为 .nav-badge--group
     */
    function syncBlockBadges(nav) {
        if (!nav || typeof nav.querySelectorAll !== 'function') { return; }
        nav.querySelectorAll('.nav-item-has-subnav').forEach(function (group) {
            var sum = 0;
            group.querySelectorAll('.nav-badge[data-badge-key]').forEach(function (el) {
                var n = el.hidden ? 0 : (parseInt(el.textContent, 10) || 0);
                if (n > 0) { sum += n; }
            });
            var box = group.querySelector('.nav-badge--group');
            if (!box) { return; }
            box.textContent = sum > 99 ? '99+' : String(sum);
            box.hidden = sum === 0;
        });
    }

    /**
     * 初始化侧边栏：当前页所在分组的展开态由服务端直出，这里负责汇总分组待办数量。
     * 「权限矩阵保存后局部替换侧边栏」会再次调用，因此内部只依赖传入/查找到的节点，可重复执行
     */
    window.sideNavInit = function (nav) {
        nav = nav || document.getElementById('side-nav');
        if (!nav || typeof nav.querySelectorAll !== 'function') { return; }
        syncBlockBadges(nav);
    };

    window.sideNavInit();

    /**
     * 退出登录：确认弹窗（规范 §4.1 退出属确认类）→ POST 提交 → 成功后跳首页
     */
    window.logoutConfirm = function (url) {
        Confirm.show({
            title: '退出登录',
            message: '确定要退出当前账号吗？',
            confirmText: '退出',
            onConfirm: async function () {
                var res = await App.post(url);
                if (res.success && res.data && res.data.redirect) {
                    location.href = res.data.redirect;
                } else {
                    Notify.error(res.message || '退出失败');
                }
            }
        });
    };

    // 波纹点击反馈：作用于所有按钮与磁贴类可点元素
    document.addEventListener('pointerdown', function (e) {
        var target = e.target.closest('.btn, .tile');
        if (!target) { return; }
        var rect = target.getBoundingClientRect();
        var ripple = document.createElement('span');
        var size = Math.max(rect.width, rect.height) * 2;
        ripple.className = 'ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        target.appendChild(ripple);
        setTimeout(function () { ripple.remove(); }, 600);
    });

    /**
     * 局部表渲染：仅替换 [data-table-wrap] 容器内容（规范 §2.5 无刷新）
     * 权限在操作期间被回收时服务端返回 403，会话失效时跳登录页，两种情况下都保持原内容并提示，
     * 避免把错误页或登录页塞进列表区
     */
    window.admTable = async function (url) {
        var wrap = document.querySelector('[data-table-wrap]');
        if (!wrap) { location.href = url; return; }
        wrap.style.opacity = '0.55';
        try {
            var res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
            if (!res.ok || res.redirected) {
                Notify.error(res.status === 403 ? '权限已变更，无法加载该列表，请刷新页面' : '登录状态已失效，请重新登录');
                return;
            }
            wrap.innerHTML = await res.text();
            wrap.dataset.url = url;
        } catch (err) {
            Notify.error('网络异常，列表未能刷新');
        } finally {
            wrap.style.opacity = '1';
        }
    };

    /**
     * 刷新当前列表（操作成功后调用）
     */
    window.admRefresh = function () {
        var wrap = document.querySelector('[data-table-wrap]');
        if (wrap && wrap.dataset.url) { admTable(wrap.dataset.url); }
    };

    /**
     * 工具栏检索/分页入口：拼接查询串后局部刷新
     */
    window.admSearch = function (base, params) {
        var qs = new URLSearchParams(params || {}).toString();
        admTable(base + (qs ? '?' + qs : ''));
    };

    /**
     * 刷新图形验证码：加时间戳绕过浏览器缓存，并清空已输入的旧值
     */
    window.refreshCaptcha = function (scene) {
        var img = document.getElementById('captcha-img-' + scene);
        var input = document.getElementById('captcha-' + scene);
        if (img) { img.src = '/captcha?scene=' + encodeURIComponent(scene) + '&t=' + Date.now(); }
        if (input) { input.value = ''; }
    };

    /**
     * 读取表单控件值（后台各页表单通用，控件不存在时返回空串）
     */
    window.val = function (id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    };

    /**
     * 表单类操作统一入口：先二次确认再执行（规范 §4.1 编辑保存类属确认范围）
     * @param {string}   message 确认弹窗正文
     * @param {Function} run     确认后执行的回调（可返回 Promise，期间按钮显示加载态）
     * @param {Object}   [opts]  可选：title / level / confirmText / danger
     */
    window.admConfirm = function (message, run, opts) {
        opts = opts || {};
        Confirm.show({
            title: opts.title || '操作确认',
            level: opts.level || 'L1',
            message: message,
            confirmText: opts.confirmText || '确认执行',
            // 与 confirm.js 的默认一致：确认弹窗默认按危险操作呈现（图标+红色按钮），传 danger:false 可关闭
            danger: opts.danger !== false,
            onConfirm: run
        });
    };

    /**
     * 对话框表单统一提交：成功关弹窗 + 刷新列表
     */
    window.admSubmit = async function (form) {
        var data = Object.fromEntries(new FormData(form).entries());
        var res = await App.post(form.getAttribute('action'), data);
        App.handle(res);
        if (res.success) {
            var dlg = form.closest('dialog');
            if (dlg) { dlg.close(); }
            admRefresh();
        }
        return res.success;
    };

    /**
     * 行内危险操作：确认弹窗 → POST → 刷新列表
     */
    window.admAction = function (url, data, message, options) {
        options = options || {};
        Confirm.show({
            title: options.title || '操作确认',
            level: options.level || 'L1',
            message: message || '确定执行该操作吗？',
            confirmText: options.confirmText || '确认',
            // 与 confirm.js 的默认一致：确认弹窗默认按危险操作呈现（图标+红色按钮），传 danger:false 可关闭
            danger: options.danger !== false,
            onConfirm: async function () {
                var res = await App.post(url, data);
                App.handle(res);
                if (res.success) { admRefresh(); }
            }
        });
    };

    /**
     * 带输入理由的操作（如拦截/取消原因）：由页面内 dialog 完成后调用
     */
    window.admCloseDialog = function (btn) {
        var dlg = btn.closest('dialog');
        if (dlg) { dlg.close(); }
    };
})();