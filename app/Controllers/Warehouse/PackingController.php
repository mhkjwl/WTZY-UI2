<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/PackingController.php
 * @description 打包称重：待打包运单拣货核对 → 录入打包实重与箱规 → 按实重重算运费 → 转待支付
 * @author AI
 * @version 0.6
 * @date 2026-09-14
 */

namespace App\Controllers\Warehouse;

use App\Core\Request;
use App\Enums\Status;
use App\Models\PackageModel;
use App\Models\SettingsModel;
use App\Models\WaybillModel;
use App\Models\WarehouseTaskModel;
use App\Services\UploadService;
use InvalidArgumentException;
use RuntimeException;

final class PackingController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('wh.packing');
        $page = $this->pageParams();
        $keyword = (string) Request::query('kw', '');
        $result = WaybillModel::pendingPacking($this->warehouseId(), $keyword, $page['limit'], $page['offset']);
        $stats = WaybillModel::packingStats($this->warehouseId());
        $data = [
            'title'   => '打包称重',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'stats'   => $stats,
            'base'    => '/warehouse/packing',
        ];
        if ($this->isPartial()) {
            $this->renderTable('warehouse/packing_table', $data);
        }
        $this->warehouseView('warehouse/packing', $data, 'packing', '打包称重');
    }

    /**
     * 打包作业页：包裹清单 + 库位（拣货用）+ 重量箱规录入。
     * 已打包/已出库的运单也可查看（详情只读），故查看权限放宽至打包或出库任一岗位。
     */
    public function detail(string $id): never
    {
        $this->requireStaff();
        if (!$this->can('wh.packing') && !$this->can('wh.outbound')) {
            $this->enforce('wh.packing');
        }
        $waybill = WaybillModel::findForWarehouse((int) $id, $this->warehouseId());
        if ($waybill === null) {
            $this->warehouseView('warehouse/waybill_missing', ['title' => '运单不存在'], 'packing', '打包作业');
        }
        $packages = WaybillModel::packagesOf((int) $waybill['id']);
        $actual = 0.0;
        $volume = 0.0;
        foreach ($packages as $p) {
            $actual += (float) ($p['weight'] ?? 0);
            $volume += (float) ($p['volume_weight'] ?? 0);
        }
        // 打包照挂在箱内每个包裹上，按 URL 去重后合并展示
        $photos = [];
        foreach ($packages as $p) {
            foreach (PackageModel::photos((int) $p['id']) as $photo) {
                if ((int) $photo['photo_type'] === UploadService::PHOTO_PACKING) {
                    $photos[$photo['url']] = $photo;
                }
            }
        }
        // 体积重系数取自渠道（前端录入箱规时实时预览，须与后端结算口径一致）
        $divisor = (float) ($waybill['volume_divisor'] ?? 0);
        if ($divisor <= 0) {
            $divisor = (float) SettingsModel::get('volume_divisor_default', '5000');
        }
        $this->warehouseView('warehouse/packing_detail', [
            'title'     => '打包作业 · ' . $waybill['waybill_no'],
            'waybill'   => $waybill,
            'packages'  => $packages,
            'sumActual' => round($actual, 3),
            'sumVolume' => round($volume, 3),
            'photos'    => array_values($photos),
            'volumeDivisor' => $divisor > 0 ? $divisor : 5000.0,
        ], 'packing', '打包作业');
    }

    /**
     * 打包作业页的状态探针（只读）：用户支付运费后仓库端无需刷新整页即可看到进度推进
     */
    public function status(): never
    {
        $this->requireStaff();
        if (!$this->can('wh.packing') && !$this->can('wh.outbound')) {
            $this->enforce('wh.packing');
        }
        $waybill = WaybillModel::findForWarehouse((int) Request::query('id', '0'), $this->warehouseId());
        if ($waybill === null) {
            $this->fail('运单不存在或不属于当前仓库');
        }
        $status = (int) $waybill['status'];
        $packages = WaybillModel::packagesOf((int) $waybill['id']);
        $this->ok([
            'status'      => $status,
            'status_text' => Status::waybill($status),
            'tone'        => Status::waybillTone($status),
            'step'        => Status::waybillStep($status),
            'can_pack'    => $status === 3 && $this->can('wh.packing') && $packages !== [],
            'panel_note'  => Status::packingBlockedNote($status, (float) ($waybill['due_amount'] ?? 0)),
            'paid_at'     => $waybill['paid_at'] !== null ? substr((string) $waybill['paid_at'], 0, 16) : '',
        ]);
    }

    /**
     * 提交打包：写实重与箱规、按「实重 vs 打包体积重」重算运费，随后转「待支付」
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('wh.packing');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $weight = (float) Request::input('actual_weight', 0);
        $length = (float) Request::input('length', 0);
        $width = (float) Request::input('width', 0);
        $height = (float) Request::input('height', 0);
        $remark = trim((string) Request::input('remark', ''));

        if ($weight <= 0 || $weight > 1000) {
            $this->fail('请填写有效的打包重量（0~1000kg）');
        }
        foreach (['长度' => $length, '宽度' => $width, '高度' => $height] as $label => $value) {
            if ($value <= 0 || $value > 500) {
                $this->fail('请填写有效的箱规' . $label . '（0~500cm）');
            }
        }

        try {
            $result = WaybillModel::packParcel($id, $this->warehouseId(), [
                'actual_weight' => round($weight, 3),
                'length'        => round($length, 2),
                'width'         => round($width, 2),
                'height'        => round($height, 2),
                'remark'        => $remark,
            ], $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $this->auditLog('warehouse', 'packing', $id, [
            'actual_weight' => $weight, 'billable' => $result['billable'], 'total' => $result['total'],
            'pending_pay' => (bool) ($result['pending_pay'] ?? false), 'due' => (float) ($result['due'] ?? 0),
        ], 'waybill');
        WarehouseTaskModel::finishByTarget(WarehouseTaskModel::TARGET_WAYBILL, $id, '仓库已完成打包称重');

        $pendingPay = (bool) ($result['pending_pay'] ?? false);
        $msg = '打包完成，计费重 ' . $result['billable'] . 'kg，重算运费 ¥' . number_format((float) $result['total'], 2);
        if ($pendingPay) {
            $msg .= '，用户余额不足，差额 ¥' . number_format((float) $result['due'], 2) . ' 已挂起待补缴，到账前不可出库';
        } elseif (abs((float) ($result['diff'] ?? 0)) >= 0.01) {
            $diff = (float) $result['diff'];
            $msg .= '，已' . ($diff > 0 ? '补扣' : '退还') . '差额 ¥' . number_format(abs($diff), 2) . '，运单已转「待出库」';
        } else {
            $msg .= '，与预扣一致，运单已转「待出库」';
        }
        $this->ok([
            'billable_weight' => $result['billable'],
            'total_amount'    => $result['total'],
            'pending_pay'     => $pendingPay,
            'due_amount'      => (float) ($result['due'] ?? 0),
        ], $msg);
    }

    /**
     * 上传打包照片
     * 传 waybill_id 时挂到箱内每个包裹上（与管理端/会员端按包裹展示照片的口径一致）；
     * 传 package_id 时只挂该包裹
     */
    public function upload(): never
    {
        $this->requireStaff();
        $this->enforce('wh.packing');
        $this->verifyCsrf();

        $waybillId = (int) Request::input('waybill_id', 0);
        $packageIds = [];
        if ($waybillId > 0) {
            if (WaybillModel::findForWarehouse($waybillId, $this->warehouseId()) === null) {
                $this->fail('运单不存在或不属于当前仓库');
            }
            foreach (WaybillModel::packagesOf($waybillId) as $package) {
                $packageIds[] = (int) $package['id'];
            }
            if ($packageIds === []) {
                $this->fail('该运单没有关联包裹');
            }
        } else {
            $packageId = (int) Request::input('package_id', 0);
            $package = PackageModel::findDetail($packageId);
            // 只能给自己仓库的包裹加照片，避免凭包裹ID跨仓操作
            if ($package === null || (int) $package['warehouse_id'] !== $this->warehouseId()) {
                $this->fail('包裹不存在或不属于当前仓库');
            }
            $packageIds[] = $packageId;
        }
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            $this->fail('请选择要上传的图片');
        }
        try {
            // 文件只能移动一次，落盘后再逐个包裹挂照片记录
            $url = UploadService::storeFile($_FILES['file'], 'packing', 'pk' . ($waybillId > 0 ? $waybillId : $packageIds[0]));
            foreach ($packageIds as $pid) {
                UploadService::attachPackagePhoto($pid, UploadService::PHOTO_PACKING, $url);
            }
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['url' => $url, 'packages' => count($packageIds)], '打包照片已上传');
    }
}
