<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/DashboardController.php
 * @description 会员中心工作台：账户概览、包裹与运单动态、待办提示
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Controllers\Member;

use App\Core\Database;
use App\Models\ClaimModel;
use App\Models\CouponModel;
use App\Models\MemberLevelModel;
use App\Models\MessageModel;
use App\Models\PackageModel;
use App\Models\TicketModel;

final class DashboardController extends BaseController
{
    public function index(): never
    {
        $this->requireLogin();
        // 无人访问时任务不跑、有人访问即顺带执行一次（原子限频，默认 10 分钟一次）
        \App\Services\TaskService::lazyRun();
        $userId = $this->userId();
        $pdo = Database::pdo();

        $point = static fn(string $sql): float => (float) $pdo->query($sql)->fetchColumn();
        $balance = $point('SELECT IFNULL(balance, 0) FROM ' . Database::table('user_accounts') . ' WHERE user_id = ' . $userId);
        $frozen = $point('SELECT IFNULL(frozen_amount, 0) FROM ' . Database::table('user_accounts') . ' WHERE user_id = ' . $userId);

        $pkgCounts = PackageModel::statusCounts($userId);
        $inStock = 0;
        $pendingClaim = 0;
        foreach ($pkgCounts as $status => $count) {
            $s = (int) $status;
            if ($s >= PackageModel::IN_STOCK_FROM && $s <= PackageModel::IN_STOCK_TO) {
                $inStock += (int) $count;
            }
            if ($s === 3) {
                $pendingClaim += (int) $count;
            }
        }

        $wbCounts = $this->waybillCounts($userId);
        $onGoing = 0;
        foreach ($wbCounts as $status => $count) {
            if (!in_array((int) $status, [9, 11, 12], true)) {
                $onGoing += (int) $count;
            }
        }

        $this->memberView('member/dashboard', [
            'title'      => '工作台',
            'balance'    => $balance,
            'frozen'     => $frozen,
            'inStock'    => $inStock,
            'pendingClaim' => $pendingClaim,
            'onGoing'    => $onGoing,
            'packageTotal' => array_sum(array_map('intval', $pkgCounts)),
            'waybillTotal' => array_sum(array_map('intval', $wbCounts)),
            'unread'     => MessageModel::unreadCount($userId),
            'couponUsable' => (int) (CouponModel::counts($userId)[0] ?? 0),
            'ticketPending' => $this->ticketPending($userId),
            'claimPending' => (int) (ClaimModel::counts($userId)[0] ?? 0) + (int) (ClaimModel::counts($userId)[1] ?? 0),
            'nextLevel'  => MemberLevelModel::nextLevel((int) $this->user['member_level'], (int) $this->user['points']),
            'pendingPoints' => \App\Models\PointsModel::pendingSum($userId),
            'storage'    => PackageModel::storageCountdown($userId, (int) $this->user['member_level']),
            'recentPackages' => PackageModel::search($userId, '', -1, 5, 0)['rows'],
            'recentWaybills' => $this->recentWaybills($userId),
            'recentMessages' => MessageModel::inbox($userId, 5, 0)['rows'],
            'recentNotices'  => MessageModel::broadcastList(5, 0)['rows'],
        ], 'dashboard', '工作台');
    }

    private function waybillCounts(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('waybills') . '
             WHERE user_id = :uid AND deleted_at IS NULL GROUP BY status'
        );
        $stmt->execute([':uid' => $userId]);
        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'c', 'status');
    }

    private function recentWaybills(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, waybill_no, bill_type, total_amount, currency, status, created_at
             FROM ' . Database::table('waybills') . '
             WHERE user_id = :uid AND deleted_at IS NULL ORDER BY id DESC LIMIT 5'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function ticketPending(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('tickets') . ' WHERE user_id = :uid AND status IN (0, 1)'
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
