<?php declare(strict_types=1);
/**
 * @file install/upgrade/schema_upgrade.php
 * @description 增量升级：为已安装的库补齐 schema.sql 中新增的表结构（幂等，可重复执行）
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 *
 * 用法：php install/upgrade/schema_upgrade.php
 * 说明：只做「新增列 / 新增索引」，不改动、不删除既有结构，已存在时自动跳过。
 */

if (PHP_SAPI !== 'cli') {
    exit('仅允许命令行执行');
}

define('BASE_PATH', dirname(__DIR__, 2));
require BASE_PATH . '/common.php';
require BASE_PATH . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Env;
use App\Services\HomeContent;

Autoloader::register();
Env::load(BASE_PATH . '/.env');

$pdo = Database::pdo();

/** 目标表需补齐的列定义（列名 => DDL 片段） */
$columns = [
    'claims' => [
        'finance_approved_at' => "DATETIME NULL COMMENT '财务审核通过时间'",
        'finance_approved_by' => "BIGINT UNSIGNED NULL COMMENT '财务审核人ID'",
        'admin_approved_at'   => "DATETIME NULL COMMENT '管理员终审通过时间'",
        'admin_approved_by'   => "BIGINT UNSIGNED NULL COMMENT '管理员终审人ID'",
    ],
    'recharge_orders' => [
        'gateway'          => "VARCHAR(20) NULL COMMENT '支付网关 epay=易支付（空表示未走在线网关）'",
        'channel_code'     => "VARCHAR(20) NULL COMMENT '网关支付方式 alipay/wxpay/qqpay/tron.trx'",
        'gateway_trade_no' => "VARCHAR(64) NULL COMMENT '网关平台订单号（易支付 trade_no）'",
        'api_trade_no'     => "VARCHAR(64) NULL COMMENT '第三方（微信/支付宝）交易单号'",
        'pay_type'         => "VARCHAR(20) NULL COMMENT '网关返回的发起支付类型 jump/qrcode/html/urlscheme'",
        'buyer'            => "VARCHAR(64) NULL COMMENT '支付用户标识（一般为 openid）'",
        'refund_no'        => "VARCHAR(64) NULL COMMENT '网关退款单号'",
        'refund_money'     => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计已退款金额'",
        'refund_at'        => "DATETIME NULL COMMENT '最近退款时间'",
    ],
    'waybills' => [
        'pack_length'     => "DECIMAL(10,2) NULL COMMENT '打包箱规-长cm（仓库打包时录入）'",
        'pack_width'      => "DECIMAL(10,2) NULL COMMENT '打包箱规-宽cm'",
        'pack_height'     => "DECIMAL(10,2) NULL COMMENT '打包箱规-高cm'",
        'packed_at'       => "DATETIME NULL COMMENT '仓库打包完成时间'",
        'packed_admin_id' => "BIGINT UNSIGNED NULL COMMENT '打包操作员ID（admins.id）'",
        'due_amount'      => "DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '待补差额（打包重算后余额不足挂起时记录，用户补缴后清零）'",
        'auto_signed'     => "TINYINT NOT NULL DEFAULT 0 COMMENT '是否系统自动签收 0手动 1自动'",
        'refunded_at'     => "DATETIME NULL COMMENT '运费退回时间（幂等标记，非空表示已退过）'",
    ],
    'users' => [
        'id_type'          => "TINYINT NOT NULL DEFAULT 1 COMMENT '实名证件类型 1中国身份证 2中国护照'",
        'id_photo_front'   => "VARCHAR(255) NULL COMMENT '证件正面/护照照片（身份证为正面，护照为唯一 1 张）'",
        'id_photo_back'    => "VARCHAR(255) NULL COMMENT '证件反面照片（仅身份证需要，护照为空）'",
        'blacklist_reason' => "VARCHAR(200) NULL COMMENT '拉黑原因（仅 status=3 有值；解除黑名单时清空，历史留痕见 admin_operation_logs）'",
        'blacklisted_at'   => "DATETIME NULL COMMENT '拉黑时间（仅 status=3 有值；解除黑名单时清空）'",
    ],
    'messages' => [
        'notice_type' => "TINYINT NOT NULL DEFAULT 1 COMMENT '公告类型 1普通 2重要（仅全员公告使用）'",
    ],
];

