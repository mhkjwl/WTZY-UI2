<?php declare(strict_types=1);
/**
 * @file app/Views/admin/member_coupons.php
 * @description 后台：优惠券管理与发放
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array  $rows
 * @var array  $types
 * @var array  $stats
 * @var array  $perms
 * @var string $csrf
 */
?>
<section class="astat-grid">
    <div class="astat astat--violet"><div class="astat__icon">🎫</div>
        <div><div class="astat__value"><?= (int) $stats['total'] ?></div><div class="astat__label">券种总数</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) $stats['active'] ?></div><div class="astat__label">启用中</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">📤</div>
        <div><div class="astat__value"><?= (int) $stats['granted'] ?></div><div class="astat__label">已发放</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">🔖</div>
        <div><div class="astat__value"><?= (int) $stats['used'] ?></div><div class="astat__label">已核销</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--violet">优惠券列表</span><span class="rule"></span>
        <?php if (!empty($perms['member.manage'])): ?>
            <button class="btn btn--ghost" onclick="openGrant()">发放优惠券</button>
            <button class="btn btn--primary" onclick="openCoupon(null)">+ 新建优惠券</button>
        <?php endif; ?>
    </h3>
    <div id="cp-list">
        <?= View::partial("admin/member_coupons_table", get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-coupon">
    <div class="adm-dialog__head" id="cp-title">新建优惠券</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="cp-id" value="0">
        <div class="form-row">
            <div class="form-field"><label class="form-label">券码</label><input class="form-input mono" id="cp-code" maxlength="32" placeholder="如 NEW100"></div>
            <div class="form-field"><label class="form-label">券名称</label><input class="form-input" id="cp-name" maxlength="60" placeholder="如 新人满100减10"></div>
            <div class="form-field"><label class="form-label">券类型</label>
                <select class="form-input" id="cp-type" onchange="syncCouponHint()">
                    <?php foreach ($types as $code => $label): ?>
                        <option value="<?= $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">面额</label><input class="form-input" type="number" step="0.01" id="cp-value"></div>
            <div class="form-field"><label class="form-label">使用门槛</label><input class="form-input" type="number" step="0.01" min="0" id="cp-min" value="0"></div>
            <div class="form-field"><label class="form-label">发行总量（0=不限）</label><input class="form-input" type="number" min="0" id="cp-qty" value="0"></div>
        </div>
        <p class="form-hint" id="cp-value-hint">满减/运费券填金额（如 10 表示减 10 元）；折扣券填折扣率（如 0.90 表示 9 折）。</p>
        <div class="form-row">
            <div class="form-field"><label class="form-label">每人限领</label><input class="form-input" type="number" min="1" id="cp-per" value="1"></div>
            <div class="form-field"><label class="form-label">状态</label>
                <select class="form-input" id="cp-status"><option value="1">启用</option><option value="0">停用</option></select></div>
            <div class="form-field"><label class="form-label">开始日期（选填）</label><input class="form-input" type="date" id="cp-start"></div>
            <div class="form-field"><label class="form-label">结束日期（选填）</label><input class="form-input" type="date" id="cp-end"></div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveCoupon()">保存</button>
    </div>
</dialog>

<dialog class="adm-dialog" id="dlg-grant">
    <div class="adm-dialog__head">发放优惠券</div>
    <div class="adm-dialog__body">
        <div class="form-row">
            <div class="form-field"><label class="form-label">选择优惠券</label>
                <select class="form-input" id="gr-coupon">
                    <?php foreach ($rows as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= View::e($c['name']) ?>（<?= View::e($c['code']) ?>）</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">发放范围</label>
                <select class="form-input" id="gr-scope" onchange="syncGrantScope()">
                    <option value="user">指定用户（按用户名或用户编号）</option>
                    <option value="level">按会员等级批量发放</option>
                    <option value="all">全部正常状态用户</option>
                </select></div>
            <div class="form-field"><label class="form-label">每人发放张数</label>
                <input class="form-input" type="number" min="1" max="100" id="gr-qty" value="1"></div>
        </div>
        <div class="form-row">
            <div class="form-field" id="gr-user-box"><label class="form-label">用户名 / 用户编号</label>
                <input class="form-input" id="gr-user" placeholder="如 wtzy_user_a 或 U100001"></div>
            <div class="form-field" id="gr-level-box" hidden><label class="form-label">会员等级</label>
                <select class="form-input" id="gr-level">
                    <?php foreach ([1 => 'Lv1 普通用户', 2 => 'Lv2 白银会员', 3 => 'Lv3 黄金会员', 4 => 'Lv4 钻石会员'] as $lv => $label): ?>
                        <option value="<?= $lv ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <p class="form-hint">发放后立即进入用户券包。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="grantCoupon()">确认发放</button>
    </div>
</dialog>

<script>
async function refreshCouponList() {
    var el = document.getElementById('cp-list');
    if (!el) { return; }
    try {
        var res = await fetch('/admin/member/coupons/list', { headers: { 'X-Requested-With': 'fetch' } });
        // 校验响应有效性，避免会话过期/权限变更时把登录页或 403 页塞进列表区
        if (!res.ok || res.redirected) {
            Notify.error(res.status === 403 ? '权限已变更，无法加载该列表，请刷新页面' : '登录状态已失效，请重新登录');
            return;
        }
        el.innerHTML = await res.text();
    } catch (err) {
        Notify.error('网络异常，优惠券列表未能刷新');
    }
}

function syncCouponHint() {
    var isDiscount = val('cp-type') === '2';
    document.getElementById('cp-value-hint').textContent = isDiscount
        ? '折扣券填折扣率：如 0.90 表示 9 折（必须小于 1）'
        : '满减/运费券填金额：如 10 表示立减 10 元';
}

function openCoupon(row) {
    var isNew = !row;
    document.getElementById('cp-title').textContent = isNew ? '新建优惠券' : '编辑优惠券 · ' + row.code;
    document.getElementById('cp-id').value = isNew ? 0 : row.id;
    document.getElementById('cp-code').value = isNew ? '' : row.code;
    document.getElementById('cp-name').value = isNew ? '' : row.name;
    document.getElementById('cp-type').value = isNew ? '1' : row.type;
    document.getElementById('cp-value').value = isNew ? '' : row.value;
    document.getElementById('cp-min').value = isNew ? '0' : row.min_amount;
    document.getElementById('cp-qty').value = isNew ? '0' : row.total_qty;
    document.getElementById('cp-per').value = isNew ? '1' : row.per_user_limit;
    document.getElementById('cp-status').value = isNew ? '1' : row.status;
    document.getElementById('cp-start').value = isNew || !row.start_at ? '' : row.start_at.slice(0, 10);
    document.getElementById('cp-end').value = isNew || !row.end_at ? '' : row.end_at.slice(0, 10);
    syncCouponHint();
    document.getElementById('dlg-coupon').showModal();
}

function saveCoupon() {
    var code = val('cp-code').trim().toUpperCase();
    var name = val('cp-name').trim();
    if (code.length < 4) { Notify.warning('券码至少 4 位'); return; }
    if (!name) { Notify.warning('请填写券名称'); return; }
    if (!(parseFloat(val('cp-value')) > 0)) { Notify.warning('请填写券面额'); return; }
    admConfirm('确定保存优惠券「' + name + '」吗？', async function () {
        var res = await App.post('/admin/member/coupons/save', {
            id: val('cp-id'), code: code, name: name, type: val('cp-type'),
            value: val('cp-value'), min_amount: val('cp-min'), total_qty: val('cp-qty'),
            per_user_limit: val('cp-per'), start_at: val('cp-start'), end_at: val('cp-end'),
            status: val('cp-status')
        });
        App.handle(res, '优惠券已保存');
        if (res.success) { document.getElementById('dlg-coupon').close(); refreshCouponList(); }
    }, { title: '保存优惠券', confirmText: '确认保存' });
}

function toggleCoupon(id) {
    admConfirm('确定切换该优惠券的启停状态吗？停用后用户端将无法领取。', async function () {
        var res = await App.post('/admin/member/coupons/toggle', { id: id });
        App.handle(res, '状态已切换');
        if (res.success) { refreshCouponList(); }
    }, { title: '切换优惠券状态', confirmText: '确认切换' });
}

function syncGrantScope() {
    var scope = val('gr-scope');
    document.getElementById('gr-user-box').hidden = scope !== 'user';
    document.getElementById('gr-level-box').hidden = scope !== 'level';
}

function openGrant() {
    syncGrantScope();
    document.getElementById('gr-user').value = '';
    document.getElementById('gr-qty').value = '1';
    if (!document.getElementById('gr-coupon').options.length) {
        Notify.warning('请先创建优惠券');
        return;
    }
    document.getElementById('dlg-grant').showModal();
}

function grantCoupon() {
    var scope = val('gr-scope');
    if (scope === 'user' && !val('gr-user').trim()) { Notify.warning('请填写用户名或用户编号'); return; }
    var scopeText = scope === 'all' ? '全部正常用户' : (scope === 'level' ? 'Lv' + val('gr-level') : '指定用户');
    var bulk = scope !== 'user';
    admConfirm('确定向「' + scopeText + '」每人发放 ' + val('gr-qty') + ' 张优惠券吗？发放后立即生效，'
        + (bulk ? '且无法批量撤回。' : '如需撤回请逐张处理。'), async function () {
        var res = await App.post('/admin/member/coupons/grant', {
            coupon_id: val('gr-coupon'), scope: scope, user_no: val('gr-user').trim(),
            level: val('gr-level'), qty: val('gr-qty')
        });
        App.handle(res, '发放完成');
        if (res.success) { document.getElementById('dlg-grant').close(); refreshCouponList(); }
    }, { title: '发放优惠券', confirmText: '确认发放', level: bulk ? 'L2' : 'L1' });
}
</script>
