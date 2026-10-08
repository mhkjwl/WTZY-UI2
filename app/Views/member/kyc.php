<?php declare(strict_types=1);
/**
 * @file app/Views/member/kyc.php
 * @description 会员中心：实名认证（身份证正反面 / 护照照片，提交后由后台审核）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-14
 */

use App\Core\View;
use App\Models\UserModel;

/**
 * @var array $user
 */
$status = (int) $user['kyc_status'];
$map = [0 => ['未认证', 'gray'], 1 => ['审核中', 'orange'], 2 => ['已认证', 'green'], 3 => ['已驳回', 'red']];
$info = $map[$status] ?? ['未知', 'gray'];
$typeLabels = [1 => '中国身份证', 2 => '中国护照'];
?>
<section class="panel" id="kyc-panel">
    <h3 class="section-head">
        <span class="badge badge--blue">认证状态</span><span class="rule"></span>
        <span class="badge badge--<?= $info[1] ?>"><?= View::e($info[0]) ?></span>
    </h3>

    <?php if ($status === 2): ?>
        <div class="adm-toolbar" style="margin-bottom:0">
            <span class="badge badge--green">✅ 已通过实名认证</span>
            <span class="form-hint" style="margin-left:12px">
                姓名 <?= View::e((string) ($user['real_name'] ?? '')) ?> ·
                <?= View::e($typeLabels[(int) ($user['id_type'] ?? 1)] ?? '中国身份证') ?>
                <?= View::e(UserModel::maskIdNo((string) ($user['id_card_no'] ?? ''))) ?>
            </span>
        </div>
    <?php elseif ($status === 1): ?>
        <div class="adm-toolbar" style="margin-bottom:0">
            <span class="badge badge--orange">⏳ 已提交，等待审核</span>
            <span class="form-hint" style="margin-left:12px">审核通过后会通过站内信通知您。</span>
        </div>
    <?php else: ?>
        <?php if ($status === 3 && ($user['kyc_remark'] ?? '') !== ''): ?>
            <div class="adm-toolbar" style="margin-bottom:14px">
                <span class="badge badge--red">驳回原因</span>
                <span class="form-hint" style="margin-left:12px"><?= View::e((string) $user['kyc_remark']) ?></span>
                <span class="form-hint" style="margin-left:auto">可修改后立即重新提交。</span>
            </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-field" style="max-width:220px">
                <label class="form-label">证件类型</label>
                <select class="form-input" id="ky-type" onchange="onKycTypeChange()">
                    <option value="1" <?= (int) ($user['id_type'] ?? 1) === 1 ? 'selected' : '' ?>>中国身份证</option>
                    <option value="2" <?= (int) ($user['id_type'] ?? 1) === 2 ? 'selected' : '' ?>>中国护照</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="ky-name">真实姓名</label>
                <input class="form-input" id="ky-name" maxlength="30" value="<?= View::e((string) ($user['real_name'] ?? '')) ?>">
            </div>
            <div class="form-field">
                <label class="form-label" for="ky-card">证件号码</label>
                <input class="form-input mono" id="ky-card" maxlength="20" value="<?= View::e((string) ($user['id_card_no'] ?? '')) ?>">
            </div>
        </div>

        <div class="form-row" id="ky-photos">
            <div class="form-field">
                <label class="form-label" id="ky-front-label">身份证人像面</label>
                <input class="form-input" type="file" id="ky-front-file" accept="image/*" onchange="uploadKycPhoto('front', this)">
                <input type="hidden" id="ky-front" value="<?= View::e((string) ($user['id_photo_front'] ?? '')) ?>">
                <div id="ky-front-preview" style="margin-top:8px"></div>
            </div>
            <div class="form-field" id="ky-back-field">
                <label class="form-label">身份证国徽面</label>
                <input class="form-input" type="file" id="ky-back-file" accept="image/*" onchange="uploadKycPhoto('back', this)">
                <input type="hidden" id="ky-back" value="<?= View::e((string) ($user['id_photo_back'] ?? '')) ?>">
                <div id="ky-back-preview" style="margin-top:8px"></div>
            </div>
        </div>
        <p class="form-hint">照片仅用于实名审核，支持 jpg/png，单张不超过 5MB。</p>
        <button class="btn btn--primary" type="button" onclick="submitKyc()">提交认证</button>
    <?php endif; ?>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">为什么需要实名</span><span class="rule"></span></h3>
    <p class="form-hint">收件人信息须与证件一致，否则可能清关退回；实名信息仅用于合规用途，不做对外展示。</p>
