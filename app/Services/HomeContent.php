<?php declare(strict_types=1);
/**
 * @file app/Services/HomeContent.php
 * @description 官网首页可配置内容：统计数字 / 服务承诺 / 常见问题
 *               默认值即首页设计稿的原值，后台「站点信息」改后首页即时生效
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */

namespace App\Services;

use App\Models\SettingsModel;
use App\Models\WarehouseModel;

final class HomeContent
{
    /** FAQ 与首页数字中免费仓储天数的占位符，渲染时按仓库设置替换（足够短，适配后台数值框 12 字限制） */
    public const STORAGE_PLACEHOLDER = '{days}';

    /** 无启用仓库时的免费仓储天数回落值（与设计稿默认一致） */
    private const STORAGE_DAYS_FALLBACK = 90;

    /** 统计数字（value=数值，unit=单位后缀，label=说明） */
    public const STATS_DEFAULT = [
        ['value' => '220',  'unit' => '+',   'label' => '覆盖国家与地区'],
        ['value' => '3.8',  'unit' => '亿+', 'label' => '年处理包裹量'],
        ['value' => '40',   'unit' => '+',   'label' => '全球自营海外仓'],
        ['value' => '99.2', 'unit' => '%',   'label' => '干线时效达成率'],
    ];

    /** 服务承诺（value=数值，unit=单位，label=说明；仓储天数由 storageDays() 动态填充） */
    public const PROMISES_DEFAULT = [
        ['value' => '8',    'unit' => '小时', 'label' => '平均清关放行，白名单通道优先查验'],
        ['value' => '48',   'unit' => '小时', 'label' => '理赔快速到账，资料齐全极速审核'],
        ['value' => self::STORAGE_PLACEHOLDER, 'unit' => '天', 'label' => '免费仓储期，多商家包裹从容合箱'],
        ['value' => '7×24', 'unit' => '小时', 'label' => '专属顾问全天在线，1 对 1 中文服务'],
    ];

    /** 常见问题默认值（后台「营销内容 → 常见问题」可改，该项在库中为空时使用） */
    public const FAQ_DEFAULT = [
        ['q' => '国际转运一般需要多久？', 'a' => '空运 3-7 个工作日，海运 25-35 天，具体以所选渠道的时效说明为准；下单前可在运费试算中看到预计时效。'],
        ['q' => '带电产品、化妆品、食品能寄吗？', 'a' => '带电（内置电池）、化妆品、食品属敏感货，需走支持敏感货的渠道；下单时选择标注「敏感货」的渠道即可，禁运品清单见帮助中心。'],
        ['q' => '有哪些免费增值服务？', 'a' => '免费仓储期内存放、出库质检拍照、智能合箱减重、基础加固打包等均不额外收费；拍照留档、加固、换箱等可选项目按明细计费。'],
        ['q' => '包裹丢失或损坏怎么办？', 'a' => '在「客服工单 → 理赔申请」提交，附包裹编号、问题描述与凭证照片；资料齐全后按核定金额赔付到账户余额。'],
        ['q' => '清关需要我提供什么资料？', 'a' => '下单时如实填写报关明细（品名、数量、申报价值）即可；部分国家需要收件人身份信息时，客服会通过站内信单独联系你。'],
        ['q' => '如何合并多个商家的包裹？', 'a' => '把各商家的包裹预报到同一账户，到仓认领后在「新建转运」里勾选多个包裹一起提交，系统会按合箱后的实际重量计费。'],
    ];

    /**
     * 首页统计数字：后台配置优先，未配置或格式非法时回落设计稿默认值
     * @return array<int, array{value: string, unit: string, label: string}>
     */
    public static function stats(): array
    {
        return self::rows('homepage_stats', self::STATS_DEFAULT);
    }

