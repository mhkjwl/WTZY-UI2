<?php declare(strict_types=1);
/**
 * @file app/Views/member/recharge_list.php
 * @description 会员中心：充值记录表区域（独立片段，供整页与翻页局部刷新共用）
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\RechargeOrderModel;

/**
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var array $config
 */
$expireSeconds = (int) ($config['expire_minutes'] ?? 30) * 60;
?>
<section class="panel">
    <h3 class="section-head"><span class="badge badge--gray">充值记录</span><span class="rule"></span></h3>
    <div class="table-scroll">
        <table class="table">
            <thead><tr><th>充值单号</th><th>金额</th><th>支付方式</th><th>状态</th><th class="nowrap">提交时间</th><th class="nowrap">操作</th></tr></thead>
            <tbody id="rc-body">
            <?php if ($rows === []): ?>
                <tr><td colspan="6"><div class="empty-state">暂无充值记录。</div></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php
                $st = (int) $r['status'];
                // 人工入账渠道（银行转账/后台人工入账）不受支付时效约束，故不渲染倒计时
                $isManualPay = RechargeOrderModel::isManualMethod((int) $r['pay_method']);
                ?>
                <tr data-id="<?= (int) $r['id'] ?>" data-status="<?= $st ?>">
                    <td class="mono"><?= View::e($r['order_no']) ?></td>
                    <td class="nowrap">¥<?= number_format((float) $r['amount'], 2) ?></td>
                    <td><?= View::badge(Status::payMethod((int) $r['pay_method']), 'blue') ?></td>
                    <td>
                        <span class="badge badge--<?= Status::rechargeTone($st) ?>"><?= View::e(Status::recharge($st)) ?></span>
                        <?php if ($st === 0): ?>
                            <?php if ($isManualPay): ?>
                                <br><small class="form-hint">不受支付时效限制</small>
                            <?php else: ?>
                                <br><small class="rc-remain" data-remain="<?= (int) ($r['remain_seconds'] ?? $expireSeconds) ?>"></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
                    <td class="nowrap">
                        <?php if ($st === 0): ?>
                            <button class="btn btn--primary btn--sm" onclick="openPay(<?= (int) $r['id'] ?>, <?= (float) $r['amount'] ?>, <?= (int) $r['pay_method'] ?>, '<?= View::e($r['order_no']) ?>')">去支付</button>
                            <button class="btn btn--ghost btn--sm" onclick="cancelRecharge(<?= (int) $r['id'] ?>, '<?= View::e($r['order_no']) ?>')">取消</button>
                        <?php elseif ($st === 5 && (int) $r['pay_method'] === 5): ?>
                            <span class="msg-item__read">等待入账</span>
                            <button class="btn btn--ghost btn--sm" onclick="openPay(<?= (int) $r['id'] ?>, <?= (float) $r['amount'] ?>, <?= (int) $r['pay_method'] ?>, '<?= View::e($r['order_no']) ?>')">查看进度</button>
                        <?php elseif ($st === 5): ?>
                            <span class="msg-item__read">等待入账</span>
                        <?php else: ?>
                            <span class="msg-item__read">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pager">
            <?php if ($page > 1): ?><a href="javascript:rcPage(<?= $page - 1 ?>)">‹ 上一页</a><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:rcPage(<?= $i ?>)"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="javascript:rcPage(<?= $page + 1 ?>)">下一页 ›</a><?php endif; ?>
            <span class="is-disabled">共 <?= (int) $total ?> 条</span>
        </nav>
    <?php endif; ?>
</section>
