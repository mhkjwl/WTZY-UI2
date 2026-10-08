<?php declare(strict_types=1);
/**
 * @file app/Views/member/flows.php
 * @description 会员中心：资金流水（按类型筛选 + 局部表）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

use App\Core\View;
use App\Enums\Status;

/**
 * @var array $account
 * @var array $rows
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 * @var int   $flowType
 */
$income = 0.0;
$expense = 0.0;
foreach ($rows as $r) {
    if ((int) $r['direction'] === 1) {
        $income += (float) $r['amount'];
    } else {
        $expense += (float) $r['amount'];
    }
}
$initialUrl = '/user/finance/flows?' . http_build_query(array_filter(['type' => $flowType, 'page' => $page], static fn($v): bool => $v !== '' && $v !== -1));
?>
<section class="astat-grid">
    <div class="astat astat--blue"><div class="astat__icon">💰</div>
        <div><div class="astat__value">¥<?= number_format((float) $account['balance'], 2) ?></div><div class="astat__label">当前余额</div></div></div>
    <div class="astat astat--emerald"><div class="astat__icon">⬆️</div>
        <div><div class="astat__value">¥<?= number_format($income, 2) ?></div><div class="astat__label">本页收入</div></div></div>
    <div class="astat astat--amber"><div class="astat__icon">⬇️</div>
        <div><div class="astat__value">¥<?= number_format($expense, 2) ?></div><div class="astat__label">本页支出</div></div></div>
    <div class="astat astat--violet"><div class="astat__icon">📄</div>
        <div><div class="astat__value"><?= (int) $total ?></div><div class="astat__label">流水条数</div></div></div>
</section>

<section class="panel">
    <div class="adm-toolbar">
        <select class="form-input" id="f-type">
            <option value="-1">全部类型</option>
            <?php foreach (Status::FLOW_TYPE as $code => $label): ?>
                <option value="<?= $code ?>" <?= $flowType === $code ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn--primary" onclick="admSearch('/user/finance/flows', {type: val('f-type')})">筛选</button>
        <a class="btn btn--ghost" style="margin-left:auto" href="/user/finance/recharge">去充值</a>
    </div>
    <div class="table-wrap" data-table-wrap data-url="<?= View::e($initialUrl) ?>">
        <?= View::partial('member/flows_table', get_defined_vars()) ?>
    </div>
</section>