/** 需补齐的索引（表名 => [索引名 => 列定义]） */
$indexes = [
    'recharge_orders' => [
        'idx_gateway_trade' => '`gateway_trade_no`',
    ],
    'waybills' => [
        'idx_status_outbound' => '`status`, `outbound_at`',
    ],
];

/** 需修改类型的列（表名 => [列名 => DDL 片段]，幂等：类型一致时 MODIFY 无副作用） */
$modify = [
    'users' => [
        'points' => "INT NOT NULL DEFAULT 0 COMMENT '可用积分（退款扣回后可为负，负分限制下单）'",
    ],
    'points_logs' => [
        'balance_after' => "INT NOT NULL COMMENT '变动后积分余额（扣回后可为负）'",
    ],
];

/** 需更新的列注释（表名 => [列名 => 注释文本]） */
$comments = [
    'recharge_orders' => [
        'pay_method' => '支付方式 1支付宝 2微信支付 3PayPal 4Stripe信用卡 5银行转账 6后台人工入账 7QQ钱包 8TRX-TRC20',
    ],
];

/** 需补齐的表（表名 => 建表 DDL，表名用 {P} 占位符代表配置的表前缀） */
$tables = [
    'warehouse_tasks' => <<<'DDL'
CREATE TABLE IF NOT EXISTS `{P}warehouse_tasks` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_no`         VARCHAR(32)     NOT NULL                COMMENT '任务号（如 WT20260912120000xxxx）',
  `warehouse_id`    BIGINT UNSIGNED NOT NULL                COMMENT '仓库ID',
  `task_type`       TINYINT         NOT NULL                COMMENT '任务类型 1收货入库 2上架 3打包 4出库 5异常处理',
  `target_type`     TINYINT         NOT NULL                COMMENT '目标类型 1包裹 2运单',
  `target_id`       BIGINT UNSIGNED NOT NULL                COMMENT '目标ID（按 target_type 指向包裹或运单）',
  `target_no`       VARCHAR(32)     NOT NULL                COMMENT '目标单号（冗余存储，列表直接展示无需联表）',
  `assign_admin_id` BIGINT UNSIGNED NOT NULL                COMMENT '执行人（admins.id）',
  `assign_by`       BIGINT UNSIGNED NOT NULL                COMMENT '指派人/主管（admins.id）',
  `status`          TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0待处理 1处理中 2已完成 3已取消',
  `priority`        TINYINT         NOT NULL DEFAULT 0      COMMENT '优先级 0普通 1加急',
  `remark`          VARCHAR(255)    NULL                    COMMENT '指派说明',
  `finish_remark`   VARCHAR(255)    NULL                    COMMENT '完成说明（作业提交时自动写入）',
  `assigned_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '指派时间',
  `finished_at`     DATETIME        NULL                    COMMENT '完成时间',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_no` (`task_no`),
  KEY `idx_assign_status` (`assign_admin_id`, `status`),
  KEY `idx_wh_status` (`warehouse_id`, `status`),
  KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库作业任务表'
DDL,
    'points_pending' => <<<'DDL'
CREATE TABLE IF NOT EXISTS `{P}points_pending` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`      BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `points`       INT             NOT NULL                COMMENT '待激活积分数',
  `base_amount`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '计分基数（实付基础运费）',
  `biz_type`     TINYINT         NOT NULL DEFAULT 8      COMMENT '业务类型（与积分流水口径一致，8签收到账）',
  `biz_no`       VARCHAR(32)     NOT NULL                COMMENT '关联单号（运单号，作幂等键）',
  `status`       TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0待激活 1已到账 2已取消',
  `activate_at`  DATETIME        NOT NULL                COMMENT '应激活时间（签收时间 + 缓冲天数）',
  `signed_at`    DATETIME        NOT NULL                COMMENT '签收时间（手动或自动签收）',
  `activated_at` DATETIME        NULL                    COMMENT '实际到账时间',
  `remark`       VARCHAR(200)    NULL                    COMMENT '说明',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_biz` (`biz_type`, `biz_no`),
  KEY `idx_status_activate` (`status`, `activate_at`),
  KEY `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='待激活积分表'
DDL,
];

// Database::table() 已返回带反引号的完整表名；information_schema 查询需要不带引号的裸表名
$table = static fn(string $name): string => Database::table($name);
$rawTable = static fn(string $name): string => (string) \App\Core\Config::get('database.prefix', 'wt_') . $name;

