<?php declare(strict_types=1);
/**
 * @file app/Models/WaybillModel.php
 * @description 运单与报关明细数据访问（订单运单管理、清关管理共用）
 * @author AI
 * @version 0.6
 * @date 2026-09-15
 */

namespace App\Models;

use App\Core\Database;
use App\Enums\Status;
use App\Services\FreightService;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class WaybillModel
{
    /** 出库交接完成：等待客服录入轨迹并推进运输段 */
    private const STATUS_OUTBOUND = 5;
    /** 已进入国际运输与清关段的状态（运输中 / 清关中） */
    private const STATUSES_IN_TRANSIT = [6, 7];
    /** 清关终态（已放行 / 已完税），到这两个状态即无需再维护 */
    private const CLEAR_FINAL = [3, 5];

    /**
     * 运单流转时同步更新关联包裹的状态：包裹状态跟随运单的实际进度
     * 只映射「同一件事」的阶段（待出库→出库→运输→清关→派送→签收）与拦截终态，
     * 取消已由取消流程把包裹释放回「已认领」，不在此列；
     * 拦截同步为「已拦截」后，若后续走异常件处置（退运/销毁/恢复），
     * 异常流程直接改写包裹状态，优先级高于拦截标记
     */
    private const PACKAGE_STATUS_ON_WAYBILL = [
        4 => 9,   // 待出库 → 包裹待出库
        5 => 10,  // 已出库 → 包裹已出库
        6 => 11,  // 运输中 → 包裹运输中
        7 => 12,  // 清关中 → 包裹清关中
        8 => 13,  // 派送中 → 包裹派送中
        9 => 14,  // 已签收 → 包裹已签收
        12 => 18, // 已拦截 → 包裹已拦截
    ];

    /**
     * 后台运单检索（关键词命中运单号/承运单号/物流商单号，可按状态过滤）
     * @return array{rows: array, total: int}
     */
    public static function search(string $keyword, int $status, int $limit, int $offset): array
    {
        $where = ['w.deleted_at IS NULL'];
        $params = [];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['w.waybill_no', 'w.carrier_tracking_no', 'w.channel_order_no', 'w.receiver_name'], $params, $keyword);
        }
        if ($status >= 0) {
            $where[] = 'w.status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('waybills') . " w WHERE {$whereSql}");
        $countStmt->execute($params);
        $listStmt = $pdo->prepare(
            'SELECT w.id, w.waybill_no, w.user_id, w.bill_type, w.package_count, w.receiver_name, w.dest_country,
                    w.carrier, w.carrier_tracking_no, w.total_amount, w.due_amount, w.currency, w.status, w.clear_status,
                    w.paid_at, w.outbound_at, w.created_at
             FROM ' . Database::table('waybills') . " w WHERE {$whereSql} ORDER BY w.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    /**
     * 各状态运单数
     */
    public static function statusCounts(): array
    {
        $rows = Database::pdo()->query(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('waybills') . ' WHERE deleted_at IS NULL GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'c', 'status');
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, user_id, waybill_no, status, cancel_reason, deleted_at FROM ' . Database::table('waybills') . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 管理端运单详情（跨仓库，不限定归属）：运单全字段 + 用户 + 物流渠道
     * 供订单运单「查看详情」展示，客服据此答复运费、渠道、轨迹等咨询
     */
    public static function findDetailForAdmin(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT w.*, u.user_no, u.username, u.nickname, u.phone, u.email,
                    c.name AS channel_name, c.aging_desc
             FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = w.user_id
             LEFT JOIN ' . Database::table('channels') . ' c ON c.id = w.channel_id
             WHERE w.id = :id AND w.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 状态流转：更新状态 + 写状态流转日志 + 同步关联包裹状态
     * @param int $operatorType 操作方 1用户 2仓库员工 3管理端 4系统自动
     */
    public static function changeStatus(int $id, int $toStatus, int $operatorId, string $remark, int $operatorType = 3): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $cur = $pdo->prepare('SELECT status FROM ' . Database::table('waybills') . ' WHERE id = :id FOR UPDATE');
            $cur->execute([':id' => $id]);
            $from = $cur->fetchColumn();
            if ($from === false) {
                throw new RuntimeException('运单不存在');
            }
            $upd = $pdo->prepare('UPDATE ' . Database::table('waybills') . ' SET status = :to, cancel_reason = :remark WHERE id = :id');
            $upd->execute([':to' => $toStatus, ':remark' => $remark !== '' ? $remark : null, ':id' => $id]);
            if ($toStatus === 9) {
                // 签收时间以首次落地为准，重复推进不改写（手工填轨迹与自动签收共用）
                $pdo->prepare('UPDATE ' . Database::table('waybills') . ' SET signed_at = COALESCE(signed_at, NOW()) WHERE id = :id')
                    ->execute([':id' => $id]);
            }
            $log = $pdo->prepare(
                'INSERT INTO ' . Database::table('waybill_status_logs') . ' (waybill_id, from_status, to_status, operator_type, operator_id, remark)
                 VALUES (:wid, :from_s, :to_s, :otype, :oid, :remark)'
            );
            $log->execute([
                ':wid' => $id,
                ':from_s' => $from !== null ? (int) $from : null,
                ':to_s' => $toStatus,
                ':otype' => $operatorType,
                ':oid' => $operatorId,
                ':remark' => $remark !== '' ? $remark : null,
            ]);
            self::syncPackages($id, $toStatus);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // 签收即登记待激活积分（独立事务，重复推进由待激活账本唯一键拦截）
        if ($toStatus === 9) {
            \App\Services\PointsService::awardForSign($id);
        }
    }

    /**
     * 用户手动确认签收：运输中/清关中/派送中均可确认，签收后按 T+N 登记积分
     */
    public static function signByUser(int $id, int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status FROM ' . Database::table('waybills') . ' WHERE id = :id AND user_id = :uid AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            throw new InvalidArgumentException('运单不存在');
        }
        if (!in_array((int) $status, [5, 6, 7, 8], true)) {
            throw new InvalidArgumentException('当前状态不可确认签收：' . Status::waybill((int) $status));
        }
        self::changeStatus($id, 9, $userId, '用户确认签收', 1);
    }

    /**
     * 关联包裹状态跟随运单（同事务内执行，避免运单已推进而包裹状态落后）
     */
    private static function syncPackages(int $waybillId, int $waybillStatus): void
    {
        $packageStatus = self::PACKAGE_STATUS_ON_WAYBILL[$waybillStatus] ?? null;
        if ($packageStatus === null) {
            return;
        }
        Database::pdo()->prepare(
            'UPDATE ' . Database::table('packages') . ' p
             JOIN ' . Database::table('waybill_packages') . ' wp ON wp.package_id = p.id
             SET p.status = :st
             WHERE wp.waybill_id = :wid'
        )->execute([':st' => $packageStatus, ':wid' => $waybillId]);
    }

    /**
     * 历史数据校正：把关联包裹的状态按运单当前状态补齐（幂等，供升级脚本调用）
     * 本次改动之前包裹状态不会随运单推进，留在出库前/出库时的状态，用户端「我的包裹」因此长期显示旧状态
     * @return int 实际被修正的包裹数
     */
    public static function backfillPackageStatuses(): int
    {
        $pdo = Database::pdo();
        $fixed = 0;
        foreach (self::PACKAGE_STATUS_ON_WAYBILL as $waybillStatus => $packageStatus) {
            // 原生预处理不允许同名占位符复用，故目标状态与当前状态用两个参数
            $stmt = $pdo->prepare(
                'UPDATE ' . Database::table('packages') . ' p
                 JOIN ' . Database::table('waybill_packages') . ' wp ON wp.package_id = p.id
                 JOIN ' . Database::table('waybills') . ' w ON w.id = wp.waybill_id
                 SET p.status = :st
                 WHERE w.status = :ws AND w.deleted_at IS NULL AND p.status <> :current'
            );
            $stmt->execute([':st' => $packageStatus, ':ws' => $waybillStatus, ':current' => $packageStatus]);
            $fixed += $stmt->rowCount();
        }
        return $fixed;
    }

    /**
     * 报关明细（清关管理列表，联运单）
     * @return array{rows: array, total: int}
     */
    public static function declareList(string $keyword, int $limit, int $offset): array
    {
        // 清关管理只维护「已进入国际运输、尚未签收」的运单报关数据：
        // 待出库(4) 尚未安排出库、已签收(9) 清关已终结，两者都不需要在此维护清关状态
        $where = ['w.status NOT IN (4, 9)'];
        $params = [];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['d.name_cn', 'd.name_en', 'w.waybill_no', 'd.hs_code'], $params, $keyword, 'dkw');
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('declare_items') . ' d
             JOIN ' . Database::table('waybills') . " w ON w.id = d.waybill_id WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $listStmt = $pdo->prepare(
            'SELECT d.id, d.waybill_id, w.waybill_no, w.dest_country, w.clear_status, w.status AS waybill_status,
                    d.name_cn, d.name_en, d.hs_code, d.qty, d.unit, d.unit_price, d.currency, d.brand, d.created_at
             FROM ' . Database::table('declare_items') . ' d
             JOIN ' . Database::table('waybills') . " w ON w.id = d.waybill_id WHERE {$whereSql}
             ORDER BY d.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $listStmt->execute($params);
        return ['rows' => $listStmt->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $countStmt->fetchColumn()];
    }

    public static function updateClearStatus(int $waybillId, int $clearStatus): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE ' . Database::table('waybills') . ' SET clear_status = :cs WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':cs' => $clearStatus, ':id' => $waybillId]);
    }

    /**
     * 侧边栏待办：已出库但尚未录入轨迹的运单数
     * 出库后由客服录入轨迹推进运输段，推进后即离开该状态，数字随之归零
     */
    public static function pendingAdvanceCount(): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('waybills') . ' WHERE deleted_at IS NULL AND status = :s'
        );
        $stmt->execute([':s' => self::STATUS_OUTBOUND]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 侧边栏待办：已进入运输/清关段、清关状态尚未到终态的运单数
     * 终态为已放行(3)与已完税(5)；无报关明细的历史单据不计入，避免徽标指向空白列表
     */
    public static function pendingClearCount(): int
    {
        $inTransit = implode(', ', self::STATUSES_IN_TRANSIT);
        $final = implode(', ', self::CLEAR_FINAL);
        $stmt = Database::pdo()->query(
            'SELECT COUNT(*) FROM ' . Database::table('waybills') . " w
             WHERE w.deleted_at IS NULL AND w.status IN ({$inTransit}) AND w.clear_status NOT IN ({$final})
               AND EXISTS (SELECT 1 FROM " . Database::table('declare_items') . ' d WHERE d.waybill_id = w.id)'
        );
        return (int) $stmt->fetchColumn();
    }

    /* ================= 用户端 ================= */

    /**
     * 我的运单检索
     * @return array{rows: array, total: int}
     */
    public static function userSearch(int $userId, string $keyword, int $status, int $limit, int $offset): array
    {
        $where = ['w.user_id = :uid', 'w.deleted_at IS NULL'];
        $params = [':uid' => $userId];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['w.waybill_no', 'w.carrier_tracking_no', 'w.receiver_name'], $params, $keyword, 'wkw');
        }
        if ($status >= 0) {
            $where[] = 'w.status = :status';
            $params[':status'] = $status;
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('waybills') . " w WHERE {$whereSql}");
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT w.id, w.waybill_no, w.bill_type, w.package_count, w.receiver_name, w.dest_country,
                    w.carrier_tracking_no, w.total_amount, w.currency, w.status, w.clear_status, w.created_at,
                    c.name AS channel_name
             FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('channels') . " c ON c.id = w.channel_id
             WHERE {$whereSql} ORDER BY w.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    public static function userStatusCounts(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS c FROM ' . Database::table('waybills') . '
             WHERE user_id = :uid AND deleted_at IS NULL GROUP BY status'
        );
        $stmt->execute([':uid' => $userId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'c', 'status');
    }

    /**
     * 运单详情（含增值服务与关联包裹）
     */
    public static function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT w.*, c.name AS channel_name, c.aging_desc
             FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('channels') . ' c ON c.id = w.channel_id
             WHERE w.id = :id AND w.user_id = :uid AND w.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public static function services(int $waybillId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, service_name, price, qty, amount, status FROM ' . Database::table('waybill_services') . '
             WHERE waybill_id = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $waybillId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function packages(int $waybillId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.id, p.package_no, p.item_name, p.item_qty, p.weight FROM ' . Database::table('waybill_packages') . ' wp
             JOIN ' . Database::table('packages') . ' p ON p.id = wp.package_id
             WHERE wp.waybill_id = :id ORDER BY p.id ASC'
        );
        $stmt->execute([':id' => $waybillId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function tracking(int $waybillId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT event_time, status_desc, location, source FROM ' . Database::table('tracking_records') . '
             WHERE waybill_id = :id ORDER BY event_time DESC, id DESC'
        );
        $stmt->execute([':id' => $waybillId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function statusLogs(int $waybillId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT from_status, to_status, remark, created_at FROM ' . Database::table('waybill_status_logs') . '
             WHERE waybill_id = :id ORDER BY id ASC'
        );
        $stmt->execute([':id' => $waybillId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 用户下单：建单 + 关联包裹 + 增值服务 + 状态日志，整体一个事务
     */
    /**
     * 会员提交转运订单：同一事务内完成「扣运费 + 建单(直接已支付) + 锁定包裹 + 服务/报关明细」。
     * 提交即扣款：按入库称重试算的金额全额预扣，仓库打包重算后多退少补。
     * 余额不足抛出「余额不足」异常，控制器引导用户先充值。
     */
    public static function createForUser(int $userId, array $data, array $quote): int
    {
        // 负积分用户限制下单：退款扣回产生的欠分需先还清，避免坏账扩大
        $gate = PointsModel::negativeGate($userId);
        if ($gate !== null) {
            throw new RuntimeException($gate);
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // 余额校验与扣款（行锁防并发超扣）
            $lock = $pdo->prepare('SELECT balance FROM ' . Database::table('user_accounts') . ' WHERE user_id = :uid FOR UPDATE');
            $lock->execute([':uid' => $userId]);
            $balance = $lock->fetchColumn();
            if ($balance === false) {
                throw new InvalidArgumentException('用户账户不存在');
            }
            $total = (float) $quote['total_amount'];
            if ((float) $balance < $total) {
                throw new RuntimeException('余额不足，当前余额 ¥' . number_format((float) $balance, 2) . '，应付 ¥' . number_format($total, 2) . '，请先充值后再提交转运');
            }
            $pdo->prepare('UPDATE ' . Database::table('user_accounts') . ' SET balance = balance - :t, total_consume = total_consume + :tc WHERE user_id = :uid')
                ->execute([':t' => $total, ':tc' => $total, ':uid' => $userId]);
            $pdo->prepare(
                'INSERT INTO ' . Database::table('fund_flows') . '
                 (flow_no, user_id, flow_type, direction, amount, balance_before, balance_after, biz_type, biz_no, remark)
                 VALUES (:fno, :uid, 2, 2, :t, :before, :after, 2, :no, :remark)'
            )->execute([
                ':fno'    => 'FF' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                ':uid'    => $userId, ':t' => $total,
                ':before' => (float) $balance, ':after' => (float) $balance - $total,
                ':no'     => $data['waybill_no'],
                ':remark' => '提交转运支付运费（余额）',
            ]);

            $stmt = $pdo->prepare(
                'INSERT INTO ' . Database::table('waybills') . '
                 (waybill_no, user_id, warehouse_id, bill_type, package_count, receiver_name, receiver_phone,
                  dest_country, dest_address, channel_id, carrier, actual_weight, volume_weight, billable_weight,
                  freight_amount, fuel_fee, remote_fee, sensitive_fee, service_amount, insurance_amount, insurance_fee,
                  discount_amount, total_amount, currency, status, pay_method, paid_at, fee_detail, remark)
                 VALUES (:no, :uid, :wid, :btype, :pcount, :rname, :rphone, :country, :addr, :cid, :carrier,
                         :aw, :vw, :bw, :freight, :fuel, :remote, :sensitive, :service, :ins_amount, :ins_fee,
                         :discount, :total, :currency, 3, 1, NOW(), :detail, :remark)'
            );
            $stmt->execute([
                ':no' => $data['waybill_no'], ':uid' => $userId, ':wid' => $data['warehouse_id'],
                ':btype' => $data['bill_type'], ':pcount' => count($data['package_ids']),
                ':rname' => $data['receiver_name'], ':rphone' => $data['receiver_phone'],
                ':country' => $data['dest_country'],
                ':addr' => json_encode($data['dest_address'], JSON_UNESCAPED_UNICODE),
                ':cid' => $quote['channel_id'], ':carrier' => (string) ($data['carrier'] ?? ''),
                ':aw' => $data['actual_weight'], ':vw' => $data['volume_weight'], ':bw' => $data['billable_weight'],
                ':freight' => $quote['freight_amount'], ':fuel' => $quote['fuel_fee'],
                ':remote' => $quote['remote_fee'], ':sensitive' => $quote['sensitive_fee'],
                ':service' => $quote['service_amount'], ':ins_amount' => $quote['insurance_amount'],
                ':ins_fee' => $quote['insurance_fee'], ':discount' => $quote['discount_amount'],
                ':total' => $quote['total_amount'], ':currency' => $quote['currency'],
                ':detail' => json_encode($quote, JSON_UNESCAPED_UNICODE),
                ':remark' => (string) ($data['remark'] ?? ''),
            ]);
            $waybillId = (int) $pdo->lastInsertId();

            foreach ($data['package_ids'] as $pid) {
                $pdo->prepare('INSERT INTO ' . Database::table('waybill_packages') . ' (waybill_id, package_id) VALUES (:w, :p)')
                    ->execute([':w' => $waybillId, ':p' => $pid]);
                // 锁定包裹：仅「已认领」可转入待打包，防止重复下单
                $lockPkg = $pdo->prepare(
                    'UPDATE ' . Database::table('packages') . ' SET status = 5 WHERE id = :p AND user_id = :u AND status = 4'
                );
                $lockPkg->execute([':p' => $pid, ':u' => $userId]);
                if ($lockPkg->rowCount() !== 1) {
                    throw new InvalidArgumentException('包裹 #' . $pid . ' 状态已变化，请刷新后重新选择');
                }
                // 有进行中的退运/销毁申请时不可提交转运：仓库会按申请处置货物，转运指令与之矛盾
                $pend = $pdo->prepare(
                    'SELECT abnormal_type FROM ' . Database::table('package_abnormals') . '
                     WHERE package_id = :p AND abnormal_type IN (6, 7) AND status = 0 LIMIT 1'
                );
                $pend->execute([':p' => $pid]);
                $pendType = $pend->fetchColumn();
                if ($pendType !== false) {
                    $label = (int) $pendType === 7 ? '销毁' : '退运';
                    throw new InvalidArgumentException('包裹 #' . $pid . ' 有进行中的' . $label . '申请，请先取消该申请再提交转运');
                }
            }
            foreach ($quote['services'] as $svc) {
                $pdo->prepare(
                    'INSERT INTO ' . Database::table('waybill_services') . ' (waybill_id, service_id, service_name, price, qty, amount, status)
                     VALUES (:w, :sid, :name, :price, :qty, :amount, 0)'
                )->execute([
                    ':w' => $waybillId, ':sid' => $svc['id'], ':name' => $svc['name'],
                    ':price' => $svc['price'], ':qty' => $svc['qty'], ':amount' => $svc['amount'],
                ]);
            }
            // 报关明细随订单落库，供后台清关管理使用
            foreach ($data['declare_items'] ?? [] as $item) {
                $pdo->prepare(
                    'INSERT INTO ' . Database::table('declare_items') . '
                     (waybill_id, name_cn, name_en, hs_code, qty, unit, unit_price, currency, brand)
                     VALUES (:w, :cn, :en, :hs, :qty, :unit, :price, :currency, :brand)'
                )->execute([
                    ':w' => $waybillId,
                    ':cn' => $item['name_cn'], ':en' => $item['name_en'],
                    ':hs' => $item['hs_code'] !== '' ? $item['hs_code'] : null,
                    ':qty' => $item['qty'], ':unit' => $item['unit'],
                    ':price' => $item['unit_price'], ':currency' => $item['currency'],
                    ':brand' => $item['brand'] !== '' ? $item['brand'] : null,
                ]);
            }
            $pdo->prepare(
                'INSERT INTO ' . Database::table('waybill_status_logs') . ' (waybill_id, from_status, to_status, operator_type, remark, operator_id)
                 VALUES (:w, 0, 3, 1, :remark, :op)'
            )->execute([':w' => $waybillId, ':remark' => '用户提交转运订单并支付运费（余额）', ':op' => $userId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $waybillId;
    }

    /**
     * 用户取消运单：仅待提交/待称重/待支付可取消，并释放关联包裹
     */
    public static function cancelForUser(int $id, int $userId, string $reason): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT status FROM ' . Database::table('waybills') . ' WHERE id = :id AND user_id = :uid FOR UPDATE');
            $lock->execute([':id' => $id, ':uid' => $userId]);
            $status = $lock->fetchColumn();
            if ($status === false) {
                throw new \InvalidArgumentException('运单不存在');
            }
            if (!in_array((int) $status, [0, 1, 2, 3], true)) {
                throw new \InvalidArgumentException('运单已进入处理流程，无法取消，请联系客服');
            }
            // 已支付(3)或打包后待补款(2)的运单取消都需退款；
            // 待补款运单只退已付部分（总额 − 未缴差额），避免把用户没交的钱也退出去
            $refunded = 0.0;
            $waybillNo = '';
            if (in_array((int) $status, [2, 3], true)) {
                $billed = $pdo->prepare('SELECT total_amount, due_amount, waybill_no FROM ' . Database::table('waybills') . ' WHERE id = :id');
                $billed->execute([':id' => $id]);
                $bill = $billed->fetch(PDO::FETCH_ASSOC) ?: ['total_amount' => 0, 'due_amount' => 0, 'waybill_no' => ''];
                $waybillNo = (string) $bill['waybill_no'];
                $refunded = self::refundPaidFreight(
                    $pdo,
                    $userId,
                    $waybillNo,
                    (float) $bill['total_amount'],
                    (float) $bill['due_amount'],
                    null,
                    '取消运单退款'
                );
            }
            $pdo->prepare('UPDATE ' . Database::table('waybills') . ' SET status = 11, cancel_reason = :r, due_amount = 0.00 WHERE id = :id')
                ->execute([':r' => $reason, ':id' => $id]);
            // 已入运单但尚未出库的包裹（待打包/打包中/待补款）都要释放回「已认领」，否则取消后包裹会卡住
            $pdo->prepare(
                'UPDATE ' . Database::table('packages') . ' SET status = 4 WHERE id IN (
                    SELECT package_id FROM ' . Database::table('waybill_packages') . ' WHERE waybill_id = :id
                 ) AND status IN (5, 6, 7)'
            )->execute([':id' => $id]);
            $pdo->prepare(
                'INSERT INTO ' . Database::table('waybill_status_logs') . ' (waybill_id, from_status, to_status, operator_type, remark, operator_id)
                 VALUES (:w, :from, 11, 1, :remark, :op)'
            )->execute([':w' => $id, ':from' => (int) $status, ':remark' => '用户取消：' . $reason, ':op' => $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // 运单退出即回收积分：未到账取消、已到账扣回（独立事务，失败只记日志）
        if ($waybillNo !== '') {
            \App\Services\PointsService::cancelForWaybill($waybillNo, '取消运单');
        }
    }

    /**
     * 管理端取消运单：逻辑同用户取消，操作方记为管理端；已支付(3)的运单全额退款
     */
    public static function cancelByAdmin(int $id, int $operatorId, string $reason): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT status, user_id, total_amount, due_amount, waybill_no FROM ' . Database::table('waybills') . ' WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $id]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new \InvalidArgumentException('运单不存在');
            }
            $status = (int) $row['status'];
            if (!in_array($status, [0, 1, 2, 3], true)) {
                throw new \InvalidArgumentException('运单已进入处理流程，无法取消');
            }
            // 待补款(2)与已支付(3)都要退款，但待补款只退已付部分（总额 − 未缴差额）
            if (in_array($status, [2, 3], true)) {
                self::refundPaidFreight(
                    $pdo,
                    (int) $row['user_id'],
                    (string) $row['waybill_no'],
                    (float) $row['total_amount'],
                    (float) $row['due_amount'],
                    $operatorId,
                    '后台取消运单退款'
                );
            }
            $pdo->prepare('UPDATE ' . Database::table('waybills') . ' SET status = 11, cancel_reason = :r, due_amount = 0.00 WHERE id = :id')
                ->execute([':r' => $reason, ':id' => $id]);
            // 未出库的包裹（待打包/打包中/待补款）释放回「已认领」，避免取消后包裹卡在原状态
            $pdo->prepare(
                'UPDATE ' . Database::table('packages') . ' SET status = 4 WHERE id IN (
                    SELECT package_id FROM ' . Database::table('waybill_packages') . ' WHERE waybill_id = :id
                 ) AND status IN (5, 6, 7)'
            )->execute([':id' => $id]);
            $pdo->prepare(
                'INSERT INTO ' . Database::table('waybill_status_logs') . ' (waybill_id, from_status, to_status, operator_type, operator_id, remark)
                 VALUES (:w, :from, 11, 3, :op, :remark)'
            )->execute([':w' => $id, ':from' => $status, ':op' => $operatorId, ':remark' => '后台取消：' . $reason]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        \App\Services\PointsService::cancelForWaybill((string) $row['waybill_no'], '取消运单');
    }

    /**
     * 退还运单已付运费（总额 − 未缴差额），走统一资金账本入口
     * 调用方须已在事务中，本方法只写账本不做事务控制
     */
    private static function refundPaidFreight(
        PDO $pdo,
        int $userId,
        string $waybillNo,
        float $totalAmount,
        float $dueAmount,
        ?int $operatorId,
        string $remark
    ): float {
        $refund = round(max(0.0, $totalAmount - $dueAmount), 2);
        if ($refund <= 0) {
            return 0.0;
        }
        FundFlowModel::record($userId, 3, 1, $refund, 2, $waybillNo, $operatorId, $remark);
        return $refund;
    }

    /**
     * 运单下包裹全部退运/销毁时自动退还运费（未出库限定，幂等防重复退）
     * 由仓库端处置完毕后触发，失败由定时任务兜底重试
     */
    public static function refundIfAllPackagesDisposed(int $id, ?int $operatorId = null): bool
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare(
                'SELECT status, user_id, total_amount, due_amount, waybill_no, refunded_at FROM ' . Database::table('waybills') . '
                 WHERE id = :id AND deleted_at IS NULL FOR UPDATE'
            );
            $lock->execute([':id' => $id]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $pdo->rollBack();
                return false;
            }
            // 已出库/运输中的运单不退（运输已发生）；已退过的不再重复退
            if (!in_array((int) $row['status'], [2, 3, 4], true) || $row['refunded_at'] !== null) {
                $pdo->rollBack();
                return false;
            }
            $pkg = $pdo->prepare(
                'SELECT COUNT(*) AS total, SUM(CASE WHEN p.status IN (16, 17) THEN 1 ELSE 0 END) AS disposed
                 FROM ' . Database::table('waybill_packages') . ' wp
                 JOIN ' . Database::table('packages') . ' p ON p.id = wp.package_id
                 WHERE wp.waybill_id = :id'
            );
            $pkg->execute([':id' => $id]);
            $stat = $pkg->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'disposed' => 0];
            $total = (int) $stat['total'];
            if ($total <= 0 || (int) $stat['disposed'] !== $total) {
                $pdo->rollBack();
                return false;
            }
            $fromStatus = (int) $row['status'];
            $refunded = self::refundPaidFreight(
                $pdo,
                (int) $row['user_id'],
                (string) $row['waybill_no'],
                (float) $row['total_amount'],
                (float) $row['due_amount'],
                $operatorId,
                '退运/销毁处理完毕，自动退还运费'
            );
            $upd = $pdo->prepare(
                'UPDATE ' . Database::table('waybills') . '
                 SET status = 11, due_amount = 0.00, refunded_at = NOW(), cancel_reason = :r
                 WHERE id = :id AND status = :old'
            );
            $upd->execute([':r' => '包裹全部退运/销毁，自动取消并退款', ':id' => $id, ':old' => $fromStatus]);
            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }
            $pdo->prepare(
                'INSERT INTO ' . Database::table('waybill_status_logs') . ' (waybill_id, from_status, to_status, operator_type, operator_id, remark)
                 VALUES (:w, :from, 11, 4, :op, :remark)'
            )->execute([':w' => $id, ':from' => $fromStatus, ':op' => $operatorId ?? 0, ':remark' => '包裹全部退运/销毁，自动退款']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // 运单退出即回收积分（独立事务）
        \App\Services\PointsService::cancelForWaybill((string) $row['waybill_no'], '退运/销毁');
        return $refunded > 0;
    }

    /**
     * 支付成功后回写运单状态
     */
    public static function markPaid(int $id, int $payMethod): void
    {
        Database::pdo()->prepare(
            'UPDATE ' . Database::table('waybills') . ' SET status = 3, pay_method = :m, paid_at = NOW() WHERE id = :id'
        )->execute([':m' => $payMethod, ':id' => $id]);
    }

    /* ================= 仓库端作业 ================= */

    /**
     * 待打包运单（本仓库，用户已提交尚未完成打包测重）
     * @return array{rows: array, total: int}
     */
    public static function pendingPacking(int $warehouseId, string $keyword, int $limit, int $offset): array
    {
        // 新流程：提交时已支付运费；待打包(3)与已打包(4)保留在列表中供作业与查看，
        // 打包后差额挂起(2)的运单也留在此页，仓库能看到卡在哪一步、等谁补款
        $where = ['w.warehouse_id = :wh', 'w.deleted_at IS NULL', 'w.status IN (2, 3, 4)'];
        $params = [':wh' => $warehouseId];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['w.waybill_no', 'w.receiver_name', 'u.user_no', 'u.username'], $params, $keyword, 'pkw');
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('users') . " u ON u.id = w.user_id WHERE {$whereSql}"
        );
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT w.id, w.waybill_no, w.user_id, w.bill_type, w.package_count, w.dest_country, w.channel_id,
                    w.actual_weight, w.billable_weight, w.total_amount, w.due_amount, w.status, w.created_at, w.remark,
                    u.user_no, u.username, u.nickname,
                    c.name AS channel_name
             FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = w.user_id
             LEFT JOIN ' . Database::table('channels') . " c ON c.id = w.channel_id
             WHERE {$whereSql} ORDER BY w.id ASC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 待出库运单（本仓库，用户已支付等待交接）
     * @return array{rows: array, total: int}
     */
    public static function pendingOutbound(int $warehouseId, string $keyword, int $limit, int $offset): array
    {
        // 新流程：打包完成(4 待出库)的运单进入出库队列
        $where = ['w.warehouse_id = :wh', 'w.deleted_at IS NULL', 'w.status IN (4, 5)'];
        $params = [':wh' => $warehouseId];
        if ($keyword !== '') {
            $where[] = KeywordFilter::any(['w.waybill_no', 'w.receiver_name', 'u.user_no', 'u.username', 'w.carrier_tracking_no'], $params, $keyword, 'okw');
        }
        $whereSql = implode(' AND ', $where);
        $pdo = Database::pdo();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('users') . " u ON u.id = w.user_id WHERE {$whereSql}"
        );
        $count->execute($params);
        $list = $pdo->prepare(
            'SELECT w.id, w.waybill_no, w.bill_type, w.package_count, w.dest_country, w.channel_id,
                    w.carrier, w.carrier_tracking_no, c.name AS channel_name,
                    w.billable_weight, w.total_amount, w.currency, w.status, w.paid_at, w.created_at,
                    u.user_no, u.username, u.nickname
             FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = w.user_id
             LEFT JOIN ' . Database::table('channels') . " c ON c.id = w.channel_id
             WHERE {$whereSql} ORDER BY w.status ASC, w.id ASC LIMIT {$limit} OFFSET {$offset}"
        );
        $list->execute($params);
        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $count->fetchColumn()];
    }

    /**
     * 待打包/待出库/待补款件数（导航徽标与仓库提示）
     */
    public static function warehousePendingCounts(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS packing,
                SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) AS outbound,
                SUM(CASE WHEN status = 2 AND due_amount > 0 THEN 1 ELSE 0 END) AS due
             FROM ' . Database::table('waybills') . ' WHERE deleted_at IS NULL AND warehouse_id = :wh'
        );
        $stmt->execute([':wh' => $warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'packing'  => (int) ($row['packing'] ?? 0),
            'outbound' => (int) ($row['outbound'] ?? 0),
            'due'      => (int) ($row['due'] ?? 0),
        ];
    }

    /**
     * 打包统计：待打包 / 已打包 / 今日完成
     */
    public static function packingStats(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) AS packed,
                SUM(CASE WHEN status = 4 AND packed_at >= CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN status = 2 AND due_amount > 0 THEN 1 ELSE 0 END) AS due
             FROM ' . Database::table('waybills') . ' WHERE deleted_at IS NULL AND warehouse_id = :wh'
        );
        $stmt->execute([':wh' => $warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'packed'  => (int) ($row['packed'] ?? 0),
            'today'   => (int) ($row['today'] ?? 0),
            'due'     => (int) ($row['due'] ?? 0),
        ];
    }

    /**
     * 出库统计：待出库 / 已出库 / 今日出库
     */
    public static function outboundStats(int $warehouseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT
                SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END) AS shipped,
                SUM(CASE WHEN status = 5 AND outbound_at >= CURDATE() THEN 1 ELSE 0 END) AS today
             FROM ' . Database::table('waybills') . ' WHERE deleted_at IS NULL AND warehouse_id = :wh'
        );
        $stmt->execute([':wh' => $warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['pending' => (int) ($row['pending'] ?? 0), 'shipped' => (int) ($row['shipped'] ?? 0), 'today' => (int) ($row['today'] ?? 0)];
    }

    /**
     * 仓库侧运单详情（含包裹清单与收货地址），供打包/出库作业页使用
     */
    public static function findForWarehouse(int $id, int $warehouseId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT w.*, u.user_no, u.username, u.nickname, u.phone, u.email, u.member_level,
                    c.name AS channel_name, c.volume_divisor
             FROM ' . Database::table('waybills') . ' w
             LEFT JOIN ' . Database::table('users') . ' u ON u.id = w.user_id
             LEFT JOIN ' . Database::table('channels') . ' c ON c.id = w.channel_id
             WHERE w.id = :id AND w.warehouse_id = :wh AND w.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':wh' => $warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * 运单内的包裹清单（含库位，便于打包拣货）
     */
    public static function packagesOf(int $waybillId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.id, p.package_no, p.tracking_no, p.item_name, p.item_qty, p.category, p.declared_value,
                    p.currency, p.weight, p.volume_weight, p.status, p.location_id, p.inbound_at, p.storage_fee,
                    l.code AS location_code
             FROM ' . Database::table('waybill_packages') . ' wp
             JOIN ' . Database::table('packages') . ' p ON p.id = wp.package_id
             LEFT JOIN ' . Database::table('warehouse_locations') . ' l ON l.id = p.location_id
             WHERE wp.waybill_id = :wid ORDER BY p.id ASC'
        );
        $stmt->execute([':wid' => $waybillId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 管理端录入物流轨迹：写轨迹记录；签收时回填签收时间（清关子状态由清关管理页维护）
     */
    public static function addTracking(int $id, array $data, int $operatorId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO ' . Database::table('tracking_records') . '
                 (waybill_id, event_time, status_desc, location, source)
                 VALUES (:wid, :etime, :desc, :loc, 1)'
            );
            $ins->execute([
                ':wid' => $id,
                ':etime' => $data['event_time'],
                ':desc' => $data['status_desc'],
                ':loc' => $data['location'] !== '' ? $data['location'] : null,
            ]);
            if ((int) $data['to_status'] === 9) {
                $pdo->prepare('UPDATE ' . Database::table('waybills') . ' SET signed_at = :etime WHERE id = :id')
                    ->execute([':etime' => $data['event_time'], ':id' => $id]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * 打包完成 + 按实际打包重量重算运费，随后转入「待支付」交由用户付款
     * 计费重量取「打包实重」与「打包体积重」的较大者，与用户下单口径一致
     * @return array{quote: array, billable: float, total: float}
     */
    public static function packParcel(int $id, int $warehouseId, array $data, int $adminId): array
    {
        $pdo = Database::pdo();
        $waybill = self::findForWarehouse($id, $warehouseId);
        if ($waybill === null) {
            throw new InvalidArgumentException('运单不存在或不属于当前仓库');
        }
        if (!in_array((int) $waybill['status'], [3], true)) {
            throw new InvalidArgumentException('仅「已支付」的运单可打包，当前状态：' . Status::waybill((int) $waybill['status']));
        }

        $packages = self::packagesOf($id);
        if ($packages === []) {
            throw new RuntimeException('该运单没有关联包裹，无法打包');
        }
        $declared = 0.0;
        $goodsType = 1;
        $storageFee = 0.0;
        $freeDays = (int) $pdo->query(
            'SELECT free_storage_days FROM ' . Database::table('warehouses') . ' WHERE id = ' . (int) $waybill['warehouse_id']
        )->fetchColumn();
        // 会员额外免仓储天数在结算时一并叠加，与前端预估口径保持一致
        $level = \App\Models\MemberLevelModel::find((int) ($waybill['member_level'] ?? 0));
        $memberDays = (int) ($level['extra_storage_days'] ?? 0);
        foreach ($packages as $p) {
            $declared += (float) $p['declared_value'];
            $goodsType = max($goodsType, (int) $p['category']);
            // 逾期仓储费随打包并入运单结算（免费期 + 会员额外天数 + 宽限期后按件/天核算）
            $storageFee += PackageModel::estimatedStorageFee($p, $freeDays + $memberDays);
        }

        // 体积重按打包后的箱规与渠道系数换算（渠道未配置系数时回落系统默认值）
        $volumeWeight = PackageModel::volumeWeight(
            (float) $data['length'],
            (float) $data['width'],
            (float) $data['height'],
            (float) ($waybill['volume_divisor'] ?? 0)
        );
        $actualWeight = round((float) $data['actual_weight'], 3);
        $billable = round(max($actualWeight, $volumeWeight), 3);
        if ($billable <= 0) {
            throw new InvalidArgumentException('请填写有效的打包重量');
        }

        // 增值服务按原单数量带入重算
        $services = [];
        $svc = $pdo->prepare('SELECT service_id, qty FROM ' . Database::table('waybill_services') . ' WHERE waybill_id = :wid');
        $svc->execute([':wid' => $id]);
        foreach ($svc->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $services[(int) $row['service_id']] = max(1, (int) $row['qty']);
        }

        $quote = FreightService::quote([
            'channel_id'       => (int) $waybill['channel_id'],
            'dest_country'     => (string) $waybill['dest_country'],
            'weight'           => $billable,
            'declared_value'   => $declared,
            'goods_type'       => $goodsType,
            'services'         => $services,
            'insurance_amount' => (float) $waybill['insurance_amount'],
            'member_level'     => (int) ($waybill['member_level'] ?? 0),
        ]);
        if (!$quote['ok']) {
            throw new RuntimeException('运费重算失败：' . $quote['message']);
        }
        $detail = $quote['detail'];
        // 仓储费在运费重算之后叠加，且写入费用明细快照供对账
        $detail['storage_fee'] = round($storageFee, 2);
        $totalWithStorage = round((float) $detail['total_amount'] + $storageFee, 2);

        // 差额需用户补缴时挂起（余额不足），补缴前运单停在「待支付」不许出库
        $pendingPay = false;
        $dueAmount = 0.0;
        $diff = 0.0;
        $pdo->beginTransaction();
        try {
            // 重算差额多退少补：提交时按入库称重预扣，打包后以实际重量结算
            $oldTotal = (float) $waybill['total_amount'];
            $diff = round($totalWithStorage - $oldTotal, 2);
            $settleNote = '';
            if (abs($diff) >= 0.01) {
                $accLock = $pdo->prepare('SELECT balance FROM ' . Database::table('user_accounts') . ' WHERE user_id = :uid FOR UPDATE');
                $accLock->execute([':uid' => (int) $waybill['user_id']]);
                $balance = (float) $accLock->fetchColumn();
                if ($diff > 0) {
                    if ($balance < $diff) {
                        // 余额不足不阻塞打包：差额挂账，运单转「待支付」，用户充值后补缴即可放行出库
                        // 此时未发生真实收付，故不落资金流水
                        $pendingPay = true;
                        $dueAmount = $diff;
                        $settleNote = '（待用户补差 ¥' . number_format($diff, 2) . '）';
                    } else {
                        $pdo->prepare('UPDATE ' . Database::table('user_accounts') . ' SET balance = balance - :d WHERE user_id = :uid')
                            ->execute([':d' => $diff, ':uid' => (int) $waybill['user_id']]);
                        $after = $balance - $diff;
                        $settleNote = '（补差 ¥' . number_format($diff, 2) . '）';
                    }
                } else {
                    $pdo->prepare('UPDATE ' . Database::table('user_accounts') . ' SET balance = balance + :d WHERE user_id = :uid')
                        ->execute([':d' => abs($diff), ':uid' => (int) $waybill['user_id']]);
                    $after = $balance + abs($diff);
                    $settleNote = '（退差 ¥' . number_format(abs($diff), 2) . '）';
                }
                if (!$pendingPay) {
                    $pdo->prepare(
                        'INSERT INTO ' . Database::table('fund_flows') . '
                         (flow_no, user_id, flow_type, direction, amount, balance_before, balance_after, biz_type, biz_no, remark)
                         VALUES (:fno, :uid, :ftype, :dir, :d, :before, :after, 2, :no, :remark)'
                    )->execute([
                        ':fno'    => 'FF' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                        ':uid'    => (int) $waybill['user_id'],
                        ':ftype'  => $diff > 0 ? 2 : 3,
                        ':dir'    => $diff > 0 ? 2 : 1,
                        ':d'      => abs($diff),
                        ':before' => $balance, ':after' => $after,
                        ':no'     => (string) $waybill['waybill_no'],
                        ':remark' => '打包重算' . ($diff > 0 ? '补差' : '退差'),
                    ]);
                }
            }

            $pdo->prepare(
                'UPDATE ' . Database::table('waybills') . '
                 SET actual_weight = :aw, volume_weight = :vw, billable_weight = :bw,
                     freight_amount = :freight, fuel_fee = :fuel, remote_fee = :remote, sensitive_fee = :sensitive,
                     service_amount = :service, insurance_amount = :ins_amount, insurance_fee = :ins_fee,
                     discount_amount = :discount, other_fee = :storage, total_amount = :total,
                     due_amount = :due, fee_detail = :detail,
                     carrier = :carrier, pack_length = :len, pack_width = :wid_w, pack_height = :hei,
                     admin_remark = :remark, packed_at = NOW(), packed_admin_id = :aid
                 WHERE id = :id AND status = 3'
            )->execute([
                ':aw' => $actualWeight, ':vw' => $volumeWeight, ':bw' => $billable,
                ':freight' => $detail['freight_amount'], ':fuel' => $detail['fuel_fee'],
                ':remote' => $detail['remote_fee'], ':sensitive' => $detail['sensitive_fee'],
                ':service' => $detail['service_amount'], ':ins_amount' => $detail['insurance_amount'],
                ':ins_fee' => $detail['insurance_fee'], ':discount' => $detail['discount_amount'],
                ':storage' => $detail['storage_fee'], ':total' => $totalWithStorage, ':due' => $dueAmount,
                ':detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
                ':carrier' => (string) ($detail['channel_name'] ?? $waybill['carrier'] ?? ''),
                ':len' => $data['length'], ':wid_w' => $data['width'], ':hei' => $data['height'],
                ':remark' => $data['remark'] !== '' ? mb_substr($data['remark'], 0, 500) : null,
                ':aid' => $adminId, ':id' => $id,
            ]);
            // 包裹置为打包完成；仓储费已并入运单，清零避免重复结算
            // 差额挂起时停在「待支付」，补缴后随运单流转到「待出库」
            foreach ($packages as $p) {
                $pdo->prepare('UPDATE ' . Database::table('packages') . ' SET status = :pst, storage_fee = 0 WHERE id = :pid AND status = 5')
                    ->execute([':pst' => $pendingPay ? 7 : 6, ':pid' => (int) $p['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // 状态流转写日志（操作方=仓库员工）
        $packNote = '仓库打包称重完成，实重 ' . $actualWeight . 'kg，计费重 ' . $billable . 'kg，运费 ¥' . $totalWithStorage
            . ($storageFee > 0 ? '（含仓储费 ¥' . number_format($storageFee, 2) . '）' : '') . $settleNote;
        if ($pendingPay) {
            // 差额未结清：运单停在「待支付」，等用户充值补缴后转「待出库」，避免欠款出库
            self::changeStatus($id, 2, $adminId, $packNote . '，待用户充值补缴差额后方可出库', 2);
            self::notifyDuePayment((int) $waybill['user_id'], (string) $waybill['waybill_no'], $dueAmount);
        } else {
            self::changeStatus($id, 4, $adminId, $packNote, 2);
        }
        return [
            'quote' => $detail, 'billable' => $billable, 'total' => $totalWithStorage, 'diff' => $diff,
            'pending_pay' => $pendingPay, 'due' => $dueAmount,
        ];
    }

    /**
     * 待补缴差额的运单（运单列表与侧边栏徽标共用）
     */
    public static function pendingPaymentCount(int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM ' . Database::table('waybills') . '
             WHERE user_id = :uid AND status = 2 AND due_amount > 0 AND deleted_at IS NULL'
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 补缴差额后放行：清零待补金额并转「待出库」，仓库随即可正常交接
     */
    public static function markDuePaid(int $id, int $payMethod): void
    {
        Database::pdo()->prepare(
            'UPDATE ' . Database::table('waybills') . '
             SET status = 4, due_amount = 0.00, pay_method = :m, paid_at = NOW() WHERE id = :id'
        )->execute([':m' => $payMethod, ':id' => $id]);
    }

    /**
     * 打包差额挂起时通知用户补缴：站内信留痕，失败不影响打包结果
     */
    private static function notifyDuePayment(int $userId, string $waybillNo, float $due): void
    {
        try {
            MessageModel::sendToUser(
                $userId,
                '转运订单需补缴运费差额',
                '您的运单 ' . $waybillNo . ' 已完成打包测重，按实际重量与体积重计费后需补缴差额 ¥'
                    . number_format($due, 2) . '。请在「转运订单」中完成支付，结清后仓库将安排出库。',
                2,
                $waybillNo
            );
        } catch (\Throwable $e) {
            // 通知失败不应影响打包与运单状态，用户仍可在订单列表看到待补款提示
        }
    }

    /**
     * 确认出库交接：写承运单号并流转为「已出库」，包裹同步出库、释放库位
     * 承运商取运单下单时用户选定的物流渠道（channels.name），保证「用户选渠道 → 仓库出库 → 用户端跟踪」全流程一致，
     * 仓库端不再手工填写，避免同一运单在两端显示不同承运商
     */
    public static function confirmOutbound(int $id, int $warehouseId, array $data, int $adminId): void
    {
        $pdo = Database::pdo();
        $waybill = self::findForWarehouse($id, $warehouseId);
        if ($waybill === null) {
            throw new InvalidArgumentException('运单不存在或不属于当前仓库');
        }
        if (!in_array((int) $waybill['status'], [4], true)) {
            throw new InvalidArgumentException('仅「待出库」的运单可出库，当前状态：' . Status::waybill((int) $waybill['status']));
        }

        // 渠道名称以运单关联的渠道为准；历史数据渠道已删时回落到运单已存的承运商
        $carrier = trim((string) ($waybill['channel_name'] ?? ''));
        if ($carrier === '') {
            $carrier = trim((string) $waybill['carrier']);
        }

        $packages = self::packagesOf($id);
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE ' . Database::table('waybills') . '
                 SET carrier = :carrier, carrier_tracking_no = :tracking, outbound_at = NOW(),
                     admin_remark = :remark
                 WHERE id = :id AND status IN (3, 4)'
            )->execute([
                ':carrier'  => $carrier,
                ':tracking' => $data['tracking_no'],
                ':remark'   => $data['remark'] !== '' ? mb_substr($data['remark'], 0, 500) : null,
                ':id'       => $id,
            ]);
            foreach ($packages as $p) {
                // 出库后包裹离开仓库：清空库位指向并释放占用，避免库位与包裹互相残留
                // 包裹状态由随后的 changeStatus 按运单→包裹映射统一写入，此处不再各写一份
                $pdo->prepare('UPDATE ' . Database::table('packages') . ' SET location_id = NULL WHERE id = :pid')
                    ->execute([':pid' => (int) $p['id']]);
                $pdo->prepare(
                    'UPDATE ' . Database::table('warehouse_locations') . ' SET package_id = NULL, status = 1 WHERE package_id = :pid'
                )->execute([':pid' => (int) $p['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        self::changeStatus($id, 5, $adminId, '仓库已出库交接，承运单号 ' . $data['tracking_no'], 2);
    }
}
