<?php declare(strict_types=1);
/**
 * @file app/Views/member/packages.php
 * @description 会员中心：我的包裹（统计 + 检索 + 局部表）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-15
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var string $keyword
 * @var int   $status
 * @var array $counts
 * @var array $summary
 */
$inStock = 0;
foreach ($counts as $code => $cnt) {
    if ((int) $code >= 2 && (int) $code <= 9) {
        $inStock += (int) $cnt;
    }
}
$initialUrl = '/user/packages?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $page],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">📦</div>
        <div><div class="astat__value"><?= array_sum(array_map('intval', $counts)) ?></div><div class="astat__label">全部包裹</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">🏬</div>
        <div><div class="astat__value"><?= (int) $inStock ?></div><div class="astat__label">在库</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">🔔</div>
        <div><div class="astat__value"><?= (int) ($counts[3] ?? 0) ?></div><div class="astat__label">待认领</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">💸</div>
        <div><div class="astat__value">¥<?= number_format((float) $summary['fee'], 2) ?></div><div class="astat__label">累计仓储费</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="p-kw" placeholder="包裹编号 / 快递单号 / 商品名称" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="p-status">
            <option value="-1">全部状态</option>
            <?php foreach (Status::PACKAGE as $code => $label): ?>
                <option value="<?= $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('/user/packages', {kw: val('p-kw'), status: val('p-status')})">检索</button>
        <a class="btn btn--ghost" style="margin-left:auto" href="/user/forecast">+ 新增预报</a>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('member/packages_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-disposal">
    <div class="adm-dialog__head">申请退运 / 销毁 · <span id="dp-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="dp-id">
        <div class="form-field"><label class="form-label">申请类型</label>
            <select class="form-input" id="dp-type">
                <option value="6">退运（寄回发件人，运费自理）</option>
                <option value="7">销毁（仓库代为废弃，不可恢复）</option>
            </select></div>
        <div class="form-field"><label class="form-label">申请原因</label>
            <textarea class="form-input" id="dp-reason" rows="3" maxlength="200" placeholder="请说明原因，便于仓库核对处理"></textarea></div>
        <p class="form-hint">提交后由仓库审核，结果通过站内信通知您。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitDisposal()">提交申请</button>
    </div>
</dialog>

<script>
function openDisposal(id, no) {
    document.getElementById('dp-id').value = id;
    document.getElementById('dp-no').textContent = no;
    document.getElementById('dp-reason').value = '';
    document.getElementById('dlg-disposal').showModal();
}
function cancelDisposal(id, label) {
    admConfirm('确定取消「' + label + '」申请吗？取消后仓库将不再处理该申请。', async function () {
        var res = await App.post('/user/packages/disposal/cancel', { id: id });
        App.handle(res, '申请已取消');
        if (res.success) { admRefresh(); }
    }, { title: '取消' + label + '申请', confirmText: '确认取消', danger: false });
}
function submitDisposal() {
    var reason = val('dp-reason').trim();
    if (reason.length < 4) { Notify.warning('请填写申请原因（至少 4 个字）'); return; }
    var label = val('dp-type') === '6' ? '退运' : '销毁';
    admConfirm('确定提交「' + label + '」申请吗？提交后由仓库处理，'
        + (val('dp-type') === '7' ? '销毁后包裹将被废弃且不可恢复。' : '退运后包裹将退回寄件方。'),
        async function () {
        var res = await App.post('/user/packages/disposal', { id: val('dp-id'), type: val('dp-type'), reason: reason });
        App.handle(res, '申请已提交');
        if (res.success) { document.getElementById('dlg-disposal').close(); admRefresh(); }
    }, { title: label + '申请', confirmText: '确认提交', danger: true, level: 'L2' });
}
document.getElementById('p-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { admSearch('/user/packages', { kw: this.value, status: val('p-status') }); }
});
</script>
