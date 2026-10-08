<?php declare(strict_types=1);
/**
 * @file app/Views/admin/waybill_detail.php
 * @description 后台运单详情：运单/用户/费用明细 + 关联包裹 + 物流轨迹 + 状态流转记录
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array $waybill
 * @var array $services
 * @var array $packages
 * @var array $tracking
 * @var array $logs
 * @var array $feeDetail
 */
$status = (int) $waybill['status'];
$due = (float) ($waybill['due_amount'] ?? 0);
$addr = json_decode((string) $waybill['dest_address'], true) ?: [];
$billTypes = [1 => '原箱转运', 2 => '合箱转运', 3 => '分箱转运'];
?>
<div class="adm-toolbar">
    <a class="btn btn--ghost btn--sm" href="/admin/orders">‹ 返回列表</a>
    <span class="badge badge--<?= Status::waybillTone($status) ?>"><?= View::e($due > 0 ? '待补款' : Status::waybill($status)) ?></span>
    <span class="badge badge--<?= Status::clearTone((int) $waybill['clear_status']) ?>">清关：<?= View::e(Status::clear((int) $waybill['clear_status'])) ?></span>
    <?php if ($due > 0): ?><span class="badge badge--red">待补 ¥<?= number_format($due, 2) ?></span><?php endif; ?>
    <?php if ($waybill['user_no'] !== null): ?>
        <a class="btn btn--ghost btn--sm" style="margin-left:auto"
           href="/admin/users?kw=<?= urlencode((string) $waybill['user_no']) ?>">查看该用户</a>
    <?php endif; ?>
</div>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--blue">运单信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>运单号</span><b class="v-code mono"><?= View::e($waybill['waybill_no']) ?></b></div>
            <div class="hero-address__row"><span>所属用户</span><b><?= View::e((string) ($waybill['user_no'] ?? '—')) ?> · <?= View::e(UserModel::displayName($waybill)) ?></b></div>
            <div class="hero-address__row"><span>转运方式</span><span><?= View::badge($billTypes[(int) $waybill['bill_type']] ?? '—', 'indigo') ?> <?= View::badge((int) $waybill['package_count'] . ' 件', 'cyan') ?></span></div>
            <div class="hero-address__row"><span>物流渠道</span><b><?= View::e((string) ($waybill['channel_name'] ?? '') ?: '—') ?></b></div>
            <div class="hero-address__row"><span>参考时效</span><b><?= View::e((string) ($waybill['aging_desc'] ?? '') ?: '—') ?></b></div>
            <div class="hero-address__row"><span>承运商</span><b><?= View::e((string) $waybill['carrier'] ?: '—') ?></b></div>
            <div class="hero-address__row"><span>承运单号</span><b class="v-code mono"><?= View::e((string) $waybill['carrier_tracking_no'] ?: '出库后生成') ?></b></div>
            <div class="hero-address__row"><span>目的国</span><b class="mono"><?= View::e((string) $waybill['dest_country']) ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--purple">收件信息</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>收件人</span><b><?= View::e((string) $waybill['receiver_name']) ?></b></div>
            <div class="hero-address__row"><span>联系电话</span><b class="v-code mono"><?= View::e((string) $waybill['receiver_phone']) ?></b></div>
            <div class="hero-address__row"><span>州/省</span><b><?= View::e((string) ($addr['state'] ?? '') ?: '—') ?></b></div>
            <div class="hero-address__row"><span>城市</span><b><?= View::e((string) ($addr['city'] ?? '') ?: '—') ?></b></div>
            <div class="hero-address__row"><span>详细地址</span><b><?= View::e((string) ($addr['address1'] ?? '')) ?> <?= View::e((string) ($addr['address2'] ?? '')) ?></b></div>
            <div class="hero-address__row"><span>邮编</span><b class="mono"><?= View::e((string) ($addr['zip_code'] ?? '') ?: '—') ?></b></div>
        </div>
    </section>
</div>

<div class="site-grid">
    <section class="panel">
        <h3 class="section-head"><span class="badge badge--violet">重量与计费</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>实际重量</span><b class="v-measure"><?= number_format((float) $waybill['actual_weight'], 3) ?> kg</b></div>
            <div class="hero-address__row"><span>体积重</span><b class="v-measure"><?= number_format((float) $waybill['volume_weight'], 3) ?> kg</b></div>
            <div class="hero-address__row"><span>计费重量</span><b style="color:#4f46e5"><?= number_format((float) $waybill['billable_weight'], 3) ?> kg</b></div>
            <div class="hero-address__row"><span>打包箱规</span><b class="v-measure"><?= $waybill['pack_length'] !== null
                ? number_format((float) $waybill['pack_length'], 1) . ' × ' . number_format((float) $waybill['pack_width'], 1) . ' × ' . number_format((float) $waybill['pack_height'], 1) . ' cm'
                : '未打包' ?></b></div>
        </div>
    </section>

    <section class="panel">
        <h3 class="section-head"><span class="badge badge--teal">时间轴</span><span class="rule"></span></h3>
        <div class="hero-address">
            <div class="hero-address__row"><span>支付方式</span><b><?= View::e(Status::payMethod((int) $waybill['pay_method'])) ?></b></div>
            <div class="hero-address__row"><span>支付时间</span><b><?= View::e($waybill['paid_at'] ? substr((string) $waybill['paid_at'], 0, 16) : '未支付') ?></b></div>
            <div class="hero-address__row"><span>打包时间</span><b><?= View::e($waybill['packed_at'] ? substr((string) $waybill['packed_at'], 0, 16) : '未打包') ?></b></div>
            <div class="hero-address__row"><span>出库时间</span><b><?= View::e($waybill['outbound_at'] ? substr((string) $waybill['outbound_at'], 0, 16) : '未出库') ?></b></div>
            <div class="hero-address__row"><span>签收时间</span><b><?= View::e($waybill['signed_at'] ? substr((string) $waybill['signed_at'], 0, 16) : '未签收') ?></b></div>
            <div class="hero-address__row"><span>创建时间</span><b><?= View::e(substr((string) $waybill['created_at'], 0, 16)) ?></b></div>
        </div>
    </section>
