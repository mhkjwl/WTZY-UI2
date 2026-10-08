<?php declare(strict_types=1);
/**
 * @file app/Services/BrandAsset.php
 * @description 站点品牌素材：浏览器图标与网站 Logo 的固定化存取
 *              上传即统一规格落盘（固定文件名覆盖）：favicon 居中裁剪为 500×500 固定方形，
 *              logo 等比压缩到上限内；页面侧显示尺寸由样式固定，素材尺寸差异不会撑乱版式
 * @author AI
 * @version 0.6.1
 * @date 2026-10-09
 */

namespace App\Services;

use RuntimeException;

final class BrandAsset
{
    /** 浏览器图标：固定名称 favicon.ico（内容为 PNG，浏览器按内容识别，扩展名保持 .ico 约定） */
    public const FAVICON = 'favicon.ico';

    /** 图标画布：固定 500×500 方形（非正方形素材居中裁剪） */
    public const FAVICON_SIDE = 500;

    /** 网站 Logo：固定名称 logo.png，存储时等比压缩到上限内（不放大小图） */
    public const LOGO = 'logo.png';
    public const LOGO_MAX_WIDTH = 720;
    public const LOGO_MAX_HEIGHT = 240;

    /** 素材原图边长上限：超出请先压缩，避免解码超大图耗尽内存 */
    private const MAX_SOURCE_SIDE = 3000;

    /** 浏览器图标访问地址（未上传为空串） */
    public static function faviconUrl(): string
    {
        return self::url(self::FAVICON);
    }

    /** 网站 Logo 访问地址（未上传为空串） */
    public static function logoUrl(): string
    {
        return self::url(self::LOGO);
    }

    /** 浏览器标签图标 <link> 标签（未上传输出空串，页面不产生死链接） */
    public static function faviconTag(): string
    {
        $url = self::faviconUrl();
        return $url === '' ? '' : '<link rel="icon" type="image/png" href="' . htmlspecialchars($url, ENT_QUOTES) . '">';
    }

    /**
     * 处理并保存品牌素材（固定文件名覆盖），返回带版本号的访问地址
     */
    public static function store(string $type, array $file): string
    {
        $name = match ($type) {
            'favicon' => self::FAVICON,
            'logo'    => self::LOGO,
            default   => throw new RuntimeException('未知的素材类型'),
        };
        $bytes = self::readSource($file);

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('图片解析失败，请更换图片重试');
        }
        $width = imagesx($source);
        $height = imagesy($source);

        if ($type === 'favicon') {
            $side = min($width, $height);
            $canvas = self::transparentCanvas(self::FAVICON_SIDE, self::FAVICON_SIDE);
            imagecopyresampled(
                $canvas, $source,
                0, 0,
                (int) (($width - $side) / 2), (int) (($height - $side) / 2),
                self::FAVICON_SIDE, self::FAVICON_SIDE, $side, $side
            );
        } else {
            $scale = min(1, self::LOGO_MAX_WIDTH / $width, self::LOGO_MAX_HEIGHT / $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $canvas = self::transparentCanvas($targetWidth, $targetHeight);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        }
        $dir = BASE_PATH . '/public/uploads/site';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('素材目录不可写，请联系管理员');
        }
        $path = $dir . '/' . $name;
        if (!imagepng($canvas, $path)) {
            throw new RuntimeException('素材保存失败，请重试');
        }
        // 覆盖上传后清除文件状态缓存再取时间戳，保证版本号变化令浏览器放弃旧图
        clearstatcache(true, $path);
        return '/uploads/site/' . $name . '?v=' . filemtime($path);
    }

    /**
     * 读取并校验上传原图字节（类型与大小判定与图片上传服务保持一致）
     */
    private static function readSource(array $file): string
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('请选择要上传的图片');
        }
        if ((int) ($file['size'] ?? 0) > UploadService::MAX_BYTES) {
            throw new RuntimeException('图片不能超过 5MB');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $mime = UploadService::detectMime($tmp);
        if (!isset(UploadService::ALLOWED_MIME[$mime])) {
            throw new RuntimeException('仅支持 JPG / PNG / WebP 图片');
        }
        $info = @getimagesize($tmp);
        if (!is_array($info)) {
            throw new RuntimeException('图片解析失败，请更换图片重试');
        }
        if ((int) $info[0] > self::MAX_SOURCE_SIDE || (int) $info[1] > self::MAX_SOURCE_SIDE) {
            throw new RuntimeException('图片边长不能超过 ' . self::MAX_SOURCE_SIDE . ' 像素，请先压缩');
        }
        $bytes = (string) @file_get_contents($tmp);
        if ($bytes === '') {
            throw new RuntimeException('图片读取失败，请重试');
        }
        return $bytes;
    }

    private static function url(string $name): string
    {
        $path = BASE_PATH . '/public/uploads/site/' . $name;
        return is_file($path) ? '/uploads/site/' . $name . '?v=' . filemtime($path) : '';
    }

    /**
     * 透明底画布：保留 PNG 透明通道，带透明的源图处理结果同样透明
     */
    private static function transparentCanvas(int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        return $canvas;
    }
}
