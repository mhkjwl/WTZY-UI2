<?php declare(strict_types=1);
/**
 * @file app/Views/member/abnormal.php
 * @description 会员中心：异常件与处置申请记录（翻页局部刷新，规范 §2.5）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

use App\Core\View;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */
?>
<div id="abn-region">
    <?= View::partial('member/abnormal_region', get_defined_vars()) ?>
</div>

<script>
/**
 * 翻页：仅替换统计与列表区域，不整页刷新（规范 §2.5.2 分页）
 */
async function abnPage(n) {
    var region = document.getElementById('abn-region');
    if (!region) { return; }
    region.style.opacity = '0.55';
    try {
        var res = await fetch('/user/packages/abnormal?page=' + n, { headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || res.redirected) {
            Notify.error('登录状态已失效，请重新登录');
            return;
        }
        region.innerHTML = await res.text();
        history.pushState({}, '', '/user/packages/abnormal?page=' + n);
    } catch (err) {
        Notify.error('网络异常，列表未能刷新');
    } finally {
        region.style.opacity = '1';
    }
}

window.addEventListener('popstate', function () {
    var n = parseInt(new URLSearchParams(location.search).get('page'), 10) || 1;
    abnPage(n);
});
</script>
