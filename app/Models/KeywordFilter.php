<?php declare(strict_types=1);
/**
 * @file app/Models/KeywordFilter.php
 * @description 关键词检索条件构造：多列 OR LIKE 的占位符生成
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Models;

final class KeywordFilter
{
    /**
     * 生成「多列任一命中」的 LIKE 条件
     *
     * 连接层关闭了预处理模拟（走 MySQL 服务端预处理），同名命名占位符在同一语句中
     * 无法复用，会抛 HY093，因此这里按列生成独立占位符并绑定同一个关键词。
     *
     * @param string[] $columns 参与匹配的列名（含表别名）
     * @param array    $params  绑定参数容器，按引用写入
     * @param string   $keyword 用户输入的关键词（由占位符绑定，不做拼接）
     */
    public static function any(array $columns, array &$params, string $keyword, string $prefix = 'kw'): string
    {
        $conditions = [];
        foreach (array_values($columns) as $index => $column) {
            $placeholder = ':' . $prefix . $index;
            $conditions[] = $column . ' LIKE ' . $placeholder;
            $params[$placeholder] = '%' . $keyword . '%';
        }
        return '(' . implode(' OR ', $conditions) . ')';
    }
}
