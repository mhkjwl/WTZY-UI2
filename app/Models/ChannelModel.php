<?php declare(strict_types=1);
/**
 * @file app/Models/ChannelModel.php
 * @description 物流渠道、分区、价格与附加费数据访问
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class ChannelModel
{
    /**
     * 渠道检索
     * @return array{rows: array, total: int}
     */
    public static function search(string $keyword, int $limit, int $offset): array
    {
        $where = 'c.deleted_at IS NULL';
        $params = [];
        if ($keyword !== '') {
            $where .= ' AND ' . KeywordFilter::any(['c.name', 'c.channel_code', 'c.carrier'], $params, $keyword);
        }
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('channels') . " c WHERE {$where}");
        $countStmt->execute($params);
        $listStmt = $pdo->prepare(
            'SELECT c.id, c.channel_code, c.name, c.carrier, c.goods_type, c.volume_divisor, c.aging_desc,
                    c.tax_mode, c.status, c.sort,
                    (SELECT COUNT(*) FROM ' . Database::table('channel_zones') . ' z WHERE z.channel_id = c.id) AS zone_count,
                    (SELECT COUNT(DISTINCT p.zone_id) FROM ' . Database::table('channel_prices') . ' p
                      WHERE p.channel_id = c.id AND p.status = 1
                        AND (p.effective_from IS NULL OR p.effective_from <= CURDATE())
                        AND (p.effective_to IS NULL OR p.effective_to >= CURDATE())) AS price_count
             FROM ' . Database::table('channels') . " c WHERE {$where} ORDER BY c.sort ASC, c.id ASC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, channel_code, name, carrier, goods_type, volume_divisor, aging_desc, dest_countries,
                    tax_mode, label_template, track_api_code, status, sort
             FROM ' . Database::table('channels') . ' WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function create(array $d): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('channels') . '
             (channel_code, name, carrier, goods_type, volume_divisor, aging_desc, tax_mode, label_template, track_api_code, status, sort)
             VALUES (:code, :name, :carrier, :goods, :divisor, :aging, :tax, :label, :track, :status, :sort)'
        );
        $stmt->execute([
            ':code' => $d['channel_code'], ':name' => $d['name'], ':carrier' => $d['carrier'],
            ':goods' => $d['goods_type'], ':divisor' => $d['volume_divisor'], ':aging' => $d['aging_desc'],
            ':tax' => $d['tax_mode'], ':label' => $d['label_template'] !== '' ? $d['label_template'] : null,
            ':track' => $d['track_api_code'] !== '' ? $d['track_api_code'] : null,
            ':status' => $d['status'], ':sort' => $d['sort'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('channels') . ' SET
             channel_code = :code, name = :name, carrier = :carrier, goods_type = :goods, volume_divisor = :divisor,
             aging_desc = :aging, tax_mode = :tax, label_template = :label, track_api_code = :track, status = :status, sort = :sort
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':code' => $d['channel_code'], ':name' => $d['name'], ':carrier' => $d['carrier'],
            ':goods' => $d['goods_type'], ':divisor' => $d['volume_divisor'], ':aging' => $d['aging_desc'],
            ':tax' => $d['tax_mode'], ':label' => $d['label_template'] !== '' ? $d['label_template'] : null,
            ':track' => $d['track_api_code'] !== '' ? $d['track_api_code'] : null,
            ':status' => $d['status'], ':sort' => $d['sort'], ':id' => $id,
        ]);
    }

    public static function toggleStatus(int $id): int
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('channels') . ' SET status = 1 - status WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return (int) Database::pdo()->query('SELECT status FROM ' . Database::table('channels') . ' WHERE id = ' . (int) $id)->fetchColumn();
    }

    public static function zones(int $channelId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, zone_name, countries FROM ' . Database::table('channel_zones') . ' WHERE channel_id = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $channelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 正在生效的分区价格：同一分区允许陆续配置多条，只有日期窗口覆盖当天的最新一条参与计费
     * 计费（运费试算）与后台展示共用这一处判定，避免「显示的」和「实际计费的」不是同一条
     */
    public static function priceFor(int $channelId, int $zoneId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM ' . Database::table('channel_prices') . '
             WHERE channel_id = :cid AND zone_id = :zid AND status = 1
               AND (effective_from IS NULL OR effective_from <= CURDATE())
               AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY effective_from DESC, id DESC LIMIT 1'
        );
        $stmt->execute([':cid' => $channelId, ':zid' => $zoneId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 下一个将要生效的价格（生效日还没到）：用于「预约调价」，到生效日当天自动接替当前价格
     */
    public static function nextPrice(int $channelId, int $zoneId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM ' . Database::table('channel_prices') . '
             WHERE channel_id = :cid AND zone_id = :zid AND status = 1 AND effective_from > CURDATE()
             ORDER BY effective_from ASC, id ASC LIMIT 1'
        );
        $stmt->execute([':cid' => $channelId, ':zid' => $zoneId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 渠道详情展示用：每个分区最多两行——当前生效的那条 + 已预约待生效的那条
     * 过期、已被取代的历史价格不出现；某分区一行都没有，说明它当下无法计费
     * @return array<int, array> 每行带 state：active=生效中，scheduled=待生效
     */
    public static function currentPrices(int $channelId): array
    {
        $rows = [];
        foreach (self::zones($channelId) as $zone) {
            $zoneId = (int) $zone['id'];
            $active = self::priceFor($channelId, $zoneId);
            if ($active !== null) {
                $active['zone_name'] = $zone['zone_name'];
                $active['state'] = 'active';
                $rows[] = $active;
            }
            $next = self::nextPrice($channelId, $zoneId);
            if ($next !== null) {
                $next['zone_name'] = $zone['zone_name'];
                $next['state'] = 'scheduled';
                $rows[] = $next;
            }
        }
        return $rows;
    }

    /**
     * 实际参与计费的附加费：停用的既不参与算价也不在渠道详情展示
     */
    public static function activeSurcharges(int $channelId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, fee_type, name, calc_mode, fee_value, condition_params
             FROM ' . Database::table('channel_surcharges') . '
             WHERE channel_id = :cid AND status = 1 ORDER BY fee_type ASC'
        );
        $stmt->execute([':cid' => $channelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 保存分区价格：一个分区最多保留「当前生效」与「已预约」两条，保存即覆盖对应的那条
     * 逐次追加会让「列表里看到的」与「实际计费的」不是同一条，故不再堆积历史价格
     * @param array $d first_weight / first_fee / step_weight / step_fee / effective_from / effective_to
     * @return array{created: bool, before: ?array} before 为被覆盖的原价格，新建时为 null
     */
    public static function priceSave(int $channelId, int $zoneId, array $d): array
    {
        $isFuture = strtotime($d['effective_from']) > strtotime(date('Y-m-d'));
        // 预约调价覆盖已预约的那条；立即生效的调价覆盖当前生效的那条
        // （当下无生效价格时退回覆盖最近一条已到期的，避免与预约记录互相覆盖）
        $before = $isFuture
            ? self::nextPrice($channelId, $zoneId)
            : (self::priceFor($channelId, $zoneId) ?? self::latestPastPrice($channelId, $zoneId));
        $effectiveTo = $d['effective_to'] !== '' ? $d['effective_to'] : null;

        if ($before === null) {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO ' . Database::table('channel_prices') . '
                 (channel_id, zone_id, first_weight, first_fee, step_weight, step_fee, effective_from, effective_to, status)
                 VALUES (:cid, :zid, :fw, :ff, :sw, :sf, :from, :to, 1)'
            );
            $stmt->execute([
                ':cid' => $channelId, ':zid' => $zoneId, ':fw' => $d['first_weight'], ':ff' => $d['first_fee'],
                ':sw' => $d['step_weight'], ':sf' => $d['step_fee'],
                ':from' => $d['effective_from'], ':to' => $effectiveTo,
            ]);
            return ['created' => true, 'before' => null];
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('channel_prices') . '
             SET first_weight = :fw, first_fee = :ff, step_weight = :sw, step_fee = :sf,
                 effective_from = :from, effective_to = :to, status = 1
             WHERE id = :id'
        );
        $stmt->execute([
            ':fw' => $d['first_weight'], ':ff' => $d['first_fee'],
            ':sw' => $d['step_weight'], ':sf' => $d['step_fee'],
            ':from' => $d['effective_from'], ':to' => $effectiveTo, ':id' => (int) $before['id'],
        ]);
        return ['created' => false, 'before' => $before];
    }

    /**
     * 该分区已到生效日期的最近一条价格（不限是否已失效），用于「立即生效」的覆盖保存定位目标行
     */
    private static function latestPastPrice(int $channelId, int $zoneId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM ' . Database::table('channel_prices') . '
             WHERE channel_id = :cid AND zone_id = :zid AND effective_from <= CURDATE()
             ORDER BY effective_from DESC, id DESC LIMIT 1'
        );
        $stmt->execute([':cid' => $channelId, ':zid' => $zoneId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 保存附加费：同一渠道同一费用类型只保留一条，保存即覆盖该类型原有设置
     * 同类型若同时存在多条启用记录，试算会把它们叠加计算，等于对用户重复收费
     * @return array{created: bool, before: ?array}
     */
    public static function surchargeSave(int $channelId, array $d): array
    {
        $pdo = Database::pdo();
        $find = $pdo->prepare(
            'SELECT id, fee_type, name, calc_mode, fee_value FROM ' . Database::table('channel_surcharges') . '
             WHERE channel_id = :cid AND fee_type = :t ORDER BY id DESC LIMIT 1'
        );
        $find->execute([':cid' => $channelId, ':t' => $d['fee_type']]);
        $before = $find->fetch(PDO::FETCH_ASSOC);

        if ($before === false) {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . Database::table('channel_surcharges') . '
                 (channel_id, fee_type, name, calc_mode, fee_value, condition_params, status)
                 VALUES (:cid, :type, :name, :mode, :value, :conds, 1)'
            );
            $stmt->execute([
                ':cid' => $channelId, ':type' => $d['fee_type'], ':name' => $d['name'],
                ':mode' => $d['calc_mode'], ':value' => $d['fee_value'], ':conds' => null,
            ]);
            return ['created' => true, 'before' => null];
        }

        // 同类型此前若被停用，这里一并置回启用，避免「保存了却不生效」
        $stmt = $pdo->prepare(
            'UPDATE ' . Database::table('channel_surcharges') . '
             SET name = :name, calc_mode = :mode, fee_value = :value, status = 1
             WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $d['name'], ':mode' => $d['calc_mode'],
            ':value' => $d['fee_value'], ':id' => (int) $before['id'],
        ]);
        return ['created' => false, 'before' => $before];
    }

    public static function zoneCreate(int $channelId, string $zoneName, array $countries): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('channel_zones') . ' (channel_id, zone_name, countries) VALUES (:cid, :name, :countries)'
        );
        $stmt->execute([':cid' => $channelId, ':name' => $zoneName, ':countries' => json_encode(array_values($countries), JSON_UNESCAPED_UNICODE)]);
    }
}
