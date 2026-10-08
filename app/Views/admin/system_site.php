<?php declare(strict_types=1);
/**
 * @file app/Views/admin/system_site.php
 * @description 站点信息页：网站名称 / 网站关键词 / 网站介绍 编辑与实时预览
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

use App\Core\View;

/**
 * @var array $site  含 site_name / site_keywords / site_description
 * @var array $perms
 * @var string $csrf
 * @var string $faviconUrl 浏览器图标地址（未上传为空串）
 * @var string $logoUrl    网站 Logo 地址（未上传为空串）
 */
?>
<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">基本信息</span><span class="rule"></span></h3>

        <div class="form-field">
            <label class="form-label" for="site-name">网站名称 <span class="badge badge--red">必填</span></label>
            <input class="form-input" id="site-name" maxlength="50" value="<?= View::e($site['site_name']) ?>"
                   placeholder="如：环球领航">
            <p class="form-hint">建议 3~20 个字符。</p>
        </div>

        <div class="form-field">
            <label class="form-label" for="site-keywords">网站关键词</label>
            <input class="form-input" id="site-keywords" maxlength="200" value="<?= View::e($site['site_keywords']) ?>"
                   placeholder="如：转运,海外仓,集运,代购">
            <p class="form-hint">多个关键词用英文逗号分隔。</p>
        </div>

        <div class="form-field">
            <label class="form-label" for="site-description">网站介绍</label>
            <textarea class="form-input" id="site-description" rows="5" maxlength="500"
                      placeholder="一句话说明网站提供的服务与优势"><?= View::e($site['site_description']) ?></textarea>
            <p class="form-hint">搜索引擎摘要与分享卡片，建议 60~160 个字符。</p>
        </div>

        <?php $faviconUrl = $site['faviconUrl'] ?? ''; $logoUrl = $site['logoUrl'] ?? ''; ?>
        <h3 class="section-head" style="margin-top:6px"><span class="badge badge--sky">品牌素材</span><span class="rule"></span></h3>
        <div class="form-row">
            <div class="form-field">
                <label class="form-label">浏览器图标</label>
                <div class="brand-upload">
                    <input type="file" id="favicon-file" accept="image/jpeg,image/png,image/webp" hidden>
                    <button class="btn btn--ghost btn--sm" type="button" onclick="pickBrand('favicon')">选择图片</button>
                    <div class="brand-upload__status">
                        <img id="favicon-preview" class="brand-upload__preview brand-upload__preview--favicon" alt=""
                             src="<?= $faviconUrl !== '' ? View::e($faviconUrl) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ?>"
                             <?= $faviconUrl === '' ? 'hidden' : '' ?>>
                        <span id="favicon-state" class="brand-upload__state<?= $faviconUrl !== '' ? ' is-set' : '' ?>"><?= $faviconUrl !== '' ? '已设置' : '未设置' ?></span>
                    </div>
                </div>
                <p class="form-hint">上传后自动裁为 500×500 固定方形并保存为 favicon.ico，全站浏览器标签页生效。</p>
            </div>
            <div class="form-field">
                <label class="form-label">网站 Logo</label>
                <div class="brand-upload">
                    <input type="file" id="logo-file" accept="image/jpeg,image/png,image/webp" hidden>
                    <button class="btn btn--ghost btn--sm" type="button" onclick="pickBrand('logo')">选择图片</button>
                    <div class="brand-upload__status">
                        <img id="logo-preview" class="brand-upload__preview" alt=""
                             src="<?= $logoUrl !== '' ? View::e($logoUrl) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ?>"
                             <?= $logoUrl === '' ? 'hidden' : '' ?>>
                        <span id="logo-state" class="brand-upload__state<?= $logoUrl !== '' ? ' is-set' : '' ?>"><?= $logoUrl !== '' ? '已设置' : '未设置' ?></span>
                    </div>
                </div>
                <p class="form-hint">显示在三端页面左上方，按固定高度等比展示；上传后自动压缩到合理尺寸。</p>
            </div>
        </div>

        <h3 class="section-head" style="margin-top:6px"><span class="badge badge--violet">联系方式与备案</span><span class="rule"></span></h3>
        <p class="form-hint" style="margin-bottom:14px">显示在官网页头与页脚；留空的项在官网对应位置自动隐藏，不会出现空占位。</p>
        <div class="form-row">
            <div class="form-field" style="max-width:260px">
                <label class="form-label" for="site-phone">客服电话</label>
                <input class="form-input" id="site-phone" maxlength="50" value="<?= View::e($site['site_phone']) ?>"
                       placeholder="如：400-888-6688">
            </div>
            <div class="form-field" style="max-width:300px">
                <label class="form-label" for="site-email">客服邮箱</label>
                <input class="form-input" id="site-email" maxlength="100" value="<?= View::e($site['site_email']) ?>"
                       placeholder="如：service@example.com">
            </div>
            <div class="form-field" style="max-width:280px">
                <label class="form-label" for="site-hours">服务时间</label>
                <input class="form-input" id="site-hours" maxlength="50" value="<?= View::e($site['site_hours']) ?>"
                       placeholder="如：周一至周日 8:00-24:00">
            </div>
        </div>
        <div class="form-row">
            <div class="form-field" style="max-width:320px">
                <label class="form-label" for="site-company">公司全称</label>
                <input class="form-input" id="site-company" maxlength="100" value="<?= View::e($site['site_company']) ?>"
                       placeholder="显示在页脚版权行">
            </div>
            <div class="form-field" style="max-width:300px">
                <label class="form-label" for="site-icp">备案信息</label>
                <input class="form-input" id="site-icp" maxlength="200" value="<?= View::e($site['site_icp']) ?>"
                       placeholder="如：粤ICP备20268866号-1">
            </div>
        </div>
        <div class="form-field">
            <label class="form-label" for="site-address">公司地址</label>
            <input class="form-input" id="site-address" maxlength="200" value="<?= View::e($site['site_address']) ?>"
                   placeholder="显示在页脚「联系我们」">
        </div>

        <h3 class="section-head" style="margin-top:6px"><span class="badge badge--indigo">海外仓地址</span><span class="rule"></span></h3>
        <div class="form-field" style="max-width:240px">
            <label class="form-label" for="site-addr-prefix">地址前缀 <span class="badge badge--red">必填</span></label>
            <input class="form-input mono" id="site-addr-prefix" maxlength="10"
                   value="<?= View::e($site['warehouse_address_prefix']) ?>" placeholder="如：WTZY">
            <p class="form-hint">仅字母与数字，最多 10 位。修改后新生成的海外仓地址使用新前缀，已生成的地址不受影响。</p>
        </div>
        <div class="form-field">
            <label class="form-label">当前面单收件人格式</label>
            <input class="form-input mono" value="<?= View::e($site['warehouse_address_prefix']) ?>-仓库编码 (用户编号)" readonly>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--emerald">官网首页数字</span><span class="rule"></span></h3>
        <p class="form-hint" style="margin-bottom:14px">
            首屏数字条与统计卡、服务承诺数字；整行留空即恢复默认值。服务承诺的「免费仓储期」数值填 <b>{days}</b> 即跟随仓库设置的免费仓储天数。首页「常见问题」请到「营销内容 → 常见问题」编辑，文案中的 <b>{days}</b> 同样会按仓库设置替换。
        </p>
        <?php foreach ([
            ['homepage_stats', '统计数字', $site['homepage_stats']],
            ['homepage_promises', '服务承诺', $site['homepage_promises']],
        ] as [$hpKey, $hpLabel, $hpRows]): ?>
            <div class="form-field">
                <label class="form-label"><?= View::e($hpLabel) ?></label>
                <?php foreach ($hpRows as $hpRow): ?>
                <div style="display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap">
                    <input class="form-input mono" style="max-width:120px" name="<?= View::e($hpKey) ?>_value[]" maxlength="12"
                           value="<?= View::e((string) $hpRow['value']) ?>" placeholder="数值">
                    <input class="form-input" style="max-width:100px" name="<?= View::e($hpKey) ?>_unit[]" maxlength="8"
                           value="<?= View::e((string) $hpRow['unit']) ?>" placeholder="单位">
                    <input class="form-input" style="flex:1 1 240px" name="<?= View::e($hpKey) ?>_label[]" maxlength="30"
                           value="<?= View::e((string) $hpRow['label']) ?>" placeholder="说明文字">
                </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($perms['system.manage'])): ?>
        <h3 class="section-head" style="margin-top:6px"><span class="badge badge--cyan">注册与实名设置</span><span class="rule"></span></h3>
        <div class="form-row">
            <div class="form-field">
                <label class="form-label">邮箱验证注册</label>
                <div style="padding-top:8px">
                    <span class="badge badge--<?= $site['register_email_verify'] === '1' ? 'green' : 'gray' ?>"><?= $site['register_email_verify'] === '1' ? '已开启' : '已关闭' ?></span>
                    <a class="auth-link-mini" style="display:inline-block;margin-left:10px" href="/admin/system/mail">在「邮箱设置」中维护</a>
                </div>
                <p class="form-hint">开启后注册必须填写邮箱并完成验证码校验；关闭后按用户名直接注册。</p>
            </div>
            <div class="form-field">
                <label class="form-label">强制实名认证</label>
                <div style="padding-top:8px">
                    <label style="display:inline-flex;align-items:center;gap:8px;font-size:14px;cursor:pointer">
                        <input type="checkbox" id="rg-realname" <?= $site['realname_required'] === '1' ? 'checked' : '' ?>>
                        开启后未实名用户仅可修改资料、实名认证与退出登录
                    </label>
                </div>
                <p class="form-hint">关闭时不限制任何业务操作，注册无限制。</p>
            </div>
        </div>

        <div class="adm-toolbar" style="margin-bottom:0">
            <button class="btn btn--primary" onclick="saveSite()">保存站点信息</button>
            <button class="btn btn--ghost" onclick="resetSiteForm()">放弃修改</button>
        </div>
        <?php endif; ?>
    </section>
