<?php declare(strict_types=1);
/**
 * @file app/Views/admin/services.php
 * @description 增值服务设置页：下单页可勾选增值服务的增删改与启停
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array  $rows
 * @var array  $stats
 * @var array  $modes
 * @var array  $rules
 * @var array  $perms
 * @var string $csrf
 */
?>
<section class="astat-grid">
    <div class="astat astat--violet"><div class="astat__icon">✨</div>
        <div><div class="astat__value"><?= (int) $stats['total'] ?></div><div class="astat__label">服务总数</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) $stats['active'] ?></div><div class="astat__label">下单页在售</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⏸️</div>
        <div><div class="astat__value"><?= (int) $stats['disabled'] ?></div><div class="astat__label">已停用</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">🧾</div>
        <div><div class="astat__value"><?= (int) $stats['used'] ?></div><div class="astat__label">已被运单引用</div></div></div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--violet">增值服务列表</span><span class="rule"></span>
        <?php if (!empty($perms['channel.manage'])): ?>
            <button class="btn btn--primary" onclick="openService(null)">+ 新建增值服务</button>
        <?php endif; ?>
    </h3>
    <div id="svc-list">
        <?= View::partial('admin/services_table', get_defined_vars()) ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--blue">仓储与费用规则</span><span class="rule"></span>
        <span class="form-hint">免费仓储天数由「仓库管理」逐仓设置，会员额外天数由「会员等级」配置</span>
    </h3>
    <div class="form-row">
        <div class="form-field">
            <label class="form-label">超期仓储费单价（元/件/天）</label>
            <input class="form-input" type="number" step="0.01" min="0" id="rule-price" value="<?= View::e($rules['storage_fee_per_item_day']) ?>">
            <p class="form-hint">超出「仓库免费期 + 会员额外免仓储天数」后，每件每天按此单价计收，随出库结算并入运费。</p>
        </div>
        <div class="form-field">
            <label class="form-label">宽限天数</label>
            <input class="form-input" type="number" step="1" min="0" max="365" id="rule-grace" value="<?= View::e($rules['storage_grace_days']) ?>">
            <p class="form-hint">免费期结束后的缓冲天数，不计费，避免刚到期的包裹立刻产生费用。</p>
        </div>
    </div>
</section>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--purple">自动签收与积分规则</span><span class="rule"></span>
        <span class="form-hint">积分在运单签收后按缓冲天数到账，退运/销毁/取消时未到账积分作废、已到账扣回</span>
    </h3>
    <div class="form-row">
        <div class="form-field">
            <label class="form-label">自动签收天数（出库后）</label>
            <input class="form-input" type="number" step="1" min="0" max="365" id="rule-autosign" value="<?= View::e($rules['auto_sign_days']) ?>">
            <p class="form-hint">出库满该天数用户仍未手动签收时，系统自动签收并触发积分登记；0 表示关闭自动签收。清关异常、有进行中理赔或异常包裹的运单不自动签收。</p>
        </div>
        <div class="form-field">
            <label class="form-label">积分到账缓冲天数（T+N）</label>
            <input class="form-input" type="number" step="1" min="0" max="365" id="rule-delay" value="<?= View::e($rules['points_award_delay_days']) ?>">
            <p class="form-hint">签收后进入待激活，满 N 天自动到账；期间发生退件/退运/销毁/理赔则直接取消。0 表示签收即到账。</p>
        </div>
    </div>
    <div class="form-row">
        <div class="form-field">
            <label class="form-label">每消费 1 元赠送积分</label>
            <input class="form-input" type="number" step="0.01" min="0" max="100" id="rule-rate" value="<?= View::e($rules['points_per_yuan']) ?>">
            <p class="form-hint">基数取运单实付基础运费（不含附加费、增值服务费、仓储费）。</p>
        </div>
        <div class="form-field">
            <label class="form-label">负积分限制下单</label>
            <select class="form-input" id="rule-block">
                <option value="1" <?= $rules['points_negative_block'] === '1' ? 'selected' : '' ?>>限制（欠分须先还清）</option>
                <option value="0" <?= $rules['points_negative_block'] !== '1' ? 'selected' : '' ?>>不限制</option>
            </select>
            <p class="form-hint">退款扣回造成积分负数后，是否禁止该用户继续提交转运订单。</p>
        </div>
    </div>
    <?php if (!empty($perms['channel.manage'])): ?>
        <div class="adm-toolbar" style="margin-top:12px">
            <button class="btn btn--primary" onclick="saveRules()">保存规则</button>
        </div>
    <?php endif; ?>
