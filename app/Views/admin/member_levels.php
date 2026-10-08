<?php declare(strict_types=1);
/**
 * @file app/Views/admin/member_levels.php
 * @description 后台：会员等级权益配置
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $levels
 * @var array $dist
 * @var array $perms
 * @var string $csrf
 */
$total = array_sum(array_map('intval', $dist));
?>
<section class="astat-grid">
    <div class="astat astat--violet"><div class="astat__icon">👑</div>
        <div><div class="astat__value"><?= count($levels) ?></div><div class="astat__label">等级数量</div></div></div>
    <?php foreach (array_slice($levels, 0, 3) as $lv): ?>
        <div class="astat astat--blue"><div class="astat__icon">Lv<?= (int) $lv['level'] ?></div>
            <div><div class="astat__value"><?= (int) ($dist[(int) $lv['level']] ?? 0) ?></div>
                <div class="astat__label"><?= View::e($lv['name']) ?></div></div></div>
    <?php endforeach; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--purple">等级权益配置</span><span class="rule"></span>
        <span class="form-hint">共 <?= (int) $total ?> 位用户 · 运费折扣按订单原价计算</span>
    </h3>
    <p class="form-hint" style="margin-bottom:14px">等级门槛须随等级递增；保存后按累计积分重算等级，只升不降。</p>
    <div class="table-scroll">
        <table class="table">
            <thead>
            <tr><th>等级</th><th>等级名称</th><th>所需积分</th><th>运费折扣</th><th>额外免仓储</th><th>权益说明</th><th>当前人数</th><th class="nowrap">操作</th></tr>
            </thead>
            <tbody>
            <?php foreach ($levels as $lv): ?>
                <tr data-level="<?= (int) $lv['level'] ?>">
                    <td><b>Lv<?= (int) $lv['level'] ?></b></td>
                    <td data-field="name"><?= View::e($lv['name']) ?></td>
                    <td class="nowrap" data-field="min_points"><?= number_format((int) $lv['min_points']) ?></td>
                    <td class="nowrap" data-field="discount"><?= number_format((float) $lv['discount'] * 10, 1) ?> 折</td>
                    <td class="nowrap" data-field="days"><?= (int) $lv['extra_storage_days'] ?> 天</td>
                    <td data-field="remark"><?= View::e((string) ($lv['remark'] ?? '—')) ?></td>
                    <td class="nowrap" data-field="count"><?= (int) ($dist[(int) $lv['level']] ?? 0) ?></td>
                    <td class="nowrap">
                        <?php if (!empty($perms['member.manage'])): ?>
                            <button class="btn btn--ghost btn--sm"
                                    onclick='openLevel(<?= json_encode($lv, JSON_UNESCAPED_UNICODE) ?>)'>编辑</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<dialog class="adm-dialog" id="dlg-level">
    <div class="adm-dialog__head">编辑等级权益 · <span id="lv-title"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="lv-level">
        <div class="form-row">
            <div class="form-field"><label class="form-label">等级名称</label><input class="form-input" id="lv-name" maxlength="30"></div>
            <div class="form-field"><label class="form-label">所需积分</label><input class="form-input" type="number" min="0" id="lv-points"></div>
            <div class="form-field"><label class="form-label">运费折扣（0.01~1.00）</label><input class="form-input" type="number" step="0.01" min="0.01" max="1" id="lv-discount"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">额外免仓储天数</label><input class="form-input" type="number" min="0" max="365" id="lv-days"></div>
            <div class="form-field"><label class="form-label">权益说明</label><input class="form-input" id="lv-remark" maxlength="200"></div>
        </div>
        <p class="form-hint">折扣填写 1.00 表示不打折，0.95 表示 95 折。</p>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveLevel()">保存</button>
    </div>
</dialog>

<script>
function openLevel(row) {
    document.getElementById('lv-level').value = row.level;
    document.getElementById('lv-title').textContent = 'Lv' + row.level + ' ' + row.name;
    document.getElementById('lv-name').value = row.name;
    document.getElementById('lv-points').value = row.min_points;
    document.getElementById('lv-discount').value = row.discount;
    document.getElementById('lv-days').value = row.extra_storage_days;
    document.getElementById('lv-remark').value = row.remark || '';
    document.getElementById('dlg-level').showModal();
}

/**
 * 保存后原地更新该等级所在行，不整页刷新（规范 §2.5.4「只更新该条数据展示」）
 * 人数分布由服务端按累计积分重算，属异步派生数据，故不在此处臆测更新
 */
function updateLevelRow(level, d) {
    var tr = document.querySelector('tr[data-level="' + level + '"]');
    if (!tr) { return; }
    var set = function (field, text) {
        var td = tr.querySelector('[data-field="' + field + '"]');
        if (td) { td.textContent = text; }
    };
    set('name', d.name);
    set('min_points', d.min_points);
    set('discount', (Math.round(parseFloat(d.discount) * 100) / 10).toFixed(1) + ' 折');
    set('days', d.days + ' 天');
    set('remark', d.remark || '—');
}

function saveLevel() {
    var name = val('lv-name').trim();
    if (!name) { Notify.warning('请填写等级名称'); return; }
    var level = val('lv-level'), points = val('lv-points'), discount = val('lv-discount');
    var days = val('lv-days'), remark = val('lv-remark').trim();
    admConfirm('确定保存 Lv' + level + ' 的等级权益吗？保存后会按新门槛重算全部用户等级（只升不降）。',
        async function () {
            var res = await App.post('/admin/member/levels/save', {
                level: level, name: name, min_points: points,
                discount: discount, extra_storage_days: days, remark: remark
            });
            App.handle(res, '等级权益已保存');
            if (res.success) {
                document.getElementById('dlg-level').close();
                updateLevelRow(level, {
                    name: name, min_points: Number(points).toLocaleString(),
                    discount: discount, days: days, remark: remark
                });
            }
        }, { title: '保存等级权益', level: 'L2', confirmText: '确认保存' });
}
</script>
