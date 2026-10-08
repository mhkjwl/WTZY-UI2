<?php declare(strict_types=1);
/**
 * @file app/Controllers/Admin/RateController.php
 * @description 转运资费：前台资费页的内容维护（按仓库的线路资费 + 增值服务）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\SettingsModel;
use App\Services\RateContent;

final class RateController extends BaseController
{
    /**
     * 资费内容维护页
     */
    public function index(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->adminView('admin/rates', [
            'title'          => '转运资费',
            'routeFields'    => RateContent::ROUTE_FIELDS,
            'serviceFields'  => RateContent::SERVICE_FIELDS,
            'routeRows'      => RateContent::editableRoutes(),
            'serviceRows'    => RateContent::editableServices(),
        ], 'rates', '转运资费');
    }

    /**
     * 保存资费内容（两组表格一并提交）
     * 整行留空的忽略，缺少关键列的行也会被丢弃（见 RateContent::normalize）
     */
    public function save(): never
    {
        $this->requireStaff();
        $this->enforce('channel.manage');
        $this->verifyCsrf();
        $this->saveRows('rates_routes', array_keys(RateContent::ROUTE_FIELDS), '线路资费（按仓库）');
        $this->saveRows('rates_services', array_keys(RateContent::SERVICE_FIELDS), '增值服务');
        $this->ok(null, '转运资费已保存');
    }

    /**
     * 采集并列数组并组装为行数组：前端按 `字段名+[]` 提交，同一行下标对齐
     * @param array<int, string> $fields
     */
    private function saveRows(string $key, array $fields, string $label): void
    {
        $columns = [];
        $max = 0;
        foreach ($fields as $field) {
            $col = (array) Request::input($key . '_' . $field, []);
            $columns[$field] = $col;
            $max = max($max, count($col));
        }
        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $row = [];
            foreach ($fields as $field) {
                $row[$field] = (string) ($columns[$field][$i] ?? '');
            }
            $rows[] = $row;
        }
        $normalized = RateContent::normalize($rows, $fields);
        SettingsModel::upsert(
            $key,
            $normalized === [] ? '' : (string) json_encode($normalized, JSON_UNESCAPED_UNICODE),
            'rate',
            $label
        );
    }
}
