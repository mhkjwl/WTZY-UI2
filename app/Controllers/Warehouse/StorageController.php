<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/StorageController.php
 * @description 仓储管理：在库检索 / 上架移库 / 无主件指定归属 / 库位占用一览
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Warehouse;

use App\Core\Request;
use App\Enums\Status;
use App\Models\PackageModel;
use App\Models\WarehouseModel;
use App\Models\WarehouseTaskModel;
use App\Services\UploadService;
use InvalidArgumentException;
use RuntimeException;

final class StorageController extends BaseController
{
    /** 库位一览每页条数（库位可达数千个，一页放太多同样会拉长页面） */
    private const LOC_PER_PAGE = 15;
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('wh.storage');
        $warehouseId = $this->warehouseId();
        $page = $this->pageParams();
        $keyword = (string) Request::query('kw', '');
        $status = (int) Request::query('status', '-1');
        $result = PackageModel::warehouseSearch($warehouseId, $keyword, $status, $page['limit'], $page['offset']);
        $unattributed = PackageModel::unattributed($warehouseId, 15, 0);

        // 在库包裹附预估仓储费（只读展示，不落库；按件/天并叠加会员额外免仓储）
        foreach ($result['rows'] as &$row) {
            $row['storage_fee_est'] = PackageModel::estimatedStorageFee($row, null, (int) ($row['member_level'] ?? 0));
        }
        unset($row);

        // 库位一览只渲染首页，翻页与筛选走独立局部路由（不动在库包裹表）
        $locResult = WarehouseModel::locationOverview($warehouseId, 0, -1, self::LOC_PER_PAGE, 0);

