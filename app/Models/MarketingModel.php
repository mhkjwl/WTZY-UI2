<?php declare(strict_types=1);
/**
 * @file app/Models/MarketingModel.php
 * @description 营销内容数据访问：内容页（协议/隐私/禁运品/FAQ）配置
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Models;

final class MarketingModel
{
    /**
     * 保存内容页（协议/隐私/禁运品/FAQ 归入 content 配置组）
     */
    public static function upsertContent(string $key, string $value): void
    {
        SettingsModel::upsert($key, $value, 'content', '内容页');
    }

    /**
     * 内容页键（协议/隐私/禁运品/FAQ），存储于 settings 的 content 组
     */
    public static function contentList(): array
    {
        $keys = [
            'content_user_agreement' => '用户协议',
            'content_privacy_policy' => '隐私政策',
            'content_contraband'     => '禁运品说明',
            'content_faq'            => '常见问题',
        ];
        $result = [];
        foreach ($keys as $key => $label) {
            $result[] = [
                'key' => $key,
                'label' => $label,
                'value' => SettingsModel::get($key, ''),
            ];
        }
        return $result;
    }
}
