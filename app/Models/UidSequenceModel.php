<?php declare(strict_types=1);
/**
 * @file app/Models/UidSequenceModel.php
 * @description 对外唯一编号的并发取号（注册分配 UID 等场景）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-12
 */

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;

final class UidSequenceModel
{
    /** 序列元信息在一次请求内不变，取到后缓存，避免每次取号都多查一次 */
    private static array $meta = [];

    /**
     * 原子分配一个编号并格式化返回（如 U100001）
     *
     * 并发安全依赖两点：
     *   1. 取号写成单条 UPDATE（last_value = last_value + 1），InnoDB 对该行加排他锁，
     *      并发请求在此排队，各拿各的值，不依赖调用方是否包裹事务；
     *   2. LAST_INSERT_ID(expr) 记录在连接上，同一连接紧接着读回的一定是自己刚取的值。
     * 因此不使用自增主键派生，也不需要用空值占位再回填。
     */
    public static function allocate(string $name): string
    {
        $meta = self::meta($name);
        $pdo = Database::pdo();

        $update = $pdo->prepare(
            'UPDATE ' . Database::table('uid_sequences') . '
             SET last_value = LAST_INSERT_ID(last_value + 1) WHERE name = :name'
        );
        $update->execute([':name' => $name]);
        if ($update->rowCount() === 0) {
            throw new RuntimeException('编号序列未配置：' . $name);
        }

        $value = (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
        return $meta['prefix'] . str_pad((string) $value, $meta['min_digits'], '0', STR_PAD_LEFT);
    }

    /**
     * @return array{prefix: string, min_digits: int}
     */
    private static function meta(string $name): array
    {
        if (isset(self::$meta[$name])) {
            return self::$meta[$name];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT prefix, min_digits FROM ' . Database::table('uid_sequences') . ' WHERE name = :name LIMIT 1'
        );
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('编号序列未配置：' . $name);
        }
        return self::$meta[$name] = [
            'prefix'     => (string) $row['prefix'],
            'min_digits' => (int) $row['min_digits'],
        ];
    }
}
