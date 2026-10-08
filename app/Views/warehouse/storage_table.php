<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/storage_table.php
 * @description 在库包裹列表局部渲染（含上架、登记异常入口）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var int    $status
 * @var array  $perms
 */
$qs = static fn(int $p): string => '/warehouse/storage?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $p],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>包裹编号</th><th>归属用户</th><th>商品</th><th>重量/体积重</th><th>库位</th>
        <th>状态</th><th>预估仓储费</th><th class="nowrap">入库时间</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="9"><div class="empty-state">没有匹配的在库包裹。可调整关键词或状态筛选。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php
        $s = (int) $r['status'];
        ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono">
                <?= View::e($r['package_no']) ?>
                <br><small><?= View::e($r['tracking_no'] ?: '—') ?></small>
            </td>
            <td>
                <?php if ((int) $r['user_id'] === 0): ?>
                    <?= View::badge('无主件', 'orange') ?>
                <?php else: ?>
                    <?= View::e((string) $r['user_no']) ?>
                    <br><small><?= View::e(UserModel::displayName($r)) ?></small>
                <?php endif; ?>
            </td>
            <td><?= View::e($r['item_name']) ?> ×<?= (int) $r['item_qty'] ?>
                <br><small><?= View::e(Status::PACKAGE_CATEGORY[(int) $r['category']] ?? '') ?></small></td>
            <td class="nowrap">
                <?= $r['weight'] !== null ? number_format((float) $r['weight'], 3) . ' kg' : '—' ?>
                <br><small class="mono"><?= $r['volume_weight'] !== null ? number_format((float) $r['volume_weight'], 3) . ' kg' : '—' ?></small>
            </td>
            <td class="mono"><?= View::e((string) ($r['location_code'] ?? '') ?: '未上架') ?></td>
            <td>
                <?= View::badge(Status::package($s), Status::packageTone($s)) ?>
                <?php if ((int) $r['is_abnormal'] === 1): ?><br><?= View::badge('异常待处理', 'red') ?><?php endif; ?>
            </td>
            <td class="nowrap">
                <?php $fee = (float) ($r['storage_fee_est'] ?? 0); ?>
                <?= $fee > 0 ? '<b class="v-money">¥' . number_format($fee, 2) . '</b>' : '<small>免仓储期</small>' ?>
            </td>
            <td class="nowrap"><?= View::e(substr((string) ($r['inbound_at'] ?? ''), 0, 16) ?: '—') ?></td>
            <td class="nowrap">
                <a class="btn btn--ghost btn--sm" href="/warehouse/storage/package/<?= (int) $r['id'] ?>">详情</a>
                <?php if (!empty($perms['wh.storage'])): ?>
                    <button class="btn btn--ghost btn--sm"
                            onclick="shelve(<?= (int) $r['id'] ?>, '<?= View::e($r['package_no']) ?>', <?= (int) ($r['location_id'] ?? 0) ?>)"><?= (int) ($r['location_id'] ?? 0) > 0 ? '移库' : '上架' ?></button>
                    <?php if ((int) ($r['location_id'] ?? 0) > 0 && in_array($s, [2, 3, 4], true)): ?>
                        <button class="btn btn--ghost btn--sm"
                                onclick="unshelve(<?= (int) $r['id'] ?>, '<?= View::e($r['package_no']) ?>')">下架</button>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($perms['wh.abnormal']) && (int) $r['is_abnormal'] === 0 && $s !== 15): ?>
                    <button class="btn btn--ghost btn--sm"
                            onclick="openAbnormal(<?= (int) $r['id'] ?>, '<?= View::e($r['package_no']) ?>')">登记异常</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a href="javascript:admTable('<?= View::e($qs($page - 1)) ?>')">‹ 上一页</a><?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($qs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="javascript:admTable('<?= View::e($qs($page + 1)) ?>')">下一页 ›</a><?php endif; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>

<dialog class="adm-dialog adm-dialog--sm" id="dlg-abnormal">
    <div class="adm-dialog__head">登记异常 · <span id="ab-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="ab-package-id">
        <div class="wh-field">
            <label class="form-label">异常类型</label>
            <select class="form-input" id="ab-type">
                <?php foreach (Status::ABNORMAL_TYPE as $code => $label): ?>
                    <option value="<?= (int) $code ?>"><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="wh-field">
            <label class="form-label">异常说明</label>
            <textarea class="form-input" id="ab-desc" rows="3" maxlength="500" placeholder="如：外箱左侧凹陷，内含玻璃制品需开箱确认"></textarea>
        </div>
        <p class="form-hint">登记后包裹标记为异常件并进入「异常处理」待办。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="submitAbnormal()">提交登记</button>
    </div>
</dialog>

<script>
function openAbnormal(packageId, packageNo) {
    document.getElementById('ab-package-id').value = packageId;
    document.getElementById('ab-no').textContent = packageNo;
    document.getElementById('ab-desc').value = '';
    document.getElementById('dlg-abnormal').showModal();
}
function submitAbnormal() {
    var desc = val('ab-desc').trim();
    if (desc.length < 4) { Notify.warning('请填写异常说明（至少 4 个字）'); return; }
    admConfirm('确认登记该异常吗？包裹会被标记为异常件并进入异常处理待办。', async function () {
        var res = await App.post('/warehouse/abnormal/save', {
            package_id: val('ab-package-id'), abnormal_type: val('ab-type'), description: desc
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-abnormal').close(); admRefresh(); }
    }, { title: '登记异常', confirmText: '确认登记', danger: true });
}
</script>