/** 列是否已存在 */
$hasColumn = static function (string $tableName, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':t' => $tableName, ':c' => $column]);
    return (int) $stmt->fetchColumn() > 0;
};

/** 索引是否已存在 */
$hasIndex = static function (string $tableName, string $index) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i'
    );
    $stmt->execute([':t' => $tableName, ':i' => $index]);
    return (int) $stmt->fetchColumn() > 0;
};

/** 表是否已存在 */
$hasTable = static function (string $tableName) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
    );
    $stmt->execute([':t' => $tableName]);
    return (int) $stmt->fetchColumn() > 0;
};

$changed = 0;

// 先补表：新表可能带新列，先建表再补列可避免重复工作
$prefix = (string) \App\Core\Config::get('database.prefix', 'wt_');
foreach ($tables as $shortName => $ddl) {
    $rawName = $prefix . $shortName;
    if ($hasTable($rawName)) {
        echo "[跳过] 表 {$rawName} 已存在\n";
        continue;
    }
    $pdo->exec(str_replace('{P}', $prefix, $ddl));
    echo "[新建] 表 {$rawName}\n";
    $changed++;
}

foreach ($columns as $shortName => $defs) {
    $fullName = $table($shortName);
    $rawName = $rawTable($shortName);
    foreach ($defs as $column => $ddl) {
        if ($hasColumn($rawName, $column)) {
            echo "[跳过] {$rawName}.{$column} 已存在\n";
            continue;
        }
        $pdo->exec("ALTER TABLE {$fullName} ADD COLUMN `{$column}` {$ddl}");
        echo "[新增] {$rawName}.{$column}\n";
        $changed++;
    }
}

foreach ($modify as $shortName => $defs) {
    $fullName = $table($shortName);
    $rawName = $rawTable($shortName);
    foreach ($defs as $column => $ddl) {
        if (!$hasColumn($rawName, $column)) {
            continue;
        }
        $pdo->exec("ALTER TABLE {$fullName} MODIFY COLUMN `{$column}` {$ddl}");
        echo "[改型] {$rawName}.{$column}\n";
        $changed++;
    }
}

foreach ($comments as $shortName => $defs) {
    $fullName = $table($shortName);
    $rawName = $rawTable($shortName);
    foreach ($defs as $column => $text) {
        if (!$hasColumn($rawName, $column)) {
            continue;
        }
        $safe = str_replace("'", "''", $text);
        $pdo->exec("ALTER TABLE {$fullName} MODIFY COLUMN `{$column}` TINYINT NOT NULL COMMENT '{$safe}'");
        echo "[注释]  {$rawName}.{$column}\n";
    }
}

foreach ($indexes as $shortName => $defs) {
    $fullName = $table($shortName);
    $rawName = $rawTable($shortName);
    foreach ($defs as $index => $cols) {
        if ($hasIndex($rawName, $index)) {
            echo "[跳过] {$rawName}.{$index} 已存在\n";
            continue;
        }
        $pdo->exec("ALTER TABLE {$fullName} ADD INDEX `{$index}` ({$cols})");
        echo "[新增] {$rawName}.{$index}\n";
        $changed++;
    }
}

/* ================= 邮箱/证件唯一性迁移（幂等） =================
   用户表 email 与 (id_type, id_card_no) 加唯一索引前必须先处理存量冲突：
   ① 证件号空串归一为 NULL（NULL 不参与唯一约束）
   ② 重复邮箱保留最早注册的账号，其余置空待重新绑定
   ③ 重复证件号保留 kyc 状态最高的记录，其余清空实名信息重新认证 */
