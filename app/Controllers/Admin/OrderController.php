<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/OrderController.php
 * @description 订单运单管理：全量运单列表 / 状态筛选 / 取消与拦截（写状态流转日志）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\StaffAuth;
use App\Models\MessageModel;
use App\Models\WaybillModel;
use RuntimeException;

final class OrderController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('order.view');
        $this->renderList((string) Request::query('kw', ''), (int) Request::query('status', '-1'));
    }

    private function renderList(string $keyword, int $status): void
    {
        $page = $this->pageParams();
        $result = WaybillModel::search($keyword, $status, $page['limit'], $page['offset']);
        $data = [
            'title'   => '订单运单',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'status'  => $status,
            'counts'  => WaybillModel::statusCounts(),
            'base'    => '/admin/orders',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/orders_table', $data);
        }
        $this->adminView('admin/orders', $data, 'orders', '订单运单管理');
    }

    /**
     * 运单详情：运单/用户/费用明细 + 关联包裹 + 物流轨迹 + 状态流转记录
     */
    public function detail(string $id): never
    {
        $this->requireStaff();
        $this->enforce('order.view');
        $waybill = WaybillModel::findDetailForAdmin((int) $id);
        if ($waybill === null) {
            $this->adminView('admin/order_missing', ['title' => '运单不存在'], 'orders', '运单详情');
        }
        $this->adminView('admin/waybill_detail', [
            'title'     => '运单详情 · ' . $waybill['waybill_no'],
            'waybill'   => $waybill,
            'services'  => WaybillModel::services((int) $waybill['id']),
            'packages'  => WaybillModel::packages((int) $waybill['id']),
            'tracking'  => WaybillModel::tracking((int) $waybill['id']),
            'logs'      => WaybillModel::statusLogs((int) $waybill['id']),
            'feeDetail' => json_decode((string) ($waybill['fee_detail'] ?? '{}'), true) ?: [],
        ], 'orders', '运单详情');
    }

    /**
     * 取消运单：未打包的运单可取消；已支付(3)的运单取消时全额退款（提交即扣款流程）
     */
    public function cancel(): never
    {
        $this->requireStaff();
        $this->enforce('order.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $waybill = WaybillModel::findById($id);
        if ($waybill === null || $waybill['deleted_at'] !== null) {
            $this->fail('运单不存在');
        }
        if ((int) $waybill['status'] > 3) {
            $this->fail('当前状态不可取消（打包完成后请走拦截/售后流程）');
        }
        try {
            WaybillModel::cancelByAdmin($id, StaffAuth::id(), trim((string) Request::input('reason', '后台取消')));
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '运单已取消' . ((int) $waybill['status'] === 3 ? '，运费已退回用户余额' : ''));
    }

    /**
     * 拦截运单：出库后仍可发起拦截，转人工处理
     */
    public function intercept(): never
    {
        $this->requireStaff();
        $this->enforce('order.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $waybill = WaybillModel::findById($id);
        if ($waybill === null || $waybill['deleted_at'] !== null) {
            $this->fail('运单不存在');
        }
        $status = (int) $waybill['status'];
        if ($status < 4 || $status > 9) {
            $this->fail('当前状态不可拦截（限已支付至派送中阶段）');
        }
        $reason = trim((string) Request::input('reason', ''));
        if ($reason === '') {
            $this->fail('请填写拦截原因');
        }
        try {
            WaybillModel::changeStatus($id, 12, StaffAuth::id(), '拦截：' . $reason);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(null, '运单已标记拦截');
    }

    /**
     * 运输段状态推进 + 轨迹录入：已出库(5) → 运输中(6) → 清关中(7) → 派送中(8) → 已签收(9)
     * 每次推进写一条轨迹记录，签收时回填签收时间并站内信通知用户
     */
    public function advance(): never
    {
        $this->requireStaff();
        $this->enforce('order.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $toStatus = (int) Request::input('to_status', 0);
        $statusDesc = trim((string) Request::input('status_desc', ''));
        $location = trim((string) Request::input('location', ''));
        $eventTime = trim((string) Request::input('event_time', ''));

        $waybill = WaybillModel::findById($id);
        if ($waybill === null || $waybill['deleted_at'] !== null) {
            $this->fail('运单不存在');
        }
        $from = (int) $waybill['status'];
        // 允许的推进路径：出库后按链条前进，签收可从任一运输段直达
        $allowed = [
            5 => [6, 7, 8, 9],
            6 => [7, 8, 9],
            7 => [8, 9],
            8 => [9],
        ];
        if (!isset($allowed[$from]) || !in_array($toStatus, $allowed[$from], true)) {
            $this->fail('当前状态不允许推进到所选状态');
        }
        if ($statusDesc === '' || mb_strlen($statusDesc) > 100) {
            $this->fail('请填写轨迹描述（1~100 字）');
        }
        if ($eventTime !== '' && strtotime($eventTime) === false) {
            $this->fail('轨迹时间格式不正确');
        }

        try {
            WaybillModel::changeStatus($id, $toStatus, StaffAuth::id(), '轨迹推进：' . $statusDesc);
            WaybillModel::addTracking($id, [
                'to_status'   => $toStatus,
                'status_desc' => $statusDesc,
                'location'    => $location,
                'event_time'  => $eventTime !== '' ? date('Y-m-d H:i:s', strtotime($eventTime)) : date('Y-m-d H:i:s'),
            ], StaffAuth::id());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        if ($toStatus === 9) {
            MessageModel::sendToUser(
                (int) $waybill['user_id'],
                '运单已签收',
                '您的运单 ' . $waybill['waybill_no'] . ' 已签收。如有问题请在 7 天内提交理赔或工单。',
                2,
                (string) $waybill['waybill_no']
            );
        }
        $this->ok(null, '轨迹已录入，状态已推进');
    }
}
