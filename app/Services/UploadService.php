<?php declare(strict_types=1);
/**
 * @file app/Services/UploadService.php
 * @description 图片上传：类型/大小校验 → 按年月归档落盘 → 返回可访问 URL（用户端与仓库端共用）
 * @author AI
 * @version 0.6
 * @date 2026-09-13
 */

namespace App\Services;

use App\Models\PackageModel;
use RuntimeException;

final class UploadService
{
    /** 允许的图片类型（MIME => 扩展名） */
    public const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** 单文件大小上限 5MB */
    public const MAX_BYTES = 5242880;

    /** 包裹照片类型：1入库照 2打包照 3用户凭证 4异常照 */
    public const PHOTO_INBOUND = 1;
    public const PHOTO_PACKING = 2;
    public const PHOTO_USER_PROOF = 3;
    public const PHOTO_ABNORMAL = 4;

    /**
     * 保存上传图片并挂到包裹照片记录
     * @param array  $file   $_FILES 中的单文件项
     * @param string $subdir 归档子目录（如 forecast / inbound / packing）
     * @param string $prefix 文件名前缀（便于排查来源）
     * @return string 可直接引用的相对 URL
     */
    public static function storePackagePhoto(array $file, string $subdir, string $prefix, int $packageId, int $photoType): string
    {
        $url = self::storeFile($file, $subdir, $prefix);
        self::attachPackagePhoto($packageId, $photoType, $url);
        return $url;
    }

    /**
     * 把已落盘的图片挂到包裹照片记录上
     * 与 storeFile 分开：一张打包照要挂到箱内多个包裹时，文件只能移动一次
     */
    public static function attachPackagePhoto(int $packageId, int $photoType, string $url): void
    {
        \App\Core\Database::pdo()->prepare(
            'INSERT INTO ' . \App\Core\Database::table('package_photos') . ' (package_id, photo_type, file_url)
             VALUES (:pid, :type, :url)'
        )->execute([':pid' => $packageId, ':type' => $photoType, ':url' => $url]);
    }

    /**
     * 仅保存文件，返回相对 URL
     */
    public static function storeFile(array $file, string $subdir, string $prefix): string
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('请选择要上传的图片');
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('图片不能超过 5MB');
        }
        $mime = self::detectMime((string) $file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new RuntimeException('仅支持 JPG / PNG / WebP 图片');
        }
        $subdir = trim(preg_replace('/[^a-z0-9_]/', '', strtolower($subdir)) ?? '', '_');
        if ($subdir === '') {
            throw new RuntimeException('上传目录参数不合法');
        }
        $month = date('Ym');
        $dir = BASE_PATH . '/public/uploads/' . $subdir . '/' . $month;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('上传目录不可写，请联系管理员');
        }
        $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED_MIME[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            throw new RuntimeException('图片保存失败，请重试');
        }
        return '/uploads/' . $subdir . '/' . $month . '/' . $name;
    }

    /**
     * 识别上传文件的真实类型
     * 优先用 fileinfo 扩展做字节级嗅探；未启用该扩展时退回 getimagesize（标准库，读文件头），
     * 两者都不可用则返回空串按拒绝处理，避免未校验的文件被放行
     */
    private static function detectMime(string $path): string
    {
        if (class_exists(\finfo::class)) {
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            if ($mime !== '') {
                return $mime;
            }
        }
        if (function_exists('getimagesize')) {
            $info = @getimagesize($path);
            if (is_array($info) && isset($info['mime'])) {
                return (string) $info['mime'];
            }
        }
        return '';
    }

    /**
     * 包裹照片类型标签（展示用）
     */
    public static function photoTypeLabel(int $type): string
    {
        return [
            self::PHOTO_INBOUND    => '入库照',
            self::PHOTO_PACKING    => '打包照',
            self::PHOTO_USER_PROOF => '用户凭证',
            self::PHOTO_ABNORMAL   => '异常照',
        ][$type] ?? '照片';
    }
}
