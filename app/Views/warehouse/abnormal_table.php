<?php declare(strict_types=1);
/**
 * @file app/Views/warehouse/abnormal_table.php
 * @description 待处理异常列表局部渲染（含用户处置申请的标识）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-15
 */

use App\Core\View;
use App\Enums\Status;
use App\Models\UserModel;

/**
 * @var array  $rows
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var string $keyword
 * @var array  $perms
 */
$qs = static fn(int $p): string => '/warehouse/abnormal?' . http_build_query(array_filter(['kw' => $keyword, 'page' => $p]));
$userDisposals = [6, 7];
?>
<div class="table-scroll">
<table class="table">
    <thead>
    <tr>
        <th>包裹编号</th><th>用户</th><th>异常类型</th><th>异常说明</th><th>异常照片</th><th>来源</th>
        <th>单据状态</th><th>包裹状态</th><th class="nowrap">登记时间</th><th class="nowrap">操作</th>
    </tr>
    </thead>
    <tbody>
    <?php if ($rows === []): ?>
        <tr><td colspan="10"><div class="empty-state">暂无待处理异常。仓库登记或用户提交处置申请后会出现在这里。</div></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <?php
        $type = (int) $r['abnormal_type'];
        $fromUser = in_array($type, $userDisposals, true);
        $ps = (int) $r['package_status'];
        $st = (int) $r['status'];
        // 异常照片以 JSON 数组字符串落库，解析后渲染缩略图；异常内容可能被历史数据写成非法 JSON，故做容错
        $images = [];
        if (!empty($r['images'])) {
            $decoded = json_decode((string) $r['images'], true);
            if (is_array($decoded)) {
                $images = array_values(array_filter($decoded, 'is_string'));
            }
        }
        ?>
        <tr data-id="<?= (int) $r['id'] ?>">
            <td class="mono"><?= View::e($r['package_no']) ?><br><small><?= View::e($r['tracking_no'] ?: '—') ?></small></td>
            <td>
                <?php if ((int) $r['user_id'] === 0): ?>
                    <?= View::badge('无主件', 'orange') ?>
                <?php else: ?>
                    <?= View::e((string) $r['user_no']) ?><br><small><?= View::e(UserModel::displayName($r)) ?></small>
                <?php endif; ?>
            </td>
            <td><?= View::badge(Status::ABNORMAL_TYPE[$type] ?? '其他', $fromUser ? 'violet' : Status::abnormalTone($type)) ?></td>
            <td><?= View::e($r['description']) ?></td>
            <td class="nowrap">
                <?php if ($images === []): ?>
                    <span class="form-hint">—</span>
                <?php else: ?>
                    <?php foreach (array_slice($images, 0, 3) as $img): ?>
                        <img class="zoomable" src="<?= View::e($img) ?>" data-zoom="<?= View::e($img) ?>" alt="异常照片"
                             style="width:46px;height:46px;object-fit:cover;border-radius:6px;margin-right:4px">
                    <?php endforeach; ?>
                    <?php if (count($images) > 3): ?><small class="form-hint">+<?= count($images) - 3 ?></small><?php endif; ?>
                <?php endif; ?>
            </td>
            <td><?= $fromUser ? View::badge('用户申请', 'violet') : View::badge('仓库登记', 'amber') ?></td>
            <td><span class="badge badge--<?= Status::abnormalStatusTone($st) ?>"><?= View::e(Status::ABNORMAL_STATUS[$st] ?? '处理中') ?></span></td>
            <td><?= View::badge(Status::package($ps), Status::packageTone($ps)) ?></td>
            <td class="nowrap"><?= View::e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td class="nowrap">
                <?php if ($st === 0 && !empty($perms['wh.abnormal'])): ?>
                    <button class="btn btn--primary btn--sm"
                            onclick="openHandle(<?= (int) $r['id'] ?>, '<?= View::e(Status::ABNORMAL_TYPE[$type] ?? '其他') ?>', <?= View::e(json_encode((string) $r['description'], JSON_UNESCAPED_UNICODE)) ?>, <?= View::e(json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>)">处理</button>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if ($pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a href="javascript:admTable('<?= View::e($qs($page - 1)) ?>')">‹ 上一页</a><?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <a class="<?= $i === $page ? 'is-current' : '' ?>" href="javascript:admTable('<?= View::e($qs($i)) ?>')"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="javascript:admTable('<?= View::e($qs($page + 1)) ?>')">下一页 ›</a><?php endif; ?>
        <span class="is-disabled">共 <?= (int) $total ?> 条</span>
    </nav>
<?php endif; ?>
