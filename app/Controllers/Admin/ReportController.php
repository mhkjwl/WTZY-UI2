<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/ReportController.php
 * @description 数据报表：用户增长 / 收支趋势 / 运单与渠道概览（纯 SQL 聚合 + CSS 图表）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Admin;

use App\Core\Database;
use PDO;

final class ReportController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('report.view');
        $pdo = Database::pdo();

        $days = 14;
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[date('Y-m-d', strtotime("-{$i} days"))] = ['date' => date('m-d', strtotime("-{$i} days")), 'users' => 0, 'recharge' => 0.0, 'orders' => 0];
        }

        foreach ($pdo->query(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM ' . Database::table('users') . '
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY) GROUP BY DATE(created_at)'
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($dates[$row['d']])) {
                $dates[$row['d']]['users'] = (int) $row['c'];
            }
        }
        foreach ($pdo->query(
            'SELECT DATE(created_at) AS d, SUM(amount) AS s FROM ' . Database::table('fund_flows') . '
             WHERE flow_type = 1 AND direction = 1 AND created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY) GROUP BY DATE(created_at)'
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($dates[$row['d']])) {
                $dates[$row['d']]['recharge'] = (float) $row['s'];
            }
        }
        foreach ($pdo->query(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM ' . Database::table('waybills') . '
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . $days . ' DAY) GROUP BY DATE(created_at)'
        )->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($dates[$row['d']])) {
                $dates[$row['d']]['orders'] = (int) $row['c'];
            }
        }

        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('users') . ' WHERE deleted_at IS NULL')->fetchColumn();
        $totalWaybills = (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::table('waybills') . ' WHERE deleted_at IS NULL')->fetchColumn();
        $totalRevenue = (float) $pdo->query(
            'SELECT IFNULL(SUM(amount),0) FROM ' . Database::table('fund_flows') . ' WHERE flow_type = 1 AND direction = 1'
        )->fetchColumn();
        $totalPackages = (int) $pdo->query(
            'SELECT COUNT(*) FROM ' . Database::table('packages') . ' WHERE status IN (2, 3, 4, 5, 6) AND deleted_at IS NULL'
        )->fetchColumn();

        // 包裹状态分布（官网仓库流转全链路：预报到签收 + 异常/退回/销毁）
        $packageCounts = array_map('intval', array_column(
            $pdo->query('SELECT status, COUNT(*) AS c FROM ' . Database::table('packages') . ' WHERE deleted_at IS NULL GROUP BY status')->fetchAll(PDO::FETCH_ASSOC),
            'c', 'status'
        ));
        $packageBuckets = [
            'inWarehouse' => ($packageCounts[2] ?? 0) + ($packageCounts[3] ?? 0) + ($packageCounts[4] ?? 0),
            'transit'     => ($packageCounts[10] ?? 0) + ($packageCounts[11] ?? 0) + ($packageCounts[12] ?? 0) + ($packageCounts[13] ?? 0),
            'signed'      => $packageCounts[14] ?? 0,
            'abnormal'    => ($packageCounts[15] ?? 0) + ($packageCounts[16] ?? 0) + ($packageCounts[17] ?? 0),
        ];
        $packageBuckets['total'] = array_sum($packageCounts);

        // 工单状态分布
        $ticketCounts = array_map('intval', array_column(
            $pdo->query('SELECT status, COUNT(*) AS c FROM ' . Database::table('tickets') . ' GROUP BY status')->fetchAll(PDO::FETCH_ASSOC),
            'c', 'status'
        ));
        $ticketBuckets = [
            'pending' => $ticketCounts[0] ?? 0,
            'process' => $ticketCounts[1] ?? 0,
            'replied' => $ticketCounts[2] ?? 0,
            'closed'  => $ticketCounts[3] ?? 0,
        ];
        $ticketBuckets['total'] = array_sum($ticketCounts);

        // 理赔状态分布与赔付金额
        $claimCounts = array_map('intval', array_column(
            $pdo->query('SELECT status, COUNT(*) AS c FROM ' . Database::table('claims') . ' GROUP BY status')->fetchAll(PDO::FETCH_ASSOC),
            'c', 'status'
        ));
        $claimBuckets = [
            'pending'  => $claimCounts[0] ?? 0,
            'process'  => $claimCounts[1] ?? 0,
            'reviewed' => ($claimCounts[2] ?? 0) + ($claimCounts[5] ?? 0) + ($claimCounts[6] ?? 0),
            'paid'     => $claimCounts[4] ?? 0,
            'rejected' => $claimCounts[3] ?? 0,
        ];
        $claimBuckets['total'] = array_sum($claimCounts);
        $claimPaidAmount = (float) $pdo->query(
            'SELECT IFNULL(SUM(approved_amount),0) FROM ' . Database::table('claims') . ' WHERE status = 4'
        )->fetchColumn();
        $claimApplying = $claimBuckets['pending'] + $claimBuckets['process'];

        $this->adminView('admin/reports', [
            'title'        => '数据报表',
            'days'         => array_values($dates),
            'maxUsers'     => max(1, max(array_column($dates, 'users'))),
            'maxRecharge'  => max(1.0, max(array_column($dates, 'recharge'))),
            'maxOrders'    => max(1, max(array_column($dates, 'orders'))),
            'totalUsers'   => $totalUsers,
            'totalWaybills'=> $totalWaybills,
            'totalRevenue' => $totalRevenue,
            'totalPackages'=> $totalPackages,
            'packageBuckets' => $packageBuckets,
            'ticketBuckets'  => $ticketBuckets,
            'claimBuckets'   => $claimBuckets,
            'claimPaidAmount' => $claimPaidAmount,
            'claimApplying'   => $claimApplying,
        ], 'reports', '数据报表');
    }
}
