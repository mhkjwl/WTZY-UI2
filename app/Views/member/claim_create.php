<?php declare(strict_types=1);
/**
 * @file app/Views/member/claim_create.php
 * @description 会员中心：提交理赔申请
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $types
 * @var array $packages
 * @var array $waybills
 * @var int   $preselect
 */
?>
<section class="panel" id="cl-form-panel">
    <h3 class="section-head"><span class="badge badge--blue">理赔申请</span><span class="rule"></span></h3>
    <div class="form-row">
        <div class="form-field" style="max-width:220px">
            <label class="form-label">理赔类型</label>
            <select class="form-input" id="cl-type">
                <?php foreach ($types as $code => $label): ?>
                    <option value="<?= $code ?>"><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field" style="max-width:240px">
            <label class="form-label">关联包裹</label>
            <select class="form-input" id="cl-package">
                <option value="0">不关联</option>
                <?php foreach ($packages as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= $preselect === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= View::e($p['package_no']) ?> · <?= View::e($p['item_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field" style="max-width:240px">
            <label class="form-label">关联运单</label>
            <select class="form-input" id="cl-waybill">
                <option value="0">不关联</option>
                <?php foreach ($waybills as $w): ?>
                    <option value="<?= (int) $w['id'] ?>"><?= View::e($w['waybill_no']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-field" style="max-width:260px">
        <label class="form-label">申请赔付金额（元）</label>
        <input class="form-input" type="number" step="0.01" min="0.01" id="cl-amount" placeholder="按实际损失填写">
    </div>
    <div class="form-field">
        <label class="form-label">问题描述</label>
        <textarea class="form-input" id="cl-desc" rows="6" maxlength="500" placeholder="请写明包裹编号、问题表现、涉及商品与期望处理方式（至少 10 个字）"></textarea>
    </div>
    <div class="adm-toolbar" style="margin-bottom:0">
        <button class="btn btn--primary" type="button" onclick="submitClaim()">提交理赔申请</button>
        <a class="btn btn--ghost" href="/user/claims">返回列表</a>
    </div>
</section>

<script>
function submitClaim() {
    var amount = parseFloat(val('cl-amount'));
    var desc = val('cl-desc').trim();
    if (!(amount > 0)) { Notify.warning('请填写申请赔付金额'); return; }
    if (desc.length < 10) { Notify.warning('问题描述至少 10 个字'); return; }
    if (val('cl-package') === '0' && val('cl-waybill') === '0') {
        Notify.warning('请至少关联一个包裹或运单，便于客服核对');
        return;
    }

    admConfirm('确定提交理赔申请 ¥' + amount.toFixed(2) + ' 吗？客服会尽快跟进。', async function () {
        var res = await App.post('/user/claims/save', {
            claim_type: val('cl-type'), amount: amount, description: desc,
            package_id: val('cl-package'), waybill_id: val('cl-waybill')
        });
        App.handle(res, '理赔申请已提交');
        if (res.success && res.data && res.data.id) {
            // 就地切换为提交成功态并给出前往详情的入口，不整页跳转（规范 §2.5）
            var panel = document.getElementById('cl-form-panel');
            if (panel) {
                panel.innerHTML = '<h3 class="section-head"><span class="badge badge--emerald">提交成功</span>'
                    + '<span class="rule"></span></h3>'
                    + '<div class="empty-state">理赔申请已提交，客服会尽快跟进。审核进度与结果会在该理赔单详情与站内信中更新。</div>'
                    + '<div class="adm-toolbar" style="margin-bottom:0">'
                    + '<a class="btn btn--primary" href="/user/claims/' + res.data.id + '">查看理赔详情</a>'
                    + '<a class="btn btn--ghost" href="/user/claims">返回理赔列表</a>'
                    + '<a class="btn btn--ghost" href="/user/messages">查看消息</a>'
                    + '</div>';
            }
        }
    }, { title: '提交理赔申请', confirmText: '确认提交' });
}
</script>
