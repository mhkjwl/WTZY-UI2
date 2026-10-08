<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/AbnormalController.php
 * @description 异常处理：破损/缺件/禁运等登记 + 用户处置申请（退运/销毁）执行
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

namespace App\Controllers\Warehouse;

use App\Core\Database;
use App\Core\Request;
use App\Enums\Status;
use App\Models\PackageModel;
use App\Models\WarehouseTaskModel;
use App\Services\UploadService;
use InvalidArgumentException;
use RuntimeException;

final class AbnormalController extends BaseController
{
    /** 处置动作：0=仅处理并恢复在库 1=执行退运 2=执行销毁 */
    private const DISPOSAL = [0 => '处理并恢复在库', 1 => '执行退运', 2 => '执行销毁'];

    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('wh.abnormal');
        $page = $this->pageParams();
        $keyword = (string) Request::query('kw', '');
        $result = PackageModel::pendingAbnormals($this->warehouseId(), $keyword, $page['limit'], $page['offset']);
        $data = [
            'title'      => '异常处理',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page['page'],
            'pages'      => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'    => $keyword,
            'types'      => Status::ABNORMAL_TYPE,
            'disposals'  => self::DISPOSAL,
            'stats'      => PackageModel::abnormalStats($this->warehouseId()),
            'base'       => '/warehouse/abnormal',
        ];
        if ($this->isPartial()) {
            $this->renderTable('warehouse/abnormal_table', $data);
        }
        $this->warehouseView('warehouse/abnormal', $data, 'abnormal', '异常处理');
    }

    /**
     * 登记异常：包裹标记异常并写入异常记录（可在入库或仓储作业中发现时登记）
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('wh.abnormal');
        $this->verifyCsrf();

        $packageId = (int) Request::input('package_id', 0);
        $type = (int) Request::input('abnormal_type', 0);
        $description = trim((string) Request::input('description', ''));

        if (!isset(Status::ABNORMAL_TYPE[$type])) {
            $this->fail('请选择异常类型');
        }
        if (mb_strlen($description) < 4 || mb_strlen($description) > 500) {
            $this->fail('请填写异常说明（4~500 字）');
        }
        $package = PackageModel::findDetail($packageId);
        if ($package === null) {
            $this->fail('包裹不存在');
        }
        if ((int) $package['warehouse_id'] !== $this->warehouseId()) {
            $this->fail('该包裹不属于当前仓库');
        }

        try {
            $id = PackageModel::registerAbnormal($packageId, [
                'abnormal_type' => $type,
                'description'   => $description,
                'images'        => $this->normalizeImages(Request::input('images', '')),
            ], $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'abnormal_register', $id, [
            'package_id' => $packageId, 'abnormal_type' => $type,
        ]);
        $this->ok(['id' => $id], '异常已登记，用户端包裹会标记为异常件');
    }

    /**
     * 按包裹编号登记异常（异常处理页入口，避免先回仓储页查找）
     */
    public function registerByNo(): never
    {
        $this->requireStaff();
        $this->enforce('wh.abnormal');
        $this->verifyCsrf();

        $packageNo = trim((string) Request::input('package_no', ''));
        if ($packageNo === '') {
            $this->fail('请填写包裹编号');
        }
        $stmt = \App\Core\Database::pdo()->prepare(
            'SELECT id FROM ' . \App\Core\Database::table('packages') . '
             WHERE warehouse_id = :wh AND deleted_at IS NULL AND package_no = :no LIMIT 1'
        );
        $stmt->execute([':wh' => $this->warehouseId(), ':no' => $packageNo]);
        $packageId = (int) $stmt->fetchColumn();
        if ($packageId === 0) {
            $this->fail('未找到本仓库的包裹 ' . $packageNo);
        }

        $type = (int) Request::input('abnormal_type', 0);
        $description = trim((string) Request::input('description', ''));
        if (!isset(Status::ABNORMAL_TYPE[$type])) {
            $this->fail('请选择异常类型');
        }
        if (mb_strlen($description) < 4 || mb_strlen($description) > 500) {
            $this->fail('请填写异常说明（4~500 字）');
        }

        try {
            $id = PackageModel::registerAbnormal($packageId, [
                'abnormal_type' => $type,
                'description'   => $description,
                'images'        => $this->normalizeImages(Request::input('images', '')),
            ], $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'abnormal_register', $id, [
            'package_id' => $packageId, 'abnormal_type' => $type,
        ]);
        $this->ok(['id' => $id], '异常已登记');
    }

    /**
     * 处理异常：写处理结果，并按处置动作落地包裹最终状态
     */
    public function handle(): never
    {
        $this->requireStaff();
        $this->enforce('wh.abnormal');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $result = trim((string) Request::input('handle_result', ''));
        $disposal = (int) Request::input('disposal', 0);

        if (mb_strlen($result) < 4 || mb_strlen($result) > 500) {
            $this->fail('请填写处理结果（4~500 字）');
        }
        if (!isset(self::DISPOSAL[$disposal])) {
            $this->fail('请选择处置方式');
        }

        try {
            PackageModel::handleAbnormal($id, $this->warehouseId(), $this->adminId(), $result, $disposal);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'abnormal_handle', $id, ['disposal' => $disposal, 'result' => $result]);
        $this->ok(null, '异常已处理：' . self::DISPOSAL[$disposal]);
    }

    /**
     * 上传异常照片：落盘并挂为包裹异常照（photo_type 4），前端把返回 URL 随登记一并提交
     */
    public function upload(): never
    {
        $this->requireStaff();
        $this->enforce('wh.abnormal');
        $this->verifyCsrf();

        $packageId = $this->resolvePackageId();
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            $this->fail('请选择要上传的图片');
        }
        try {
            $url = UploadService::storePackagePhoto($_FILES['file'], 'abnormal', 'ab', $packageId, UploadService::PHOTO_ABNORMAL);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['url' => $url], '异常照片已上传');
    }

    /**
     * 解析目标包裹：登记入口只填了包裹编号，包内作业可直接传包裹ID
     * 两种入参都限定在当前仓库内，避免凭编号或 ID 跨仓挂照片
     */
    private function resolvePackageId(): int
    {
        $packageId = (int) Request::input('package_id', 0);
        if ($packageId > 0) {
            $package = PackageModel::findDetail($packageId);
            if ($package === null || (int) $package['warehouse_id'] !== $this->warehouseId()) {
                $this->fail('包裹不存在或不属于当前仓库');
            }
            return $packageId;
        }
        $packageNo = trim((string) Request::input('package_no', ''));
        if ($packageNo === '') {
            $this->fail('请先填写包裹编号再上传照片');
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM ' . Database::table('packages') . '
             WHERE warehouse_id = :wh AND deleted_at IS NULL AND package_no = :no LIMIT 1'
        );
        $stmt->execute([':wh' => $this->warehouseId(), ':no' => $packageNo]);
        $id = (int) $stmt->fetchColumn();
        if ($id === 0) {
            $this->fail('未找到本仓库的包裹 ' . $packageNo);
        }
        return $id;
    }

    /**
     * 规范化异常照片列表：只接受本站 /uploads/ 下的图片路径，返回 JSON 数组字符串（无有效项时为空串）
     */
    private function normalizeImages(mixed $raw): string
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : ($raw === '' ? [] : [$raw]);
        }
        $urls = [];
        foreach ((array) $raw as $item) {
            $path = trim((string) $item);
            if ($path !== '' && str_starts_with($path, '/uploads/') && !str_contains($path, '..')) {
                $urls[] = $path;
            }
        }
        return $urls === [] ? '' : (string) json_encode(array_values(array_unique($urls)), JSON_UNESCAPED_SLASHES);
    }
}
