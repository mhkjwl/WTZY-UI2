<?php declare(strict_types=1);
/**
 * @file app/Views/admin/package_detail.php
 * @description 后台包裹详情：所属用户 + 包裹信息 + 称重计量 + 照片 + 异常与处置记录
 * @author AI
 * @version 0.6
 * @date 2026-09-16
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;
use App\Services\UploadService;

/**
 * @var array $package
 * @var array $photos
 * @var array $abnormals
 * @var array|null $pendingDisposal 进行中的退运/销毁申请
 * @var array $disposalTypes
 */
$s = (int) $package['status'];
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/admin/packages">‹ 返回列表</a>
    <span class="badge badge--<?= Status::packageTone($s) ?>"><?= View::e(Status::package($s)) ?></span>
    <?php if ($pendingDisposal !== null): ?>
        <span class="badge badge--<?= Status::abnormalTone((int) $pendingDisposal['abnormal_type']) ?>"><?= Status::ABNORMAL_TYPE[(int) $pendingDisposal['abnormal_type']] ?? '处置申请' ?> · 审核中</span>
    <?php elseif ((int) $package['is_abnormal'] === 1): ?><span class="badge badge--orange">异常件</span><?php endif; ?>
    <a class="btn btn--ghost btn--sm" style="margin-left:auto"
       href="/admin/users?kw=<?= urlencode((string) $package['user_no']) ?>">查看该用户</a>
</div>

<div class="site-grid site-grid--3">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--purple">所属用户</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>用户编号</span><b class="v-code mono"><?= View::e((string) ($package['user_no'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>用户名</span><b><?= View::e(UserModel::displayName($package)) ?></b></div>
            <div class="hero-address__row"><span>手机号</span><b><?= View::e((string) ($package['phone'] ?? '') ?: '未绑定') ?></b></div>
            <div class="hero-address__row"><span>邮箱</span><b><?= View::e((string) ($package['email'] ?? '') ?: '未绑定') ?></b></div>
            <div class="hero-address__row"><span>入库识别码</span><b class="v-code mono"><?= View::e((string) $package['user_code']) ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">包裹信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>包裹编号</span><b class="v-code mono"><?= View::e($package['package_no']) ?></b></div>
            <div class="hero-address__row"><span>预报单号</span><b class="v-code mono"><?= View::e((string) ($package['forecast_no'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>快递单号</span><b class="v-code mono"><?= View::e($package['tracking_no'] ?: '—') ?></b></div>
            <div class="hero-address__row"><span>快递公司</span><b><?= View::e($package['express_company'] ?: '—') ?></b></div>
            <div class="hero-address__row"><span>商品</span><b><?= View::e($package['item_name']) ?> ×<?= (int) $package['item_qty'] ?></b></div>
            <div class="hero-address__row"><span>类别</span><?= View::badge(Status::PACKAGE_CATEGORY[(int) $package['category']] ?? '其他', Status::categoryTone((int) $package['category'])) ?></div>
            <div class="hero-address__row"><span>申报价值</span><b class="v-money"><?= View::e((string) $package['currency']) ?> <?= number_format((float) $package['declared_value'], 2) ?></b></div>
            <div class="hero-address__row"><span>所属仓库</span><b><?= View::e((string) ($package['warehouse_name'] ?? '—')) ?></b></div>
            <div class="hero-address__row"><span>库位</span><b><?= View::e((string) ($package['location_code'] ?? '未上架')) ?></b></div>
            <div class="hero-address__row"><span>用户备注</span><b><?= View::e((string) ($package['remark'] ?? '') ?: '—') ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--violet">称重与计量</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>实际重量</span><b class="v-measure"><?= $package['weight'] !== null ? number_format((float) $package['weight'], 3) . ' kg' : '未称重' ?></b></div>
            <div class="hero-address__row"><span>尺寸</span><b class="v-measure">
                <?= $package['length'] !== null
                    ? number_format((float) $package['length'], 1) . ' × ' . number_format((float) $package['width'], 1) . ' × ' . number_format((float) $package['height'], 1) . ' cm'
                    : '未测量' ?></b></div>
            <div class="hero-address__row"><span>体积重</span><b class="v-measure"><?= $package['volume_weight'] !== null ? number_format((float) $package['volume_weight'], 3) . ' kg' : '—' ?></b></div>
            <div class="hero-address__row"><span>仓储费</span><b class="v-money">¥<?= number_format((float) $package['storage_fee'], 2) ?></b></div>
            <div class="hero-address__row"><span>到仓时间</span><b><?= View::e($package['inbound_at'] ? substr((string) $package['inbound_at'], 0, 16) : '未到仓') ?></b></div>
            <div class="hero-address__row"><span>认领时间</span><b><?= View::e($package['claimed_at'] ? substr((string) $package['claimed_at'], 0, 16) : '未认领') ?></b></div>
            <div class="hero-address__row"><span>预报时间</span><b><?= View::e(substr((string) $package['created_at'], 0, 16)) ?></b></div>
        </div>
    </section>
</div>

<section class="panel">
    <h3 class="section-head">
        <span class="badge badge--emerald">包裹照片与凭证</span><span class="rule"></span>
        <span class="form-hint">仓库上传的入库/打包照与用户上传的凭证</span>
    </h3>
    <?php if ($photos === []): ?>
        <div class="empty-state">暂无照片。包裹入库称重后仓库会上传照片。</div>
    <?php endif; ?>
    <div class="card-grid card-grid--4">
        <?php foreach ($photos as $photo): ?>
            <article class="card" style="padding:12px">
                <img class="zoomable" src="<?= View::e($photo['url']) ?>" data-zoom="<?= View::e($photo['url']) ?>"
                     alt="包裹照片" style="width:100%;height:140px;object-fit:cover;border-radius:8px">
                <p class="form-hint" style="margin-top:8px">
                    <?= View::e(UploadService::photoTypeLabel((int) $photo['photo_type'])) ?>
                    · <?= View::e(substr((string) $photo['created_at'], 0, 10)) ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--orange">异常与处置记录</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>类型</th><th>说明</th><th>状态</th><th>处理结果</th><th class="nowrap">提交时间</th></tr></thead>
            <tbody>
            <?php if ($abnormals === []): ?>
                <tr><td colspan="5"><div class="empty-state">暂无异常记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($abnormals as $a): ?>
                <tr>
                    <td><?= View::badge(Status::ABNORMAL_TYPE[(int) $a['abnormal_type']] ?? '其他', Status::abnormalTone((int) $a['abnormal_type'])) ?></td>
                    <td><?= View::e($a['description']) ?></td>
                    <td><span class="badge badge--<?= Status::abnormalStatusTone((int) $a['status']) ?>"><?= View::e(Status::ABNORMAL_STATUS[(int) $a['status']] ?? '未知') ?></span></td>
                    <td><?= View::e((string) ($a['handle_result'] ?? '—')) ?></td>
                    <td class="nowrap"><?= View::e(substr((string) $a['created_at'], 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
