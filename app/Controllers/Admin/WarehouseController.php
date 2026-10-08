<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/WarehouseController.php
 * @description 仓库管理：仓库增改/启停 + 库位列表与新增
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\WarehouseModel;
use InvalidArgumentException;

final class WarehouseController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.config');
        $this->renderList((int) Request::query('wid', '0'), (int) Request::query('lpage', '1'));
    }

    /**
     * 仓库作业报表：各仓在库、今日入库/出库、待打包/待出库、异常、库位占用
     */
    public function reports(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.report');
        $this->adminView('admin/warehouse_reports', [
            'title'  => '仓库报表',
            'rows'   => WarehouseModel::operationStats(),
            'status' => WarehouseModel::LOCATION_STATUS,
        ], 'warehouse_reports', '仓库报表');
    }

    private function renderList(int $locationWarehouseId, int $locationPage): void
    {
        $page = max(1, $locationPage);
        $locations = WarehouseModel::locationList($locationWarehouseId, 15, ($page - 1) * 15);
        $data = [
            'title'      => '仓库管理',
            'warehouses' => WarehouseModel::all(),
            'locations'  => $locations['rows'],
            'locTotal'   => $locations['total'],
            'locPage'    => $page,
            'locPages'   => (int) ceil($locations['total'] / 15) ?: 1,
            'wid'        => $locationWarehouseId,
            'base'       => '/admin/warehouse',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/warehouse_content', $data);
        }
        $this->adminView('admin/warehouse', $data, 'warehouse', '仓库管理');
    }

    /**
     * 仓库新建/编辑（id=0 新建）
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.config');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $d = [
            'warehouse_no'      => trim((string) Request::input('warehouse_no', '')),
            'name'              => trim((string) Request::input('name', '')),
            'code'              => strtoupper(trim((string) Request::input('code', ''))),
            'country'           => strtoupper(trim((string) Request::input('country', ''))),
            'state'             => trim((string) Request::input('state', '')),
            'city'              => trim((string) Request::input('city', '')),
            'address'           => trim((string) Request::input('address', '')),
            'zip_code'          => trim((string) Request::input('zip_code', '')),
            'contact_phone'     => trim((string) Request::input('contact_phone', '')),
            'timezone'          => trim((string) Request::input('timezone', 'Asia/Shanghai')),
            'free_storage_days' => max(0, (int) Request::input('free_storage_days', 30)),
            'status'            => (int) Request::input('status', 1),
            'sort'              => (int) Request::input('sort', 0),
        ];
        foreach (['warehouse_no', 'name', 'code', 'country', 'state', 'city', 'address', 'zip_code'] as $required) {
            if ($d[$required] === '') {
                $this->fail('请完整填写仓库基础信息（' . $required . ' 缺失）');
            }
        }
        if (!preg_match('/^[A-Z0-9]{2,4}$/', $d['code'])) {
            $this->fail('仓库代码需 2~4 位大写字母或数字（如 CA01）');
        }
        if ($id > 0) {
            WarehouseModel::update($id, $d);
            $this->ok(null, '仓库已更新');
        }
        WarehouseModel::create($d);
        $this->ok(null, '仓库已创建');
    }

    public function toggle(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.config');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $status = WarehouseModel::toggleStatus($id);
        $this->ok(['status' => $status], $status === 1 ? '仓库已启用' : '仓库已停用');
    }

    /**
     * 新增库位（编码唯一性由唯一索引兜底）
     */
    public function addLocation(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.config');
        $this->verifyCsrf();
        $warehouseId = (int) Request::input('warehouse_id', 0);
        $code = strtoupper(trim((string) Request::input('code', '')));
        $type = (int) Request::input('type', 2);
        if ($warehouseId <= 0 || $code === '') {
            $this->fail('请选择仓库并填写库位编码');
        }
        if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $code)) {
            $this->fail('库位编码仅允许大写字母、数字与中划线');
        }
        if (!in_array($type, [1, 2, 3, 4, 5], true)) {
            $this->fail('无效的库位类型');
        }
        try {
            WarehouseModel::locationCreate($warehouseId, $code, $type);
        } catch (\PDOException $e) {
            $this->fail('库位编码在该仓库内已存在');
        }
        $this->ok(null, '库位已创建');
    }

    /**
     * 批量生成库位：给了起止序号按位宽补零顺序编号，序号留空则按前缀拼随机码
     * 与库中已有编码重复的自动跳过并计数，不影响其余库位落库
     */
    public function batchLocation(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.config');
        $this->verifyCsrf();
        $warehouseId = (int) Request::input('warehouse_id', 0);
        $type = (int) Request::input('type', 2);
        $prefix = (string) Request::input('prefix', '');
        $start = trim((string) Request::input('start', ''));
        $end = trim((string) Request::input('end', ''));
        $count = (int) Request::input('count', 10);

        if ($warehouseId <= 0 || WarehouseModel::findById($warehouseId) === null) {
            $this->fail('请选择有效的仓库');
        }
        if (!in_array($type, [1, 2, 3, 4, 5], true)) {
            $this->fail('无效的库位类型');
        }
        try {
            $codes = WarehouseModel::buildLocationCodes($prefix, $start, $end, $count);
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        try {
            $result = WarehouseModel::locationBatchCreate($warehouseId, $codes, $type);
        } catch (\PDOException $e) {
            $this->fail('生成失败：与现有库位编码冲突，请调整前缀后重试');
        }
        if ($result['created'] === 0) {
            $this->fail('这 ' . $result['skipped'] . ' 个编码在该仓库内已全部存在，未新增库位');
        }
        $created = $result['created_codes'];
        $message = '已生成 ' . $result['created'] . ' 个库位';
        if ($result['skipped'] > 0) {
            $message .= '，跳过 ' . $result['skipped'] . ' 个已存在的编码';
        }
        $this->ok([
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'first'   => $created[0],
            'last'    => $created[count($created) - 1],
        ], $message);
    }

    /**
     * 编辑库位：调整编码与类型（所属仓库不可改，避免已上架包裹归属漂移）
     */
    public function updateLocation(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.config');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $code = strtoupper(trim((string) Request::input('code', '')));
        $type = (int) Request::input('type', 2);
        if ($id <= 0 || $code === '') {
            $this->fail('缺少库位标识或编码');
        }
        if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $code)) {
            $this->fail('库位编码仅允许大写字母、数字与中划线');
        }
        if (!in_array($type, [1, 2, 3, 4, 5], true)) {
            $this->fail('无效的库位类型');
        }
        try {
            WarehouseModel::locationUpdate($id, $code, $type);
        } catch (\InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (\PDOException $e) {
            $this->fail('库位编码在该仓库内已存在');
        }
        $this->ok(null, '库位已更新');
    }

    /**
     * 启用 / 停用库位：占用中的库位不可停用（其上仍压着包裹）
     */
    public function toggleLocation(): never
    {
        $this->requireStaff();
        $this->enforce('warehouse.config');
        $this->verifyCsrf();
        try {
            $status = WarehouseModel::locationToggle((int) Request::input('id', 0));
        } catch (\InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['status' => $status], $status === 1 ? '库位已启用' : '库位已停用');
    }
}
