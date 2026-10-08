<?php declare(strict_types=1);
/**
 * @file app/Services/RateContent.php
 * @description 转运资费页内容：按仓库分组的线路资费与增值服务
 *               两项内容均由后台「转运资费」页录入（JSON 行数组），前台按仓库自动分组展示
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Services;

use App\Models\SettingsModel;

final class RateContent
{
    /**
     * 线路资费字段：键 => [标签, 输入框宽度(px), 占位提示]
     * 扁平结构（每行一个「仓库+线路」），前台按「仓库」列自动分组，
     * 这样后台是一张普通表格，无需嵌套式重复行编辑器
     */
    public const ROUTE_FIELDS = [
        'warehouse'    => ['仓库类别', 150, '如：美国加州仓'],
        'route'        => ['线路名称', 170, '如：美西快船'],
        'first_weight' => ['首重(kg)', 100, '0.5'],
        'first_fee'    => ['首重价(元)', 110, '85'],
        'step_weight'  => ['续重(kg)', 100, '0.5'],
        'step_fee'     => ['续重价(元)', 110, '35'],
        'aging'        => ['平均时效', 140, '如：12-15 天'],
    ];

    /** 增值服务字段：键 => [标签, 输入框宽度(px), 占位提示] */
    public const SERVICE_FIELDS = [
        'name'  => ['服务名称', 190, '如：拍照留档'],
        'price' => ['价格', 150, '如：3 元/张'],
        'desc'  => ['服务说明', 0, '如：逐件拍摄外箱与内件，出库前留档'],
    ];

    /** 后台表格默认给出的空行数（便于直接填写） */
    public const BLANK_ROWS = 5;

    /**
     * 线路资费行（后台已填写的内容；未配置时返回空数组，前台展示空状态引导）
     * @return array<int, array<string, string>>
     */
    public static function routes(): array
    {
        return self::rows('rates_routes', array_keys(self::ROUTE_FIELDS));
    }

    /**
     * 线路资费按仓库分组（保持后台录入顺序，空白仓库名归入「其他」）
     * @return array<int, array{warehouse: string, routes: array<int, array<string, string>>}>
     */
    public static function routesByWarehouse(): array
    {
        $groups = [];
        $index = [];
        foreach (self::routes() as $row) {
            $warehouse = trim((string) ($row['warehouse'] ?? ''));
            if ($warehouse === '') {
                $warehouse = '其他';
            }
            if (!isset($index[$warehouse])) {
                $index[$warehouse] = count($groups);
                $groups[] = ['warehouse' => $warehouse, 'routes' => []];
            }
            $groups[$index[$warehouse]]['routes'][] = $row;
        }
        return $groups;
    }

    /**
     * 增值服务行
     * @return array<int, array<string, string>>
     */
    public static function services(): array
    {
        return self::rows('rates_services', array_keys(self::SERVICE_FIELDS));
    }

    /**
     * 后台编辑用：不足最小行数时补空白行，保证页面始终可录入
     * @return array<int, array<string, string>>
     */
    public static function editableRoutes(int $min = self::BLANK_ROWS): array
    {
        return self::withBlankRows(self::routes(), array_keys(self::ROUTE_FIELDS), $min);
    }

    /** @return array<int, array<string, string>> */
    public static function editableServices(int $min = self::BLANK_ROWS): array
    {
        return self::withBlankRows(self::services(), array_keys(self::SERVICE_FIELDS), $min);
    }

    /**
     * 保存前的规范化：去掉整行空白的行，逐字段裁剪并做长度与数字校验
     * @param array<int, array<string, string>> $rows
     * @param array<int, string> $fields
     * @return array<int, array<string, string>>
     */
    public static function normalize(array $rows, array $fields): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            $empty = true;
            foreach ($fields as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                // 长度上限与列宽匹配，避免后台贴入超长文本撑破前台表格
                if (mb_strlen($value) > 60) {
                    $value = mb_substr($value, 0, 60);
                }
                $item[$field] = $value;
                if ($value !== '') {
                    $empty = false;
                }
            }
            if ($empty) {
                continue;
            }
            // 线路行必须能看出属于哪个仓库、是哪条线路，否则前台无法成行展示
            if (in_array('warehouse', $fields, true) && ($item['warehouse'] === '' || $item['route'] === '')) {
                continue;
            }
            if (in_array('name', $fields, true) && $item['name'] === '') {
                continue;
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * @param array<int, string> $fields
     * @return array<int, array<string, string>>
     */
    private static function rows(string $key, array $fields): array
    {
        $raw = trim(SettingsModel::get($key, ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return self::normalize($decoded, $fields);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<int, string> $fields
     * @return array<int, array<string, string>>
     */
    private static function withBlankRows(array $rows, array $fields, int $min): array
    {
        $blank = array_fill_keys($fields, '');
        while (count($rows) < $min) {
            $rows[] = $blank;
        }
        return $rows;
    }
}
