<?php declare(strict_types=1);
/**
 * @file app/Services/CaptchaService.php
 * @description 图形验证码：SVG 渲染（不依赖 GD 与字体文件），会话暂存、一次性核销
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 */

namespace App\Services;

use InvalidArgumentException;

final class CaptchaService
{
    private const LENGTH = 4;
    private const TTL = 300;
    private const MAX_ATTEMPTS = 3;

    /** 七段数码管各段的矩形（x, y, w, h），字形以矩形拼出，SVG 中不出现明文数字 */
    private const SEGMENTS = [
        'a' => [7, 0, 20, 7],
        'g' => [7, 24, 20, 7],
        'd' => [7, 48, 20, 7],
        'f' => [0, 7, 7, 17],
        'b' => [27, 7, 7, 17],
        'e' => [0, 31, 7, 17],
        'c' => [27, 31, 7, 17],
    ];

    private const DIGITS = [
        '0' => 'abcdef',
        '1' => 'bc',
        '2' => 'abged',
        '3' => 'abgcd',
        '4' => 'fgbc',
        '5' => 'afgcd',
        '6' => 'afgecd',
        '7' => 'abc',
        '8' => 'abcdefg',
        '9' => 'abcdfg',
    ];

    /**
     * 生成一枚验证码并返回可直接输出的 SVG
     */
    public static function issue(string $scene): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= (string) random_int(0, 9);
        }

        $_SESSION['_captcha'][$scene] = [
            'code'     => $code,
            'expires'  => time() + self::TTL,
            'attempts' => 0,
        ];

        return self::render($code);
    }

    /**
     * 校验验证码：不区分大小写、过期作废、错误累计到上限即销毁，必须重新获取
     */
    public static function verify(string $scene, string $input): void
    {
        $stored = $_SESSION['_captcha'][$scene] ?? null;
        if (!is_array($stored)) {
            throw new InvalidArgumentException('请先获取验证码');
        }
        if (time() > (int) $stored['expires']) {
            unset($_SESSION['_captcha'][$scene]);
            throw new InvalidArgumentException('验证码已过期，请点击图片刷新');
        }
        if ((int) $stored['attempts'] >= self::MAX_ATTEMPTS) {
            unset($_SESSION['_captcha'][$scene]);
            throw new InvalidArgumentException('验证码错误次数过多，请点击图片刷新');
        }

        $input = strtoupper(trim($input));
        if ($input === '' || !hash_equals((string) $stored['code'], $input)) {
            $_SESSION['_captcha'][$scene]['attempts'] = (int) $stored['attempts'] + 1;
            throw new InvalidArgumentException('验证码不正确');
        }

        // 一次性使用：核销后立即销毁，防止同一枚验证码重复提交
        unset($_SESSION['_captcha'][$scene]);
    }

    /**
     * 把数字画成七段矩形并做位移/旋转扰动，字号与基线不落明文
     */
    private static function render(string $code): string
    {
        $digitWidth = 34;
        $digitHeight = 55;
        $gap = 9;
        $padX = 12;
        $padY = 10;
        $width = self::LENGTH * $digitWidth + (self::LENGTH - 1) * $gap + $padX * 2;
        $height = $digitHeight + $padY * 2;

        $parts = [];
        $parts[] = sprintf('<rect width="%d" height="%d" fill="#f1f5f9"/>', $width, $height);

        // 背景干扰线
        for ($i = 0; $i < 5; $i++) {
            $parts[] = sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="1.4" opacity="0.5"/>',
                random_int(0, $width), random_int(0, $height),
                random_int(0, $width), random_int(0, $height),
                self::noiseColor()
            );
        }

        foreach (str_split($code) as $index => $digit) {
            $originX = $padX + $index * ($digitWidth + $gap);
            $originY = $padY + random_int(-4, 4);
            $angle = random_int(-16, 16);
            $centerX = $originX + $digitWidth / 2;
            $centerY = $originY + $digitHeight / 2;
            $fill = self::inkColor();

            // 单个字符的七段矩形先拼好，再整体旋转，避免各段错位
            $glyph = '';
            foreach (str_split(self::DIGITS[$digit] ?? '') as $segment) {
                [$x, $y, $w, $h] = self::SEGMENTS[$segment];
                $glyph .= sprintf(
                    '<rect x="%s" y="%s" width="%d" height="%d" rx="2" fill="%s"/>',
                    $originX + $x, $originY + $y, $w, $h, $fill
                );
            }
            $parts[] = sprintf('<g transform="rotate(%s %s %s)">%s</g>', $angle, $centerX, $centerY, $glyph);
        }

        // 前景干扰点
        for ($i = 0; $i < 24; $i++) {
            $parts[] = sprintf(
                '<circle cx="%d" cy="%d" r="1.2" fill="%s" opacity="0.6"/>',
                random_int(0, $width), random_int(0, $height), self::noiseColor()
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="图形验证码">%s</svg>',
            $width, $height, $width, $height,
            implode('', $parts)
        );
    }

    private static function inkColor(): string
    {
        $palette = ['#1e293b', '#312e81', '#0f172a', '#3f3f46'];
        return $palette[random_int(0, count($palette) - 1)];
    }

    private static function noiseColor(): string
    {
        $palette = ['#94a3b8', '#a5b4fc', '#cbd5e1', '#818cf8'];
        return $palette[random_int(0, count($palette) - 1)];
    }
}
