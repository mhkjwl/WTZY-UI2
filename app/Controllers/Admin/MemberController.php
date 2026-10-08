<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/MemberController.php
 * @description 后台会员与营销：会员等级权益、优惠券管理发放、邀请返利设置
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\StaffAuth;
use App\Core\Request;
use App\Models\MemberLevelModel;
use App\Models\SettingsModel;
use App\Models\UserModel;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class MemberController extends BaseController
{
    /** 邀请返利配置键与默认值 */
    private const INVITE_KEYS = [
        'invite_enabled'          => '1',
        'invite_reward_type'      => '1',
        'invite_reward_inviter'   => '200',
        'invite_reward_invitee'   => '100',
    ];

    /* ================= 会员等级权益 ================= */

    public function levels(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->adminView('admin/member_levels', [
            'title'  => '会员等级',
            'levels' => MemberLevelModel::all(),
            'dist'   => $this->levelDistribution(),
        ], 'levels', '会员等级');
    }

    /**
     * 保存等级权益（按 level 覆盖写入；等级为业务主键，不新增不删除）
     */
    public function saveLevel(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->verifyCsrf();

        $level = (int) Request::input('level', 0);
        $name = trim((string) Request::input('name', ''));
        $minPoints = (int) Request::input('min_points', 0);
        $discount = round((float) Request::input('discount', 1), 2);
        $extraDays = (int) Request::input('extra_storage_days', 0);
        $remark = trim((string) Request::input('remark', ''));

        if ($level < 1 || $level > 10) {
            $this->fail('无效的等级');
        }
        if ($name === '' || mb_strlen($name) > 30) {
            $this->fail('等级名称需为 1~30 字');
        }
        if ($minPoints < 0) {
            $this->fail('所需积分不能为负');
        }
        if ($discount <= 0 || $discount > 1) {
            $this->fail('运费折扣需在 0.01~1.00 之间（1.00 表示不打折）');
        }
        if ($extraDays < 0 || $extraDays > 365) {
            $this->fail('额外免仓储天数需在 0~365 之间');
        }

        // 等级门槛必须随等级递增，否则升级逻辑会失效
        foreach (MemberLevelModel::all() as $row) {
            $other = (int) $row['level'];
            if ($other === $level) {
                continue;
            }
            if ($other < $level && (int) $row['min_points'] > $minPoints) {
                $this->fail('所需积分必须高于 Lv' . $other . '（当前 ' . (int) $row['min_points'] . '）');
            }
            if ($other > $level && (int) $row['min_points'] < $minPoints) {
                $this->fail('所需积分必须低于 Lv' . $other . '（当前 ' . (int) $row['min_points'] . '）');
            }
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('member_levels') . '
             SET name = :n, min_points = :p, discount = :d, extra_storage_days = :e, remark = :r
             WHERE level = :l'
        );
        $stmt->execute([
            ':n' => $name, ':p' => $minPoints, ':d' => $discount,
            ':e' => $extraDays, ':r' => $remark !== '' ? $remark : null, ':l' => $level,
        ]);
        if ($stmt->rowCount() === 0) {
            $exists = Database::pdo()->prepare('SELECT COUNT(*) FROM ' . Database::table('member_levels') . ' WHERE level = :l');
            $exists->execute([':l' => $level]);
            if ((int) $exists->fetchColumn() === 0) {
                $this->fail('等级不存在');
            }
        }

        // 等级门槛变化后，按累计积分重算受影响用户的等级（只升不降）
        $this->recalcLevels();
        $this->ok(null, '等级权益已保存，符合条件的用户已同步升级');
    }

    /**
     * 按新门槛重算用户等级（只升不降，避免降级引发客诉）
     * 以「每等级一条集合更新」替代逐用户循环，用户量增长后不再随行数放大写操作
     */
    private function recalcLevels(): void
    {
        $pdo = Database::pdo();
        foreach (MemberLevelModel::all() as $level) {
            $pdo->prepare(
                'UPDATE ' . Database::table('users') . '
                 SET member_level = :target
                 WHERE deleted_at IS NULL AND member_level < :current AND points >= :min_points'
            )->execute([
                ':target'     => (int) $level['level'],
                ':current'    => (int) $level['level'],
                ':min_points' => (int) $level['min_points'],
            ]);
        }
    }

    private function levelDistribution(): array
    {
        $rows = Database::pdo()->query(
            'SELECT member_level, COUNT(*) AS c FROM ' . Database::table('users') . '
             WHERE deleted_at IS NULL GROUP BY member_level'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'member_level');
    }

    /* ================= 优惠券 ================= */

    public function coupons(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->renderCoupons();
    }

    private function renderCoupons(): void
    {
        $pdo = Database::pdo();
        $rows = $pdo->query(
            'SELECT c.*, (SELECT COUNT(*) FROM ' . Database::table('user_coupons') . ' uc WHERE uc.coupon_id = c.id) AS granted
             FROM ' . Database::table('coupons') . ' c ORDER BY c.id DESC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->adminView('admin/member_coupons', [
            'title'   => '优惠券管理',
            'rows'    => $rows,
            'types'   => [1 => '满减券', 2 => '折扣券', 3 => '运费券'],
            'stats'   => $this->couponStats(),
        ], 'coupons', '优惠券管理');
    }

    private function couponStats(): array
    {
        $pdo = Database::pdo();
        return [
            'total'   => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('coupons'))->fetchColumn(),
            'active'  => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('coupons') . ' WHERE status = 1')->fetchColumn(),
            'granted' => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('user_coupons'))->fetchColumn(),
            'used'    => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('user_coupons') . ' WHERE status = 1')->fetchColumn(),
        ];
    }

    /**
     * 优惠券列表局部渲染（保存/启停/发放后区域替换）
     */
    public function couponList(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $pdo = Database::pdo();
        $this->renderTable('admin/member_coupons_table', [
            'rows' => $pdo->query(
                'SELECT c.*, (SELECT COUNT(*) FROM ' . Database::table('user_coupons') . ' uc WHERE uc.coupon_id = c.id) AS granted
                 FROM ' . Database::table('coupons') . ' c ORDER BY c.id DESC LIMIT 100'
            )->fetchAll(PDO::FETCH_ASSOC),
            'types' => [1 => '满减券', 2 => '折扣券', 3 => '运费券'],
        ]);
    }

    /**
     * 新建或修改优惠券
     */
    public function saveCoupon(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $code = strtoupper(trim((string) Request::input('code', '')));
        $name = trim((string) Request::input('name', ''));
        $type = (int) Request::input('type', 1);
        $value = round((float) Request::input('value', 0), 2);
        $minAmount = round((float) Request::input('min_amount', 0), 2);
        $totalQty = (int) Request::input('total_qty', 0);
        $perUser = max(1, (int) Request::input('per_user_limit', 1));
        $startAt = trim((string) Request::input('start_at', ''));
        $endAt = trim((string) Request::input('end_at', ''));
        $status = (int) Request::input('status', 1) === 1 ? 1 : 0;

        if (preg_match('/^[A-Z0-9]{4,32}$/', $code) !== 1) {
            $this->fail('券码需为 4~32 位大写字母或数字');
        }
        if ($name === '' || mb_strlen($name) > 60) {
            $this->fail('券名称需为 1~60 字');
        }
        if (!in_array($type, [1, 2, 3], true)) {
            $this->fail('无效的券类型');
        }
        if ((int) $type === 2) {
            if ($value <= 0 || $value >= 1) {
                $this->fail('折扣券面额需为 0.01~0.99 之间的小数（如 0.90 表示 9 折）');
            }
        } elseif ($value <= 0) {
            $this->fail('券面额需大于 0');
        }
        if ($minAmount < 0) {
            $this->fail('使用门槛不能为负');
        }
        if ($totalQty < 0) {
            $this->fail('发行量不能为负（0 表示不限量）');
        }
        foreach (['开始' => $startAt, '结束' => $endAt] as $label => $date) {
            if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                $this->fail($label . '日期格式应为 YYYY-MM-DD');
            }
        }
        if ($startAt !== '' && $endAt !== '' && strtotime($startAt) > strtotime($endAt)) {
            $this->fail('结束日期不能早于开始日期');
        }

        $pdo = Database::pdo();
        $dup = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('coupons') . ' WHERE code = :c AND id <> :id');
        $dup->execute([':c' => $code, ':id' => $id]);
        if ((int) $dup->fetchColumn() > 0) {
            $this->fail('券码已存在，请更换');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE ' . Database::table('coupons') . '
                 SET code = :c, name = :n, type = :t, value = :v, min_amount = :m, total_qty = :q,
                     per_user_limit = :p, start_at = :sa, end_at = :ea, status = :s
                 WHERE id = :id'
            );
            $stmt->execute([
                ':c' => $code, ':n' => $name, ':t' => $type, ':v' => $value, ':m' => $minAmount,
                ':q' => $totalQty, ':p' => $perUser,
                ':sa' => $startAt !== '' ? $startAt . ' 00:00:00' : null,
                ':ea' => $endAt !== '' ? $endAt . ' 23:59:59' : null,
                ':s' => $status, ':id' => $id,
            ]);
            $this->ok(null, '优惠券已更新');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('coupons') . '
             (code, name, type, value, min_amount, total_qty, per_user_limit, start_at, end_at, status)
             VALUES (:c, :n, :t, :v, :m, :q, :p, :sa, :ea, :s)'
        );
        $stmt->execute([
            ':c' => $code, ':n' => $name, ':t' => $type, ':v' => $value, ':m' => $minAmount,
            ':q' => $totalQty, ':p' => $perUser,
            ':sa' => $startAt !== '' ? $startAt . ' 00:00:00' : null,
            ':ea' => $endAt !== '' ? $endAt . ' 23:59:59' : null,
            ':s' => $status,
        ]);
        $this->ok(['id' => (int) $pdo->lastInsertId()], '优惠券已创建');
    }

    public function toggleCoupon(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->verifyCsrf();
        $stmt = Database::pdo()->prepare('UPDATE ' . Database::table('coupons') . ' SET status = 1 - status WHERE id = :id');
        $stmt->execute([':id' => (int) Request::input('id', 0)]);
        $this->ok(null, '优惠券状态已切换');
    }

    /**
     * 发放优惠券：按用户编号定向发放，或按条件批量发放
     */
    public function grantCoupon(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->verifyCsrf();

        $couponId = (int) Request::input('coupon_id', 0);
        $scope = (string) Request::input('scope', 'user');
        $userNo = trim((string) Request::input('user_no', ''));
        $level = (int) Request::input('level', 0);
        $qtyPerUser = max(1, (int) Request::input('qty', 1));

        $pdo = Database::pdo();
        $coupon = $pdo->prepare('SELECT id, name, total_qty, used_qty FROM ' . Database::table('coupons') . ' WHERE id = :id');
        $coupon->execute([':id' => $couponId]);
        $row = $coupon->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            $this->fail('优惠券不存在');
        }

        $userIds = [];
        if ($scope === 'user') {
            $user = UserModel::findByUsername($userNo);
            if ($user === null) {
                // 允许用用户编号发放
                $stmt = $pdo->prepare('SELECT id FROM ' . Database::table('users') . ' WHERE user_no = :no AND deleted_at IS NULL LIMIT 1');
                $stmt->execute([':no' => $userNo]);
                $found = $stmt->fetchColumn();
                if ($found === false) {
                    $this->fail('未找到该用户，请确认用户名或用户编号');
                }
                $userIds = [(int) $found];
            } else {
                $userIds = [(int) $user['id']];
            }
        } elseif ($scope === 'level') {
            if ($level < 1 || $level > 10) {
                $this->fail('请选择有效的会员等级');
            }
            $stmt = $pdo->prepare('SELECT id FROM ' . Database::table('users') . ' WHERE deleted_at IS NULL AND member_level = :l AND status = 1');
            $stmt->execute([':l' => $level]);
            $userIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } elseif ($scope === 'all') {
            $userIds = array_map('intval', $pdo->query(
                'SELECT id FROM ' . Database::table('users') . ' WHERE deleted_at IS NULL AND status = 1'
            )->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $this->fail('无效的发放范围');
        }

        if ($userIds === []) {
            $this->fail('发放范围内没有符合条件的用户');
        }

        $granted = 0;
        $pdo->beginTransaction();
        try {
            foreach ($userIds as $uid) {
                for ($i = 0; $i < $qtyPerUser; $i++) {
                    $pdo->prepare('INSERT INTO ' . Database::table('user_coupons') . ' (user_id, coupon_id) VALUES (:u, :c)')
                        ->execute([':u' => $uid, ':c' => $couponId]);
                    $granted++;
                }
            }
            $pdo->prepare('UPDATE ' . Database::table('coupons') . ' SET used_qty = used_qty + :n WHERE id = :id')
                ->execute([':n' => $granted, ':id' => $couponId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $this->ok(['granted' => $granted], '已发放 ' . $granted . ' 张优惠券给 ' . count($userIds) . ' 位用户');
    }

    /* ================= 邀请返利设置 ================= */

    public function invite(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $config = [];
        foreach (self::INVITE_KEYS as $key => $default) {
            $config[$key] = SettingsModel::get($key, $default);
        }
        $pdo = Database::pdo();
        $this->adminView('admin/member_invite', [
            'title'     => '邀请设置',
            'config'    => $config,
            'invited'   => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('users') . ' WHERE inviter_id IS NOT NULL AND deleted_at IS NULL')->fetchColumn(),
            'rewards'   => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('invite_rewards'))->fetchColumn(),
            'pending'   => (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('invite_rewards') . ' WHERE status = 0')->fetchColumn(),
            'topInviter' => $pdo->query(
                'SELECT u.nickname, u.user_no, COUNT(r.id) AS c
                 FROM ' . Database::table('invite_rewards') . ' r
                 JOIN ' . Database::table('users') . ' u ON u.id = r.inviter_id
                 GROUP BY r.inviter_id ORDER BY c DESC LIMIT 5'
            )->fetchAll(PDO::FETCH_ASSOC),
        ], 'invite', '邀请设置');
    }

    public function saveInvite(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->verifyCsrf();

        $enabled = (int) Request::input('invite_enabled', 0) === 1 ? '1' : '0';
        $rewardType = (int) Request::input('invite_reward_type', 1);
        $inviter = (int) Request::input('invite_reward_inviter', 0);
        $invitee = (int) Request::input('invite_reward_invitee', 0);

        if (!in_array($rewardType, [1, 2], true)) {
            $this->fail('无效的奖励形式');
        }
        if ($inviter < 0 || $inviter > 100000) {
            $this->fail('邀请人奖励需在 0~100000 之间');
        }
        if ($invitee < 0 || $invitee > 100000) {
            $this->fail('被邀请人奖励需在 0~100000 之间');
        }
        if ($enabled === '1' && $inviter === 0 && $invitee === 0) {
            $this->fail('开启邀请功能时，至少给一方设置大于 0 的奖励');
        }

        SettingsModel::upsert('invite_enabled', $enabled, 'invite', '邀请功能开关');
        SettingsModel::upsert('invite_reward_type', (string) $rewardType, 'invite', '奖励形式 1积分 2余额');
        SettingsModel::upsert('invite_reward_inviter', (string) $inviter, 'invite', '邀请人奖励');
        SettingsModel::upsert('invite_reward_invitee', (string) $invitee, 'invite', '被邀请人奖励');
        $this->ok(null, '邀请设置已保存');
    }

    /**
     * 手动发放待发放的邀请返利
     */
    public function payInviteRewards(): never
    {
        $this->requireStaff();
        $this->enforce('member.manage');
        $this->verifyCsrf();

        $pdo = Database::pdo();
        $rows = $pdo->query(
            'SELECT id, inviter_id, reward_type, amount, points FROM ' . Database::table('invite_rewards') . '
             WHERE status = 0 ORDER BY id ASC LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            $this->fail('没有待发放的返利记录');
        }

        $ok = 0;
        foreach ($rows as $row) {
            try {
                if ((int) $row['reward_type'] === 2 && (float) $row['amount'] > 0) {
                    \App\Models\FundFlowModel::record(
                        (int) $row['inviter_id'], 8, 1, (float) $row['amount'], 4,
                        'INV' . $row['id'], StaffAuth::id(), '邀请返利发放'
                    );
                } elseif ((int) $row['reward_type'] === 1 && (int) $row['points'] > 0) {
                    \App\Models\PointsModel::change(
                        (int) $row['inviter_id'], (int) $row['points'],
                        \App\Models\PointsModel::TYPE_INVITE, 'INV' . $row['id'], '邀请返利发放'
                    );
                }
                $pdo->prepare('UPDATE ' . Database::table('invite_rewards') . ' SET status = 1 WHERE id = :id')
                    ->execute([':id' => (int) $row['id']]);
                $ok++;
            } catch (InvalidArgumentException | RuntimeException $e) {
                // 单条失败不影响其余记录，保留待发放状态由人工排查
                continue;
            }
        }
        $this->ok(['paid' => $ok], '已发放 ' . $ok . ' 条返利');
    }
}
