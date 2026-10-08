<?php declare(strict_types=1);
/**
 * @file app/Views/admin/marketing.php
 * @description 营销与内容页：全站公告管理 + 内容页（协议/隐私/禁运品/FAQ）编辑
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;

/**
 * @var array $notices
 * @var int $page
 * @var int $pages
 * @var array $contents
 * @var bool $mailConfigured
 * @var array $perms
 * @var string $csrf
 */
?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">全站公告</span><span class="rule"></span>
        <?php if (!empty($perms['marketing.manage'])): ?>
            <button class="btn btn--primary" onclick="openNotice(0, null)">+ 发布公告</button>
        <?php endif; ?>
    </h3>
    <?php if (!$mailConfigured): ?>
        <p class="form-hint" style="margin-bottom:10px">
            邮箱渠道需先完成 <a href="/admin/system/mail">系统 → 邮箱设置</a>，否则只能走站内信。
        </p>
    <?php endif; ?>
    <div class="table-wrap" data-table-wrap data-url="/admin/marketing?page=<?= (int) $page ?>">
        <?= View::partial('admin/marketing_content', get_defined_vars()) ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--violet">内容页</span><span class="rule"></span></h3>
    <?php foreach ($contents as $c): ?>
        <div class="form-field">
            <label class="form-label"><?= View::e($c['label']) ?> <span class="mono">(<?= View::e($c['key']) ?>)</span></label>
            <textarea class="form-input" id="ct-<?= View::e($c['key']) ?>" rows="4"><?= View::e($c['value']) ?></textarea>
            <?php if ($c['key'] === 'content_faq'): ?>
                <p class="form-hint">文案中的 <b>{days}</b> 会按「基本信息 → 官网首页数字 → 服务承诺」的免费仓储天数自动替换，请保留占位符、不要手填数字。</p>
            <?php endif; ?>
            <div style="margin-top:6px">
                <button class="btn btn--ghost btn--sm" onclick="saveContent('<?= View::e($c['key']) ?>')">保存<?= View::e($c['label']) ?></button>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<dialog class="adm-dialog" id="dlg-notice">
    <div class="adm-dialog__head" id="nt-head">发布全站公告</div>
    <div class="adm-dialog__body">
        <input type="hidden" id="nt-id" value="0">
        <div class="form-row">
            <div class="form-field"><label class="form-label">标题</label><input class="form-input" id="nt-title" maxlength="100"></div>
            <div class="form-field"><label class="form-label">公告类型</label>
                <select class="form-input" id="nt-notice-type">
                    <option value="1">普通</option>
                    <option value="2">重要</option>
                </select>
                <p class="form-hint">重要公告在用户端会以醒目标签展示。</p>
            </div>
            <div class="form-field"><label class="form-label">通知渠道</label>
                <select class="form-input" id="nt-type" onchange="syncNoticeChannel()">
                    <option value="1">站内信（即时送达消息中心）</option>
                    <option value="3">邮箱（另发一封邮件到用户注册邮箱）</option>
                </select>
                <p class="form-hint" id="nt-type-hint"></p>
            </div>
        </div>
        <div class="form-field"><label class="form-label">内容</label><textarea class="form-input" id="nt-content" rows="5" maxlength="5000"></textarea></div>
    </div>
    <div class="adm-dialog__foot">
        <button class="btn btn--ghost" onclick="admCloseDialog(this)">取消</button>
        <button class="btn btn--primary" onclick="saveNotice()">保存</button>
    </div>
</dialog>

<script>
var MAIL_CONFIGURED = <?= $mailConfigured ? 'true' : 'false' ?>;
function openNotice(id, data) {
    var isEdit = id > 0;
    document.getElementById('nt-head').textContent = isEdit ? '编辑全站公告' : '发布全站公告';
    document.getElementById('nt-id').value = isEdit ? id : 0;
    document.getElementById('nt-title').value = data ? data.title : '';
    document.getElementById('nt-content').value = data ? data.content : '';
    document.getElementById('nt-type').value = data ? String(data.msg_type) : '1';
    document.getElementById('nt-notice-type').value = data ? String(data.notice_type ?? 1) : '1';
    syncNoticeChannel();
    document.getElementById('dlg-notice').showModal();
}
/** 邮箱渠道提示随选择变化，SMTP 未配置时直接给出提醒，避免发布后才发现发不出去 */
function syncNoticeChannel() {
    var hint = document.getElementById('nt-type-hint');
    if (!hint) { return; }
    if (val('nt-type') !== '3') {
        hint.textContent = '公告会出现在用户端「消息中心」，不发送邮件。';
        return;
    }
    hint.textContent = MAIL_CONFIGURED
        ? '公告会出现在用户端「消息中心」，同时向已绑定邮箱的正常用户各发一封邮件。'
        : '邮件服务尚未配置，发布后无法投递，请先到「系统 → 邮箱设置」完成 SMTP 配置。';
}
function saveNotice() {
    var id = parseInt(val('nt-id'), 10) || 0;
    var title = val('nt-title').trim();
    var content = val('nt-content').trim();
    if (!title) { Notify.warning('请填写公告标题'); return; }
    if (!content) { Notify.warning('请填写公告内容'); return; }
    if (val('nt-type') === '3' && !MAIL_CONFIGURED) {
        Notify.warning('邮件服务尚未配置，请先到「系统 → 邮箱设置」完成配置');
        return;
    }
    var isEdit = id > 0;
    var message = isEdit
        ? '确定保存对公告「' + title + '」的修改吗？用户端消息中心会按最新时间重新排到最前。'
        : '确定发布公告「' + title + '」吗？发布后用户端消息中心立即可见。';
    admConfirm(message, async function () {
        var res = await App.post(isEdit ? '/admin/marketing/notice/update' : '/admin/marketing/notice', {
            id: id, title: title, content: content, msg_type: val('nt-type'), notice_type: val('nt-notice-type')
        });
        App.handle(res);
        if (res.success) { document.getElementById('dlg-notice').close(); admRefresh(); }
    }, {
        title: isEdit ? '保存公告修改' : '发布公告',
        confirmText: isEdit ? '确认保存' : '确认发布'
    });
}
function saveContent(key) {
    admConfirm('确定保存该内容页吗？保存后用户端相关页面立即生效。', async function () {
        var res = await App.post('/admin/marketing/content', {
            key: key, value: document.getElementById('ct-' + key).value
        });
        App.handle(res, '内容已保存');
    }, { title: '保存内容页', confirmText: '确认保存' });
}
</script>
