<?php declare(strict_types=1);
/**
 * @file app/Controllers/Warehouse/InboundController.php
 * @description 收货入库：扫码/单号匹配归属 → 称重量尺 → 上架库位 → 确认入库（未匹配可登记无主件）
 * @author AI
 * @version 0.6.1
 * @date 2026-09-13
 */

namespace App\Controllers\Warehouse;

use App\Core\Request;
use App\Core\Response;
use App\Enums\Status;
use App\Models\MessageModel;
use App\Models\PackageModel;
use App\Models\UserModel;
use App\Models\WarehouseModel;
use App\Models\WarehouseTaskModel;
use App\Services\UploadService;
use InvalidArgumentException;
use RuntimeException;

final class InboundController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('wh.inbound');
        $warehouseId = $this->warehouseId();
        $page = $this->pageParams();
        // 待收货：已预报未到仓
        $result = PackageModel::warehouseSearch($warehouseId, (string) Request::query('kw', ''), 1, $page['limit'], $page['offset']);
        $this->warehouseView('warehouse/inbound', [
            'title'      => '收货入库',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page['page'],
            'pages'      => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'    => (string) Request::query('kw', ''),
            'locations'  => WarehouseModel::locationOptions($warehouseId),
            'locTypes'   => WarehouseModel::LOCATION_TYPES,
            'categories' => Status::PACKAGE_CATEGORY,
            'pendingInbound' => PackageModel::pendingInboundCount($warehouseId),
        ], 'inbound', '收货入库');
    }

    /**
     * 扫码/输入单号定位包裹：命中返回归属与商品，未命中提示可登记无主件
     */
    public function lookup(): never
    {
        $this->requireStaff();
        $this->enforce('wh.inbound');
        $this->verifyCsrf();
        $keyword = trim((string) Request::input('keyword', ''));
        if ($keyword === '') {
            $this->fail('请扫描或输入快递单号 / 入库识别码');
        }
        $package = PackageModel::findForInbound($keyword, $this->warehouseId());
        if ($package === null) {
            // 该单号未匹配到预报：给出无主件登记引导
            $this->ok([
                'matched'  => false,
                'keyword'  => $keyword,
                'message'  => '未匹配到该单号的预报记录，可作为「无主件」登记入库，待用户认领或人工指定归属。',
            ], '未匹配到预报记录');
        }
        if ((int) $package['status'] !== 1) {
            $this->fail('该包裹当前状态为「' . Status::package((int) $package['status']) . '」，无需重复入库');
        }
        $this->ok([
            'matched' => true,
            'package' => [
                'id'          => (int) $package['id'],
                'package_no'  => (string) $package['package_no'],
                'tracking_no' => (string) $package['tracking_no'],
                'express'     => (string) $package['express_company'],
                'item_name'   => (string) $package['item_name'],
                'item_qty'    => (int) $package['item_qty'],
                'category'    => (int) $package['category'],
                'declared'    => (float) $package['declared_value'],
                'currency'    => (string) $package['currency'],
                'user_no'     => (string) ($package['user_no'] ?? ''),
                'user_name'   => (string) ($package['username'] ?? $package['nickname'] ?? ''),
                'user_code'   => (string) $package['user_code'],
                'phone'       => (string) ($package['phone'] ?? ''),
            ],
        ], '已匹配到预报记录');
    }

    /**
     * 确认入库：写入实重与箱规（自动算体积重）、可选上架库位，状态流转为已到仓
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('wh.inbound');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $weight = (float) Request::input('weight', 0);
        $length = (float) Request::input('length', 0);
        $width = (float) Request::input('width', 0);
        $height = (float) Request::input('height', 0);
        $locationId = (int) Request::input('location_id', 0);
        $remark = trim((string) Request::input('remark', ''));

        if ($weight <= 0 || $weight > 1000) {
            $this->fail('请填写有效的实际重量（0~1000kg）');
        }
        foreach (['长度' => $length, '宽度' => $width, '高度' => $height] as $label => $value) {
            if ($value <= 0 || $value > 500) {
                $this->fail('请填写有效的' . $label . '（0~500cm）');
            }
        }

        try {
            PackageModel::inbound($id, $this->warehouseId(), [
                'weight'      => round($weight, 3),
                'length'      => round($length, 2),
                'width'       => round($width, 2),
                'height'      => round($height, 2),
                'location_id' => $locationId,
            ], $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $volume = PackageModel::volumeWeight($length, $width, $height);
        $this->auditLog('warehouse', 'inbound', $id, [
            'weight' => $weight, 'volume_weight' => $volume, 'location_id' => $locationId, 'remark' => $remark,
        ]);
        // 作业完成，收口对应任务
        WarehouseTaskModel::finishByTarget(WarehouseTaskModel::TARGET_PACKAGE, $id, '仓库已入库');
        $this->ok([
            'package_no'    => (string) PackageModel::findDetail($id)['package_no'],
            'volume_weight' => $volume,
        ], '入库完成，包裹已置为「已到仓」');
    }

    /**
     * 登记无主件：单号未匹配到预报时先入库，待认领或人工指定归属
     */
    public function unattributed(): never
    {
        $this->requireStaff();
        $this->enforce('wh.inbound');
        $this->verifyCsrf();

        $tracking = trim((string) Request::input('tracking_no', ''));
        $itemName = trim((string) Request::input('item_name', ''));
        $itemQty = (int) Request::input('item_qty', 1);
        $category = (int) Request::input('category', 1);
        $weight = (float) Request::input('weight', 0);
        $length = (float) Request::input('length', 0);
        $width = (float) Request::input('width', 0);
        $height = (float) Request::input('height', 0);

        if ($tracking === '' || mb_strlen($tracking) > 64) {
            $this->fail('请填写快递单号');
        }
        if ($itemName === '' || mb_strlen($itemName) > 100) {
            $this->fail('请填写商品名称');
        }
        if ($itemQty < 1 || $itemQty > 999) {
            $this->fail('请填写有效的商品数量');
        }
        if (!isset(Status::PACKAGE_CATEGORY[$category])) {
            $this->fail('请选择物品类别');
        }
        if ($weight <= 0 || $weight > 1000) {
            $this->fail('请填写有效的实际重量（0~1000kg）');
        }
        foreach (['长度' => $length, '宽度' => $width, '高度' => $height] as $label => $value) {
            if ($value <= 0 || $value > 500) {
                $this->fail('请填写有效的' . $label . '（0~500cm）');
            }
        }

        try {
            $id = PackageModel::createUnattributed($this->warehouseId(), [
                'tracking_no'     => $tracking,
                'express_company' => trim((string) Request::input('express_company', '')),
                'item_name'       => $itemName,
                'item_qty'        => $itemQty,
                'category'        => $category,
                'weight'          => round($weight, 3),
                'length'          => round($length, 2),
                'width'           => round($width, 2),
                'height'          => round($height, 2),
                'remark'          => trim((string) Request::input('remark', '')),
            ], $this->adminId());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->auditLog('warehouse', 'inbound_unattributed', $id, ['tracking_no' => $tracking]);
        $this->ok([
            'id'         => $id,
            'package_no' => (string) PackageModel::findDetail($id)['package_no'],
        ], '无主件已登记入库，可在「仓储管理」中指定归属');
    }

    /**
     * 手动添加包裹：面单磨损、无法扫码或事后补录时由收货员直接建档
     * 识别码命中用户则直接归属并置「已到仓」；识别码为空或匹配不到时，先把选择权交回收货员，
     * 确认后才按无主件入库（面单上的原识别码原样保留，便于后续人工核对与指定归属）
     */
    public function manual(): never
    {
        $this->requireStaff();
        $this->enforce('wh.inbound');
        $this->verifyCsrf();

        $tracking = trim((string) Request::input('tracking_no', ''));
        $codeRaw = strtoupper(trim((string) Request::input('user_code', '')));
        $itemName = trim((string) Request::input('item_name', ''));
        $itemQty = (int) Request::input('item_qty', 1);
        $category = (int) Request::input('category', 1);
        $weight = (float) Request::input('weight', 0);
        $length = (float) Request::input('length', 0);
        $width = (float) Request::input('width', 0);
        $height = (float) Request::input('height', 0);
        $locationId = (int) Request::input('location_id', 0);
        $remark = trim((string) Request::input('remark', ''));
        $asUnattributed = (int) Request::input('as_unattributed', 0) === 1;
        $force = (int) Request::input('force', 0) === 1;

        if ($tracking === '' || mb_strlen($tracking) > 64) {
            $this->fail('请填写快递单号');
        }
        if ($itemName === '' || mb_strlen($itemName) > 100) {
            $this->fail('请填写商品名称');
        }
        if ($itemQty < 1 || $itemQty > 999) {
            $this->fail('请填写有效的商品数量');
        }
        if (!isset(Status::PACKAGE_CATEGORY[$category])) {
            $this->fail('请选择物品类别');
        }
        if ($weight <= 0 || $weight > 1000) {
            $this->fail('请填写有效的实际重量（0~1000kg）');
        }
        foreach (['长度' => $length, '宽度' => $width, '高度' => $height] as $label => $value) {
            if ($value <= 0 || $value > 500) {
                $this->fail('请填写有效的' . $label . '（0~500cm）');
            }
        }
        if (mb_strlen($codeRaw) > 16) {
            $this->fail('识别码不超过 16 位');
        }
        if (mb_strlen($remark) > 200) {
            $this->fail('备注不超过 200 字');
        }

        // 重号判断优先于归属判断：该单号已有预报记录时，正确做法是直接给那条包裹入库，而不是再建一条
        $existing = PackageModel::findByTracking($tracking, $this->warehouseId());
        if ($existing !== null && !$force) {
            $isForecast = (int) $existing['status'] === 1;
            Response::json([
                'success' => false,
                'message' => $isForecast
                    ? '该单号已有预报记录（包裹 ' . (string) $existing['package_no'] . '），请直接为该包裹办理入库'
                    : '该单号已存在（包裹 ' . (string) $existing['package_no'] . ' · ' . Status::package((int) $existing['status']) . '）',
                'data' => [
                    'needConfirm' => $isForecast ? 'forecast' : 'duplicate',
                    'tracking_no' => $tracking,
                    'package' => [
                        'id'         => (int) $existing['id'],
                        'package_no' => (string) $existing['package_no'],
                        'status'     => (int) $existing['status'],
                        'user_no'    => (string) ($existing['user_no'] ?? ''),
                        'user_name'  => (string) ($existing['username'] ?? $existing['nickname'] ?? ''),
                    ],
                ],
            ]);
        }

        // 识别码 → 归属用户：只有状态「正常」的账号可直接归属
        $user = $codeRaw === '' ? null : UserModel::findByCode($codeRaw);
        $bindable = $user !== null && (int) $user['status'] === 1;
        if (!$bindable && !$asUnattributed) {
            if ($user === null) {
                $message = $codeRaw === ''
                    ? '未填写识别码，确认后可按「无主件」登记入库，之后在「仓储管理」指定归属用户'
                    : '未找到识别码 ' . $codeRaw . ' 对应的用户，确认后可按「无主件」登记入库';
            } else {
                $message = '识别码 ' . $codeRaw . ' 对应用户状态为「' . Status::user((int) $user['status']) . '」，不能直接归属；确认后可按「无主件」登记入库';
            }
            Response::json([
                'success' => false,
                'message' => $message,
                'data'    => ['needConfirm' => 'unattributed', 'code' => $codeRaw],
            ]);
        }

        $userId = $bindable ? (int) $user['id'] : 0;
        try {
            $id = PackageModel::createManual($this->warehouseId(), [
                'user_id'         => $userId,
                // 无主件时保留面单上的原识别码，仓储管理可按它检索回货物
                'user_code'       => $bindable ? (string) $user['user_code'] : $codeRaw,
                'tracking_no'     => $tracking,
                'express_company' => trim((string) Request::input('express_company', '')),
                'item_name'       => $itemName,
                'item_qty'        => $itemQty,
                'category'        => $category,
                'weight'          => round($weight, 3),
                'length'          => round($length, 2),
                'width'           => round($width, 2),
                'height'          => round($height, 2),
                'location_id'     => $locationId,
                'remark'          => $remark,
            ], $this->adminId());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $this->auditLog('warehouse', 'inbound_manual', $id, [
            'tracking_no' => $tracking,
            'user_id'     => $userId,
            'user_code'   => $codeRaw,
            'location_id' => $locationId,
            'force'       => $force ? 1 : 0,
        ]);

        // 绑定成功的包裹用户此前并未预报，必须通知，否则其账户会凭空多出一个包裹
        $packageNo = (string) PackageModel::findDetail($id)['package_no'];
        $notified = 0;
        if ($userId > 0) {
            $notified = MessageModel::sendToUser(
                $userId,
                '包裹已入库',
                '你的包裹 ' . $packageNo . ' 已在「' . $this->warehouseName . '」入库，已在会员中心「我的包裹」自动认领。',
                1,
                $packageNo
            ) > 0 ? 1 : 0;
        }

        $this->ok([
            'id'            => $id,
            'package_no'    => $packageNo,
            'is_unattributed' => $userId === 0,
            'user_no'       => $bindable ? (string) $user['user_no'] : '',
            'user_name'     => $bindable ? (string) ($user['username'] ?? $user['nickname'] ?? '') : '',
            'user_code'     => $bindable ? (string) $user['user_code'] : $codeRaw,
            'volume_weight' => PackageModel::volumeWeight($length, $width, $height),
            'notified'      => $notified,
        ], $userId > 0 ? '包裹已入库并归属到用户' : '无主件已登记，请在「仓储管理」指定归属用户');
    }

    /**
     * 上传入库照片
     */
    public function upload(): never
    {
        $this->requireStaff();
        $this->enforce('wh.inbound');
        $this->verifyCsrf();

        $packageId = (int) Request::input('package_id', 0);
        $package = PackageModel::findDetail($packageId);
        // 只能给自己仓库的包裹加照片，避免凭包裹ID跨仓操作
        if ($package === null || (int) $package['warehouse_id'] !== $this->warehouseId()) {
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
        $this->ok(['url' => $url], '入库照片已上传');
    }
}
