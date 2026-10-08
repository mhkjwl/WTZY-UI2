<?php declare(strict_types=1);
/**
 * @file app/Services/PointsService.php
 * @description 积分产出与回收：签收后按实付基础运费登记待激活积分，缓冲期满入账；
 *              退款/退件/退运/销毁时取消未到账并扣回已到账（可负）。异常只记日志不阻断主流程
 * @author AI
 * @version 0.6.1
 * @date 2026-09-16
 */

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Models\PointsModel;
use App\Models\SettingsModel;
use PDO;
use Throwable;

final class PointsService
{
    /**
     * 签收赠送积分：按实付基础运费登记待激活，签收时间 + 缓冲天数后由定时任务入账
     * 同一运单重复触发时由待激活账本唯一键拦截，不会重复发放
     */
    public static function awardForSign(int $waybillId): bool
    {
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT id, user_id, waybill_no, freight_amount, signed_at FROM ' . Database::table('waybills') . '
                 WHERE id = :id AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([':id' => $waybillId]);
            $waybill = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($waybill === false || empty($waybill['signed_at'])) {
                return false;
            }
            $rate = (float) SettingsModel::get('points_per_yuan', '1');
            $base = (float) $waybill['freight_amount'];
            $points = (int) floor($base * $rate);
            if ($rate <= 0 || $points <= 0) {
                return false;
            }
            $delayDays = max(0, (int) SettingsModel::get('points_award_delay_days', '7'));
            $activateAt = date('Y-m-d H:i:s', (int) strtotime((string) $waybill['signed_at']) + $delayDays * 86400);
            return PointsModel::pending(
                (int) $waybill['user_id'],
                $points,
                PointsModel::TYPE_SIGN,
                (string) $waybill['waybill_no'],
                $base,
                $activateAt,
                '签收赠送积分（基础运费 ¥' . number_format($base, 2) . '，T+' . $delayDays . ' 到账）'
            );
        } catch (Throwable $e) {
            Logger::write('points', [
                'event' => 'sign_award_failed',
                'waybill_id' => $waybillId,
                'error' => $e->getMessage(),
            ], 'error');
            return false;
        }
    }

    /**
     * 激活到期的待激活积分（定时任务与懒执行共用）
     */
    public static function activateDue(int $limit = 200): int
    {
        try {
            return PointsModel::activateDue($limit);
        } catch (Throwable $e) {
            Logger::write('points', ['event' => 'activate_failed', 'error' => $e->getMessage()], 'error');
            return 0;
        }
    }

    /**
     * 运单退出（取消/退运/销毁/理赔）：未到账积分作废，已到账积分按退出比例扣回（可负）
     * @return array{canceled: int, clawed: int}
     */
    public static function cancelForWaybill(string $waybillNo, string $reason = '运单退出，积分回收'): array
    {
        $result = ['canceled' => 0, 'clawed' => 0];
        try {
            $result['canceled'] = PointsModel::cancelPending($waybillNo, $reason . '（未到账积分取消）');
            $result['clawed'] = PointsModel::clawback($waybillNo, $reason . '（已到账积分扣回）');
        } catch (Throwable $e) {
            Logger::write('points', [
                'event' => 'cancel_failed',
                'biz_no' => $waybillNo,
                'error' => $e->getMessage(),
            ], 'error');
        }
        return $result;
    }
}