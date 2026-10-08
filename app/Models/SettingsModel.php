<?php declare(strict_types=1);
/**
 * @file app/Models/SettingsModel.php
 * @description 系统配置（键值对）数据访问
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class SettingsModel
{
    /** 请求级缓存：全量配置行（保持后台配置页所需的排序，本次请求内复用） */
    private static ?array $rows = null;

    /** 请求级缓存：配置键到值的映射（get 高频使用） */
    private static ?array $map = null;

    /**
     * 全部配置（按配置组分组）
     */
    public static function allGrouped(): array
    {
        $grouped = [];
        foreach (self::rows() as $row) {
            $grouped[$row['group_name']][] = $row;
        }
        return $grouped;
    }

    public static function get(string $key, string $default = ''): string
    {
        $map = self::map();
        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    /**
     * 配置表为小型键值表：整表加载一次换取逐键零查询，写操作后失效重新加载
     */
    private static function rows(): array
    {
        if (self::$rows === null) {
            self::$rows = Database::pdo()->query(
                'SELECT id, setting_key, setting_value, group_name, remark FROM ' . Database::table('settings') . ' ORDER BY group_name ASC, id ASC'
            )->fetchAll(PDO::FETCH_ASSOC);
            self::$map = [];
            foreach (self::$rows as $row) {
                self::$map[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
        }
        return self::$rows;
    }

    private static function map(): array
    {
        if (self::$map === null) {
            self::rows();
        }
        return self::$map;
    }

    /**
     * 写入配置：存在则更新，不存在则新增
     */
    public static function upsert(string $key, string $value, string $group, string $remark = ''): void
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM ' . Database::table('settings') . ' WHERE setting_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        if ($stmt->fetchColumn() !== false) {
            $upd = Database::pdo()->prepare(
                'UPDATE ' . Database::table('settings') . ' SET setting_value = :v WHERE setting_key = :k'
            );
            $upd->execute([':v' => $value, ':k' => $key]);
        } else {
            $ins = Database::pdo()->prepare(
                'INSERT INTO ' . Database::table('settings') . ' (setting_key, setting_value, group_name, remark) VALUES (:k, :v, :g, :r)'
            );
            $ins->execute([':k' => $key, ':v' => $value, ':g' => $group, ':r' => $remark]);
        }
        // 写后失效请求级缓存，保证同请求内后续读取拿到新值
        self::$rows = null;
        self::$map = null;
    }

    /**
     * 汇率列表
     */
    public static function rates(): array
    {
        return Database::pdo()->query(
            'SELECT id, base_currency, quote_currency, rate, source, updated_at FROM ' . Database::table('exchange_rates') . ' ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 保存汇率（同币对存在则更新）
     */
    public static function saveRate(string $base, string $quote, float $rate): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM ' . Database::table('exchange_rates') . ' WHERE base_currency = :b AND quote_currency = :q LIMIT 1'
        );
        $stmt->execute([':b' => $base, ':q' => $quote]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $upd = $pdo->prepare('UPDATE ' . Database::table('exchange_rates') . ' SET rate = :r, source = :s WHERE id = :id');
            $upd->execute([':r' => $rate, ':s' => 'manual', ':id' => (int) $id]);
            return;
        }
        $ins = $pdo->prepare(
            'INSERT INTO ' . Database::table('exchange_rates') . ' (base_currency, quote_currency, rate, source) VALUES (:b, :q, :r, :s)'
        );
        $ins->execute([':b' => $base, ':q' => $quote, ':r' => $rate, ':s' => 'manual']);
    }
}