</div>

<script>
/** 联系方式字段（拼写固定，与后端 saveSite 的键一一对应） */
var CONTACT_FIELDS = {
    'site-company': 'site_company',
    'site-phone': 'site_phone',
    'site-email': 'site_email',
    'site-hours': 'site_hours',
    'site-address': 'site_address',
    'site-icp': 'site_icp',
    'site-addr-prefix': 'warehouse_address_prefix'
};

var SITE_INITIAL = {
    site_name: <?= json_encode($site['site_name'], JSON_UNESCAPED_UNICODE) ?>,
    site_keywords: <?= json_encode($site['site_keywords'], JSON_UNESCAPED_UNICODE) ?>,
    site_description: <?= json_encode($site['site_description'], JSON_UNESCAPED_UNICODE) ?>,
    site_company: <?= json_encode($site['site_company'], JSON_UNESCAPED_UNICODE) ?>,
    site_phone: <?= json_encode($site['site_phone'], JSON_UNESCAPED_UNICODE) ?>,
    site_email: <?= json_encode($site['site_email'], JSON_UNESCAPED_UNICODE) ?>,
    site_hours: <?= json_encode($site['site_hours'], JSON_UNESCAPED_UNICODE) ?>,
    site_address: <?= json_encode($site['site_address'], JSON_UNESCAPED_UNICODE) ?>,
    site_icp: <?= json_encode($site['site_icp'], JSON_UNESCAPED_UNICODE) ?>,
    warehouse_address_prefix: <?= json_encode($site['warehouse_address_prefix'], JSON_UNESCAPED_UNICODE) ?>,
    homepage_stats: <?= json_encode($site['homepage_stats'], JSON_UNESCAPED_UNICODE) ?>,
    homepage_promises: <?= json_encode($site['homepage_promises'], JSON_UNESCAPED_UNICODE) ?>,
    realname_required: <?= $site['realname_required'] === '1' ? 'true' : 'false' ?>
};

