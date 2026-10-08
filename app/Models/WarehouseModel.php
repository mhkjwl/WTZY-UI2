<?php declare(strict_types=1);
/**
 * @file app/Models/WarehouseModel.php
 * @description 仓库与库位数据访问
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class WarehouseModel
{
    /**
     * 注册分配使用：取排序最前的启用仓库作为默认收货仓
     */
    public static function findFirstActive(): ?array
    {
        $stmt = Database::pdo()->query(
            'SELECT id, code, country, state, city, address, zip_code, contact_phone, name
             FROM ' . Database::table('warehouses') . ' WHERE status = 1 AND deleted_at IS NULL ORDER BY sort ASC, id ASC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 会员端快捷入口：按国家聚合的仓库分布（有任一启用仓即为运营中，含停用国家供用户知情）
     */
    public static function publicCountries(): array
    {
        return Database::pdo()->query(
            'SELECT country, MAX(status) AS status FROM ' . Database::table('warehouses')
            . ' WHERE deleted_at IS NULL GROUP BY country ORDER BY MIN(sort) ASC, MIN(id) ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 仓库列表（含在库包裹计数概览）
     */
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT w.id, w.warehouse_no, w.name, w.code, w.country, w.state, w.city, w.address, w.zip_code,
                    w.contact_phone, w.timezone, w.free_storage_days, w.status, w.sort,
                    (SELECT COUNT(*) FROM ' . Database::table('warehouse_locations') . ' l WHERE l.warehouse_id = w.id AND l.status = 2) AS occupied,
                    (SELECT COUNT(*) FROM ' . Database::table('warehouse_locations') . ' l WHERE l.warehouse_id = w.id) AS location_total
             FROM ' . Database::table('warehouses') . ' w WHERE w.deleted_at IS NULL ORDER BY w.sort ASC, w.id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, warehouse_no, name, code, country, state, city, address, zip_code, contact_phone, timezone,
                    free_storage_days, status, sort FROM ' . Database::table('warehouses') . ' WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function create(array $d): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('warehouses') . '
             (warehouse_no, name, code, country, state, city, address, zip_code, contact_phone, timezone, free_storage_days, status, sort)
             VALUES (:no, :name, :code, :country, :state, :city, :address, :zip, :phone, :tz, :free_days, :status, :sort)'
        );
        $stmt->execute([
            ':no' => $d['warehouse_no'], ':name' => $d['name'], ':code' => $d['code'], ':country' => $d['country'],
            ':state' => $d['state'], ':city' => $d['city'], ':address' => $d['address'], ':zip' => $d['zip_code'],
            ':phone' => $d['contact_phone'], ':tz' => $d['timezone'], ':free_days' => $d['free_storage_days'],
            ':status' => $d['status'], ':sort' => $d['sort'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('warehouses') . ' SET
             name = :name, code = :code, country = :country, state = :state, city = :city, address = :address,
             zip_code = :zip, contact_phone = :phone, timezone = :tz, free_storage_days = :free_days, status = :status, sort = :sort
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':name' => $d['name'], ':code' => $d['code'], ':country' => $d['country'], ':state' => $d['state'],
            ':city' => $d['city'], ':address' => $d['address'], ':zip' => $d['zip_code'], ':phone' => $d['contact_phone'],
            ':tz' => $d['timezone'], ':free_days' => $d['free_storage_days'], ':status' => $d['status'], ':sort' => $d['sort'],
            ':id' => $id,
        ]);
    }

    public static function toggleStatus(int $id): int
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('warehouses') . ' SET status = 1 - status WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return (int) Database::pdo()->query('SELECT status FROM ' . Database::table('warehouses') . ' WHERE id = ' . (int) $id)->fetchColumn();
    }

    /**
     * 启用仓库中的最大免费仓储天数（官网对外承诺按最宽松仓库口径展示）
     * 无启用仓库时返回 null，由调用方决定回落值
     */
    public static function maxFreeStorageDays(): ?int
    {
        $v = Database::pdo()->query(
            'SELECT MAX(free_storage_days) FROM ' . Database::table('warehouses') . ' WHERE status = 1 AND deleted_at IS NULL'
        )->fetchColumn();
        return $v === null ? null : (int) $v;
    }

    /**
     * 库位列表（可按仓库过滤）
     * 关联占用包裹，供列表直接展示编号/商品与详情弹窗使用（避免逐行再查）
     * @return array{rows: array, total: int}
     */
    public static function locationList(int $warehouseId, int $limit, int $offset): array
    {
        $where = $warehouseId > 0 ? 'WHERE l.warehouse_id = ' . $warehouseId : '';
        $pdo = Database::pdo();
        $total = (int) $pdo->query(
            'SELECT COUNT(*) FROM ' . Database::table('warehouse_locations') . " l {$where}"
        )->fetchColumn();
        $rows = $pdo->query(
            'SELECT l.id, l.warehouse_id, w.name AS warehouse_name, l.code, l.type, l.status, l.package_id, l.updated_at,
                    p.package_no, p.item_name, p.status AS package_status, p.weight
             FROM ' . Database::table('warehouse_locations') . ' l
             JOIN ' . Database::table('warehouses') . ' w ON w.id = l.warehouse_id
             LEFT JOIN ' . Database::table('packages') . ' p ON p.id = l.package_id'
             . " {$where}
             ORDER BY l.warehouse_id ASC, l.code ASC LIMIT {$limit} OFFSET {$offset}"
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['rows' => $rows, 'total' => $total];
    }

    public static function locationCreate(int $warehouseId, string $code, int $type): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO ' . Database::table('warehouse_locations') . ' (warehouse_id, code, type, status) VALUES (:wid, :code, :type, 1)'
        );
        $stmt->execute([':wid' => $warehouseId, ':code' => $code, ':type' => $type]);
    }

    /**
     * 批量生成库位编码
     * 顺序模式（给了起止序号）：按两者的最大位数补零后顺序编号，含起止本身；
     * 随机模式（序号留空）：前缀 + 4 位随机码，字符集去掉易混的 I/O/0/1 便于扫码与人眼辨认；
     * 生成结果先做本批去重，再统一校验长度与字符集（与单条新增同一口径）
     * @return string[] 待写入的编码（是否与库中已有编码重复由 locationBatchCreate 处理）
     * @throws InvalidArgumentException 参数不合法（起止倒置、超量、编码超长等）
     */
    public static function buildLocationCodes(string $prefix, string $start, string $end, int $count): array
    {
        $prefix = strtoupper(trim($prefix));
        if ($prefix === '' || !preg_match('/^[A-Z0-9\-]{1,16}$/', $prefix)) {
            throw new InvalidArgumentException('前缀需为 1~16 位大写字母、数字或中划线');
        }
        $codes = [];
        if ($start !== '' && $end !== '') {
            if (!preg_match('/^\d{1,4}$/', $start) || !preg_match('/^\d{1,4}$/', $end)) {
                throw new InvalidArgumentException('序号需为 0~9999 的整数');
            }
            $from = (int) $start;
            $to = (int) $end;
            if ($from > $to) {
                throw new InvalidArgumentException('起始序号不能大于结束序号');
            }
            $total = $to - $from + 1;
            if ($total > self::LOCATION_BATCH_MAX) {
                throw new InvalidArgumentException('单次最多生成 ' . self::LOCATION_BATCH_MAX . ' 个库位，请分批生成');
            }
            $width = max(strlen($start), strlen($end));
            for ($i = $from; $i <= $to; $i++) {
                $codes[] = $prefix . str_pad((string) $i, $width, '0', STR_PAD_LEFT);
            }
        } else {
            if ($count < 1 || $count > self::LOCATION_BATCH_MAX) {
                throw new InvalidArgumentException('随机生成数量需在 1~' . self::LOCATION_BATCH_MAX . ' 之间');
            }
            $alphabet = self::LOCATION_RANDOM_ALPHABET;
            $max = strlen($alphabet) - 1;
            $unique = [];
            while (count($unique) < $count) {
                $suffix = '';
                for ($i = 0; $i < 4; $i++) {
                    $suffix .= $alphabet[random_int(0, $max)];
                }
                $unique[$prefix . $suffix] = true;
            }
            $codes = array_keys($unique);
        }
        foreach ($codes as $code) {
            if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $code)) {
                throw new InvalidArgumentException('生成的编码「' . $code . '」不符合规范（需 2~20 位），请缩短前缀或调整位数');
            }
        }
        return array_values($codes);
    }

    /**
     * 该仓库已有库位编码集合（编码为键，便于判重）
     */
    public static function locationCodeMap(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT code FROM ' . Database::table('warehouse_locations') . ' WHERE warehouse_id = :wid'
        );
        $stmt->execute([':wid' => $warehouseId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
            $map[(string) $code] = true;
        }
        return $map;
    }

    /**
     * 批量建库位：跳过该仓库内已存在的编码，其余在同一事务内写入
     * @param string[] $codes
     * @return array{created:int, skipped:int, created_codes:string[]}
     */
    public static function locationBatchCreate(int $warehouseId, array $codes, int $type): array
    {
        $pdo = Database::pdo();
        $existing = self::locationCodeMap($warehouseId);
        $insert = [];
        $skipped = 0;
        foreach ($codes as $code) {
            if (isset($existing[$code])) {
                $skipped++;
                continue;
            }
            $existing[$code] = true;
            $insert[] = $code;
        }
        if ($insert === []) {
            return ['created' => 0, 'skipped' => $skipped, 'created_codes' => []];
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . Database::table('warehouse_locations') . ' (warehouse_id, code, type, status) VALUES (:wid, :code, :type, 1)'
            );
            foreach ($insert as $code) {
                $stmt->execute([':wid' => $warehouseId, ':code' => $code, ':type' => $type]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['created' => count($insert), 'skipped' => $skipped, 'created_codes' => $insert];
    }

    /**
     * 编辑库位：调整编码与类型（所属仓库不随编辑变更，避免已上架包裹的归属漂移）
     * 编码在同一仓库内唯一，占用中的库位也可改名/改用途
     */
    public static function locationUpdate(int $id, string $code, int $type): void
    {
        $pdo = Database::pdo();
        $cur = $pdo->prepare(
            'SELECT id, warehouse_id FROM ' . Database::table('warehouse_locations') . ' WHERE id = :id LIMIT 1'
        );
        $cur->execute([':id' => $id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('库位不存在');
        }
        // 同仓库内编码不得与其他库位重复（唯一索引兜底，这里先给出可读提示）
        $dup = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('warehouse_locations') . ' WHERE warehouse_id = :wid AND code = :code AND id <> :id'
        );
        $dup->execute([':wid' => (int) $row['warehouse_id'], ':code' => $code, ':id' => $id]);
        if ((int) $dup->fetchColumn() > 0) {
            throw new InvalidArgumentException('该库位编码在本仓库内已存在');
        }
        $upd = $pdo->prepare(
            'UPDATE ' . Database::table('warehouse_locations') . ' SET code = :code, type = :type, updated_at = NOW() WHERE id = :id'
        );
        $upd->execute([':code' => $code, ':type' => $type, ':id' => $id]);
    }

    /** 库位详情（编辑回填用） */
    public static function findLocation(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT l.id, l.warehouse_id, w.name AS warehouse_name, l.code, l.type, l.status, l.package_id, l.updated_at
             FROM ' . Database::table('warehouse_locations') . ' l
             JOIN ' . Database::table('warehouses') . ' w ON w.id = l.warehouse_id
             WHERE l.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 启用 / 停用库位（可逆维护动作）
     * 占用中的库位不允许停用：其上还压着包裹，停用会让包裹失去库位归属
     * @return int 变更后的状态
     */
    public static function locationToggle(int $id): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, status FROM ' . Database::table('warehouse_locations') . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('库位不存在');
        }
        $status = (int) $row['status'];
        if ($status === 2) {
            throw new InvalidArgumentException('该库位正被包裹占用，不能停用；请先完成下架或移库');
        }
        $next = $status === 1 ? 0 : 1;
        $upd = $pdo->prepare(
            'UPDATE ' . Database::table('warehouse_locations') . ' SET status = :s WHERE id = :id AND status = :cur'
        );
        $upd->execute([':s' => $next, ':id' => $id, ':cur' => $status]);
        if ($upd->rowCount() !== 1) {
            throw new InvalidArgumentException('库位状态已变化，请刷新后重试');
        }
        return $next;
    }

    /* ================= 仓库端：库位作业 ================= */

    /** 库位类型：1入库暂存 2常规存储 3打包区 4出库暂存 5异常件区 */
    public const LOCATION_TYPES = [
        1 => '入库暂存', 2 => '常规存储', 3 => '打包区', 4 => '出库暂存', 5 => '异常件区',
    ];

    /** 库位编码：单次批量生成上限（防误操作一次灌爆库位表） */
    public const LOCATION_BATCH_MAX = 200;

    /** 库位编码：随机段字符集（去掉易混的 I 与 1、O 与 0，便于扫码与人眼辨认） */
    private const LOCATION_RANDOM_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** 库位状态：0停用 1空闲 2占用 */
    public const LOCATION_STATUS = [
        0 => '停用', 1 => '空闲', 2 => '占用',
    ];

    /**
     * 可选库位（本仓库、启用中）；上架只允许选非停用库位
     * @param bool $onlyFree 只返回空闲的常规存储位
     */
    public static function locationOptions(int $warehouseId, bool $onlyFree = false): array
    {
        $sql = 'SELECT id, code, type, status, package_id FROM ' . Database::table('warehouse_locations') . '
                WHERE warehouse_id = :wh AND status <> 0';
        if ($onlyFree) {
            $sql .= ' AND status = 1 AND type = 2';
        }
        $sql .= ' ORDER BY type ASC, code ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':wh' => $warehouseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 库位占用一览（含当前占用包裹单号），按类型/状态过滤
     * @param int $limit 每次取多少条，0 表示不分页取全部
     * @return array{rows:array, total:int} total 为过滤后的总数，供分页显示
     */
    public static function locationOverview(int $warehouseId, int $type = 0, int $status = -1, int $limit = 0, int $offset = 0): array
    {
        $where = ['l.warehouse_id = :wh'];
        $params = [':wh' => $warehouseId];
        if ($type > 0) {
            $where[] = 'l.type = :type';
            $params[':type'] = $type;
        }
        if ($status >= 0) {
            $where[] = 'l.status = :status';
            $params[':status'] = $status;
        }
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('warehouse_locations') . ' l WHERE ' . implode(' AND ', $where)
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT l.id, l.code, l.type, l.status, l.package_id, l.updated_at,
                       p.package_no, p.item_name, p.status AS package_status
                FROM ' . Database::table('warehouse_locations') . ' l
                LEFT JOIN ' . Database::table('packages') . ' p ON p.id = l.package_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY l.type ASC, l.code ASC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /**
     * 各仓库作业概况（仓库报表：在库/今日入库/待打包/待出库/异常/库位占用）
     */
    public static function operationStats(): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT w.id, w.name, w.code, w.country, w.status,
                    (SELECT COUNT(*) FROM ' . Database::table('packages') . ' p
                     WHERE p.warehouse_id = w.id AND p.deleted_at IS NULL AND p.status BETWEEN 2 AND 9) AS in_stock,
                    (SELECT COUNT(*) FROM ' . Database::table('packages') . ' p
                     WHERE p.warehouse_id = w.id AND p.deleted_at IS NULL AND DATE(p.inbound_at) = CURDATE()) AS today_inbound,
                    (SELECT COUNT(*) FROM ' . Database::table('packages') . ' p
                     WHERE p.warehouse_id = w.id AND p.deleted_at IS NULL AND p.status = 3) AS unattributed,
                    (SELECT COUNT(*) FROM ' . Database::table('waybills') . ' b
                     WHERE b.warehouse_id = w.id AND b.deleted_at IS NULL AND b.status IN (0, 1)) AS pending_packing,
                    (SELECT COUNT(*) FROM ' . Database::table('waybills') . ' b
                     WHERE b.warehouse_id = w.id AND b.deleted_at IS NULL AND b.status IN (3, 4)) AS pending_outbound,
                    (SELECT COUNT(*) FROM ' . Database::table('waybills') . ' b
                     WHERE b.warehouse_id = w.id AND b.deleted_at IS NULL AND DATE(b.outbound_at) = CURDATE()) AS today_outbound,
                    (SELECT COUNT(*) FROM ' . Database::table('package_abnormals') . ' a
                     JOIN ' . Database::table('packages') . ' p2 ON p2.id = a.package_id
                     WHERE p2.warehouse_id = w.id AND p2.deleted_at IS NULL AND a.status = 0) AS abnormal,
                    (SELECT COUNT(*) FROM ' . Database::table('warehouse_locations') . ' l
                     WHERE l.warehouse_id = w.id AND l.status <> 0) AS loc_total,
                    (SELECT COUNT(*) FROM ' . Database::table('warehouse_locations') . ' l
                     WHERE l.warehouse_id = w.id AND l.status = 2) AS loc_used
             FROM ' . Database::table('warehouses') . ' w
             WHERE w.deleted_at IS NULL ORDER BY w.sort ASC, w.id ASC';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $total = (int) $row['loc_total'];
            $row['loc_rate'] = $total > 0 ? round((int) $row['loc_used'] * 100 / $total, 1) : 0.0;
        }
        unset($row);
        return $rows;
    }

    /**
     * 库位概览统计（总数/空闲/占用/停用）
     */
    public static function locationSummary(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS free,
                    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS used,
                    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS disabled
             FROM ' . Database::table('warehouse_locations') . ' WHERE warehouse_id = :wh'
        );
        $stmt->execute([':wh' => $warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total'    => (int) ($row['total'] ?? 0),
            'free'     => (int) ($row['free'] ?? 0),
            'used'     => (int) ($row['used'] ?? 0),
            'disabled' => (int) ($row['disabled'] ?? 0),
        ];
    }
}
