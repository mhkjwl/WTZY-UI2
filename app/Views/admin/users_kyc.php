<?php declare(strict_types=1);
/**
 * @file app/Views/admin/users_kyc.php
 * @description 实名认证审核页：统计 + 检索 + 局部表 + 审核对话框
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var int    $status
 * @var array  $counts
 * @var string $base
 * @var array  $perms
 * @var string $csrf
 */
$initialUrl = '/admin/users/kyc?' . http_build_query(array_filter(
    ['kw' => $keyword, 'status' => $status, 'page' => $page],
    static fn($v): bool => $v !== '' && $v !== -1
));
?>
<section class="astat-grid">
    <div class="astat astat--amber"><div class="astat__icon">⏳</div>
        <div><div class="astat__value"><?= (int) ($counts[1] ?? 0) ?></div><div class="astat__label">待审核</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">✅</div>
        <div><div class="astat__value"><?= (int) ($counts[2] ?? 0) ?></div><div class="astat__label">已通过</div></div></div>
    <div class="astat astat--red"><div class="astat__icon">❌</div>
        <div><div class="astat__value"><?= (int) ($counts[3] ?? 0) ?></div><div class="astat__label">已驳回</div></div></div>
    <div class="astat astat--gray"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) ($counts[0] ?? 0) ?></div><div class="astat__label">未提交</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="k-kw" placeholder="编号 / 用户名 / 昵称 / 姓名 / 证件号" value="<?= View::e($keyword) ?>">
        <select class="form-input" id="k-status">
            <option value="-1" <?= $status === -1 ? 'selected' : '' ?>>全部已提交</option>
            <option value="1" <?= $status === 1 ? 'selected' : '' ?>>待审核</option>
            <option value="2" <?= $status === 2 ? 'selected' : '' ?>>已通过</option>
            <option value="3" <?= $status === 3 ? 'selected' : '' ?>>已驳回</option>
        </select>
        <button class="btn btn--primary" onclick="admSearch('<?= View::e($base) ?>', {kw: val('k-kw'), status: val('k-status')})">检索</button>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('admin/users_kyc_table', get_defined_vars()) ?>
    </div>
</section>

<dialog class="adm-dialog" id="dlg-kyc">
    <div class="adm-dialog__head">实名审核 · <span id="kyc-no"></span></div>
    <div class="adm-dialog__body">
        <input type="hidden" id="kyc-id">
        <div class="hero-address" style="margin-bottom:14px">
            <div class="hero-address__row"><span>真实姓名</span><b id="kyc-name"></b></div>
            <div class="hero-address__row"><span>证件</span><b><span id="kyc-type"></span> · <span class="mono" id="kyc-card"></span></b></div>
            <div class="hero-address__row"><span>提交时间</span><b id="kyc-at"></b></div>
        </div>
        <div class="form-field">
            <label class="form-label">认证材料</label>
            <div id="kyc-photos" style="display:flex;gap:10px;flex-wrap:wrap"></div>
        </div>
        <div class="form-field">
            <label class="form-label">驳回原因（驳回时必填）</label>
            <input class="form-input" id="kyc-remark" maxlength="200" placeholder="如：证件号码与姓名不匹配">
        </div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--danger" onclick="reviewKyc('reject')">驳回</button>
        <button class="btn btn--primary" onclick="reviewKyc('approve')">通过</button>
    </div>
</dialog>

<script>
function openKyc(row) {
    document.getElementById('kyc-id').value = row.id;
    document.getElementById('kyc-no').textContent = row.user_no;
    document.getElementById('kyc-name').textContent = row.real_name || '—';
    document.getElementById('kyc-type').textContent = (row.id_type === 2 ? '中国护照' : '中国身份证');
    document.getElementById('kyc-card').textContent = maskCard(row.id_card_no || '');
    document.getElementById('kyc-at').textContent = row.kyc_at || '—';
    var photos = document.getElementById('kyc-photos');
    photos.innerHTML = '';
    var labels = row.id_type === 2 ? [['id_photo_front', '护照照片']] : [['id_photo_front', '人像面'], ['id_photo_back', '国徽面']];
    labels.forEach(function (item) {
        var url = row[item[0]] || '';
        var card = document.createElement('div');
        card.style.cssText = 'text-align:center';
        card.innerHTML = url
            ? '<img class="zoomable" src="' + url + '" data-zoom="' + url + '" alt="' + item[1] + '" style="width:150px;height:96px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0">'
            + '<p class="form-hint" style="margin-top:4px">' + item[1] + '</p>'
            : '<span class="form-hint">' + item[1] + '：未上传</span>';
        photos.appendChild(card);
    });
    document.getElementById('kyc-remark').value = '';
    document.getElementById('dlg-kyc').showModal();
}

/** 证件号脱敏：保留前 3 位与后 3 位 */
function maskCard(card) {
    if (card.length <= 6) { return card; }
    return card.slice(0, 3) + '****' + card.slice(-3);
}

function reviewKyc(action) {
    var remark = val('kyc-remark').trim();
    var label = action === 'approve' ? '通过' : '驳回';
    if (action === 'reject' && remark.length < 2) {
        Notify.warning('驳回时必须填写原因');
        return;
    }
    admConfirm('确定' + label + '该用户的实名认证吗？', async function () {
        var res = await App.post('/admin/users/kyc/review', { id: val('kyc-id'), action: action, remark: remark });
        App.handle(res, '实名' + label + '成功');
        if (res.success) { document.getElementById('dlg-kyc').close(); admRefresh(); }
    }, { title: '实名' + label, confirmText: '确认' + label, danger: action === 'reject' });
}
</script>