function fieldValue(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim() : '';
}

/** 首页数字行：按 name 前缀采集为三列并列数组（与后端 saveNumberRows 对应） */
function numberRowsPayload() {
    var data = {};
    ['homepage_stats', 'homepage_promises'].forEach(function (key) {
        var value = [], unit = [], label = [];
        document.querySelectorAll('[name^="' + key + '_"]').forEach(function (el) {
            var n = el.getAttribute('name');
            if (n.indexOf(key + '_value') === 0) { value.push(el.value.trim()); }
            else if (n.indexOf(key + '_unit') === 0) { unit.push(el.value.trim()); }
            else if (n.indexOf(key + '_label') === 0) { label.push(el.value.trim()); }
        });
        data[key + '_value'] = value;
        data[key + '_unit'] = unit;
        data[key + '_label'] = label;
    });
    return data;
}

/** 还原首页数字行为初始值 */
function resetNumberRows() {
    ['homepage_stats', 'homepage_promises'].forEach(function (key) {
        var rows = SITE_INITIAL[key] || [];
        var k = 0;
        ['value', 'unit', 'label'].forEach(function (kind) {
            document.querySelectorAll('[name="' + key + '_' + kind + '[]"]').forEach(function (el, i) {
                var row = rows[i] || {};
                el.value = row[kind] === undefined ? '' : row[kind];
            });
        });
    });
}

