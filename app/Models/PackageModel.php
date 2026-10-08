<?php declare(strict_types=1);
/**
 * @file app/Models/PackageModel.php
 * @description 包裹数据访问（用户端视角：预报 / 列表 / 认领 / 退运销毁 / 仓储费）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-15
 */

namespace App\Models;

use App\Core\Database;
use App\Enums\Status;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class PackageModel
{
    /** 在库区间：已到仓 → 待出库（货物仍在仓库内） */
    public const IN_STOCK_FROM = 2;
    public const IN_STOCK_TO = 9;

    /**
     * 我的包裹检索
     * @return array{rows: array, total: int}
     */
    public static function search(int $userId, string $keyword, int $status, int $limit, int $offset): array
    {
        $where = ['p.user_id = :uid', 'p.deleted_at IS NULL'];
        $params = [':uid' => $userId];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['p.package_no', 'p.tracking_no', 'p.item_name', 'p.forecast_no'], $params, $keyword, 'pkw');
        }
        if ($status >= 0) {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('packages') . " p WHERE {$whereSql}");
        $countStmt->execute($params);

        $listStmt = $pdo->prepare(
            'SELECT p.id, p.package_no, p.forecast_no, p.tracking_no, p.express_company, p.item_name, p.item_qty,
                    p.declared_value, p.currency, p.category, p.expected_arrival, p.weight, p.volume_weight,
                    p.storage_fee, p.inbound_at, p.status, p.is_abnormal, p.remark, p.created_at,
                    w.name AS warehouse_name
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('warehouses') . " w ON w.id = p.warehouse_id
             WHERE {$whereSql} ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);

        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 各状态包裹数（状态编码为键）
     */
    public static function statusCounts(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('packages') . '
             WHERE user_id = :uid AND deleted_at IS NULL GROUP BY status'
        );
        $stmt->execute([':uid' => $userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    /**
     * 归属校验：只允许操作自己的包裹
     */
    public static function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, w.name AS warehouse_name, l.code AS location_code
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('warehouses') . ' w ON w.id = p.warehouse_id
             LEFT JOIN ' . Database::table('warehouse_locations') . ' l ON l.id = p.location_id
             WHERE p.id = :id AND p.user_id = :uid AND p.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 包裹最近一次关联的转运运单：包裹可能参与多轮转运（退回重发、分箱），
     * 取运单号最大的一条即为当前这轮，用户端据此展示运单号与运费支付时间
     */
    public static function latestWaybillOf(int $packageId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT w.id, w.waybill_no, w.status, w.paid_at, w.total_amount, w.currency, w.due_amount
             FROM ' . Database::table('waybill_packages') . ' wp
             JOIN ' . Database::table('waybills') . ' w ON w.id = wp.waybill_id
             WHERE wp.package_id = :pid AND w.deleted_at IS NULL
             ORDER BY w.id DESC LIMIT 1'
        );
        $stmt->execute([':pid' => $packageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 包裹照片（入库照 / 打包照 / 用户凭证）
     */
    public static function photos(int $packageId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, photo_type, file_url AS url, created_at FROM ' . Database::table('package_photos') . '
             WHERE package_id = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $packageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 新增预报：生成预报单号并落库，状态为「待预报」→ 直接置「已预报」
     */
    public static function createForecast(int $userId, string $userCode, array $data): int
    {
        $pdo = Database::pdo();
        $packageNo = self::nextNo($pdo, 'P', 'package_no');
        $forecastNo = self::nextNo($pdo, 'F', 'forecast_no');

        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('packages') . '
             (package_no, forecast_no, user_id, warehouse_id, user_code, tracking_no, express_company,
              item_name, item_qty, declared_value, currency, category, expected_arrival, status, remark)
             VALUES (:no, :fno, :uid, :wid, :code, :tracking, :express, :item, :qty, :value, :currency, :category, :eta, 1, :remark)'
        );
        $stmt->execute([
            ':no'       => $packageNo,
            ':fno'      => $forecastNo,
            ':uid'      => $userId,
            ':wid'      => $data['warehouse_id'],
            ':code'     => $userCode,
            ':tracking' => $data['tracking_no'],
            ':express'  => $data['express_company'],
            ':item'     => $data['item_name'],
            ':qty'      => $data['item_qty'],
            ':value'    => $data['declared_value'],
            ':currency' => $data['currency'],
            ':category' => $data['category'],
            ':eta'      => $data['expected_arrival'] !== '' ? $data['expected_arrival'] : null,
            ':remark'   => $data['remark'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * 修改预报：仅「待预报 / 已预报」状态允许修改
     */
    public static function updateForecast(int $id, int $userId, array $data): void
    {
        $pkg = self::findForUser($id, $userId);
        if ($pkg === null) {
            throw new InvalidArgumentException('包裹不存在');
        }
        if (!in_array((int) $pkg['status'], [0, 1], true)) {
            throw new InvalidArgumentException('包裹已到仓，无法再修改预报信息');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('packages') . '
             SET tracking_no = :tracking, express_company = :express, item_name = :item, item_qty = :qty,
                 declared_value = :value, category = :category, expected_arrival = :eta, remark = :remark
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            ':tracking' => $data['tracking_no'],
            ':express'  => $data['express_company'],
            ':item'     => $data['item_name'],
            ':qty'      => $data['item_qty'],
            ':value'    => $data['declared_value'],
            ':category' => $data['category'],
            ':eta'      => $data['expected_arrival'] !== '' ? $data['expected_arrival'] : null,
            ':remark'   => $data['remark'],
            ':id'       => $id,
            ':uid'      => $userId,
        ]);
    }

    /**
     * 认领包裹：待认领(3) / 已到仓(2) 均可认领
     */
    public static function claim(int $id, int $userId): void
    {
        $pkg = self::findForUser($id, $userId);
        if ($pkg === null) {
            throw new InvalidArgumentException('包裹不存在');
        }
        if (!in_array((int) $pkg['status'], [2, 3], true)) {
            throw new InvalidArgumentException('当前状态不可认领');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('packages') . ' SET status = 4, claimed_at = NOW()
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    /**
     * 历史数据校正：补齐「已认领」但缺失认领时间的包裹（幂等，供升级脚本调用）
     * 早期手动建档在识别码命中用户时直接置「已认领」却没记录认领时间，导致用户端包裹详情恒显示「未认领」。
     * 认领时间取入库时间近似（无入库时间则取建档时间），已有值的一律不动
     * @return int 实际被补齐的包裹数
     */
    public static function backfillClaimedAt(): int
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('packages') . '
             SET claimed_at = COALESCE(inbound_at, created_at)
             WHERE claimed_at IS NULL AND status >= 4 AND deleted_at IS NULL
               AND COALESCE(inbound_at, created_at) IS NOT NULL'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * 申请退运 / 销毁：登记异常单，由仓库端处理
     * 同一包裹同时只允许一个进行中的处置申请（退运与销毁互斥），避免仓库收到矛盾指令
     */
    public static function requestDisposal(int $id, int $userId, int $type, string $reason): void
    {
        $pkg = self::findForUser($id, $userId);
        if ($pkg === null) {
            throw new InvalidArgumentException('包裹不存在');
        }
        if ((int) $pkg['status'] >= 10) {
            throw new InvalidArgumentException('包裹已出库，无法申请退运或销毁');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // FOR UPDATE 锁定同包裹既有申请行，防止并发双击绕过互斥
            $exists = $pdo->prepare(
                'SELECT COUNT(*) FROM ' . Database::table('package_abnormals') . '
                 WHERE package_id = :id AND status = 0 AND abnormal_type IN (6, 7)
                 FOR UPDATE'
            );
            $exists->execute([':id' => $id]);
            if ((int) $exists->fetchColumn() > 0) {
                throw new InvalidArgumentException('已有退运或销毁申请正在处理中，请先取消原申请');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO ' . Database::table('package_abnormals') . '
                 (package_id, abnormal_type, description, status) VALUES (:pid, :type, :desc, 0)'
            );
            $stmt->execute([':pid' => $id, ':type' => $type, ':desc' => $reason]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 取消本人包裹进行中的退运/销毁申请：记录关闭留痕而非删除，仓库端不再处理
     * @return array{abnormal_id: int, type: int} 被取消的申请信息
     */
    public static function cancelDisposal(int $packageId, int $userId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // 行锁防并发：与仓库处理、再次申请互斥
            $find = $pdo->prepare(
                'SELECT a.id, a.abnormal_type FROM ' . Database::table('package_abnormals') . ' a
                 JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
                 WHERE p.id = :pid AND p.user_id = :uid AND p.deleted_at IS NULL
                   AND a.abnormal_type IN (6, 7) AND a.status = 0
                 ORDER BY a.id ASC LIMIT 1 FOR UPDATE'
            );
            $find->execute([':pid' => $packageId, ':uid' => $userId]);
            $row = $find->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new InvalidArgumentException('当前没有进行中的退运或销毁申请');
            }
            $upd = $pdo->prepare(
                'UPDATE ' . Database::table('package_abnormals') . '
                 SET status = 3, handle_result = \'用户取消申请\', handled_at = NOW()
                 WHERE id = :id AND status = 0'
            );
            $upd->execute([':id' => (int) $row['id']]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('申请状态已变化，请刷新后重试');
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['abnormal_id' => (int) $row['id'], 'type' => (int) $row['abnormal_type']];
    }

    /**
     * 某包裹进行中的处置申请（退运/销毁），无则 null；详情页展示用
     */
    public static function pendingDisposalOf(int $packageId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, abnormal_type, description, created_at
             FROM ' . Database::table('package_abnormals') . '
             WHERE package_id = :pid AND abnormal_type IN (6, 7) AND status = 0
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([':pid' => $packageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 批量查询包裹进行中的处置申请：package_id => [id, abnormal_type, created_at]
     * 列表页徽标用；同包裹若历史遗留多条（互斥修复前产生），取最早一条
     */
    public static function pendingDisposals(array $packageIds): array
    {
        if ($packageIds === []) {
            return [];
        }
        $ph = [];
        $params = [];
        foreach (array_values(array_unique(array_map('intval', $packageIds))) as $i => $pid) {
            $ph[] = ':pid' . $i;
            $params[':pid' . $i] = $pid;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT package_id, id, abnormal_type, created_at
             FROM ' . Database::table('package_abnormals') . '
             WHERE package_id IN (' . implode(',', $ph) . ') AND abnormal_type IN (6, 7) AND status = 0
             ORDER BY id ASC'
        );
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['package_id']] ??= [
                'id'            => (int) $row['id'],
                'abnormal_type' => (int) $row['abnormal_type'],
                'created_at'    => (string) $row['created_at'],
            ];
        }
        return $map;
    }

    /**
     * 我的异常件（含用户提交的退运/销毁申请与仓库登记的异常）
     */
    public static function abnormals(int $userId, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('package_abnormals') . ' a
             JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
             WHERE p.user_id = :uid AND p.deleted_at IS NULL'
        );
        $countStmt->execute([':uid' => $userId]);
        $listStmt = $pdo->prepare(
            'SELECT a.id, a.package_id, a.abnormal_type, a.description, a.status, a.handle_result, a.handled_at, a.created_at,
                    p.package_no, p.item_name
             FROM ' . Database::table('package_abnormals') . ' a
             JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
             WHERE p.user_id = :uid AND p.deleted_at IS NULL
             ORDER BY a.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $listStmt->execute([':uid' => $userId]);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 可用于转运的包裹：仅「已认领」(4)
     * 提交转运后包裹会被置为待打包(5)并锁定，因此 5~9 不允许再次下单
     * 该列表是勾选表单、不宜分页（跨页勾选会丢状态），改为保护性截断：只取最近 limit 件，
     * 超出部分等本单提交后再来（包裹总数随 total 返回供页面提示）
     * @return array{rows:array, total:int}
     */
    public static function claimableForWaybill(int $userId, int $limit = 200): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('packages') . '
             WHERE user_id = :uid AND deleted_at IS NULL AND status = 4'
        );
        $count->execute([':uid' => $userId]);
        $stmt = $pdo->prepare(
            'SELECT p.id, p.package_no, p.item_name, p.item_qty, p.weight, p.volume_weight, p.declared_value,
                    p.status, p.warehouse_id
             FROM ' . Database::table('packages') . ' p
             WHERE p.user_id = :uid AND p.deleted_at IS NULL AND p.status = 4
             ORDER BY p.id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([':uid' => $userId]);
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 汇总：在库数量与累计仓储费
     */
    public static function stockSummary(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) AS cnt, IFNULL(SUM(storage_fee), 0) AS fee
             FROM ' . Database::table('packages') . '
             WHERE user_id = :uid AND deleted_at IS NULL AND status BETWEEN ' . self::IN_STOCK_FROM . ' AND ' . self::IN_STOCK_TO
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'fee' => 0];
        return ['count' => (int) $row['cnt'], 'fee' => (float) $row['fee']];
    }

    /* ================= 管理端（全用户范围，与会员中心同源数据） ================= */

    /**
     * 后台包裹检索：不限用户，关键词命中包裹/预报/快递单号与用户编号、识别码、用户名
     * @return array{rows: array, total: int}
     */
    public static function adminSearch(string $keyword, int $status, int $limit, int $offset): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(
                ['p.package_no', 'p.forecast_no', 'p.tracking_no', 'p.item_name', 'u.user_no', 'u.user_code', 'u.username'],
                $params,
                $keyword,
                'apkw'
            );
        }
        if ($status >= 0) {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . " u ON u.id = p.user_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);

        $listStmt = $pdo->prepare(
            'SELECT p.id, p.package_no, p.forecast_no, p.tracking_no, p.express_company, p.item_name, p.item_qty,
                    p.declared_value, p.currency, p.category, p.weight, p.volume_weight, p.storage_fee,
                    p.inbound_at, p.status, p.is_abnormal, p.created_at,
                    u.id AS user_id, u.user_no, u.username, u.user_code,
                    w.name AS warehouse_name
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = p.user_id
             LEFT JOIN ' . Database::table('warehouses') . " w ON w.id = p.warehouse_id
             WHERE {$whereSql} ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);

        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'rows'              => $rows,
            'total'             => (int) $countStmt->fetchColumn(),
            'pendingDisposals'  => self::pendingDisposals(array_column($rows, 'id')),
        ];
    }

    /**
     * 后台各状态包裹数（状态编码为键）
     */
    public static function adminStatusCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('packages') . '
             WHERE deleted_at IS NULL GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'status');
    }

    /**
     * 后台包裹详情（不限归属，含用户与仓库/库位信息）
     */
    public static function findDetail(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, u.user_no, u.username, u.nickname, u.phone, u.email,
                    w.name AS warehouse_name, l.code AS location_code
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = p.user_id
             LEFT JOIN ' . Database::table('warehouses') . ' w ON w.id = p.warehouse_id
             LEFT JOIN ' . Database::table('warehouse_locations') . ' l ON l.id = p.location_id
             WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 包裹详情（限定仓库）：仓库端仓储管理查看，跨仓包裹不可见
     */
    public static function findForWarehouse(int $id, int $warehouseId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, u.user_no, u.username, u.nickname, u.phone, u.email, u.member_level,
                    w.name AS warehouse_name, l.code AS location_code
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = p.user_id
             LEFT JOIN ' . Database::table('warehouses') . ' w ON w.id = p.warehouse_id
             LEFT JOIN ' . Database::table('warehouse_locations') . ' l ON l.id = p.location_id
             WHERE p.id = :id AND p.warehouse_id = :wh AND p.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':wh' => $warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 未处理异常件数量（传入用户ID则只统计该用户的包裹），用于导航待办提示
     */
    public static function pendingAbnormalCount(?int $userId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . Database::table('package_abnormals') . ' a
                JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
                WHERE a.status = 0 AND p.deleted_at IS NULL';
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND p.user_id = :uid';
            $params[':uid'] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 某包裹的异常与处置记录（用户端与后台共用）
     */
    public static function abnormalsByPackage(int $packageId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, abnormal_type, description, status, handle_result, handled_at, created_at
             FROM ' . Database::table('package_abnormals') . '
             WHERE package_id = :pid ORDER BY id DESC'
        );
        $stmt->execute([':pid' => $packageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ================= 仓库端（按仓库隔离的作业数据） ================= */

    /** 仓库在库范围：已到仓 → 待出库（含异常件、无主件） */
    public const WH_STATUSES = [2 => '已到仓', 3 => '待认领', 4 => '已认领', 5 => '待打包', 6 => '打包中', 7 => '待支付', 8 => '已支付', 9 => '待出库', 18 => '已拦截'];

    /**
     * 按快递单号 / 预报单号 / 包裹编号定位待入库包裹（仓库端扫码入口）
     * 只找已预报未到仓的，避免重复入库
     */
    public static function findForInbound(string $keyword, int $warehouseId): ?array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT p.id, p.package_no, p.forecast_no, p.tracking_no, p.express_company, p.item_name, p.item_qty,
                    p.declared_value, p.currency, p.category, p.status, p.user_id, p.user_code, p.warehouse_id,
                    u.user_no, u.username, u.nickname, u.phone
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = p.user_id
             WHERE p.deleted_at IS NULL AND p.warehouse_id = :wh
               AND (p.tracking_no = :kw1 OR p.forecast_no = :kw2 OR p.package_no = :kw3)
             ORDER BY p.id DESC LIMIT 1'
        );
        $stmt->execute([':wh' => $warehouseId, ':kw1' => $keyword, ':kw2' => $keyword, ':kw3' => $keyword]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 按快递单号精确查本仓包裹（手动添加前的重号判断）
     * 只比快递单号：findForInbound() 连包裹编号一起比，会把「编号恰好等于单号」误判为重号
     */
    public static function findByTracking(string $trackingNo, int $warehouseId): ?array
    {
        $trackingNo = trim($trackingNo);
        if ($trackingNo === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT p.id, p.package_no, p.status, p.user_id, p.user_code, p.warehouse_id,
                    u.user_no, u.username, u.nickname
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = p.user_id
             WHERE p.deleted_at IS NULL AND p.warehouse_id = :wh AND p.tracking_no = :no
             ORDER BY p.id DESC LIMIT 1'
        );
        $stmt->execute([':wh' => $warehouseId, ':no' => $trackingNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 仓库在库/作业列表检索
     * @return array{rows: array, total: int}
     */
    public static function warehouseSearch(int $warehouseId, string $keyword, int $status, int $limit, int $offset): array
    {
        $where = ['p.deleted_at IS NULL', 'p.warehouse_id = :wh'];
        $params = [':wh' => $warehouseId];
        if ($status >= 0) {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        } else {
            // 默认只看「货还在仓库」的范围：已到仓→待出库，外加异常件；
            // 已出库/运输中/已签收等已离仓状态与退回销毁等终态都不出现在仓库列表里
            $inHouse = implode(',', array_map('intval', array_keys(self::WH_STATUSES)));
            $where[] = 'p.status IN (' . $inHouse . ')';
        }
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['p.package_no', 'p.forecast_no', 'p.tracking_no', 'p.item_name', 'p.user_code', 'u.user_no'], $params, $keyword, 'wkw');
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . " u ON u.id = p.user_id WHERE {$whereSql}"
        );
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT p.id, p.package_no, p.tracking_no, p.item_name, p.item_qty, p.category, p.weight, p.volume_weight,
                    p.storage_fee, p.inbound_at, p.status, p.is_abnormal, p.user_id, p.user_code, p.location_id,
                    p.created_at, p.warehouse_id, l.code AS location_code, u.user_no, u.username, u.nickname, u.member_level
             FROM ' . Database::table('packages') . ' p
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = p.user_id
             LEFT JOIN ' . Database::table('warehouse_locations') . " l ON l.id = p.location_id
             WHERE {$whereSql} ORDER BY p.status ASC, p.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 仓库包裹状态计数（作业看板 + 导航徽标）
     */
    public static function warehouseCounts(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('packages') . '
             WHERE deleted_at IS NULL AND warehouse_id = :wh GROUP BY status'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    /**
     * 待入库件数：已预报但未到仓（入库作业待办）
     */
    public static function pendingInboundCount(int $warehouseId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('packages') . '
             WHERE deleted_at IS NULL AND warehouse_id = :wh AND status = 1'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 按体积重公式换算（长×宽×高 ÷ 体积系数，系数取系统配置）
     */
    /**
     * 体积重 = 长×宽×高 ÷ 系数。
     * 系数取自渠道的 volume_divisor（承运商计费口径）；入库时尚未确定走哪个渠道，
     * 故不传时回落系统默认系数，仅作展示与下单预扣的参考值，最终以打包时的渠道系数结算。
     */
    public static function volumeWeight(float $length, float $width, float $height, ?float $divisor = null): float
    {
        if ($divisor === null || $divisor <= 0) {
            $divisor = (float) \App\Models\SettingsModel::get('volume_divisor_default', '5000');
        }
        if ($divisor <= 0) {
            $divisor = 5000.0;
        }
        return round($length * $width * $height / $divisor, 3);
    }

    /**
     * 收货入库：写入实重尺寸与库位并流转为「已到仓」
     * 仅已预报/待认领的包裹可入库，避免重复入库覆盖数据
     */
    public static function inbound(int $id, int $warehouseId, array $data, int $adminId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, status, warehouse_id FROM ' . Database::table('packages') . ' WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $pkg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pkg === false) {
            throw new InvalidArgumentException('包裹不存在');
        }
        if ((int) $pkg['warehouse_id'] !== $warehouseId) {
            throw new InvalidArgumentException('该包裹不属于当前仓库');
        }
        if (!in_array((int) $pkg['status'], [1], true)) {
            throw new InvalidArgumentException('仅「已预报」的包裹可入库，当前状态：' . Status::package((int) $pkg['status']));
        }

        $volumeWeight = self::volumeWeight((float) $data['length'], (float) $data['width'], (float) $data['height']);
        $pdo->beginTransaction();
        try {
            // 已预报包裹自带归属（识别码正确），入库即自动认领，用户无需手动操作
            $upd = $pdo->prepare(
                'UPDATE ' . Database::table('packages') . '
                 SET weight = :weight, length = :length, width = :width, height = :height,
                     volume_weight = :vweight, inbound_at = NOW(), inbound_admin_id = :aid,
                     status = 4, claimed_at = NOW(), location_id = :location
                 WHERE id = :id AND status = 1'
            );
            $upd->execute([
                ':weight'   => $data['weight'],
                ':length'   => $data['length'],
                ':width'    => $data['width'],
                ':height'   => $data['height'],
                ':vweight'  => $volumeWeight,
                ':aid'      => $adminId,
                ':location' => $data['location_id'] > 0 ? $data['location_id'] : null,
                ':id'       => $id,
            ]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException('包裹状态已变化，请刷新后重试');
            }
            if ($data['location_id'] > 0) {
                self::occupyLocation($pdo, (int) $data['location_id'], $id, $warehouseId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 无主件列表（入库时未能匹配到用户的包裹）
     * @return array{rows: array, total: int}
     */
    public static function unattributed(int $warehouseId, int $limit, int $offset): array
    {
        $pdo = Database::pdo();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('packages') . '
             WHERE deleted_at IS NULL AND warehouse_id = :wh AND status = 3'
        );
        $count->execute([':wh' => $warehouseId]);
        $list = $pdo->prepare(
            'SELECT id, package_no, forecast_no, tracking_no, express_company, item_name, item_qty,
                    weight, inbound_at, status, created_at
             FROM ' . Database::table('packages') . '
             WHERE deleted_at IS NULL AND warehouse_id = :wh AND status = 3
             ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $list->execute([':wh' => $warehouseId]);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 登记无主件：快递单号未匹配到任何预报，先按无主件入库等用户认领
     */
    public static function createUnattributed(int $warehouseId, array $data, int $adminId): int
    {
        $pdo = Database::pdo();
        $packageNo = self::nextNo($pdo, 'P', 'package_no');
        $volumeWeight = self::volumeWeight((float) $data['length'], (float) $data['width'], (float) $data['height']);
        $stmt = $pdo->prepare(
            'INSERT INTO ' . Database::table('packages') . '
                (package_no, user_id, warehouse_id, user_code, tracking_no, express_company, item_name, item_qty,
                 declared_value, currency, category, weight, length, width, height, volume_weight,
                 inbound_at, inbound_admin_id, status, remark)
             VALUES (:no, 0, :wh, "", :tracking, :express, :item, :qty, 0, "CNY", :category,
                     :weight, :length, :width, :height, :vweight, NOW(), :aid, 3, :remark)'
        );
        $stmt->execute([
            ':no'       => $packageNo,
            ':wh'       => $warehouseId,
            ':tracking' => $data['tracking_no'],
            ':express'  => $data['express_company'],
            ':item'     => $data['item_name'],
            ':qty'      => $data['item_qty'],
            ':category' => $data['category'],
            ':weight'   => $data['weight'],
            ':length'   => $data['length'],
            ':width'    => $data['width'],
            ':height'   => $data['height'],
            ':vweight'  => $volumeWeight,
            ':aid'      => $adminId,
            ':remark'   => '无主件入库：' . ($data['remark'] !== '' ? $data['remark'] : '面单未标注有效识别码'),
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * 手动添加包裹：面单无法扫码或需要事后补录时由收货员直接建档
     * 识别码命中用户 → 直接归属该用户并置「已认领」；未命中或未填 → 按无主件入库待指定归属
     * @param array $data user_id/user_code 由调用方（控制器）解析后传入，其余为面单与实测数据
     */
    public static function createManual(int $warehouseId, array $data, int $adminId): int
    {
        $pdo = Database::pdo();
        $userId = (int) ($data['user_id'] ?? 0);
        $userCode = strtoupper(trim((string) ($data['user_code'] ?? '')));
        $attributed = $userId > 0;
        $packageNo = self::nextNo($pdo, 'P', 'package_no');
        $volumeWeight = self::volumeWeight((float) $data['length'], (float) $data['width'], (float) $data['height']);
        $locationId = (int) ($data['location_id'] ?? 0);
        $remark = trim((string) ($data['remark'] ?? ''));
        if (!$attributed) {
            // 无主件的备注必须留下判断依据，便于后续人工核对与指定归属
            $remark = trim('识别码未匹配：' . ($userCode !== '' ? $userCode : '面单未标注') . ($remark !== '' ? '；' . $remark : ''));
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . Database::table('packages') . '
                    (package_no, user_id, warehouse_id, user_code, tracking_no, express_company, item_name, item_qty,
                     declared_value, currency, category, weight, length, width, height, volume_weight,
                     location_id, inbound_at, claimed_at, inbound_admin_id, status, remark)
                 VALUES (:no, :uid, :wh, :code, :tracking, :express, :item, :qty, 0, "CNY", :category,
                         :weight, :length, :width, :height, :vweight, :location, NOW(),
                         IF(:claimed = 1, NOW(), NULL), :aid, :status, :remark)'
            );
            $stmt->execute([
                ':no'       => $packageNo,
                ':uid'      => $userId,
                ':wh'       => $warehouseId,
                ':code'     => $userCode,
                ':tracking' => (string) $data['tracking_no'],
                ':express'  => (string) ($data['express_company'] ?? ''),
                ':item'     => (string) $data['item_name'],
                ':qty'      => (int) $data['item_qty'],
                ':category' => (int) $data['category'],
                ':weight'   => (float) $data['weight'],
                ':length'   => (float) $data['length'],
                ':width'    => (float) $data['width'],
                ':height'   => (float) $data['height'],
                ':vweight'  => $volumeWeight,
                ':location' => $locationId > 0 ? $locationId : null,
                ':aid'      => $adminId,
                // 4 已认领（识别码命中，建档即完成归属，同时落认领时间）/ 3 待认领（无主件）
                ':status'   => $attributed ? 4 : 3,
                ':claimed'  => $attributed ? 1 : 0,
                ':remark'   => $remark === '' ? null : $remark,
            ]);
            $packageId = (int) $pdo->lastInsertId();
            if ($locationId > 0) {
                self::occupyLocation($pdo, $locationId, $packageId, $warehouseId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $packageId;
    }

    /**
     * 无主件指定归属：补齐 user_id 与识别码后转为「已到仓」
     */
    public static function attributeTo(int $id, int $userId, int $warehouseId, int $adminId): void
    {
        $pdo = Database::pdo();
        $user = $pdo->prepare('SELECT id, user_no, user_code, status FROM ' . Database::table('users') . ' WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $user->execute([':id' => $userId]);
        $u = $user->fetch(PDO::FETCH_ASSOC);
        if ($u === false) {
            throw new InvalidArgumentException('指定的用户不存在');
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . Database::table('packages') . '
             SET user_id = :uid, user_code = :code, status = 4, claimed_at = NOW(), inbound_admin_id = :aid
             WHERE id = :id AND warehouse_id = :wh AND status = 3'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':code' => (string) $u['user_code'],
            ':aid'  => $adminId,
            ':id'   => $id,
            ':wh'   => $warehouseId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('该包裹不是待归属的无主件，或已处理');
        }
    }

    /**
     * 上架/移库：占用新库位并释放旧库位
     */
    public static function shelve(int $id, int $locationId, int $warehouseId, int $adminId): void
    {
        $pdo = Database::pdo();
        $cur = $pdo->prepare(
            'SELECT id, status, location_id FROM ' . Database::table('packages') . ' WHERE id = :id AND warehouse_id = :wh AND deleted_at IS NULL LIMIT 1'
        );
        $cur->execute([':id' => $id, ':wh' => $warehouseId]);
        $pkg = $cur->fetch(PDO::FETCH_ASSOC);
        if ($pkg === false) {
            throw new InvalidArgumentException('包裹不存在或不属于当前仓库');
        }
        if (!in_array((int) $pkg['status'], array_keys(self::WH_STATUSES), true)) {
            throw new InvalidArgumentException('当前状态的包裹不可上架');
        }

        $pdo->beginTransaction();
        try {
            if ((int) $pkg['location_id'] > 0 && (int) $pkg['location_id'] !== $locationId) {
                self::releaseLocation($pdo, (int) $pkg['location_id'], $id);
            }
            $pdo->prepare('UPDATE ' . Database::table('packages') . ' SET location_id = :loc WHERE id = :id')
                ->execute([':loc' => $locationId > 0 ? $locationId : null, ':id' => $id]);
            if ($locationId > 0) {
                self::occupyLocation($pdo, $locationId, $id, $warehouseId);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 占用库位：非暂存库位与包裹一对一，已被占用则报错
     */
    private static function occupyLocation(PDO $pdo, int $locationId, int $packageId, int $warehouseId): void
    {
        $loc = $pdo->prepare(
            'SELECT id, type, status, package_id FROM ' . Database::table('warehouse_locations') . '
             WHERE id = :id AND warehouse_id = :wh LIMIT 1'
        );
        $loc->execute([':id' => $locationId, ':wh' => $warehouseId]);
        $row = $loc->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('库位不存在或不属于当前仓库');
        }
        if ((int) $row['status'] === 0) {
            throw new InvalidArgumentException('该库位已停用');
        }
        if ((int) $row['type'] === 2 && (int) $row['package_id'] > 0 && (int) $row['package_id'] !== $packageId) {
            throw new InvalidArgumentException('该常规库位已被其它包裹占用，请另选库位');
        }
        $pdo->prepare('UPDATE ' . Database::table('warehouse_locations') . ' SET package_id = :pid, status = 2 WHERE id = :id')
            ->execute([':pid' => $packageId, ':id' => $locationId]);
    }

    /**
     * 随机取一个本仓库空闲的常规存储库位；全部占用返回 null
     * @return array{id: int, code: string}|null
     */
    public static function randomFreeLocation(int $warehouseId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, code FROM ' . Database::table('warehouse_locations') . '
             WHERE warehouse_id = :wh AND type = 2 AND status = 1 ORDER BY RAND() LIMIT 1'
        );
        $stmt->execute([':wh' => $warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : ['id' => (int) $row['id'], 'code' => (string) $row['code']];
    }

    /**
     * 下架：包裹移出库位并释放占用（仅限在库且尚未进入打包/出库流程的包裹）
     */
    public static function unshelve(int $id, int $warehouseId, int $adminId): void
    {
        $pdo = Database::pdo();
        $cur = $pdo->prepare(
            'SELECT id, status, location_id FROM ' . Database::table('packages') . '
             WHERE id = :id AND warehouse_id = :wh AND deleted_at IS NULL LIMIT 1'
        );
        $cur->execute([':id' => $id, ':wh' => $warehouseId]);
        $pkg = $cur->fetch(PDO::FETCH_ASSOC);
        if ($pkg === false) {
            throw new InvalidArgumentException('包裹不存在或不属于当前仓库');
        }
        if (empty($pkg['location_id'])) {
            throw new InvalidArgumentException('该包裹未上架，无需下架');
        }
        if (!in_array((int) $pkg['status'], [2, 3, 4], true)) {
            throw new InvalidArgumentException('包裹已进入打包/出库流程，不能下架');
        }
        $pdo->beginTransaction();
        try {
            self::releaseLocation($pdo, (int) $pkg['location_id'], $id);
            $pdo->prepare('UPDATE ' . Database::table('packages') . ' SET location_id = NULL WHERE id = :id')
                ->execute([':id' => $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 释放库位（移库或出库时调用）
     */
    private static function releaseLocation(PDO $pdo, int $locationId, int $packageId): void
    {
        $pdo->prepare(
            'UPDATE ' . Database::table('warehouse_locations') . '
             SET package_id = NULL, status = 1 WHERE id = :id AND package_id = :pid'
        )->execute([':id' => $locationId, ':pid' => $packageId]);
    }

    /**
     * 出库时释放包裹占用的库位（供运单出库流程调用）
     */
    public static function releaseLocationOf(int $packageId): void
    {
        Database::pdo()->prepare(
            'UPDATE ' . Database::table('warehouse_locations') . '
             SET package_id = NULL, status = 1 WHERE package_id = :pid'
        )->execute([':pid' => $packageId]);
    }

    /**
     * 登记异常件：写异常记录并标记包裹
     */
    public static function registerAbnormal(int $packageId, array $data, int $adminId): int
    {
        $pdo = Database::pdo();
        $pkg = $pdo->prepare('SELECT id, status FROM ' . Database::table('packages') . ' WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $pkg->execute([':id' => $packageId]);
        $row = $pkg->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('包裹不存在');
        }
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO ' . Database::table('package_abnormals') . '
                    (package_id, abnormal_type, description, images, status)
                 VALUES (:pid, :type, :desc, :images, 0)'
            );
            $ins->execute([
                ':pid'    => $packageId,
                ':type'   => $data['abnormal_type'],
                ':desc'   => $data['description'],
                ':images' => $data['images'] !== '' ? $data['images'] : null,
            ]);
            $abnormalId = (int) $pdo->lastInsertId();
            // 标记异常并置异常状态（已出库/已完成的不再回退状态）
            $pdo->prepare(
                'UPDATE ' . Database::table('packages') . '
                 SET is_abnormal = 1, status = IF(status BETWEEN 2 AND 9, 15, status) WHERE id = :id'
            )->execute([':id' => $packageId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $abnormalId;
    }

    /**
     * 待处理异常件列表（本仓库，含用户提交的处置申请）
     * @return array{rows: array, total: int}
     */
    public static function pendingAbnormals(int $warehouseId, string $keyword, int $limit, int $offset): array
    {
        $where = ['p.deleted_at IS NULL', 'p.warehouse_id = :wh'];
        $params = [':wh' => $warehouseId];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['p.package_no', 'p.tracking_no', 'p.user_code', 'u.user_no'], $params, $keyword, 'akw');
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('package_abnormals') . ' a
             JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
             LEFT JOIN ' . Database::table('users') . " u ON u.id = p.user_id WHERE {$whereSql}"
        );
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT a.id, a.package_id, a.abnormal_type, a.description, a.images, a.status, a.created_at,
                    p.package_no, p.tracking_no, p.status AS package_status, p.user_id, p.user_code, p.item_name,
                    u.user_no, u.username
             FROM ' . Database::table('package_abnormals') . ' a
             JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
             LEFT JOIN ' . Database::table('users') . " u ON u.id = p.user_id
             WHERE {$whereSql} ORDER BY a.id ASC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 未处理异常件数（导航徽标）
     */
    public static function warehouseAbnormalCount(int $warehouseId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('package_abnormals') . ' a
             JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
             WHERE a.status = 0 AND p.deleted_at IS NULL AND p.warehouse_id = :wh'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 异常处理统计：按处理状态分组计数
     */
    public static function abnormalStats(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.status, COUNT(*) AS c FROM ' . Database::table('package_abnormals') . ' a
             JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
             WHERE p.deleted_at IS NULL AND p.warehouse_id = :wh GROUP BY a.status'
        );
        $stmt->execute([':wh' => $warehouseId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    /**
     * 处理异常件：写处理结果；处置申请（退运6/销毁7）同时落地包裹最终状态
     * @param int $disposal 0=仅处理并恢复在库 1=执行退运 2=执行销毁
     */
    public static function handleAbnormal(int $abnormalId, int $warehouseId, int $adminId, string $result, int $disposal): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT a.id, a.package_id, a.abnormal_type, a.status, p.status AS package_status, p.warehouse_id, p.is_abnormal
             FROM ' . Database::table('package_abnormals') . ' a
             JOIN ' . Database::table('packages') . ' p ON p.id = a.package_id
             WHERE a.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $abnormalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('异常记录不存在');
        }
        if ((int) $row['warehouse_id'] !== $warehouseId) {
            throw new InvalidArgumentException('该异常件不属于当前仓库');
        }
        if ((int) $row['status'] !== 0) {
            throw new RuntimeException('该异常已处理');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE ' . Database::table('package_abnormals') . '
                 SET status = 1, handle_admin_id = :aid, handle_result = :result, handled_at = NOW()
                 WHERE id = :id AND status = 0'
            )->execute([':aid' => $adminId, ':result' => $result, ':id' => $abnormalId]);

            $packageId = (int) $row['package_id'];
            // 处置落地：1退运→16 已退回；2销毁→17 已销毁；否则视为无异常，恢复在库状态
            if ($disposal === 1 || $disposal === 2) {
                $pdo->prepare(
                    'UPDATE ' . Database::table('packages') . ' SET status = :st, is_abnormal = 0 WHERE id = :id'
                )->execute([':st' => $disposal === 1 ? 16 : 17, ':id' => $packageId]);
            } else {
                $pdo->prepare(
                    'UPDATE ' . Database::table('packages') . '
                     SET is_abnormal = 0, status = IF(status = 15, 2, status) WHERE id = :id'
                )->execute([':id' => $packageId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // 退运/销毁后若该运单下包裹已全部处置且未出库，自动退还运费（独立事务，失败由定时任务兜底）
        if ($disposal === 1 || $disposal === 2) {
            try {
                $waybill = self::latestWaybillOf((int) $row['package_id']);
                if ($waybill !== null) {
                    WaybillModel::refundIfAllPackagesDisposed((int) $waybill['id'], $adminId);
                }
            } catch (\Throwable $e) {
                \App\Core\Logger::write('refund', [
                    'event' => 'disposed_refund_failed',
                    'package_id' => (int) $row['package_id'],
                    'error' => $e->getMessage(),
                ], 'error');
            }
        }
    }

    /**
     * 预估仓储费：超出「免费期（仓库免费天数 + 会员额外天数）+ 宽限期」后按件/天计（只读展示，不落库）
     * $freeStorageDays 传 null 时按包裹所属仓库的免费期自动查询；$memberLevel 用于叠加会员额外免仓储天数
     */
    public static function estimatedStorageFee(array $package, ?int $freeStorageDays = null, int $memberLevel = 0): float
    {
        if (empty($package['inbound_at'])) {
            return 0.0;
        }
        $graceDays = (int) SettingsModel::get('storage_grace_days', '3');
        $pricePerItemDay = (float) SettingsModel::get('storage_fee_per_item_day', '1.00');
        $inbound = strtotime((string) $package['inbound_at']);
        if ($inbound === false) {
            return 0.0;
        }
        $freeDays = self::freeStorageDays($package, $freeStorageDays, $memberLevel);
        $days = (int) floor((time() - $inbound) / 86400);
        $billableDays = max(0, $days - $freeDays - $graceDays);
        if ($billableDays === 0) {
            return 0.0;
        }
        return round($billableDays * $pricePerItemDay, 2);
    }

    /**
     * 包裹的免费仓储天数：仓库免费期 + 会员等级额外天数
     * 会员天数按当前等级实时计算（等级只升不降，只会让免费期更宽，不会出现突然计费）
     */
    public static function freeStorageDays(array $package, ?int $freeStorageDays = null, int $memberLevel = 0): int
    {
        $freeDays = $freeStorageDays;
        if ($freeDays === null) {
            $freeDays = 0;
            if (!empty($package['warehouse_id'])) {
                $stmt = Database::pdo()->prepare(
                    'SELECT free_storage_days FROM ' . Database::table('warehouses') . ' WHERE id = :id'
                );
                $stmt->execute([':id' => (int) $package['warehouse_id']]);
                $freeDays = (int) $stmt->fetchColumn();
            }
        }
        if ($memberLevel > 0) {
            $level = \App\Models\MemberLevelModel::find($memberLevel);
            $freeDays += (int) ($level['extra_storage_days'] ?? 0);
        }
        return $freeDays;
    }

    /**
     * 会员免费仓储倒计时：按在库包裹逐个算到期时刻，取最紧迫的一件作为基准
     * @return array{package_count: int, free_days: int, due_at: string, remain_seconds: int, overdue_days: int, est_fee: float}|null
     */
    public static function storageCountdown(int $userId, int $memberLevel = 0): ?array
    {
        if ($memberLevel <= 0) {
            $stmt = Database::pdo()->prepare('SELECT member_level FROM ' . Database::table('users') . ' WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $memberLevel = (int) $stmt->fetchColumn();
        }
        $inHouse = implode(',', array_map('intval', array_keys(self::WH_STATUSES)));
        $stmt = Database::pdo()->prepare(
            'SELECT id, warehouse_id, inbound_at, weight, volume_weight FROM ' . Database::table('packages') . "
             WHERE user_id = :uid AND deleted_at IS NULL AND inbound_at IS NOT NULL AND status IN ({$inHouse})
             ORDER BY inbound_at ASC LIMIT 200"
        );
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return null;
        }
        $graceDays = (int) SettingsModel::get('storage_grace_days', '3');
        $earliest = null;
        $earliestDue = null;
        $estFee = 0.0;
        foreach ($rows as $row) {
            $freeDays = self::freeStorageDays($row, null, $memberLevel);
            $due = (int) strtotime((string) $row['inbound_at']) + $freeDays * 86400;
            if ($earliestDue === null || $due < $earliestDue) {
                $earliestDue = $due;
                $earliest = ['row' => $row, 'free_days' => $freeDays];
            }
            $estFee += self::estimatedStorageFee($row, null, $memberLevel);
        }
        $now = time();
        $overdueDays = max(0, (int) floor(($now - (int) $earliestDue) / 86400) - $graceDays);
        return [
            'package_count' => count($rows),
            'free_days'     => (int) $earliest['free_days'],
            'due_at'        => date('Y-m-d H:i', (int) $earliestDue),
            'remain_seconds'=> max(0, (int) $earliestDue - $now),
            'overdue_days'  => max(0, $overdueDays),
            'est_fee'       => round($estFee, 2),
        ];
    }

    /**
     * 生成业务单号：前缀 + 年月日时分秒 + 4 位随机，冲突则重试
     */
    private static function nextNo(PDO $pdo, string $prefix, string $column): string
    {
        for ($i = 0; $i < 5; $i++) {
            $no = $prefix . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('packages') . " WHERE {$column} = :no");
            $stmt->execute([':no' => $no]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $no;
            }
        }
        throw new RuntimeException('单号生成失败，请稍后重试');
    }
}
