<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/tasks.php
 * @description 任务分配：主管指派作业给操作员并跟踪完成情况
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var int    $status
 * @var int    $assignee
 * @var int    $type
 * @var string $keyword
 * @var array  $statuses
 * @var array  $types
 * @var array  $operators
 * @var array  $byOperator
 * @var array  $counts
 * @var array  $perms
 */
$initialUrl = '/warehouse/tasks?' . http_build_query(array_filter([
    'status' => $status, 'assignee' => $assignee, 'type' => $type, 'kw' => $keyword,
], static fn($v): bool => $v !== '' && (int) $v !== -1 && (int) $v !== 0));
?>
<section class="astat-grid">
    <div class="astat astat--orange"><div class="astat__icon">📋</div>
        <div><div class="astat__value"><?= (int) ($counts[0] ?? 0) ?></div><div class="astat__label">待处理</div></div></div>
    <div class="astat astat--blue"><div class="astat__icon">🔧</div>
        <div><div class="astat__value"><?= (int) ($counts[1] ?? 0) ?></div><div class="astat__label">处理中</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) ($counts[2] ?? 0) ?></div><div class="astat__label">已完成</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">👷</div>
        <div><div class="astat__value"><?= count($operators) ?></div><div class="astat__label">本仓作业人员</div></div></div>
</section>

<?php if ($byOperator !== []): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--cyan">执行人负荷</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>执行人</th><th>账号</th><th>未完成</th><th>已完成</th></tr></thead>
            <tbody>
            <?php foreach ($byOperator as $o): ?>
                <tr>
                    <td><?= View::e((string) $o['real_name']) ?></td>
                    <td class="mono"><?= View::e((string) $o['username']) ?></td>
                    <td><?= (int) $o['pending'] > 0 ? View::badge((string) (int) $o['pending'] . ' 项', 'orange') : View::badge('空闲', 'green') ?></td>
                    <td><?= View::badge((string) (int) $o['finished'] . ' 项', 'gray') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--violet">作业任务</span><span class="rule"></span>
        <?php if (!empty($perms['wh.task.assign'])): ?>
            <button class="btn btn--primary" onclick="openTask()">+ 指派任务</button>
        <?php endif; ?>
    </h3>
    <?php if ($operators === []): ?>
        <div class="wh-flash wh-flash--warn">
            ⚠️ 本仓库还没有仓库端员工，无法指派任务。请在「管理后台 → 员工管理」中新建员工，
            选择仓库端角色并指定所属仓库（本仓）。
        </div>
    <?php endif; ?>
    <div class="adm-toolbar">
        <input class="form-input" id="tk-kw" placeholder="检索：任务号 / 目标单号" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="tk-status">
            <option value="-1">全部状态</option>
            <?php foreach ($statuses as $code => $label): ?>
                <option value="<?= (int) $code ?>" <?= $status === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-input" id="tk-type">
            <option value="0">全部类型</option>
            <?php foreach ($types as $code => $label): ?>
                <option value="<?= (int) $code ?>" <?= $type === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-input" id="tk-assignee">
            <option value="0">全部执行人</option>
            <?php foreach ($operators as $o): ?>
                <option value="<?= (int) $o['id'] ?>" <?= $assignee === (int) $o['id'] ? 'selected' : '' ?>><?= View::e((string) $o['real_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="taskSearch()">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('warehouse/tasks_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog" id="dlg-task">
    <div class="adm-dialog__head">指派作业任务</div>
    <div class="adm-dialog__body">
        <div class="form-row">
            <div class="wh-field">
                <label class="form-label">作业类型</label>
                <select class="form-input" id="tk-task-type" onchange="syncTargetHint()">
                    <?php foreach ($types as $code => $label): ?>
                        <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-hint" id="tk-target-hint"></p>
            </div>
            <div class="wh-field">
                <label class="form-label">目标单号</label>
                <input class="form-input mono" id="tk-target-no" maxlength="32" placeholder="包裹编号或运单号">
            </div>
            <div class="wh-field">
                <label class="form-label">执行人</label>
                <select class="form-input" id="tk-assign">
                    <?php foreach ($operators as $o): ?>
                        <option value="<?= (int) $o['id'] ?>"><?= View::e((string) $o['real_name']) ?>（<?= View::e((string) ($o['role_name'] ?? '')) ?>）</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="wh-field" style="max-width:200px">
                <label class="form-label">优先级</label>
                <select class="form-input" id="tk-priority">
                    <option value="0">普通</option>
                    <option value="1">加急（列表靠前）</option>
                </select>
            </div>
            <div class="wh-field">
                <label class="form-label">指派说明</label>
                <input class="form-input" id="tk-remark" maxlength="200" placeholder="如：客户催件，优先处理">
            </div>
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitTask()">确认指派</button>
    </div>
</dialog>

<script>
/** 目标单号提示随作业类型变化（包裹类填包裹编号，运单类填运单号） */
var TARGET_HINT = {
    '1': '请填写包裹编号（P 开头）——指派收货入库',
    '2': '请填写包裹编号（P 开头）——指派上架理货',
    '3': '请填写运单号（W 开头）——指派打包称重',
    '4': '请填写运单号（W 开头）——指派出库交接',
    '5': '请填写包裹编号（P 开头）——指派异常处理'
};
function syncTargetHint() {
    var hint = document.getElementById('tk-target-hint');
    if (hint) { hint.textContent = TARGET_HINT[val('tk-task-type')] || ''; }
}
function taskSearch() {
    var params = new URLSearchParams();
    var kw = val('tk-kw').trim();
    if (kw) { params.set('kw', kw); }
    ['status:tk-status', 'type:tk-type', 'assignee:tk-assignee'].forEach(function (pair) {
        var parts = pair.split(':');
        var v = val(parts[1]);
        if (v !== '' && v !== '-1' && v !== '0') { params.set(parts[0], v); }
    });
    var qs = params.toString();
    admTable('/warehouse/tasks' + (qs ? '?' + qs : ''));
}
document.getElementById('tk-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { taskSearch(); }
});

function openTask() {
    document.getElementById('tk-target-no').value = '';
    document.getElementById('tk-remark').value = '';
    syncTargetHint();
    document.getElementById('dlg-task').showModal();
    document.getElementById('tk-target-no').focus();
}
function submitTask() {
    var targetNo = val('tk-target-no').trim();
    var assignId = val('tk-assign');
    if (!targetNo) { Notify.warning('请填写目标单号'); return; }
    if (!assignId) { Notify.warning('请选择执行人'); return; }
    admConfirm('确认把该作业指派给所选执行人吗？作业完成后任务会自动标记完成。', async function () {
        var res = await App.post('/warehouse/tasks/save', {
            task_type: val('tk-task-type'), target_no: targetNo, assign_admin_id: assignId,
            priority: val('tk-priority'), remark: val('tk-remark').trim()
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-task').close(); admRefresh(); }
    }, { title: '指派任务', confirmText: '确认指派' });
}
</script>
