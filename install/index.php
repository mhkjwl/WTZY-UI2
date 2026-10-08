<?php declare(strict_types=1);
/**
 * @file install/index.php
 * @description 一键安装入口：锁文件守卫 + 六步向导路由（可独立访问，也可由主入口 require）
 * @author AI
 * @version 0.6.1
 * @date 2026-10-08
 */

declare(strict_types=1);

require __DIR__ . '/functions.php';

install_session();

// 时间戳口径与运行时对齐：系统配置提交后按所选时区，提交前按运行时默认值
date_default_timezone_set(install_timezone());

// 安装锁校验（规范 §8.4.3）：已安装则拒绝进入安装向导
if (file_exists(LOCK_FILE)) {
    header('Location: /');
    exit('系统已安装。如需重新安装，请删除 install/lock/installed.lock 文件。');
}

// 步骤编号 → 步骤文件后缀映射
$stepMap = [1 => 'env', 2 => 'db', 3 => 'admin', 4 => 'config', 5 => 'install', 6 => 'done'];
$step = (int) ($_GET['step'] ?? 1);
if (!isset($stepMap[$step])) {
    $step = 1;
}

require INSTALL_ROOT . '/steps/step' . $step . '_' . $stepMap[$step] . '.php';