$pdo->exec("UPDATE {$table('users')} SET id_card_no = NULL WHERE id_card_no = ''");
$duplicateEmails = $pdo->query(
    "SELECT email, GROUP_CONCAT(id ORDER BY id) AS ids FROM {$table('users')}
     WHERE email IS NOT NULL AND deleted_at IS NULL GROUP BY email HAVING COUNT(*) > 1"
)->fetchAll(PDO::FETCH_ASSOC);
$nullEmail = $pdo->prepare("UPDATE {$table('users')} SET email = NULL WHERE id = :id");
foreach ($duplicateEmails as $dup) {
    $ids = array_slice(explode(',', (string) $dup['ids']), 1);
    foreach ($ids as $dupId) {
        $nullEmail->execute([':id' => (int) $dupId]);
    }
    echo "[冲突] 邮箱 {$dup['email']} 重复，保留最早账号，其余已置空待重新绑定\n";
    $changed++;
}
$duplicateIds = $pdo->query(
    "SELECT id_type, id_card_no, GROUP_CONCAT(id ORDER BY FIELD(kyc_status, 2, 1, 3, 0), id) AS ids
     FROM {$table('users')} WHERE id_card_no IS NOT NULL GROUP BY id_type, id_card_no HAVING COUNT(*) > 1"
)->fetchAll(PDO::FETCH_ASSOC);
$clearKyc = $pdo->prepare(
    "UPDATE {$table('users')} SET id_card_no = NULL, id_photo_front = NULL, id_photo_back = NULL,
     kyc_status = 0, kyc_at = NULL, kyc_remark = '证件号与另一账号冲突，请重新提交实名认证' WHERE id = :id"
);
foreach ($duplicateIds as $dup) {
    $ids = array_slice(explode(',', (string) $dup['ids']), 1);
    foreach ($ids as $dupId) {
        $clearKyc->execute([':id' => (int) $dupId]);
    }
    echo "[冲突] 证件号 {$dup['id_card_no']} 重复，保留状态最高的账号，其余已清空实名信息\n";
    $changed++;
}
$uniqueIndexes = [
    'users' => [
        'uk_email'      => ['`email`', '邮箱'],
        'uk_id_type_no' => ['`id_type`, `id_card_no`', '证件类型+证件号'],
    ],
];
foreach ($uniqueIndexes as $shortName => $defs) {
    $fullName = $table($shortName);
    $rawName = $rawTable($shortName);
    foreach ($defs as $index => [$cols, $label]) {
        if ($hasIndex($rawName, $index)) {
            echo "[跳过] {$rawName}.{$index} 已存在\n";
            continue;
        }
        try {
            $pdo->exec("ALTER TABLE {$fullName} ADD UNIQUE INDEX `{$index}` ({$cols})");
            echo "[新增] {$rawName}.{$index}（{$label} 全局唯一）\n";
            $changed++;
        } catch (PDOException $e) {
            echo "[失败] {$rawName}.{$index} 创建失败：数据仍存在冲突，请人工核对后再运行\n";
        }
    }
}

/* ================= 新增权限点（幂等） ================= */

/** 需补齐的权限点：perm_code => [名称, 分组, 排序, 授予的角色code] */
$permissions = [
    'package.view'        => ['包裹查看',       '仓库管理', 16,  ['customer_service']],
    'staff.view'          => ['员工查看',       '系统设置', 101, ['admin']],
    'staff.manage'        => ['员工管理操作',   '系统设置', 102, ['admin']],
    'claim.view'          => ['理赔查看',       '理赔处理', 72,  ['customer_service', 'finance']],
    'claim.review'        => ['理赔初审',       '理赔处理', 73,  ['customer_service']],
    'claim.finance_audit' => ['理赔财务审核',   '理赔处理', 74,  ['finance']],
    'claim.admin_audit'   => ['理赔管理员终审', '理赔处理', 75,  ['admin']],
    'claim.pay'           => ['理赔赔付执行',   '理赔处理', 76,  ['finance']],
];

$permTable = $table('permissions');
$rolePermTable = $table('role_permissions');
$roleTable = $table('roles');

$findPerm = $pdo->prepare("SELECT id FROM {$permTable} WHERE perm_code = :c LIMIT 1");
$insertPerm = $pdo->prepare("INSERT INTO {$permTable} (perm_code, name, group_name, sort) VALUES (:c, :n, :g, :s)");
$findRole = $pdo->prepare("SELECT id FROM {$roleTable} WHERE code = :c LIMIT 1");
$findLink = $pdo->prepare("SELECT id FROM {$rolePermTable} WHERE role_id = :r AND permission_id = :p LIMIT 1");
$insertLink = $pdo->prepare("INSERT INTO {$rolePermTable} (role_id, permission_id) VALUES (:r, :p)");

/* ================= 角色授权基线（幂等） ================= */

