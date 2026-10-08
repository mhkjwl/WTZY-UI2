<?php declare(strict_types=1);
/**
 * @file app/Views/admin/rates.php
 * @description 转运资费维护页：按仓库的线路资费 + 增值服务（前台资费页的内容来源）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $routeFields   线路资费字段定义
 * @var array $serviceFields 增值服务字段定义
 * @var array $routeRows     线路资费行
 * @var array $serviceRows   增值服务行
 * @var array $perms
 */
?>
<p class="form-hint" style="margin-bottom:6px">
    这里填写的内容会显示在前台「转运资费」页。线路资费按「仓库类别」自动分组，同一仓库的线路写多行即可；
    整行留空的行不会保存，<b>缺少仓库类别或线路名称的行也会被忽略</b>。
</p>

<?= View::partial('admin/partials/rate_table', [
    'key'    => 'rates_routes',
    'badge'  => 'indigo',
    'title'  => '线路资费（按仓库）',
    'hint'   => '首重/续重请填数字，前台会按「首重 0.5kg / 85 元，续重 0.5kg / 35 元」的形式展示；平均时效填文案，如「12-15 天」。',
    'fields' => $routeFields,
    'rows'   => $routeRows,
]) ?>

<?= View::partial('admin/partials/rate_table', [
    'key'    => 'rates_services',
    'badge'  => 'emerald',
    'title'  => '增值服务',
    'hint'   => '前台按「服务名称 / 价格 / 服务说明」三列展示；价格可直接写文案，如「3 元/张」「首件免费」。',
    'fields' => $serviceFields,
    'rows'   => $serviceRows,
]) ?>

<?php if (!empty($perms['channel.manage'])): ?>
    <div class="adm-toolbar">
        <button class="btn btn--primary" onclick="saveRates()">保存转运资费</button>
    </div>
<?php endif; ?>

<script>
var ROUTE_FIELD_KEYS = <?= json_encode(array_keys($routeFields), JSON_UNESCAPED_UNICODE) ?>;
var SERVICE_FIELD_KEYS = <?= json_encode(array_keys($serviceFields), JSON_UNESCAPED_UNICODE) ?>;

/** 新增一行：克隆表格首行并清空输入，避免另写一套模板标记 */
function addRateRow(key) {
    var tbody = document.querySelector('#' + key + '-table tbody');
    if (!tbody || tbody.rows.length === 0) { return; }
    var clone = tbody.rows[0].cloneNode(true);
    clone.querySelectorAll('input').forEach(function (el) { el.value = ''; });
    tbody.appendChild(clone);
    var first = clone.querySelector('input');
    if (first) { first.focus(); }
}

/** 删除一行；至少保留一行，便于继续录入 */
function delRateRow(btn) {
    var tr = btn.closest('tr');
    var tbody = tr.parentNode;
    if (tbody.rows.length <= 1) {
        tr.querySelectorAll('input').forEach(function (el) { el.value = ''; });
        Notify.info('已清空该行（表格需保留至少一行）');
        return;
    }
    tr.remove();
}

/** 按下标对齐采集整表：与后端 saveRows 的并列数组约定一致 */
function collectRows(key, fields) {
    var data = {};
    fields.forEach(function (f) { data[key + '_' + f] = []; });
    document.querySelectorAll('#' + key + '-table tbody tr').forEach(function (tr) {
        fields.forEach(function (f) {
            var el = tr.querySelector('[name="' + key + '_' + f + '[]"]');
            data[key + '_' + f].push(el ? el.value.trim() : '');
        });
    });
    return data;
}

function saveRates() {
    var payload = Object.assign(
        collectRows('rates_routes', ROUTE_FIELD_KEYS),
        collectRows('rates_services', SERVICE_FIELD_KEYS)
    );
    admConfirm('确定保存转运资费吗？保存后前台「转运资费」页立即生效。', async function () {
        var res = await App.post('/admin/rates/save', payload);
        App.handle(res, '转运资费已保存');
    }, { title: '保存转运资费', confirmText: '确认保存' });
}
</script>