</div>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--emerald">费用明细</span><span class="rule"></span></h3>
    <div class="hero-address">
        <?php
        // 附加费没有独立列，按下单/打包时的费用快照展示，与用户端口径一致
        $feeRows = [
            '运费' => $waybill['freight_amount'], '燃油附加费' => $waybill['fuel_fee'],
            '偏远附加费' => $waybill['remote_fee'], '敏感货附加费' => $waybill['sensitive_fee'],
            '其他附加费' => $feeDetail['other_surcharge_fee'] ?? 0,
            '增值服务' => $waybill['service_amount'],
            '保价费（保价额 ¥' . number_format((float) $waybill['insurance_amount'], 2) . '）' => $waybill['insurance_fee'],
            '其他费用（含仓储费）' => $waybill['other_fee'],
        ];
        foreach ($feeRows as $label => $amount):
            if ((float) $amount <= 0) { continue; }
            ?>
            <div class="hero-address__row"><span><?= View::e($label) ?></span><b>¥<?= number_format((float) $amount, 2) ?></b></div>
        <?php endforeach; ?>
        <?php if ((float) $waybill['discount_amount'] > 0): ?>
            <div class="hero-address__row"><span>会员折扣</span><b class="v-money">- ¥<?= number_format((float) $waybill['discount_amount'], 2) ?></b></div>
        <?php endif; ?>
        <div class="hero-address__row"><span><b>应付合计</b></span><b style="color:#4f46e5;font-size:19px">¥<?= number_format((float) $waybill['total_amount'], 2) ?></b></div>
    </div>
</section>

<?php if ($services !== []): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--purple">增值服务</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>服务</th><th>单价</th><th>数量</th><th>金额</th><th>状态</th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><?= View::e($s['service_name']) ?></td>
                    <td class="nowrap">¥<?= number_format((float) $s['price'], 2) ?></td>
                    <td><?= (int) $s['qty'] ?></td>
                    <td class="nowrap">¥<?= number_format((float) $s['amount'], 2) ?></td>
                    <td><span class="badge badge--<?= (int) $s['status'] === 1 ? 'green' : 'orange' ?>"><?= (int) $s['status'] === 1 ? '已完成' : '待处理' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--cyan">关联包裹</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>包裹编号</th><th>商品</th><th>数量</th><th>重量</th></tr></thead>
            <tbody>
            <?php if ($packages === []): ?>
                <tr><td colspan="4"><div class="empty-state">无关联包裹记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($packages as $p): ?>
                <tr>
                    <td class="mono"><a href="/admin/packages/<?= (int) $p['id'] ?>"><?= View::e($p['package_no']) ?></a></td>
                    <td><?= View::e($p['item_name']) ?></td>
                    <td><?= (int) $p['item_qty'] ?></td>
                    <td class="nowrap"><?= $p['weight'] !== null ? number_format((float) $p['weight'], 3) . ' kg' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h3 class="section-head"><span class="badge badge--blue">物流轨迹</span><span class="rule"></span>
        <span class="badge badge--gray">共 <?= count($tracking) ?> 条</span>
    </h3>
    <?php if ($tracking === []): ?>
        <div class="empty-state">暂无轨迹记录。出库后由客服在订单运单页录入轨迹推进运输段。</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table">
                <thead><tr><th class="nowrap">时间</th><th>状态</th><th>地点</th><th>来源</th></tr></thead>
                <tbody>
                <?php foreach ($tracking as $t): ?>
                    <tr>
                        <td class="nowrap"><?= View::e(substr((string) $t['event_time'], 0, 16)) ?></td>
                        <td><?= View::e($t['status_desc']) ?></td>
                        <td><?= View::e((string) ($t['location'] ?? '') ?: '—') ?></td>
                        <td><span class="badge badge--gray"><?= (int) $t['source'] === 1 ? '后台录入' : '系统' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($logs !== []): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">状态流转记录</span><span class="rule"></span></h3>
    <ul class="form-hint" style="line-height:2.1;padding-left:18px">
        <?php foreach ($logs as $log): ?>
            <li>
                <?= View::e(substr((string) $log['created_at'], 0, 16)) ?> ·
                <?= View::badge(Status::waybill((int) $log['from_status']), Status::waybillTone((int) $log['from_status'])) ?> → <?= View::badge(Status::waybill((int) $log['to_status']), Status::waybillTone((int) $log['to_status'])) ?>
                <?= View::e((string) ($log['remark'] ?? '')) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (((string) ($waybill['admin_remark'] ?? '')) !== '' || ((string) ($waybill['cancel_reason'] ?? '')) !== ''): ?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--amber">后台备注</span><span class="rule"></span></h3>
    <div class="hero-address">
        <?php if (((string) ($waybill['admin_remark'] ?? '')) !== ''): ?>
            <div class="hero-address__row"><span>交接/作业备注</span><b><?= View::e((string) $waybill['admin_remark']) ?></b></div>
        <?php endif; ?>
        <?php if (((string) ($waybill['cancel_reason'] ?? '')) !== ''): ?>
            <div class="hero-address__row"><span>取消/拦截原因</span><b><?= View::e((string) $waybill['cancel_reason']) ?></b></div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
