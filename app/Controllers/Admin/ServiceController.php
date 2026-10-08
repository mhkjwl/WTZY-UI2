<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/ServiceController.php
 * @description 增值服务设置：下单页可勾选增值服务的字典维护（新建/编辑/启停/删除）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\SettingsModel;
use App\Models\ValueAddedServiceModel;
use RuntimeException;

final class ServiceController extends BaseController
{
    /** 单价上限：超过该金额的服务属议价项，应由客服线下报价而非自助勾选 */
    private const MAX_PRICE = 99999.99;

    /** 排序值上限 */
    private const MAX_SORT = 9999;

    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->adminView('admin/services', [
            'title' => '增值服务',
            'rows'  => ValueAddedServiceModel::all(),
            'stats' => ValueAddedServiceModel::stats(),
            'modes' => ValueAddedServiceModel::PRICE_MODES,
            'rules' => [
                'storage_fee_per_item_day' => (string) SettingsModel::get('storage_fee_per_item_day', '1.00'),
                'storage_grace_days'       => (string) SettingsModel::get('storage_grace_days', '3'),
                'auto_sign_days'           => (string) SettingsModel::get('auto_sign_days', '15'),
                'points_award_delay_days'  => (string) SettingsModel::get('points_award_delay_days', '7'),
                'points_per_yuan'          => (string) SettingsModel::get('points_per_yuan', '1'),
                'points_negative_block'    => (string) SettingsModel::get('points_negative_block', '1'),
            ],
        ], 'services', '增值服务设置');
    }

    /**
     * 保存仓储与费用、自动签收与积分规则（业务规则区块，与增值服务列表同页）
     */
    public function saveRules(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();

        $price = (float) Request::input('storage_fee_per_item_day', 0);
        $grace = (int) Request::input('storage_grace_days', 0);
        $autoSign = (int) Request::input('auto_sign_days', 0);
        $delay = (int) Request::input('points_award_delay_days', 0);
        $rate = (float) Request::input('points_per_yuan', 0);
        $block = (int) Request::input('points_negative_block', 0) === 1 ? '1' : '0';

        if ($price < 0 || $price > 9999.99) {
            $this->fail('仓储费单价需在 0 ~ 9999.99 元之间');
        }
        if ($grace < 0 || $grace > 365) {
            $this->fail('宽限天数需在 0 ~ 365 天之间');
        }
        if ($autoSign < 0 || $autoSign > 365) {
            $this->fail('自动签收天数需在 0 ~ 365 天之间（0 表示不自动签收）');
        }
        if ($delay < 0 || $delay > 365) {
            $this->fail('积分缓冲天数需在 0 ~ 365 天之间');
        }
        if ($rate < 0 || $rate > 100) {
            $this->fail('每元积分比例需在 0 ~ 100 之间');
        }

        SettingsModel::upsert('storage_fee_per_item_day', number_format($price, 2, '.', ''), 'storage', '超期仓储费单价（元/件/天，超免费期后计收）');
        SettingsModel::upsert('storage_grace_days', (string) $grace, 'storage', '超免费期后的宽限天数');
        SettingsModel::upsert('auto_sign_days', (string) $autoSign, 'order', '自动签收天数（出库后 N 天未手动签收则系统自动签收，0 关闭）');
        SettingsModel::upsert('points_award_delay_days', (string) $delay, 'member', '积分到账缓冲天数（签收后 T+N 天到账，0 表示签收即到账）');
        SettingsModel::upsert('points_per_yuan', number_format($rate, 2, '.', ''), 'member', '每消费 1 元赠送的积分（基数取实付基础运费）');
        SettingsModel::upsert('points_negative_block', $block, 'member', '负积分限制下单（1 限制 0 不限制）');

        $this->ok(null, '规则已保存，后续新产生的费用与积分按新规则执行');
    }

    /**
     * 列表局部渲染（保存/启停/删除后仅替换数据区，规范 §2.5）
     */
    public function list(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->renderTable('admin/services_table', [
            'rows'  => ValueAddedServiceModel::all(),
            'modes' => ValueAddedServiceModel::PRICE_MODES,
        ]);
    }

    /**
     * 新建或修改增值服务：改价只影响后续下单，历史运单存的是名称与价格快照
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();

        $id = (int) Request::input('id', 0);
        $name = trim((string) Request::input('name', ''));
        $description = trim((string) Request::input('description', ''));
        $priceMode = (int) Request::input('price_mode', 1);
        $price = round((float) Request::input('price', 0), 2);
        $sort = (int) Request::input('sort', 0);
        $status = (int) Request::input('status', 1) === 1 ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 50) {
            $this->fail('服务名称需为 1~50 字');
        }
        if (mb_strlen($description) > 255) {
            $this->fail('服务说明不能超过 255 字');
        }
        if (!isset(ValueAddedServiceModel::PRICE_MODES[$priceMode])) {
            $this->fail('无效的计价方式');
        }
        if ($price < 0 || $price > self::MAX_PRICE) {
            $this->fail('单价需在 0~' . self::MAX_PRICE . ' 元之间');
        }
        if ($sort < 0 || $sort > self::MAX_SORT) {
            $this->fail('排序值需在 0~' . self::MAX_SORT . ' 之间');
        }
        if (ValueAddedServiceModel::nameExists($name, $id)) {
            $this->fail('已存在同名服务，请更换名称');
        }

        $payload = [
            'name'        => $name,
            'description' => $description,
            'price_mode'  => $priceMode,
            'price'       => $price,
            'status'      => $status,
            'sort'        => $sort,
        ];

        if ($id > 0) {
            if (ValueAddedServiceModel::find($id) === null) {
                $this->fail('服务不存在');
            }
            ValueAddedServiceModel::update($id, $payload);
            $this->ok(null, '增值服务已保存');
        }

        try {
            $newId = ValueAddedServiceModel::create($payload);
        } catch (RuntimeException $e) {
            $this->fail($e->getMessage());
        }
        $this->ok(['id' => $newId], '增值服务已创建');
    }

    public function toggle(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        if (ValueAddedServiceModel::find($id) === null) {
            $this->fail('服务不存在');
        }
        ValueAddedServiceModel::toggle($id);
        $this->ok(null, '服务状态已切换');
    }

    /**
     * 删除服务：已被运单勾选过的服务只能停用，
     * 直接删除会让历史运单的增值服务明细指向不存在的服务
     */
    public function delete(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        if (ValueAddedServiceModel::find($id) === null) {
            $this->fail('服务不存在');
        }
        $used = ValueAddedServiceModel::usedCount($id);
        if ($used > 0) {
            $this->fail('该服务已被 ' . $used . ' 条运单明细引用，删除会影响历史单据，请改为「停用」');
        }
        ValueAddedServiceModel::delete($id);
        $this->ok(null, '增值服务已删除');
    }
}
