<?php declare(strict_types=1);
/**
 * @file app/Models/MemberLevelModel.php
 * @description 会员等级配置与等级权益读取
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class MemberLevelModel
{
    private static ?array $cache = null;

    /**
     * 全部等级（按 level 升序）
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        return self::$cache = Database::pdo()->query(
            'SELECT level, name, min_points, discount, extra_storage_days, remark
             FROM ' . Database::table('member_levels') . ' ORDER BY level ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function nameFor(int $level): string
    {
        foreach (self::all() as $row) {
            if ((int) $row['level'] === $level) {
                return (string) $row['name'];
            }
        }
        return '普通用户';
    }

    public static function find(int $level): ?array
    {
        foreach (self::all() as $row) {
            if ((int) $row['level'] === $level) {
                return $row;
            }
        }
        return null;
    }

    /**
     * 按累计积分推算应处等级（升级用；降级不自动发生，由后台人工处理）
     */
    public static function levelForPoints(int $points): int
    {
        $level = 1;
        foreach (self::all() as $row) {
            if ($points >= (int) $row['min_points']) {
                $level = max($level, (int) $row['level']);
            }
        }
        return $level;
    }

    /**
     * 距离下一等级还差多少积分；已是最高等级返回 null
     * @return array{level: int, name: string, need: int}|null
     */
    public static function nextLevel(int $currentLevel, int $points): ?array
    {
        foreach (self::all() as $row) {
            if ((int) $row['level'] === $currentLevel + 1) {
                return [
                    'level' => (int) $row['level'],
                    'name'  => (string) $row['name'],
                    'need'  => max(0, (int) $row['min_points'] - $points),
                ];
            }
        }
        return null;
    }

    /**
     * 会员等级按积分修正（幂等，只升不降）：等级低于当前积分应得等级的账号提升到位，
     * 修正历史脏数据（如 member_level=0）与等级配置调整后未跟上的存量用户
     */
    public static function backfillLevels(): int
    {
        $pdo = Database::pdo();
        $desired = 'IFNULL((SELECT MAX(level) FROM ' . Database::table('member_levels')
            . ' WHERE min_points <= users.points), 1)';
        $stmt = $pdo->prepare(
            'UPDATE ' . Database::table('users') . ' AS users'
            . " SET users.member_level = {$desired}"
            . " WHERE users.deleted_at IS NULL AND users.member_level < {$desired}"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