</section>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-service">
    <div class="adm-dialog__head" id="svc-title">新建增值服务</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="svc-id" value="0">
        <div class="form-row">
            <div class="form-field"><label class="form-label">服务名称</label>
                <input class="form-input" id="svc-name" maxlength="50" placeholder="如：拍照留档"></div>
            <div class="form-field"><label class="form-label">计价方式</label>
                <select class="form-input" id="svc-mode">
                    <?php foreach ($modes as $code => $label): ?>
                        <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-field"><label class="form-label">单价（元）</label>
                <input class="form-input" type="number" step="0.01" min="0" id="svc-price" value="0"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">排序（小的排前面）</label>
                <input class="form-input" type="number" min="0" id="svc-sort" value="0"></div>
            <div class="form-field"><label class="form-label">状态</label>
                <select class="form-input" id="svc-status">
                    <option value="1">启用（下单页可选）</option>
                    <option value="0">停用（下单页隐藏）</option>
                </select></div>
        </div>
        <div class="form-field"><label class="form-label">服务说明</label>
            <textarea class="form-input" id="svc-desc" rows="2" maxlength="255"
                      placeholder="如：逐件拍摄外箱与内件，出库前留档"></textarea></div>
        <p class="form-hint">单价为 0 表示免费服务，仍会显示在下单页；说明会原样展示给用户，请写清交付内容。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveService()">保存</button>
    </div>
</dialog>

<script>
async function refreshServiceList() {
    var el = document.getElementById('svc-list');
    if (!el) { return; }
    try {
        var res = await fetch('/admin/services/list', { headers: { 'X-Requested-With': 'fetch' } });
        // 校验响应有效性，避免会话过期/权限变更时把登录页或 403 页塞进列表区
        if (!res.ok || res.redirected) {
            Notify.error(res.status === 403 ? '权限已变更，无法加载该列表，请刷新页面' : '登录状态已失效，请重新登录');
            return;
        }
        el.innerHTML = await res.text();
    } catch (err) {
        Notify.error('网络异常，增值服务列表未能刷新');
    }
}

function openService(btn) {
    var row = btn ? JSON.parse(btn.dataset.row) : null;
    document.getElementById('svc-title').textContent = row ? '编辑增值服务 · ' + row.name : '新建增值服务';
    document.getElementById('svc-id').value = row ? row.id : 0;
    document.getElementById('svc-name').value = row ? row.name : '';
    document.getElementById('svc-mode').value = row ? row.price_mode : '1';
    document.getElementById('svc-price').value = row ? row.price : '0';
    document.getElementById('svc-sort').value = row ? row.sort : '0';
    document.getElementById('svc-status').value = row ? row.status : '1';
    document.getElementById('svc-desc').value = row && row.description ? row.description : '';
    document.getElementById('dlg-service').showModal();
}

function saveService() {
    var name = val('svc-name').trim();
    if (!name) { Notify.warning('请填写服务名称'); return; }
    if (!(parseFloat(val('svc-price')) >= 0)) { Notify.warning('请填写不小于 0 的单价'); return; }
    admConfirm('确定保存增值服务「' + name + '」吗？保存后用户下单页立即按新价格展示。', async function () {
        var res = await App.post('/admin/services/save', {
            id: val('svc-id'), name: name, price_mode: val('svc-mode'), price: val('svc-price'),
            sort: val('svc-sort'), status: val('svc-status'), description: val('svc-desc')
        });
        App.handle(res, '增值服务已保存');
        if (res.success) { document.getElementById('dlg-service').close(); refreshServiceList(); }
    }, { title: '保存增值服务', confirmText: '确认保存' });
}

function toggleService(id, status) {
    var action = Number(status) === 1 ? '停用' : '启用';
    admConfirm('确定' + action + '该增值服务吗？' + (Number(status) === 1
        ? '停用后用户下单页不再展示该服务，已下单的运单不受影响。'
        : '启用后用户下单页立即可勾选。'), async function () {
        var res = await App.post('/admin/services/toggle', { id: id });
        App.handle(res, '服务状态已切换');
        if (res.success) { refreshServiceList(); }
    }, { title: action + '增值服务', confirmText: '确认' + action, danger: Number(status) === 1 });
}

/** 保存业务规则（仓储费 / 自动签收 / 积分），规则改动只影响后续新产生的费用与积分 */
function saveRules() {
    admConfirm('确认保存规则吗？新规则对已产生的费用与已登记的积分不追溯，只影响后续。', async function () {
        var res = await App.post('/admin/services/rules', {
            storage_fee_per_item_day: val('rule-price'),
            storage_grace_days: val('rule-grace'),
            auto_sign_days: val('rule-autosign'),
            points_award_delay_days: val('rule-delay'),
            points_per_yuan: val('rule-rate'),
            points_negative_block: val('rule-block')
        });
        App.handle(res, '规则已保存');
    }, { title: '保存业务规则', confirmText: '确认保存' });
}

function deleteService(id, btn) {
    var name = btn ? btn.closest('tr').querySelector('b').textContent : '';
    admConfirm('确定删除增值服务「' + name + '」吗？删除后无法恢复，需要重新填写。已被历史运单引用的服务不能删除，请改用「停用」。',
        async function () {
            var res = await App.post('/admin/services/delete', { id: id });
            App.handle(res, '增值服务已删除');
            if (res.success) { refreshServiceList(); }
        }, { title: '删除增值服务', confirmText: '确认删除', level: 'L2' });
}
</script>