/**
 * 在权限种子（schema.sql §8.6）之前建成的库，wt_role_permissions 会整表为空：
 * 除超级管理员外的角色都拿不到任何权限点，表现为「后台角色权限页每个角色都是 0 项授权、
 * 各岗位登录后侧边栏只剩控制台、页面全部提示无权限」。
 * 这里按与 schema.sql 完全相同的规则补齐基线，且只补「当前一项授权都没有」的角色——
 * 只要该角色已有授权就整体跳过，避免覆盖人工调整过的授权集合。
 *
 * @var array<string, string> 角色 code => 权限点筛选条件
 */
$roleBaseline = [
    'admin'                => "p.perm_code NOT LIKE 'wh.%'",
    'finance'              => "p.perm_code IN ('user.view', 'user.balance.adjust', 'order.view', 'finance.view', 'finance.manage', 'report.view')",
    'customer_service'     => "p.perm_code IN ('user.view', 'package.view', 'order.view', 'ticket.view', 'ticket.handle')",
    'warehouse_supervisor' => "p.perm_code LIKE 'wh.%'",
    'warehouse_operator'   => "p.perm_code IN ('wh.inbound', 'wh.storage', 'wh.abnormal')",
    'packer'               => "p.perm_code = 'wh.packing'",
    'outbound'             => "p.perm_code = 'wh.outbound'",
];

$countGrants = $pdo->prepare("SELECT COUNT(*) FROM {$rolePermTable} WHERE role_id = :r");

foreach ($roleBaseline as $roleCode => $condition) {
    $findRole->execute([':c' => $roleCode]);
    $roleId = $findRole->fetchColumn();
    if ($roleId === false) {
        echo "[跳过] 角色 {$roleCode} 不存在\n";
        continue;
    }
    $roleId = (int) $roleId;
    $countGrants->execute([':r' => $roleId]);
    $existing = (int) $countGrants->fetchColumn();
    if ($existing > 0) {
        echo "[跳过] 角色 {$roleCode} 已有 {$existing} 项授权，保持现状\n";
        continue;
    }
    // 权限点被停用时不参与授权，与权限判据（p.status = 1）保持一致
    $insert = $pdo->prepare(
        "INSERT INTO {$rolePermTable} (role_id, permission_id)
         SELECT :r, p.id FROM {$permTable} p WHERE p.status = 1 AND ({$condition})"
    );
    $insert->execute([':r' => $roleId]);
    $granted = $insert->rowCount();
    echo "[基线] 角色 {$roleCode} 补齐 {$granted} 项授权\n";
    $changed += $granted;
}

/* ================= 权限点授予指定角色（幂等） ================= */

foreach ($permissions as $code => [$name, $group, $sort, $roles]) {
    $findPerm->execute([':c' => $code]);
    $permId = $findPerm->fetchColumn();
    if ($permId === false) {
        $insertPerm->execute([':c' => $code, ':n' => $name, ':g' => $group, ':s' => $sort]);
        $permId = (int) $pdo->lastInsertId();
        echo "[新增] 权限点 {$code}\n";
        $changed++;
    } else {
        $permId = (int) $permId;
        echo "[跳过] 权限点 {$code} 已存在\n";
    }
    // 管理员角色的非仓库端权限由上面的基线补齐，此处只补特定岗位
    foreach ($roles as $roleCode) {
        $findRole->execute([':c' => $roleCode]);
        $roleId = $findRole->fetchColumn();
        if ($roleId === false) {
            continue;
        }
        $findLink->execute([':r' => $roleId, ':p' => $permId]);
        if ($findLink->fetchColumn() === false) {
            $insertLink->execute([':r' => $roleId, ':p' => $permId]);
            echo "[授权] {$roleCode} → {$code}\n";
            $changed++;
        }
    }
}

