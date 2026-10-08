<?php declare(strict_types=1);
/**
 * @file app/Core/View.php
 * @description 视图渲染：模板取值 + 公共布局包裹 + HTML 输出转义助手
 * @author AI
 * @version 0.6.1
 * @date 2026-10-08
 */

namespace App\Core;

use RuntimeException;

final class View
{
    /**
     * 渲染模板并可选包裹公共布局；$content 为模板渲染结果注入布局
     * 布局同时拿到 __view（当前模板名），用于判断该页是否已切换新版界面样式
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/base'): void
    {
        $content = self::partial($template, $data);
        if ($layout !== null) {
            $data['content'] = $content;
            $data['__view'] = $template;
            echo self::partial($layout, $data);
        } else {
            echo $content;
        }
    }

    /**
     * 当前视图是否已迁移到新版界面（光年 v5）：迁移进度登记在 config/ui.php，
     * 布局据此对已迁移页面停用旧样式表，未登记页面保持原观感，逐批切换互不影响
     */
    public static function isMigrated(string $view): bool
    {
        static $list = null;
        if ($list === null) {
            $file = BASE_PATH . '/config/ui.php';
            $list = is_file($file) ? (array) require $file : [];
        }
        return in_array($view, $list, true);
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = dirname(__DIR__, 2) . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("视图不存在: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * HTML 输出转义（XSS 防护，规范 §8.2.3）：所有模板输出变量必须经此处理
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 静态资源地址：附加文件修改时间作为版本号，避免改了样式/脚本浏览器仍用旧缓存
     */
    public static function asset(string $path): string
    {
        $file = BASE_PATH . '/public' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '';
        return $path . ($version !== '' ? '?v=' . $version : '');
    }

    /**
     * 彩色徽标：状态与类型取值统一走这里，避免页面只剩纯文字
     * @param string $label 显示文案
     * @param string $tone  blue|green|emerald|teal|orange|amber|red|rose|purple|violet|indigo|cyan|sky|fuchsia|gray
     * @param string $extra 追加的 class（如 mono）
     */
    public static function badge(string $label, string $tone = 'gray', string $extra = ''): string
    {
        $class = 'badge badge--' . $tone . ($extra !== '' ? ' ' . $extra : '');
        return '<span class="' . $class . '">' . self::e($label) . '</span>';
    }
}