    /**
     * 首页免费仓储天数（官网流程卡片、服务承诺、FAQ {days} 共用同一解析结果）：
     * ① 后台「基本信息 → 官网首页数字 → 服务承诺」里手填的数字优先，保证对外口径一致；
     * ② 承诺行用 {days} 占位符或未配置时按启用仓库的最大免费仓储天数；
     * ③ 无启用仓库回落设计稿默认值，避免全新安装首页出现 0 天
     */
    public static function storageDays(): int
    {
        foreach (self::promisesRaw() as $row) {
            if (!str_contains((string) ($row['label'] ?? ''), '免费仓储')) {
                continue;
            }
            $value = trim((string) ($row['value'] ?? ''));
            if ($value !== '' && $value !== self::STORAGE_PLACEHOLDER && preg_match('/^\d+(\.\d+)?$/', $value)) {
                return (int) $value;
            }
            break;
        }
        $max = WarehouseModel::maxFreeStorageDays();
        return $max ?? self::STORAGE_DAYS_FALLBACK;
    }

    /**
     * 服务承诺原始行（不做占位符替换）：后台编辑页用，保证 {days} 原样保存不被固化成数字
     * @return array<int, array{value: string, unit: string, label: string}>
     */
    public static function promisesRaw(): array
    {
        return self::rows('homepage_promises', self::PROMISES_DEFAULT);
    }

    /**
     * 首页服务承诺数字：后台配置优先；仓储天数按仓库设置动态填充（后台文本也可用 {days} 占位符）
     * @return array<int, array{value: string, unit: string, label: string}>
     */
    public static function promises(): array
    {
        $rows = self::promisesRaw();
        $days = (string) self::storageDays();
        foreach ($rows as &$row) {
            $row['value'] = str_replace(self::STORAGE_PLACEHOLDER, $days, $row['value']);
            $row['label'] = str_replace(self::STORAGE_PLACEHOLDER, $days, $row['label']);
        }
        return $rows;
    }

    /**
     * 常见问题：取后台「营销内容」的 content_faq（按空行分段，首行为问题、其余为答案）；
     * 答案中的 {days} 按仓库免费仓储天数替换，后台改仓库设置后首页即时生效
     * @return array<int, array{q: string, a: string}>
     */
    public static function faqs(): array
    {
        $raw = trim(SettingsModel::get('content_faq', ''));
        if ($raw === '') {
            $items = self::FAQ_DEFAULT;
        } else {
            $items = [];
            foreach (preg_split('/\n\s*\n/', $raw) ?: [] as $block) {
                $lines = array_values(array_filter(array_map('trim', explode("\n", trim($block))), static fn($l) => $l !== ''));
                if ($lines === []) {
                    continue;
                }
                $items[] = ['q' => (string) array_shift($lines), 'a' => implode(' ', $lines)];
            }
            if ($items === []) {
                $items = self::FAQ_DEFAULT;
            }
        }
        $days = (string) self::storageDays();
        foreach ($items as &$item) {
            $item['q'] = str_replace(self::STORAGE_PLACEHOLDER, $days, $item['q']);
            $item['a'] = str_replace(self::STORAGE_PLACEHOLDER, $days, $item['a']);
        }
        return $items;
    }

    /**
     * 读取 JSON 配置的数字行；结构不合法时回落默认值，避免首页出现空区块
     * @param array<int, array{value: string, unit: string, label: string}> $default
     * @return array<int, array{value: string, unit: string, label: string}>
     */
    private static function rows(string $key, array $default): array
    {
        $raw = trim(SettingsModel::get($key, ''));
        if ($raw === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $default;
        }
        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = trim((string) ($row['value'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($value === '' && $label === '') {
                continue;
            }
            $rows[] = ['value' => $value, 'unit' => trim((string) ($row['unit'] ?? '')), 'label' => $label];
        }
        return $rows === [] ? $default : $rows;
    }

    /**
     * 统计数字的小数位数（供首页数字滚动动画使用）
     */
    public static function decimals(string $value): int
    {
        $dot = strpos($value, '.');
        return $dot === false ? 0 : strlen($value) - $dot - 1;
    }
}
