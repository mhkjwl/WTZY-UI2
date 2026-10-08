<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/OutboundController.php
 * @description 出库交接：待出库运单（已支付/待出库）录入承运单号并确认出库，承运商以用户下单渠道为准
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Warehouse;

use App\Core\Request;
use App\Models\WaybillModel;
use App\Models\WarehouseTaskModel;
use InvalidArgumentException;
use RuntimeException;

final class OutboundController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('wh.outbound');
        $page = $this->pageParams();
        $keyword = (string) Request::query('kw', '');
        $result = WaybillModel::pendingOutbound($this->warehouseId(), $keyword, $page['limit'], $page['offset']);
        $stats = WaybillModel::outboundStats($this->warehouseId());
        $data = [
            'title'    => '出库交接',
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page['page'],
            'pages'    => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'  => $keyword,
            'stats'    => $stats,
            'base'     => '/warehouse/outbound',
        ];
        if ($this->isPartial()) {
            $this->renderTable('warehouse/outbound_table', $data);
        }
        $this->warehouseView('warehouse/outbound', $data, 'outbound', '出库交接');
    }

    /**
     * 确认出库：写承运单号，运单转「已出库」、包裹同步出库并释放库位
     * 承运商不由仓库端填写，取运单下单时用户选定的物流渠道，保证两端口径一致
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('wh.outbound');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $trackingNo = trim((string) Request::input('tracking_no', ''));
        $remark = trim((string) Request::input('remark', ''));

        if ($trackingNo === '' || mb_strlen($trackingNo) > 64) {
            $this->fail('请填写承运单号（用于用户端物流跟踪）');
        }

        try {
            WaybillModel::confirmOutbound($id, $this->warehouseId(), [
                'tracking_no' => $trackingNo,
                'remark'      => $remark,
            ], $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $this->auditLog('warehouse', 'outbound', $id, [
            'tracking_no' => $trackingNo,
        ], 'waybill');
        WarehouseTaskModel::finishByTarget(WarehouseTaskModel::TARGET_WAYBILL, $id, '仓库已出库交接');
        $this->ok(null, '出库完成，用户端可凭承运单号跟踪物流');
    }
}