/** 采集联系方式字段，键为后端接受的表单字段名 */
function contactPayload() {
    var data = {};
    Object.keys(CONTACT_FIELDS).forEach(function (id) {
        data[CONTACT_FIELDS[id]] = fieldValue(id);
    });
    return data;
}

function saveSite() {
    var name = fieldValue('site-name');
    if (!name) {
        Notify.warning('请填写网站名称');
        document.getElementById('site-name').focus();
        return;
    }
    var email = fieldValue('site-email');
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        Notify.warning('客服邮箱格式不正确');
        document.getElementById('site-email').focus();
        return;
    }
    var prefix = fieldValue('site-addr-prefix');
    if (!/^[A-Za-z0-9]{1,10}$/.test(prefix)) {
        Notify.warning('海外仓地址前缀只能包含字母与数字，且不超过 10 位');
        document.getElementById('site-addr-prefix').focus();
        return;
    }
    admConfirm('确定保存站点信息吗？保存后网站名称、SEO 与联系方式将立即生效。', async function () {
        var res = await App.post('/admin/system/site/save', Object.assign({
            site_name: name,
            site_keywords: fieldValue('site-keywords'),
            site_description: fieldValue('site-description'),
            realname_required: document.getElementById('rg-realname').checked ? '1' : '0'
        }, Object.assign(contactPayload(), numberRowsPayload())));
        App.handle(res, '站点信息已保存');
    }, { title: '保存站点信息', confirmText: '确认保存' });
}

function resetSiteForm() {
    admConfirm('确定放弃当前未保存的修改吗？表单将恢复为上次保存的内容。', function () {
        document.getElementById('site-name').value = SITE_INITIAL.site_name;
        document.getElementById('site-keywords').value = SITE_INITIAL.site_keywords;
        document.getElementById('site-description').value = SITE_INITIAL.site_description;
        Object.keys(CONTACT_FIELDS).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.value = SITE_INITIAL[CONTACT_FIELDS[id]] || ''; }
        });
        resetNumberRows();
        document.getElementById('rg-realname').checked = SITE_INITIAL.realname_required;
        Notify.info('已恢复为上次保存的内容');
    }, { title: '放弃修改', confirmText: '确认放弃', danger: true });
}

/** 品牌素材：选择图片即上传，成功后局部刷新预览（不整页刷新） */
function pickBrand(type) {
    document.getElementById(type + '-file').click();
}

function bindBrandUpload(type) {
    document.getElementById(type + '-file').addEventListener('change', function () {
        var file = this.files && this.files[0];
        this.value = '';
        if (!file) { return; }
        if (file.size > 5 * 1024 * 1024) { Notify.warning('图片不能超过 5MB'); return; }
        var form = new FormData();
        form.append('type', type);
        form.append('file', file);
        var handle = Notify.loading('上传中…');
        fetch('/admin/system/site/upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': App.csrf() },
            body: form
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (handle && handle.close) { handle.close(); }
            if (!data.success) { Notify.error(data.message || '上传失败'); return; }
            var preview = document.getElementById(type + '-preview');
            preview.src = data.data.url + '&t=' + Date.now();
            preview.hidden = false;
            var state = document.getElementById(type + '-state');
            state.textContent = '已设置';
            state.classList.add('is-set');
            Notify.success('上传成功');
        }).catch(function () {
            if (handle && handle.close) { handle.close(); }
            Notify.error('网络异常，请稍后重试');
        });
    });
}
bindBrandUpload('favicon');
bindBrandUpload('logo');
</script>