</section>

<script>
var csrfToken = (document.querySelector('meta[name=csrf-token]') || {}).content || '';

function onKycTypeChange() {
    var isPassport = val('ky-type') === '2';
    document.getElementById('ky-back-field').style.display = isPassport ? 'none' : '';
    document.getElementById('ky-front-label').textContent = isPassport ? '护照照片' : '身份证人像面';
}

function showKycPreview(side, url) {
    var box = document.getElementById('ky-' + side + '-preview');
    if (!box) { return; }
    box.innerHTML = '<img class="zoomable" src="' + url + '" data-zoom="' + url + '"'
        + ' alt="证件照片" style="width:160px;height:100px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0">';
}

async function uploadKycPhoto(side, inputEl) {
    var file = inputEl.files && inputEl.files[0];
    if (!file) { return; }
    if (file.size > 5 * 1024 * 1024) { Notify.warning('照片不能超过 5MB'); inputEl.value = ''; return; }
    var form = new FormData();
    form.append('file', file);
    var handle = Notify.loading('上传中…');
    try {
        var res = await fetch('/user/kyc/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrfToken },
            body: form
        });
        var data = await res.json();
        if (handle && handle.close) { handle.close(); }
        if (!data.success) { Notify.error(data.message || '上传失败'); return; }
        document.getElementById('ky-' + side).value = data.data.url;
        showKycPreview(side, data.data.url);
        Notify.success('照片已上传');
    } catch (e) {
        if (handle && handle.close) { handle.close(); }
        Notify.error('网络异常，请稍后重试');
    }
}

function submitKyc() {
    var idType = val('ky-type');
    var name = val('ky-name').trim();
    var card = val('ky-card').trim();
    var front = val('ky-front');
    var back = val('ky-back');
    if (!name) { Notify.warning('请填写真实姓名'); return; }
    if (!card) { Notify.warning('请填写证件号码'); return; }
    if (idType === '1' && (!front || !back)) { Notify.warning('请上传身份证人像面与国徽面两张照片'); return; }
    if (idType === '2' && !front) { Notify.warning('请上传护照照片'); return; }
    admConfirm('确定提交实名认证吗？提交后将进入人工审核。', async function () {
        var res = await App.post('/user/kyc/submit', {
            id_type: idType, real_name: name, id_card_no: card,
            id_photo_front: front, id_photo_back: back
        });
        App.handle(res, '实名信息已提交');
        if (!res.success) { return; }
        // 就地切换为「审核中」，不整页刷新（规范 §2.5）
        var panel = document.getElementById('kyc-panel');
        if (panel) {
            panel.innerHTML = '<h3 class="section-head"><span class="badge badge--blue">认证状态</span><span class="rule"></span>'
                + '<span class="badge badge--orange">审核中</span></h3>'
                + '<div class="adm-toolbar" style="margin-bottom:0">'
                + '<span class="badge badge--orange">⏳ 已提交，等待审核</span>'
                + '<span class="form-hint" style="margin-left:12px">审核通过后会通过站内信通知您。</span></div>';
        }
    }, { title: '提交实名认证', confirmText: '确认提交' });
}

onKycTypeChange();
<?php if ($status !== 2 && (string) ($user['id_photo_front'] ?? '') !== ''): ?>
showKycPreview('front', '<?= View::e((string) $user['id_photo_front']) ?>');
<?php endif; ?>
<?php if ($status !== 2 && (string) ($user['id_photo_back'] ?? '') !== ''): ?>
showKycPreview('back', '<?= View::e((string) $user['id_photo_back']) ?>');
<?php endif; ?>
</script>