        $data = [
            'title'         => '仓储管理',
            'rows'          => $result['rows'],
            'total'         => $result['total'],
            'page'          => $page['page'],
            'pages'         => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'       => $keyword,
            'status'        => $status,
            'counts'        => PackageModel::warehouseCounts($warehouseId),
            'unattributed'  => $unattributed['rows'],
            'unattributedTotal' => $unattributed['total'],
            'locations'     => WarehouseModel::locationOptions($warehouseId),
            'locationStats' => WarehouseModel::locationSummary($warehouseId),
            'locationList'  => $locResult['rows'],
            'locTotal'      => $locResult['total'],
            'locPage'       => 1,
            'locPages'      => (int) ceil($locResult['total'] / self::LOC_PER_PAGE) ?: 1,
            'locTypeFilter' => 0,
            'locStatusFilter' => -1,
            'locBase'       => '/warehouse/storage/locations',
            'locTypes'      => WarehouseModel::LOCATION_TYPES,
            'locStatus'     => WarehouseModel::LOCATION_STATUS,
            'statuses'      => PackageModel::WH_STATUSES,
            'base'          => '/warehouse/storage',
        ];
        if ($this->isPartial()) {
            $this->renderTable('warehouse/storage_table', $data);
        }
        $this->warehouseView('warehouse/storage', $data, 'storage', '仓储管理');
    }

    /**
     * 库位占用一览（局部）：类型/状态筛选与翻页只替换库位区块，不影响在库包裹表
     */
    public function locations(): never
    {
        $this->requireStaff();
        $this->enforce('wh.storage');
        $warehouseId = $this->warehouseId();
        $page = $this->pageParams(self::LOC_PER_PAGE);
        $type = (int) Request::query('ltype', '0');
        $status = (int) Request::query('lstatus', '-1');
        $result = WarehouseModel::locationOverview($warehouseId, $type, $status, $page['limit'], $page['offset']);
        $this->renderTable('warehouse/storage_locations', [
            'title'           => '库位占用',
            'locationList'    => $result['rows'],
            'locTotal'        => $result['total'],
            'locPage'         => $page['page'],
            'locPages'        => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'locTypeFilter'   => $type,
            'locStatusFilter' => $status,
            'locBase'         => '/warehouse/storage/locations',
            'locTypes'        => WarehouseModel::LOCATION_TYPES,
            'locStatus'       => WarehouseModel::LOCATION_STATUS,
        ]);
    }

    /**
     * 上架 / 移库：分配或更换库位；random=1 时随机占用一个空闲常规库位
     */
    public function shelve(): never
    {
        $this->requireStaff();
        $this->enforce('wh.storage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $random = (int) Request::input('random', 0);
        $locationId = (int) Request::input('location_id', 0);
        $locationCode = '';
        if ($random === 1) {
            $loc = PackageModel::randomFreeLocation($this->warehouseId());
            if ($loc === null) {
                $this->fail('当前仓库常规存储库位已全部占用，请先下架释放库位或扩容');
            }
            $locationId = $loc['id'];
            $locationCode = $loc['code'];
        }
        if ($locationId <= 0) {
            $this->fail('请选择库位');
        }
        try {
            PackageModel::shelve($id, $locationId, $this->warehouseId(), $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'shelve', $id, ['location_id' => $locationId]);
        WarehouseTaskModel::finishByTarget(WarehouseTaskModel::TARGET_PACKAGE, $id, '仓库已上架');
        $this->ok(['location_id' => $locationId, 'location_code' => $locationCode], $locationCode !== '' ? '已随机上架到库位 ' . $locationCode : '上架完成');
    }

    /**
     * 下架：包裹移出库位并释放占用（限在库且未进入打包/出库流程的包裹）
     */
    public function unshelve(): never
    {
        $this->requireStaff();
        $this->enforce('wh.storage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        try {
            PackageModel::unshelve($id, $this->warehouseId(), $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'unshelve', $id);
        $this->ok(null, '下架完成，库位已释放');
    }

    /**
     * 无主件指定归属：补齐用户后转为已到仓
     */
    public function attribute(): never
    {
        $this->requireStaff();
        $this->enforce('wh.storage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $userId = (int) Request::input('user_id', 0);
        // 支持「用户编号 U100001」与「专属入库识别码」两种输入：面单上印的是识别码，
        // 仓库照单录入即可，无需先去用户列表换算编号
        $account = trim((string) Request::input('user_no', ''));
        if ($account !== '') {
            $found = \App\Models\UserModel::findByUserNo($account)
                ?? \App\Models\UserModel::findByCode($account);
            if ($found === null) {
                $this->fail('未找到用户编号或识别码 ' . $account);
            }
            $userId = (int) $found['id'];
        }
        if ($userId <= 0) {
            $this->fail('请输入要归属的用户编号或识别码');
        }
        try {
            PackageModel::attributeTo($id, $userId, $this->warehouseId(), $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'attribute', $id, ['user_id' => $userId]);
        $this->ok(null, '已指定归属并自动认领');
    }

    /**
     * 包裹详情：本仓包裹完整信息 + 照片 + 异常记录，支持从详情页直接执行仓储作业
     */
    public function packageDetail(string $id): never
    {
        $this->requireStaff();
        $this->enforce('wh.storage');
        $package = PackageModel::findForWarehouse((int) $id, $this->warehouseId());
        if ($package === null) {
            $this->warehouseView('warehouse/package_missing', ['title' => '包裹不存在'], 'storage', '仓储管理');
        }
        $data = [
            'title'    => '仓储管理 · ' . $package['package_no'],
            'package'  => $package,
            'photos'   => PackageModel::photos((int) $package['id']),
            'abnormals'=> PackageModel::abnormalsByPackage((int) $package['id']),
            'storage_fee_est' => PackageModel::estimatedStorageFee($package, null, (int) ($package['member_level'] ?? 0)),
            'locations'=> WarehouseModel::locationOptions($this->warehouseId()),
            'locTypes' => WarehouseModel::LOCATION_TYPES,
            'base'     => '/warehouse/storage',
        ];
        if ($this->isPartial()) {
            $this->renderTable('warehouse/package_detail_table', $data);
        }
        $this->warehouseView('warehouse/package_detail', $data, 'storage', '仓储管理');
    }

    /**
     * 补录包裹照片：入库时未拍照（或需补拍外观）的包裹，在仓储详情页随时补传，挂为入库照
     */
    public function upload(): never
    {
        $this->requireStaff();
        $this->enforce('wh.storage');
        $this->verifyCsrf();

        $packageId = (int) Request::input('package_id', 0);
        // findForWarehouse 同时校验存在性与归属仓库，避免凭包裹ID跨仓挂图
        if (PackageModel::findForWarehouse($packageId, $this->warehouseId()) === null) {
            $this->fail('包裹不存在或不属于当前仓库');
        }
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            $this->fail('请选择要上传的图片');
        }
        try {
            $url = UploadService::storePackagePhoto(
                $_FILES['file'],
                'inbound',
                'in' . $packageId,
                $packageId,
                UploadService::PHOTO_INBOUND
            );
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['url' => $url], '包裹照片已上传');
    }
}
