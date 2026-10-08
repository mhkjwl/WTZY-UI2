<?php declare(strict_types=1);
/**
 * @file app/Views/member/ticket_create.php
 * @description 会员中心：提交客服工单
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $categories
 * @var array $packages
 * @var array $waybills
 */
?>
<section class="panel" id="tk-form-panel">
    <h3 class="section-head"><span class="badge badge--blue">提交工单</span><span class="rule"></span></h3>
    <div class="form-row">
        <div class="form-field" style="max-width:240px">
            <label class="form-label">问题类型</label>
            <select class="form-input" id="tk-cat">
                <?php foreach ($categories as $code => $label): ?>
                    <option value="<?= $code ?>"><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label class="form-label">关联运单（选填，便于客服定位）</label>
            <select class="form-input" id="tk-waybill">
                <option value="0">不关联</option>
                <?php foreach ($waybills as $w): ?>
                    <option value="<?= (int) $w['id'] ?>"><?= View::e($w['waybill_no']) ?> · <?= View::e((string) $w['receiver_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label class="form-label">关联包裹（选填）</label>
            <select class="form-input" id="tk-package">
                <option value="0">不关联</option>
                <?php foreach ($packages as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= View::e($p['package_no']) ?> · <?= View::e($p['item_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-field">
        <label class="form-label">标题</label>
        <input class="form-input" id="tk-title" maxlength="100" placeholder="一句话概括问题，如：包裹到仓后重量与预报不符">
    </div>
    <div class="form-field">
        <label class="form-label">问题描述</label>
        <textarea class="form-input" id="tk-content" rows="6" maxlength="2000" placeholder="请描述具体情况、发生时间与您的诉求，越详细处理越快"></textarea>
        <p class="form-hint">至少 5 个字。涉及金额争议请附上订单截图链接或说明。</p>
    </div>
    <div class="adm-toolbar" style="margin-bottom:0">
        <button class="btn btn--primary" type="button" onclick="submitTicket()">提交工单</button>
        <a class="btn btn--ghost" href="/user/tickets">返回列表</a>
    </div>
</section>

<script>
function submitTicket() {
    var title = val('tk-title').trim();
    var content = val('tk-content').trim();
    if (title.length < 4) { Notify.warning('请填写标题（至少 4 个字）'); return; }
    if (content.length < 5) { Notify.warning('请填写问题描述（至少 5 个字）'); return; }

    admConfirm('确定提交该工单吗？客服会尽快跟进处理。', async function () {
        var res = await App.post('/user/tickets/save', {
            category: val('tk-cat'), title: title, content: content,
            waybill_id: val('tk-waybill'), package_id: val('tk-package')
        });
        App.handle(res, '工单已提交');
        if (res.success && res.data && res.data.id) {
            // 就地切换为提交成功态并给出前往工单的入口，不整页跳转（规范 §2.5）
            var panel = document.getElementById('tk-form-panel');
            if (panel) {
                panel.innerHTML = '<h3 class="section-head"><span class="badge badge--emerald">提交成功</span>'
                    + '<span class="rule"></span></h3>'
                    + '<div class="empty-state">工单已提交，客服会尽快跟进处理，回复会同步到站内信。</div>'
                    + '<div class="adm-toolbar" style="margin-bottom:0">'
                    + '<a class="btn btn--primary" href="/user/tickets/' + res.data.id + '">查看工单详情</a>'
                    + '<a class="btn btn--ghost" href="/user/tickets">返回工单列表</a>'
                    + '<a class="btn btn--ghost" href="/user/messages">查看消息</a>'
                    + '</div>';
            }
        }
    }, { title: '提交工单', confirmText: '确认提交' });
}
</script>
