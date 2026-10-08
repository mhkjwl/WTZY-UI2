/**
 * @file assets/ui/shell.js
 * @description 新版外壳增强（光年 v5）：补齐移动端抽屉的三种收起时机（Esc / 视口复位 / bfcache 返回），
 *              并维护侧边栏分组的展开记忆（按端隔离，仅在当前页不在任何分组时恢复）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-08
 */
(function () {
    'use strict';

    /**
     * 收起移动端抽屉：模板脚本负责按钮与遮罩交互，这里补齐键盘与视口变化时的收敛，
     * 避免旋转屏幕或前进后退回到页面时残留展开态
     */
    function closeDrawer() {
        var side = document.querySelector('.lyear-layout-sidebar');
        if (!side || !side.classList.contains('lyear-aside-open')) { return; }
        side.classList.remove('lyear-aside-open');
        document.body.classList.remove('lyear-layout-sidebar-close');
        var mask = document.querySelector('.lyear-mask-modal');
        if (mask && mask.parentNode) { mask.parentNode.removeChild(mask); }
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeDrawer(); }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) { closeDrawer(); }
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { closeDrawer(); }
    });

    /**
     * 分组展开记忆：当前页所在分组由服务端直出展开态；
     * 此处记录用户手动展开/收起的分组，并在当前页不落在任何分组时恢复，
     * 记忆按端（data-side）隔离存放本地，存储不可用时静默放弃
     */
    var nav = document.getElementById('side-nav');
    if (!nav) { return; }
    var storeKey = 'wtzy.side-open.' + (nav.dataset.side || 'common');

    function groupName(li) {
        return li && li.dataset ? (li.dataset.group || '') : '';
    }
    function groups() {
        return nav.querySelectorAll('.nav-item-has-subnav');
    }

    nav.addEventListener('click', function (e) {
        var head = e.target && e.target.closest ? e.target.closest('.nav-item-has-subnav > a') : null;
        if (!head || !nav.contains(head)) { return; }
        var li = head.parentElement;
        try {
            // 点击时 class 尚未切换：未展开的分组即将展开（记忆它），已展开的即将收起（撤销记忆）
            if (li.classList.contains('open')) {
                if (window.localStorage.getItem(storeKey) === groupName(li)) {
                    window.localStorage.removeItem(storeKey);
                }
            } else {
                window.localStorage.setItem(storeKey, groupName(li));
            }
        } catch (err) {
            /* 隐私模式或存储被禁用：放弃记忆，不影响折叠本身 */
        }
    });

    if (!nav.querySelector('.nav-item-has-subnav.open')) {
        try {
            var remembered = window.localStorage.getItem(storeKey);
            if (remembered) {
                groups().forEach(function (li) {
                    if (groupName(li) === remembered) { li.classList.add('open'); }
                });
            }
        } catch (err) {
            /* 同上：存不下就不记忆 */
        }
    }
})();