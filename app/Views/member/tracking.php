<?php declare(strict_types=1);
/**
 * @file app/Views/member/tracking.php
 * @description 会员中心：物流跟踪（检索/翻页/切换运单均局部刷新，规范 §2.5）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array      $rows
 * @var int        $total
 * @var int        $page
 * @var int        $pages
 * @var string     $keyword
 * @var array|null $selected
 * @var array      $records
 */
?>
<section class="panel">
    <div class="adm-toolbar">
        <input class="form-input" id="t-kw" placeholder="运单号 / 承运单号" value="<?= View::e($keyword) ?>">
        <button class="btn btn--primary" onclick="searchTracking()">查询</button>
        <span class="form-hint" style="margin-left:auto">共 <?= (int) $total ?> 张运单，选择右侧运单查看完整轨迹</span>
    </div>
</section>

<div id="track-region">
    <?= View::partial('member/tracking_region', get_defined_vars()) ?>
</div>

<script>
/**
 * 局部加载运单列表与轨迹详情（检索/翻页/切换运单共用）
 * 用 History API 同步地址栏，浏览器前进后退可复原视图，且页面不刷新（规范 §2.5.4 第 8 条）
 */
async function trackReload(params, push) {
    var qs = new URLSearchParams();
    if (params.kw) { qs.set('kw', params.kw); }
    if (params.page && params.page > 1) { qs.set('page', params.page); }
    if (params.waybill) { qs.set('waybill', params.waybill); }
    var url = '/user/tracking' + (qs.toString() ? '?' + qs.toString() : '');

    var region = document.getElementById('track-region');
    if (!region) { return; }
    region.style.opacity = '0.55';
    try {
        var res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || res.redirected) {
            Notify.error('登录状态已失效，请重新登录');
            return;
        }
        region.innerHTML = await res.text();
        if (push !== false) { history.pushState({ track: qs.toString() }, '', url); }
    } catch (err) {
        Notify.error('网络异常，物流信息未能刷新');
    } finally {
        region.style.opacity = '1';
    }
}

function currentTrackParams() {
    var qs = new URLSearchParams(location.search);
    return { kw: qs.get('kw') || '', page: parseInt(qs.get('page'), 10) || 1, waybill: qs.get('waybill') || '' };
}

function searchTracking() {
    trackReload({ kw: val('t-kw').trim(), page: 1 });
}
function trackPage(n) {
    var p = currentTrackParams();
    trackReload({ kw: p.kw, page: n, waybill: p.waybill });
}
function trackView(id) {
    var p = currentTrackParams();
    trackReload({ kw: p.kw, page: p.page, waybill: id });
}

document.getElementById('t-kw').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { searchTracking(); }
});

// 浏览器前进/后退时按地址栏参数复原视图，不刷新整页
window.addEventListener('popstate', function () {
    var p = currentTrackParams();
    trackReload({ kw: p.kw, page: p.page, waybill: p.waybill }, false);
});
</script>