/* ── 站点配置补齐：官网首页新增的联系方式等配置项（仅补缺失项，不覆盖管理员已改的值） ── */
$siteDefaults = [
    'site_company' => ['环球领航国际物流有限公司', 'site', '公司全称（页脚版权行）'],
    'site_phone'   => ['400-888-6688', 'site', '客服电话（页头、页脚展示）'],
    'site_email'   => ['service@globepilot.com', 'site', '客服邮箱（页头、页脚展示）'],
    'site_hours'   => ['周一至周日 8:00 - 24:00', 'site', '服务时间'],
    'site_address' => ['深圳市南山区科苑南路 318 号环球领航大厦 22F', 'site', '公司地址'],
    'site_icp'     => ['粤ICP备20268866号-1 · 深圳市公安局备案 44030502088888号', 'site', '备案信息（页脚展示）'],
    'warehouse_address_prefix' => ['WTZY', 'site', '海外仓地址前缀（面单收件人名称前缀，仅字母数字）'],
    'register_email_verify'    => ['0', 'site', '开启邮箱验证注册（1 开启 0 关闭；开启后注册必须邮箱+验证码）'],
    'realname_required'        => ['0', 'site', '强制实名认证（1 开启 0 关闭；开启后未实名用户仅可改资料/实名/退出）'],
    'storage_fee_per_item_day' => ['1.00', 'storage', '超期仓储费单价（元/件/天，超免费期后计收）'],
    'storage_grace_days'       => ['3', 'storage', '超免费期后的宽限天数'],
    'points_award_delay_days'  => ['7', 'member', '积分到账缓冲天数（签收后 T+N 天到账，0 表示签收即到账）'],
    'points_negative_block'    => ['1', 'member', '负积分限制下单（1 限制 0 不限制）'],
    'auto_sign_days'           => ['15', 'order', '自动签收天数（出库后 N 天未手动签收则系统自动签收）'],
    'task_lazy_minutes'        => ['10', 'order', '页面懒执行自动任务的间隔分钟数（无 cron 时的兜底频率）'],
    'invoice_enabled'          => ['1', 'pay', '发票申请开关（关闭后用户端不再提供开票入口与申请提交，已提交的申请不受影响）'],
    'content_faq' => ['国际转运一般需要多久？
时效取决于线路：极速空运 3-7 天、标准转运 10-15 天、经济海运 25-38 天（美西）。影响时效的主要是旺季舱位与目的国清关速度，我们会在下单时给出预估送达区间，并在每个节点实时推送。

带电产品、化妆品、食品能寄吗？
可以。内置锂电池产品可走带电专线，化妆品、食品等敏感货可走敏感专线，相关附加标准会在下单前由顾问明确告知；部分国家对液体容量和成分有限制，下单前可让客服帮你核对禁运清单。

有哪些免费增值服务？
入库免费仓储 {days} 天、出库质检拍照、智能合箱与加固打包均为免费服务；代缴关税、全程保价、白手套派送、退货换标等增值服务，可在下单时按需勾选，顾问会按货物类型给出推荐组合。

包裹丢失或损坏怎么办？
标准线路默认含基础保障，可加购全额保障，按货物实际价值足额赔付。签收时请保留外箱照片，异常件在 72 小时内提交理赔申请，我们承诺 48 小时内完成审核并打款。

清关需要我提供什么资料？
普货低价值包裹（低于目的国免税额度）通常无需任何资料；超过免税额度或被抽查时，我们会主动联系你补交身份证照片 / 购物凭证，全程有专人指导，平均 8 小时内放行。

如何合并多个商家的包裹？
入库后在我的仓库勾选需要合并的包裹提交「合箱」即可，仓库会免费质检、拍照、去物称重并重新打包。包裹免费仓储 {days} 天，足够你从容凑单。', 'site', '常见问题（空行分段，首行问题、其余答案；{days} 按仓库免费仓储天数替换）'],
];
$findSetting = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::table('settings') . ' WHERE setting_key = :k');
$insertSetting = $pdo->prepare(
    'INSERT INTO ' . Database::table('settings') . ' (setting_key, setting_value, group_name, remark) VALUES (:k, :v, :g, :r)'
);
foreach ($siteDefaults as $key => [$value, $group, $remark]) {
    $findSetting->execute([':k' => $key]);
    if ((int) $findSetting->fetchColumn() > 0) {
        echo "[跳过] 配置 {$key} 已存在\n";
        continue;
    }
    $insertSetting->execute([':k' => $key, ':v' => $value, ':g' => $group, ':r' => $remark]);
    echo "[新增] 配置 {$key}\n";
    $changed++;
}

/* ── FAQ 仓储天数占位符迁移（幂等）：老种子把「免费仓储 90 天」写死在文案里，
   替换为 {days} 占位符后由首页按仓库 free_storage_days 动态展示；
   已改过文案（不含该字样）的库不受影响，重复执行无变化 ── */
