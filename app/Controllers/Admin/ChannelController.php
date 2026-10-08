<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/ChannelController.php
 * @description 物流渠道管理：渠道增改/启停 + 详情页（分区/价格/附加费维护）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Models\ChannelModel;

final class ChannelController extends BaseController
{
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('channel.view');
        $this->renderList((string) Request::query('kw', ''));
    }

    private function renderList(string $keyword): void
    {
        $page = $this->pageParams();
        $result = ChannelModel::search($keyword, $page['limit'], $page['offset']);
        $data = [
            'title'   => '物流渠道',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $page['page'],
            'pages'   => (int) ceil($result['total'] / $page['limit']) ?: 1,
            'keyword' => $keyword,
            'base'    => '/admin/channels',
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/channels_table', $data);
        }
        $this->adminView('admin/channels', $data, 'channels', '物流渠道管理');
    }

    /**
     * 渠道详情（分区/价格/附加费维护入口）
     */
    public function detail(string $id): never
    {
        $this->requireStaff();
        $this->enforce('channel.view');
        $channel = ChannelModel::findById((int) $id);
        if ($channel === null) {
            Response::redirect('/admin/channels');
        }
        $data = [
            'title'      => $channel['name'],
            'channel'    => $channel,
            'zones'      => ChannelModel::zones((int) $id),
            'prices'     => ChannelModel::currentPrices((int) $id),
            'surcharges' => ChannelModel::activeSurcharges((int) $id),
        ];
        if (Request::header('X-Requested-With') === 'fetch') {
            $this->renderTable('admin/channel_detail_content', $data);
        }
        $this->adminView('admin/channel_detail', $data, 'channels', '渠道详情');
    }

    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $id = (int) Request::input('id', 0);
        $d = [
            'channel_code'   => strtoupper(trim((string) Request::input('channel_code', ''))),
            'name'           => trim((string) Request::input('name', '')),
            'carrier'        => trim((string) Request::input('carrier', '')),
            'goods_type'     => (int) Request::input('goods_type', 1),
            'volume_divisor' => max(1000, (int) Request::input('volume_divisor', 5000)),
            'aging_desc'     => trim((string) Request::input('aging_desc', '')),
            'tax_mode'       => (int) Request::input('tax_mode', 1),
            'label_template' => trim((string) Request::input('label_template', '')),
            'track_api_code' => trim((string) Request::input('track_api_code', '')),
            'status'         => (int) Request::input('status', 1),
            'sort'           => (int) Request::input('sort', 0),
        ];
        foreach (['channel_code', 'name', 'carrier'] as $required) {
            if ($d[$required] === '') {
                $this->fail('请完整填写渠道代码、名称与承运商');
            }
        }
        if (!preg_match('/^[A-Z0-9\-]{2,32}$/', $d['channel_code'])) {
            $this->fail('渠道代码需 2~32 位大写字母、数字或中划线');
        }
        if ($id > 0) {
            ChannelModel::update($id, $d);
            $this->ok(null, '渠道已更新');
        }
        ChannelModel::create($d);
        $this->ok(null, '渠道已创建');
    }

    public function toggle(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $status = ChannelModel::toggleStatus((int) Request::input('id', 0));
        $this->ok(['status' => $status], $status === 1 ? '渠道已启用' : '渠道已停用');
    }

    /**
     * 新增分区（国家码以逗号分隔输入，落库为 JSON 数组）
     */
    public function addZone(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $channelId = (int) Request::input('channel_id', 0);
        $zoneName = trim((string) Request::input('zone_name', ''));
        $raw = trim((string) Request::input('countries', ''));
        if ($channelId <= 0 || $zoneName === '' || $raw === '') {
            $this->fail('请填写分区名称与国家码');
        }
        $countries = array_values(array_filter(array_map('trim', explode(',', strtoupper($raw)))));
        foreach ($countries as $cc) {
            if (!preg_match('/^[A-Z]{2}$/', $cc)) {
                $this->fail('国家码需为两位字母（ISO 3166-1），如 US,CA');
            }
        }
        ChannelModel::zoneCreate($channelId, $zoneName, $countries);
        $this->ok(null, '分区已创建');
    }

    /**
     * 保存分区价格：一个分区只保留一条价格，再次保存即覆盖原设置并立即生效
     * 不再逐次追加历史价格，避免列表里堆积已失效的旧价格、与实际计费的那条对不上
     */
    public function savePrice(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $channelId = (int) Request::input('channel_id', 0);
        $zoneId = (int) Request::input('zone_id', 0);
        $d = [
            'first_weight'   => (float) Request::input('first_weight', 0),
            'first_fee'      => (float) Request::input('first_fee', 0),
            'step_weight'    => (float) Request::input('step_weight', 0.5),
            'step_fee'       => (float) Request::input('step_fee', 0),
            'effective_from' => trim((string) Request::input('effective_from', '')),
            'effective_to'   => trim((string) Request::input('effective_to', '')),
        ];
        if ($channelId <= 0 || $zoneId <= 0 || $this->zoneNameOf($channelId, $zoneId) === null) {
            $this->fail('请选择该渠道下的有效分区');
        }
        $zoneName = (string) $this->zoneNameOf($channelId, $zoneId);
        if ($d['first_weight'] <= 0 || $d['first_fee'] <= 0 || $d['step_weight'] <= 0 || $d['step_fee'] < 0) {
            $this->fail('请正确填写首重与续重计费参数');
        }
        // 生效日可以选将来：届时自动接替当前价格，实现「预约调价」
        if ($d['effective_from'] === '') {
            $d['effective_from'] = date('Y-m-d');
        }
        if (!$this->isDate($d['effective_from'])) {
            $this->fail('生效日期格式应为 YYYY-MM-DD');
        }
        if ($d['effective_to'] !== '') {
            if (!$this->isDate($d['effective_to'])) {
                $this->fail('失效日期格式应为 YYYY-MM-DD');
            }
            if (strtotime($d['effective_to']) < strtotime($d['effective_from'])) {
                $this->fail('失效日期不能早于生效日期');
            }
            if (strtotime($d['effective_to']) < strtotime(date('Y-m-d'))) {
                $this->fail('失效日期不能早于今天（否则该价格保存后即刻失效）');
            }
        }

        $result = ChannelModel::priceSave($channelId, $zoneId, $d);
        $isFuture = strtotime($d['effective_from']) > strtotime(date('Y-m-d'));
        if ($isFuture) {
            $message = $result['created']
                ? '「' . $zoneName . '」预约价格已保存，将于 ' . $d['effective_from'] . ' 起自动生效'
                : '「' . $zoneName . '」预约价格已更新，将于 ' . $d['effective_from'] . ' 起自动生效';
        } else {
            $message = $result['created']
                ? '「' . $zoneName . '」价格已保存，立即生效'
                : '「' . $zoneName . '」原价格已覆盖，立即生效';
        }
        $this->ok(['created' => $result['created'], 'scheduled' => $isFuture], $message);
    }

    /**
     * 保存附加费：同一渠道同一费用类型只保留一条，再次保存即覆盖该类型原设置
     */
    public function saveSurcharge(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $channelId = (int) Request::input('channel_id', 0);
        $d = [
            'fee_type' => (int) Request::input('fee_type', 0),
            'name'     => trim((string) Request::input('name', '')),
            'calc_mode' => (int) Request::input('calc_mode', 0),
            'fee_value' => (float) Request::input('fee_value', 0),
        ];
        if ($channelId <= 0 || !in_array($d['fee_type'], [1, 2, 3, 4, 5, 6], true) || !in_array($d['calc_mode'], [1, 2, 3, 4], true)) {
            $this->fail('请正确选择附加费类型与计算方式');
        }
        if ($d['name'] === '' || $d['fee_value'] <= 0) {
            $this->fail('请填写附加费名称与计算基准值');
        }
        $result = ChannelModel::surchargeSave($channelId, $d);
        $this->ok(['created' => $result['created']], $result['created']
            ? '附加费已保存，立即参与运费试算'
            : '该类型的原附加费已覆盖，立即参与运费试算');
    }

    /**
     * 分区名（同时校验分区归属该渠道，避免跨渠道写入价格）
     */
    private function zoneNameOf(int $channelId, int $zoneId): ?string
    {
        foreach (ChannelModel::zones($channelId) as $zone) {
            if ((int) $zone['id'] === $zoneId) {
                return (string) $zone['zone_name'];
            }
        }
        return null;
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            && strtotime($value) !== false
            && date('Y-m-d', strtotime($value)) === $value;
    }
}
