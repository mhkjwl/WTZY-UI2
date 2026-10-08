<?php declare(strict_types=1);
/**
 * @file app/Views/member/shipping.php
 * @description 会员中心：转运目的地收货地址维护
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $addresses
 */
?>
<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--blue">收货地址</span><span class="rule"></span>
        <button class="btn btn--primary" onclick="openAddr(null)">+ 新增地址</button>
    </h3>
    <p class="form-hint">转运目的地地址；海外仓收货地址见「海外仓地址」。</p>

    <div id="addr-list">
        <?= View::partial('member/shipping_list', ['addresses' => $addresses]) ?>
    </div>
</section>

<dialog class="adm-dialog adm-dialog--lg" id="dlg-addr">
    <div class="adm-dialog__head" id="addr-title">新增收货地址</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="ad-id" value="0">
        <div class="form-row">
            <div class="form-field"><label class="form-label">收件人</label><input class="form-input" id="ad-name" maxlength="50"></div>
            <div class="form-field"><label class="form-label">联系电话</label><input class="form-input" id="ad-phone" maxlength="20"></div>
            <div class="form-field"><label class="form-label">国家/地区码</label><input class="form-input" id="ad-country" maxlength="2" placeholder="US"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">州/省</label><input class="form-input" id="ad-state" maxlength="50"></div>
            <div class="form-field"><label class="form-label">城市</label><input class="form-input" id="ad-city" maxlength="50"></div>
            <div class="form-field"><label class="form-label">区/县（选填）</label><input class="form-input" id="ad-district" maxlength="50"></div>
        </div>
        <div class="form-row">
            <div class="form-field"><label class="form-label">邮编</label><input class="form-input" id="ad-zip" maxlength="16"></div>
            <div class="form-field"><label class="form-label">地址行 1</label><input class="form-input" id="ad-a1" maxlength="255"></div>
            <div class="form-field"><label class="form-label">地址行 2（选填）</label><input class="form-input" id="ad-a2" maxlength="255"></div>
        </div>
        <label class="confirm-box__check" style="margin-top:6px">
            <input type="checkbox" id="ad-default"> 设为默认地址
        </label>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveAddr()">保存</button>
    </div>
</dialog>

<script>
function openAddr(row) {
    var isNew = !row;
    document.getElementById('addr-title').textContent = isNew ? '新增收货地址' : '编辑收货地址';
    document.getElementById('ad-id').value = isNew ? 0 : row.id;
    document.getElementById('ad-name').value = isNew ? '' : row.receiver_name;
    document.getElementById('ad-phone').value = isNew ? '' : row.receiver_phone;
    document.getElementById('ad-country').value = isNew ? '' : row.country;
    document.getElementById('ad-state').value = isNew ? '' : row.state;
    document.getElementById('ad-city').value = isNew ? '' : row.city;
    document.getElementById('ad-district').value = isNew ? '' : (row.district || '');
    document.getElementById('ad-zip').value = isNew ? '' : row.zip_code;
    document.getElementById('ad-a1').value = isNew ? '' : row.address1;
    document.getElementById('ad-a2').value = isNew ? '' : (row.address2 || '');
    document.getElementById('ad-default').checked = !isNew && Number(row.is_default) === 1;
    document.getElementById('dlg-addr').showModal();
}

/**
 * 刷新地址列表区域（规范 §2.5 区域整体替换，不整页刷新）
 */
async function refreshAddrList() {
    var el = document.getElementById('addr-list');
    if (!el) { return; }
    try {
        var res = await fetch('/user/shipping/list', { headers: { 'X-Requested-With': 'fetch' } });
        // 校验响应有效性，避免会话过期/出错时把登录页或错误页塞进列表区
        if (!res.ok || res.redirected) {
            Notify.error('登录状态已失效，请重新登录');
            return;
        }
        el.innerHTML = await res.text();
    } catch (err) {
        Notify.error('网络异常，地址列表未能刷新');
    }
}

function saveAddr() {
    var name = val('ad-name').trim();
    if (!name) { Notify.warning('请填写收件人姓名'); return; }
    admConfirm('确定保存收货地址「' + name + '」吗？', async function () {
        var res = await App.post('/user/shipping/save', {
            id: val('ad-id'), receiver_name: name, receiver_phone: val('ad-phone').trim(),
            country: val('ad-country').trim(), state: val('ad-state').trim(), city: val('ad-city').trim(),
            district: val('ad-district').trim(), address1: val('ad-a1').trim(), address2: val('ad-a2').trim(),
            zip_code: val('ad-zip').trim(), is_default: document.getElementById('ad-default').checked ? 1 : 0
        });
        App.handle(res, '收货地址已保存');
        if (res.success) { document.getElementById('dlg-addr').close(); refreshAddrList(); }
    }, { title: '保存收货地址', confirmText: '确认保存' });
}

function deleteAddr(id) {
    admConfirm('确定删除该收货地址吗？删除后无法恢复。', async function () {
        var res = await App.post('/user/shipping/delete', { id: id });
        App.handle(res, '收货地址已删除');
        if (res.success) {
            // 先原地淡出该卡片，再刷新区域，避免整页跳转
            var card = document.querySelector('[data-addr="' + id + '"]');
            if (card) { card.style.transition = 'opacity .3s'; card.style.opacity = '0'; setTimeout(refreshAddrList, 300); }
            else { refreshAddrList(); }
        }
    }, { title: '删除收货地址', confirmText: '确认删除', danger: true });
}
</script>
