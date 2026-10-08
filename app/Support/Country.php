<?php declare(strict_types=1);
/**
 * @file app/Support/Country.php
 * @description 国家/地区代码中文名（展示用；未知代码原样返回，避免空展示）
 * @author AI
 * @version 0.6.2
 * @date 2026-10-09
 */

namespace App\Support;

final class Country
{
    /**
     * 常用国家/地区中文名（覆盖国际转运业务范围）
     */
    private const NAMES = [
        'US' => '美国',
        'CA' => '加拿大',
        'MX' => '墨西哥',
        'BR' => '巴西',
        'GB' => '英国',
        'DE' => '德国',
        'FR' => '法国',
        'IT' => '意大利',
        'ES' => '西班牙',
        'NL' => '荷兰',
        'JP' => '日本',
        'KR' => '韩国',
        'SG' => '新加坡',
        'MY' => '马来西亚',
        'TH' => '泰国',
        'VN' => '越南',
        'ID' => '印度尼西亚',
        'PH' => '菲律宾',
        'IN' => '印度',
        'AU' => '澳大利亚',
        'NZ' => '新西兰',
        'AE' => '阿联酋',
        'RU' => '俄罗斯',
        'HK' => '中国香港',
        'TW' => '中国台湾',
        'MO' => '中国澳门',
    ];

    /** 中文显示名（未知代码原样返回） */
    public static function name(string $code): string
    {
        $code = strtoupper(trim($code));
        return $code === '' ? '' : (self::NAMES[$code] ?? $code);
    }
}
