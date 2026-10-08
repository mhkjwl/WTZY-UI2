<?php declare(strict_types=1);
/**
 * @file app/Views/admin/system_roles.php
 * @description 角色权限矩阵页
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array $perms
 * @var string $csrf
 */
?>
<section class="panel">
    <div class="adm-toolbar">
    </div>
    <div class="table-wrap" data-table-wrap data-url="/admin/system/roles">
        <?= View::partial('admin/roles_content', get_defined_vars()) ?>
    </div>
</section>
<script>
/**
 * 保存权限矩阵
 * 只提交本次勾选状态与页面初值不一致的角色：页面可能是较早打开的，
 * 若把页面上全部角色一并回写，会覆盖这期间其他管理员对其他角色的调整
 */
function saveMatrix() {
    // 接口是「按角色整体替换」：有改动的角色必须提交它当前勾选的全部权限点，
    // 只提交变化的那几个会把该角色其余授权一并清掉
    var payload = {};
    var dirty = {};
    document.querySelectorAll('.perm-box').forEach(function (box) {
        var role = box.dataset.role;
        if (!payload[role]) { payload[role] = []; }
        if (box.checked) { payload[role].push(box.value); }
        if (box.checked !== (box.dataset.initial === '1')) { dirty[role] = true; }
    });
    var roles = Object.keys(payload).filter(function (role) { return dirty[role] === true; });
    if (roles.length === 0) {
        Notify.info('尚未检测到权限改动，无需保存');
        return;
    }
    var names = roleNames();
    var orphan = orphanPerms(roles, payload);
    // 清空某角色的全部授权是批量且不可逆的调整，按规范 §4.3 用 L2 确认（需勾选确认项）
    var cleared = roles.filter(function (r) { return payload[r].length === 0; });
    var detail = roles.map(function (r) {
        return (names[r] || ('角色 ' + r)) + '：' + (payload[r].length === 0 ? '清空全部授权' : payload[r].length + ' 项授权');
    }).join('；');
    var message = '本次将调整 ' + roles.length + ' 个角色（' + detail + '）。未改动的角色保持原样。';
    if (orphan.length > 0) {
        message += '\n注意：' + orphan.join('；') + '。这类权限只控制页面内的操作按钮，漏授页面权限时页面上不会出现任何入口。';
    }

    Confirm.show({
        title: '保存权限矩阵',
        level: cleared.length > 0 ? 'L2' : 'L1',
        message: message,
        checkboxText: '我已了解：操作后这些角色原有的授权将被本次结果整体替换',
        confirmText: '保存',
        danger: cleared.length > 0,
        onConfirm: async function () {
            var failed = [];
            for (var i = 0; i < roles.length; i++) {
                var res = await App.post('/admin/system/roles/matrix', { role_id: roles[i], perms: payload[roles[i]] });
                if (!res.success) { failed.push(names[roles[i]] || ('角色 ' + roles[i]) + '（' + (res.message || '保存失败') + '）'); }
            }
            // 无论成败都刷新矩阵与侧边栏：部分角色已落库时，界面必须与数据库一致
            admRefresh();
            refreshSideNav();
            if (failed.length > 0) {
                Notify.error('以下角色未保存成功：' + failed.join('；'));
                return;
            }
            Notify.success('权限已更新，相关员工下次操作即按新权限生效');
        }
    });
}

/**
 * 找出「授了按钮级权限却没授所属页面权限」的组合
 * 这类组合下该角色登录后看不到任何对应入口，需要在下发前明确告知
 * @param {string[]} roles 本次提交的角色 ID
 * @param {Object} payload 角色 ID → 该角色勾选的权限点 ID 集合
 * @returns {string[]} 人类可读的提示文案
 */
function orphanPerms(roles, payload) {
    var map = buttonPermMap();
    var tips = [];
    roles.forEach(function (role) {
        var granted = payload[role] || [];
        granted.forEach(function (permId) {
            var meta = map[permId];
            if (!meta) { return; }
            if (granted.indexOf(meta.pageId) === -1) {
                tips.push('「' + meta.name + '」需要同时授予「' + meta.pageName + '」');
            }
        });
    });
    return tips;
}

/** 按钮级权限点 → 所属页面权限点（矩阵渲染时写入，避免 JS 里硬编码） */
function buttonPermMap() {
    var table = document.querySelector('[data-button-perms]');
    if (!table) { return {}; }
    try {
        return JSON.parse(table.dataset.buttonPerms) || {};
    } catch (err) {
        return {};
    }
}

/** 角色 ID → 角色名（表头渲染时写入，避免 JS 里硬编码） */
function roleNames() {
    var table = document.querySelector('[data-role-names]');
    if (!table) { return {}; }
    try {
        return JSON.parse(table.dataset.roleNames) || {};
    } catch (err) {
        return {};
    }
}

/** 保存后立即刷新侧边栏（权限每请求实时读取，无需重新登录），并保留当前菜单高亮 */
async function refreshSideNav() {
    var nav = document.getElementById('side-nav');
    if (!nav) { return; }
    var current = nav.querySelector('.nav-drawer a.is-active, .nav-drawer .active > a[data-key]');
    var url = '/admin/nav' + (current && current.dataset.key ? '?active=' + encodeURIComponent(current.dataset.key) : '');
    try {
        var res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
        // 会话失效时接口会跳登录页（redirected），此时保持侧边栏原样
        var html = res.ok && !res.redirected && (res.headers.get('content-type') || '').indexOf('text/html') >= 0 ? await res.text() : '';
        if (html.indexOf('nav-drawer') === -1) { return; }
        nav.innerHTML = html;
        // 重新初始化：新片段由服务端直出当前分组的展开态，这里只重建分组待办汇总
        if (typeof sideNavInit === 'function') { sideNavInit(nav); }
        if (typeof refreshTodoBadges === 'function') { refreshTodoBadges(); }
    } catch (err) {
        // 侧边栏刷新失败不影响保存结果，下次跳页即恢复
    }
}
</script>