$migratedFaq = $pdo->prepare(
    "UPDATE " . Database::table('settings') . "
     SET setting_value = REPLACE(setting_value, '免费仓储 90 天', '免费仓储 {days} 天')
     WHERE setting_key = 'content_faq' AND setting_value LIKE '%免费仓储 90 天%'"
);
$migratedFaq->execute();
if ($migratedFaq->rowCount() > 0) {
    echo "[迁移] 常见问题仓储天数改为跟随仓库设置（{$migratedFaq->rowCount()} 条）\n";
    $changed += (int) $migratedFaq->rowCount();
} else {
    echo "[跳过] 常见问题无写死的仓储天数\n";
}

/* ── 占位符命名统一（幂等）：早期迁移曾用 {free_storage_days}，统一为适配后台
   数值框 12 字限制的 {days} ── */
$renamedFaq = $pdo->prepare(
    "UPDATE " . Database::table('settings') . "
     SET setting_value = REPLACE(setting_value, '{free_storage_days}', '{days}')
     WHERE setting_value LIKE '%{free_storage_days}%'"
);
$renamedFaq->execute();
if ($renamedFaq->rowCount() > 0) {
    echo "[迁移] 仓储天数占位符统一为 {days}（{$renamedFaq->rowCount()} 条）\n";
    $changed += (int) $renamedFaq->rowCount();
}

/* ── 服务承诺仓储天数迁移（幂等）：homepage_promises 配置中「免费仓储」行的
   纯数字值改为 {days}，未配置该行或已用占位符的库无变化 ── */
$promiseRaw = Database::pdo()->query(
    "SELECT setting_value FROM " . Database::table('settings') . " WHERE setting_key = 'homepage_promises' LIMIT 1"
)->fetchColumn();
if ($promiseRaw !== false && $promiseRaw !== null && trim((string) $promiseRaw) !== '') {
    $promiseRows = json_decode((string) $promiseRaw, true);
    $promiseTouched = false;
    if (is_array($promiseRows)) {
        foreach ($promiseRows as &$pRow) {
            $pValue = (string) ($pRow['value'] ?? '');
            if (
                str_contains((string) ($pRow['label'] ?? ''), '免费仓储')
                && $pValue !== ''
                && $pValue !== HomeContent::STORAGE_PLACEHOLDER
                && preg_match('/^\d+(\.\d+)?$/', $pValue)
            ) {
                $pRow['value'] = HomeContent::STORAGE_PLACEHOLDER;
                $promiseTouched = true;
            }
        }
        unset($pRow);
        if ($promiseTouched) {
            $updPromise = $pdo->prepare(
                "UPDATE " . Database::table('settings') . " SET setting_value = :v WHERE setting_key = 'homepage_promises'"
            );
            $updPromise->execute([':v' => json_encode($promiseRows, JSON_UNESCAPED_UNICODE)]);
            echo "[迁移] 服务承诺仓储天数改为跟随仓库设置\n";
            $changed++;
        } else {
            echo "[跳过] 服务承诺仓储天数无需迁移\n";
        }
    }
} else {
    echo "[跳过] 服务承诺未配置（首页按仓库设置动态展示）\n";
}

/* ── 包裹状态跟随运单（幂等）：修正历史包裹停在出库前/出库时、而运单早已推进的记录 ── */
$fixedPackages = App\Models\WaybillModel::backfillPackageStatuses();
if ($fixedPackages > 0) {
    echo "[修正] 包裹状态跟随运单 {$fixedPackages} 件\n";
    $changed += $fixedPackages;
} else {
    echo "[跳过] 包裹状态与运单一致\n";
}

/* ── 包裹认领时间补齐（幂等）：修正早期建档「已认领」却没记认领时间的记录 ── */
$fixedClaimed = App\Models\PackageModel::backfillClaimedAt();
if ($fixedClaimed > 0) {
    echo "[修正] 包裹认领时间补齐 {$fixedClaimed} 件\n";
    $changed += $fixedClaimed;
} else {
    echo "[跳过] 包裹认领时间无缺失\n";
}

/* ── 会员等级按积分修正（幂等，只升不降）：修正历史脏数据（如 member_level=0）与等级配置调整后的存量用户 ── */
$fixedLevels = App\Models\MemberLevelModel::backfillLevels();
if ($fixedLevels > 0) {
    echo "[修正] 会员等级按积分提升 {$fixedLevels} 个账号\n";
    $changed += $fixedLevels;
} else {
    echo "[跳过] 会员等级与积分匹配\n";
}

echo "\n升级完成，本次变更 {$changed} 项。\n";