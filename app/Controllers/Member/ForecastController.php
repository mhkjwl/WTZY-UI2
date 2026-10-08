<?php declare(strict_types=1);
/**
 * @file app/Controllers/Member/ForecastController.php
 * @description 会员中心：包裹预报（新增/修改/凭证上传）
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Controllers\Member;

use App\Core\Request;
use App\Models\PackageModel;
use App\Models\WarehouseModel;
use App\Services\UploadService;
use InvalidArgumentException;
use RuntimeException;

final class ForecastController extends BaseController
{

    public function index(): never
    {
        $this->requireLogin();
        $keyword = (string) Request::query('kw', '');
        $page = $this->pageParams();
        $result = PackageModel::search($this->userId(), $keyword, -1, $page['limit'], $page['offset']);

        $data = [
            'title'    => '包裹预报',
            'rows'     => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page['page'],
            'pages'    => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword'  => $keyword,
            'warehouses' => WarehouseModel::all(),
        ];
        if ($this->isPartial()) {
            $this->renderTable('member/forecast_table', $data);
        }
        $this->memberView('member/forecast', $data, 'forecast', '包裹预报');
    }

    /**
     * 新建/修改预报
     */
    public function save(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $period = (string) Request::input('expected_arrival', '');
        if ($period !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) !== 1) {
            $this->fail('预计到仓日期格式不正确');
        }

        $data = [
            'warehouse_id'     => (int) Request::input('warehouse_id', 0),
            'tracking_no'      => trim((string) Request::input('tracking_no', '')),
            'express_company'  => trim((string) Request::input('express_company', '')),
            'item_name'        => trim((string) Request::input('item_name', '')),
            'item_qty'         => max(1, (int) Request::input('item_qty', 1)),
            'declared_value'   => round(max(0, (float) Request::input('declared_value', 0)), 2),
            'currency'         => strtoupper(trim((string) Request::input('currency', 'USD'))),
            'category'         => (int) Request::input('category', 1),
            'expected_arrival' => $period,
            'remark'           => trim((string) Request::input('remark', '')),
        ];

        if ($data['tracking_no'] === '') {
            $this->fail('请填写快递单号');
        }
        if (mb_strlen($data['item_name']) < 2 || mb_strlen($data['item_name']) > 100) {
            $this->fail('商品名称需为 2~100 字');
        }
        if (preg_match('/^[A-Z]{3}$/', $data['currency']) !== 1) {
            $this->fail('币种须为 3 位字母代码，如 USD');
        }
        if (!array_key_exists($data['category'], [1 => '', 2 => '', 3 => '', 4 => '', 5 => ''])) {
            $this->fail('无效的商品类别');
        }

        try {
            if ($id > 0) {
                PackageModel::updateForecast($id, $this->userId(), $data);
                $this->ok(null, '预报信息已更新');
            }
            if ($data['warehouse_id'] <= 0) {
                // 未指定仓库时落到默认启用仓库，保证仓地址与包裹归属一致
                $warehouse = WarehouseModel::findFirstActive();
                if ($warehouse === null) {
                    $this->fail('系统尚未启用海外仓，请联系客服');
                }
                $data['warehouse_id'] = (int) $warehouse['id'];
            }
            $newId = PackageModel::createForecast($this->userId(), (string) $this->user['user_code'], $data);
            $this->ok(['id' => $newId], '预报成功，请把识别码填写在收件人姓名上');
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
    }

    /**
     * 上传发票/订单截图（白名单校验 + 重命名 + 隔离目录）
     */
    public function upload(): never
    {
        $this->requireLogin();
        $this->verifyCsrf();

        $packageId = (int) Request::input('package_id', 0);
        $package = PackageModel::findForUser($packageId, $this->userId());
        if ($package === null) {
            $this->fail('包裹不存在');
        }
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            $this->fail('请选择要上传的图片');
        }
        try {
            $url = UploadService::storePackagePhoto(
                $_FILES['file'],
                'forecast',
                'pf' . $packageId,
                $packageId,
                UploadService::PHOTO_USER_PROOF
            );
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }

        $this->ok(['url' => $url], '凭证已上传');
    }
}
