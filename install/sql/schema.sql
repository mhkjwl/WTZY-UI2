-- ============================================================
-- @file schema.sql
-- @description 转运系统数据库建表脚本（P0 范围 · 44 表 + 种子数据）
-- @author AI
-- @version 0.6
-- @date 2026-10-08
--
-- 环境要求：MySQL 5.7+ / InnoDB / utf8mb4
-- 全局约定：
--   1. 表前缀：本文件以 wtzy_ 作为占位前缀，一键安装时按向导配置（默认 wt）统一替换，
--      运行时表名以 .env 的 DB_PREFIX 为准，应用层经 Database::table() 拼接
--   2. 金额 DECIMAL(12,2)，单位为账户币种（P0 统一 CNY）；重量 DECIMAL(10,3) kg；尺寸 DECIMAL(10,2) cm
--   3. 所有表含 created_at / updated_at；核心业务表含 deleted_at 软删除
--   4. 状态字段用 TINYINT 编码，含义见列 COMMENT，与 app/Enums/ 中枚举一一对应
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 一、用户域（13 表）
-- ============================================================

-- 1.1 用户主表
CREATE TABLE `wtzy_users` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `user_no`          VARCHAR(16)     NOT NULL                COMMENT '用户编号（对外展示，如 U100001）',
  `username`         VARCHAR(32)     NULL                    COMMENT '用户名（注册必填，可用于登录与找回密码）',
  `user_code`        VARCHAR(16)     NOT NULL                COMMENT '专属入库识别码（印在海外仓收货地址上，收货员凭此归属包裹）',
  `nickname`         VARCHAR(50)     NULL                    COMMENT '昵称',
  `avatar`           VARCHAR(255)    NULL                    COMMENT '头像URL',
  `phone`            VARCHAR(20)     NULL                    COMMENT '手机号（含国际区号）',
  `email`            VARCHAR(128)    NULL                    COMMENT '邮箱',
  `password_hash`    VARCHAR(255)    NULL                    COMMENT '密码哈希（password_hash 生成；纯第三方登录用户可为空）',
  `default_lang`     VARCHAR(10)     NOT NULL DEFAULT 'zh-CN' COMMENT '默认语言（zh-CN/en 等，多语言 P1 扩展）',
  `default_currency` CHAR(3)         NOT NULL DEFAULT 'CNY'  COMMENT '默认展示币种（ISO 4217）',
  `member_level`     TINYINT         NOT NULL DEFAULT 1      COMMENT '会员等级 1普通 2白银 3黄金 4钻石（权益 P1 扩展）',
  `status`           TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0待验证 1正常 2禁用 3黑名单',
  `risk_level`       TINYINT         NOT NULL DEFAULT 0      COMMENT '风控等级 0正常 1关注（敏感操作需复核） 2限制（禁止下单/认领）',
  `blacklist_reason` VARCHAR(200)    NULL                    COMMENT '拉黑原因（仅 status=3 有值；解除黑名单时清空，历史留痕见 admin_operation_logs）',
  `blacklisted_at`   DATETIME        NULL                    COMMENT '拉黑时间（仅 status=3 有值；解除黑名单时清空）',
  `inviter_id`       BIGINT UNSIGNED NULL                    COMMENT '邀请人用户ID（返利/分销用）',
  `real_name`        VARCHAR(30)     NULL                    COMMENT '实名认证姓名',
  `id_card_no`       VARCHAR(32)     NULL                    COMMENT '证件号码（展示时脱敏）',
  `id_type`          TINYINT         NOT NULL DEFAULT 1      COMMENT '实名证件类型 1中国身份证 2中国护照',
  `id_photo_front`   VARCHAR(255)    NULL                    COMMENT '证件正面/护照照片（身份证为正面，护照为唯一 1 张）',
  `id_photo_back`    VARCHAR(255)    NULL                    COMMENT '证件反面照片（仅身份证需要，护照为空）',
  `kyc_status`       TINYINT         NOT NULL DEFAULT 0      COMMENT '实名状态 0未提交 1待审核 2已通过 3已驳回',
  `kyc_at`           DATETIME        NULL                    COMMENT '实名认证通过时间',
  `kyc_remark`       VARCHAR(200)    NULL                    COMMENT '实名审核意见',
  `points`           INT             NOT NULL DEFAULT 0      COMMENT '可用积分（退款扣回后可为负，负分限制下单）',
  `last_login_at`    DATETIME        NULL                    COMMENT '最后登录时间',
  `last_login_ip`    VARCHAR(45)     NULL                    COMMENT '最后登录IP（长度兼容 IPv6）',
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at`       DATETIME        NULL                    COMMENT '注销/软删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_no` (`user_no`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_user_code` (`user_code`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_id_type_no` (`id_type`, `id_card_no`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email` (`email`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 1.2 用户登录方式绑定（手机/邮箱/微信/Google 多方式登录）
CREATE TABLE `wtzy_user_auths` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`     BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `auth_type`   TINYINT         NOT NULL                COMMENT '登录方式 1手机 2邮箱 3微信 4Google 5Apple',
  `identifier`  VARCHAR(128)    NOT NULL                COMMENT '登录标识（手机号/邮箱/OpenID/UnionID/Google ID）',
  `credential`  VARCHAR(255)    NULL                    COMMENT '凭证存根（第三方 token 标识；手机/邮箱方式不存明文密码）',
  `is_verified` TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '是否已验证 0否 1是',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '绑定时间',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_identifier` (`auth_type`, `identifier`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户登录方式绑定表';

-- 1.3 用户收货地址（转运目的地）
CREATE TABLE `wtzy_user_addresses` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`        BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `receiver_name`  VARCHAR(50)     NOT NULL                COMMENT '收件人姓名',
  `receiver_phone` VARCHAR(20)     NOT NULL                COMMENT '收件人电话',
  `country`        CHAR(2)         NOT NULL                COMMENT '国家/地区码（ISO 3166-1 alpha-2，如 US/JP/DE）',
  `state`          VARCHAR(50)     NOT NULL                COMMENT '州/省',
  `city`           VARCHAR(50)     NOT NULL                COMMENT '城市',
  `district`       VARCHAR(50)     NULL                    COMMENT '区/县（部分国家无此级行政区）',
  `address1`       VARCHAR(255)    NOT NULL                COMMENT '地址行1',
  `address2`       VARCHAR(255)    NULL                    COMMENT '地址行2（公寓号/门牌等补充）',
  `zip_code`       VARCHAR(16)     NOT NULL                COMMENT '邮编',
  `is_default`     TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '是否默认地址 0否 1是（每用户仅一条默认）',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at`     DATETIME        NULL                    COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户收货地址表';

-- 1.4 用户海外仓收货地址（注册后按仓库分配）
CREATE TABLE `wtzy_warehouse_addresses` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`      BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `warehouse_id` BIGINT UNSIGNED NOT NULL                COMMENT '仓库ID（wtzy_warehouses.id）',
  `user_code`    VARCHAR(16)     NOT NULL                COMMENT '用户识别码（冗余自 wtzy_users.user_code，收货扫描时凭面单识别归属）',
  `full_name`    VARCHAR(100)    NOT NULL                COMMENT '收货人名（仓库名义，如 WTZY-CA01 Inc）',
  `phone`        VARCHAR(20)     NOT NULL                COMMENT '仓库联系电话',
  `country`      CHAR(2)         NOT NULL                COMMENT '国家/地区码',
  `state`        VARCHAR(50)     NOT NULL                COMMENT '州/省',
  `city`         VARCHAR(50)     NOT NULL                COMMENT '城市',
  `address`      VARCHAR(255)    NOT NULL                COMMENT '仓库街道地址',
  `zip_code`     VARCHAR(16)     NOT NULL                COMMENT '邮编',
  `is_default`   TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '是否该用户默认仓地址 0否 1是',
  `status`       TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0已失效（仓库搬迁/调整时旧地址失效） 1有效',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '分配时间',
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_warehouse` (`user_id`, `warehouse_id`),
  KEY `idx_user_code` (`user_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户海外仓收货地址表（每用户每仓库一条专属地址）';

-- 1.5 编号序列（注册等场景原子分配对外唯一编号，不依赖自增主键、不使用空值占位）
CREATE TABLE `wtzy_uid_sequences` (
  `name`        VARCHAR(32)     NOT NULL                COMMENT '序列标识（如 user）',
  `prefix`      VARCHAR(8)      NOT NULL DEFAULT ''     COMMENT '编号前缀',
  `last_value`  BIGINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '最后已分配的序号，下一次分配取其 +1',
  `min_digits`  TINYINT UNSIGNED NOT NULL DEFAULT 6     COMMENT '序号不足该位数时左补零',
  `remark`      VARCHAR(100)    NULL                    COMMENT '用途说明',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='编号序列表（并发下原子分配，保证编号唯一）';

-- 1.6 验证码记录（找回密码等场景的邮箱/短信验证码，只存哈希、一次性使用、带过期与尝试次数）
CREATE TABLE `wtzy_verify_codes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `target`     VARCHAR(128)    NOT NULL                COMMENT '接收方（邮箱地址/手机号）',
  `channel`    TINYINT         NOT NULL                COMMENT '发送通道 1邮件 2短信',
  `purpose`    VARCHAR(32)     NOT NULL                COMMENT '用途标识（reset_password 等）',
  `code_hash`  VARCHAR(255)    NOT NULL                COMMENT '验证码哈希（不落明文）',
  `attempts`   TINYINT UNSIGNED NOT NULL DEFAULT 0     COMMENT '已尝试校验次数',
  `expires_at` DATETIME        NOT NULL                COMMENT '过期时间',
  `used_at`    DATETIME        NULL                    COMMENT '核销时间（非空即已使用）',
  `ip`         VARCHAR(45)     NULL                    COMMENT '请求来源IP（长度兼容 IPv6）',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
  PRIMARY KEY (`id`),
  KEY `idx_target_purpose` (`target`, `purpose`, `created_at`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='验证码记录表（邮件/短信通用，一次性核销）';

-- 1.7 积分流水（用户积分账本，余额只允许经流水变动）
CREATE TABLE `wtzy_points_logs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`        BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `change_amount`  INT             NOT NULL                COMMENT '变动值（正加负减）',
  `balance_after`  INT             NOT NULL                COMMENT '变动后积分余额（扣回后可为负）',
  `biz_type`       TINYINT         NOT NULL                COMMENT '业务类型 1注册 2下单 3邀请 4抵扣 5人工调整 6充值 7消费 8签收到账 9积分取消 10积分扣回',
  `biz_no`         VARCHAR(32)     NULL                    COMMENT '关联业务单号',
  `remark`         VARCHAR(200)    NULL                    COMMENT '说明',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发生时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户积分流水表';

-- 1.7.1 待激活积分（签收后进入缓冲期，到期由定时任务激活入账；期内退件/退运/销毁则取消）
CREATE TABLE `wtzy_points_pending` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='待激活积分表';

-- 1.8 会员等级配置（等级权益）
CREATE TABLE `wtzy_member_levels` (
  `level`              TINYINT UNSIGNED NOT NULL           COMMENT '等级（与 users.member_level 对应）',
  `name`               VARCHAR(30)      NOT NULL           COMMENT '等级名称',
  `min_points`         INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT '达到该等级所需累计积分',
  `discount`           DECIMAL(4,2)     NOT NULL DEFAULT 1.00 COMMENT '运费折扣率（1.00 不打折）',
  `extra_storage_days` INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT '额外免仓储天数',
  `remark`             VARCHAR(200)     NULL               COMMENT '权益说明',
  `updated_at`         DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员等级配置表';

-- 1.9 优惠券（券模板）
CREATE TABLE `wtzy_coupons` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `code`           VARCHAR(32)     NOT NULL                COMMENT '券码',
  `name`           VARCHAR(60)     NOT NULL                COMMENT '券名称',
  `type`           TINYINT         NOT NULL                COMMENT '类型 1满减 2折扣 3运费抵扣',
  `value`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '面额（满减/运费券金额；折扣券为折扣率如 0.90）',
  `min_amount`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '使用门槛（订单满多少可用）',
  `total_qty`      INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT '发行总量，0 表示不限量',
  `used_qty`       INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT '已领取数量',
  `per_user_limit` TINYINT UNSIGNED NOT NULL DEFAULT 1     COMMENT '每人限领张数',
  `start_at`       DATETIME        NULL                    COMMENT '生效时间',
  `end_at`         DATETIME        NULL                    COMMENT '失效时间',
  `status`         TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券表';

-- 1.10 用户持有优惠券
CREATE TABLE `wtzy_user_coupons` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`     BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `coupon_id`   BIGINT UNSIGNED NOT NULL                COMMENT '券ID（wtzy_coupons.id）',
  `status`      TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0未使用 1已使用 2已过期',
  `waybill_id`  BIGINT UNSIGNED NULL                    COMMENT '使用于哪张运单',
  `received_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '领取时间',
  `used_at`     DATETIME        NULL                    COMMENT '使用时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_coupon` (`coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户持有优惠券表';

-- 1.11 邀请返利记录
CREATE TABLE `wtzy_invite_rewards` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`     BIGINT UNSIGNED NOT NULL                COMMENT '被邀请人ID',
  `inviter_id`  BIGINT UNSIGNED NOT NULL                COMMENT '邀请人ID',
  `reward_type` TINYINT         NOT NULL DEFAULT 1      COMMENT '奖励形式 1积分 2余额',
  `amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '奖励金额（余额形式）',
  `points`      INT UNSIGNED    NOT NULL DEFAULT 0      COMMENT '奖励积分',
  `biz_no`      VARCHAR(32)     NULL                    COMMENT '触发业务单号',
  `status`      TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0待发放 1已发放 2已作废',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_inviter` (`inviter_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请返利记录表';

-- 1.12 理赔申请
CREATE TABLE `wtzy_claims` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `claim_no`        VARCHAR(32)     NOT NULL                COMMENT '理赔单号',
  `user_id`         BIGINT UNSIGNED NOT NULL                COMMENT '申请人ID',
  `waybill_id`      BIGINT UNSIGNED NULL                    COMMENT '关联运单ID',
  `package_id`      BIGINT UNSIGNED NULL                    COMMENT '关联包裹ID',
  `claim_type`      TINYINT         NOT NULL                COMMENT '类型 1破损 2丢失 3缺件 4错发 5其他',
  `amount`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '申请赔付金额',
  `description`     VARCHAR(500)    NOT NULL                COMMENT '问题描述',
  `images`          JSON            NULL                    COMMENT '凭证图片URL列表',
  `status`          TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0待受理 1处理中 2初审通过 3已驳回 4已赔付 5财务已审 6管理员已审',
  `finance_approved_at`  DATETIME   NULL                    COMMENT '财务审核通过时间',
  `finance_approved_by`  BIGINT UNSIGNED NULL                 COMMENT '财务审核人ID',
  `admin_approved_at`    DATETIME   NULL                    COMMENT '管理员终审通过时间',
  `admin_approved_by`    BIGINT UNSIGNED NULL                 COMMENT '管理员终审人ID',
  `approved_amount` DECIMAL(12,2)   NULL                    COMMENT '核定赔付金额',
  `handle_admin_id` BIGINT UNSIGNED NULL                    COMMENT '处理人ID',
  `handle_remark`   VARCHAR(500)    NULL                    COMMENT '处理意见',
  `handled_at`      DATETIME        NULL                    COMMENT '处理时间',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_claim_no` (`claim_no`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_waybill` (`waybill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='理赔申请表';

-- 1.13 发票申请
CREATE TABLE `wtzy_invoices` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `invoice_no`   VARCHAR(32)     NOT NULL                COMMENT '申请单号',
  `user_id`      BIGINT UNSIGNED NOT NULL                COMMENT '申请人ID',
  `title`        VARCHAR(100)    NOT NULL                COMMENT '发票抬头',
  `tax_no`       VARCHAR(40)     NULL                    COMMENT '纳税人识别号',
  `type`         TINYINT         NOT NULL DEFAULT 1      COMMENT '类型 1增值税普通发票 2增值税专用发票',
  `amount`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '开票金额',
  `email`        VARCHAR(128)    NULL                    COMMENT '接收邮箱',
  `remark`       VARCHAR(200)    NULL                    COMMENT '备注',
  `status`       TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0待开票 1已开票 2已驳回',
  `handle_remark` VARCHAR(500)   NULL                    COMMENT '处理意见',
  `handled_at`   DATETIME        NULL                    COMMENT '处理时间',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_no` (`invoice_no`),
  KEY `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票申请表';

-- ============================================================
-- 二、仓库与包裹域（6 表）
-- ============================================================

-- 2.1 仓库
CREATE TABLE `wtzy_warehouses` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '仓库ID',
  `warehouse_no`       VARCHAR(16)     NOT NULL                COMMENT '仓库编号（如 WH001）',
  `name`               VARCHAR(50)     NOT NULL                COMMENT '仓库名称',
  `code`               VARCHAR(16)     NOT NULL                COMMENT '仓库代码（体现在收货地址中，如 CA01）',
  `country`            CHAR(2)         NOT NULL                COMMENT '国家/地区码',
  `state`              VARCHAR(50)     NOT NULL                COMMENT '州/省',
  `city`               VARCHAR(50)     NOT NULL                COMMENT '城市',
  `address`            VARCHAR(255)    NOT NULL                COMMENT '仓库街道地址',
  `zip_code`           VARCHAR(16)     NOT NULL                COMMENT '邮编',
  `contact_phone`      VARCHAR(20)     NULL                    COMMENT '仓库联系电话',
  `timezone`           VARCHAR(40)     NOT NULL DEFAULT 'America/Los_Angeles' COMMENT '仓库时区（PHP timezone 标识，多时区需求）',
  `free_storage_days`  INT             NOT NULL DEFAULT 30     COMMENT '免费仓储天数（超期按 wtzy_settings.storage_fee_per_kg_day 计费）',
  `status`             TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `sort`               INT             NOT NULL DEFAULT 0      COMMENT '排序值（前端展示顺序）',
  `created_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at`         DATETIME        NULL                    COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_warehouse_no` (`warehouse_no`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库表';

-- 2.2 库位
CREATE TABLE `wtzy_warehouse_locations` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `warehouse_id` BIGINT UNSIGNED NOT NULL                COMMENT '仓库ID（wtzy_warehouses.id）',
  `code`         VARCHAR(32)     NOT NULL                COMMENT '库位编码（区-巷道-货架-层位，如 A-01-03-02，PDA 扫码上架用）',
  `type`         TINYINT         NOT NULL DEFAULT 2      COMMENT '库位类型 1入库暂存 2常规存储 3打包区 4出库暂存 5异常件区',
  `status`       TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1空闲 2占用',
  `package_id`   BIGINT UNSIGNED NULL                    COMMENT '当前占用包裹ID（常规存储位与包裹一对一，暂存区忽略）',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_warehouse_code` (`warehouse_id`, `code`),
  KEY `idx_type_status` (`warehouse_id`, `type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库库位表';

-- 2.3 包裹（预报 → 入库 → 认领 → 打包 → 出库 全生命周期）
CREATE TABLE `wtzy_packages` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '包裹ID',
  `package_no`        VARCHAR(32)     NOT NULL                COMMENT '包裹编号（仓库内部条码，入库时生成并打印标签）',
  `forecast_no`       VARCHAR(32)     NULL                    COMMENT '预报单号（用户预报时生成；无主件直入为空）',
  `user_id`           BIGINT UNSIGNED NULL                    COMMENT '归属用户ID（待认领无主件时为空）',
  `warehouse_id`      BIGINT UNSIGNED NOT NULL                COMMENT '所在仓库ID（wtzy_warehouses.id）',
  `user_code`         VARCHAR(16)     NULL                    COMMENT '面单识别码（收货扫描时用于匹配归属用户）',
  `tracking_no`       VARCHAR(64)     NULL                    COMMENT '国内快递单号（预报与到仓扫描的匹配键）',
  `express_company`   VARCHAR(50)     NULL                    COMMENT '国内快递公司',
  `item_name`         VARCHAR(100)    NULL                    COMMENT '预报品名',
  `item_qty`          INT             NULL                    COMMENT '预报件数',
  `declared_value`    DECIMAL(12,2)   NULL                    COMMENT '预报货值',
  `currency`          CHAR(3)         NULL                    COMMENT '货值币种（ISO 4217）',
  `category`          TINYINT         NULL                    COMMENT '物品类别 1普货 2敏感货 3带电 4液体膏体 5品牌类 6其他',
  `expected_arrival`  DATE            NULL                    COMMENT '预计到仓日期',
  `weight`            DECIMAL(10,3)   NULL                    COMMENT '实际重量kg（入库称重）',
  `length`            DECIMAL(10,2)   NULL                    COMMENT '长cm',
  `width`             DECIMAL(10,2)   NULL                    COMMENT '宽cm',
  `height`            DECIMAL(10,2)   NULL                    COMMENT '高cm',
  `volume_weight`     DECIMAL(10,3)   NULL                    COMMENT '体积重kg（长×宽×高÷系数，入库按默认5000预估，计费按渠道系数重算）',
  `storage_fee`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '累计仓储费（超免费期按日累计，出库时并入运单费用）',
  `location_id`       BIGINT UNSIGNED NULL                    COMMENT '当前库位ID（wtzy_warehouse_locations.id）',
  `inbound_at`        DATETIME        NULL                    COMMENT '入库时间（扫描入库）',
  `inbound_admin_id`  BIGINT UNSIGNED NULL                    COMMENT '入库操作员ID（wtzy_admins.id）',
  `claimed_at`        DATETIME        NULL                    COMMENT '认领时间',
  `status`            TINYINT         NOT NULL DEFAULT 1      COMMENT '包裹状态 0待预报 1已预报 2已到仓 3待认领(无主件) 4已认领 5待打包(已入运单) 6打包中 7待支付 8已支付 9待出库 10已出库 11运输中 12清关中 13派送中 14已签收 15异常 16已退回 17已销毁',
  `is_abnormal`       TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '是否存在未解决异常 0否 1是（明细见 wtzy_package_abnormals）',
  `remark`            VARCHAR(500)    NULL                    COMMENT '备注',
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间（预报时间）',
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at`        DATETIME        NULL                    COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_package_no` (`package_no`),
  UNIQUE KEY `uk_forecast_no` (`forecast_no`),
  KEY `idx_tracking_no` (`tracking_no`),
  KEY `idx_user_code` (`user_code`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_warehouse_status` (`warehouse_id`, `status`),
  KEY `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='包裹表（含预报信息与全生命周期状态）';

-- 2.4 包裹/运单照片（入库照、打包照、发票截图等）
CREATE TABLE `wtzy_package_photos` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `package_id` BIGINT UNSIGNED NOT NULL                COMMENT '包裹ID（wtzy_packages.id）',
  `waybill_id` BIGINT UNSIGNED NULL                    COMMENT '运单ID（打包/出库环节的照片关联运单，入库照片为空）',
  `photo_type` TINYINT         NOT NULL DEFAULT 1      COMMENT '照片类型 1入库照 2打包照 3出库照 4异常照 5发票/订单截图',
  `file_url`   VARCHAR(255)    NOT NULL                COMMENT '文件访问地址（public/uploads 下相对路径）',
  `admin_id`   BIGINT UNSIGNED NULL                    COMMENT '上传员工ID（用户上传为空）',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_package` (`package_id`, `photo_type`),
  KEY `idx_waybill` (`waybill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='包裹照片表';

-- 2.5 异常件登记（破损/缺件/错发/禁运等）
CREATE TABLE `wtzy_package_abnormals` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `package_id`      BIGINT UNSIGNED NULL                    COMMENT '包裹ID（与运单二选一，异常发生在包裹环节填此字段）',
  `waybill_id`      BIGINT UNSIGNED NULL                    COMMENT '运单ID（异常发生在运输/清关环节填此字段）',
  `abnormal_type`   TINYINT         NOT NULL                COMMENT '异常类型 1破损 2缺件 3错发 4禁运 5无主件 6申请退运 7申请销毁',
  `description`     VARCHAR(500)    NOT NULL                COMMENT '异常描述',
  `images`          TEXT            NULL                    COMMENT '异常照片URL列表（JSON数组字符串）',
  `status`          TINYINT         NOT NULL DEFAULT 0      COMMENT '处理状态 0待处理 1处理中 2已解决 3已关闭',
  `handle_admin_id` BIGINT UNSIGNED NULL                    COMMENT '处理员工ID（wtzy_admins.id）',
  `handle_result`   VARCHAR(500)    NULL                    COMMENT '处理结果说明',
  `handled_at`      DATETIME        NULL                    COMMENT '处理完成时间',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '登记时间',
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_package` (`package_id`),
  KEY `idx_waybill` (`waybill_id`),
  KEY `idx_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='异常件登记表';

-- 2.6 仓库作业任务（主管指派 → 操作员执行 → 作业完成自动收口）
CREATE TABLE `wtzy_warehouse_tasks` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `task_no`         VARCHAR(32)     NOT NULL                COMMENT '任务号（如 WT20260912120000xxxx）',
  `warehouse_id`    BIGINT UNSIGNED NOT NULL                COMMENT '仓库ID（wtzy_warehouses.id）',
  `task_type`       TINYINT         NOT NULL                COMMENT '任务类型 1收货入库 2上架 3打包 4出库 5异常处理',
  `target_type`     TINYINT         NOT NULL                COMMENT '目标类型 1包裹 2运单',
  `target_id`       BIGINT UNSIGNED NOT NULL                COMMENT '目标ID（按 target_type 指向包裹或运单）',
  `target_no`       VARCHAR(32)     NOT NULL                COMMENT '目标单号（冗余存储，列表直接展示无需联表）',
  `assign_admin_id` BIGINT UNSIGNED NOT NULL                COMMENT '执行人（wtzy_admins.id）',
  `assign_by`       BIGINT UNSIGNED NOT NULL                COMMENT '指派人/主管（wtzy_admins.id）',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='仓库作业任务表';

-- ============================================================
-- 三、运单域（7 表）
-- ============================================================

-- 3.1 运单（转运订单主表）
CREATE TABLE `wtzy_waybills` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '运单ID',
  `waybill_no`          VARCHAR(32)     NOT NULL                COMMENT '运单号（系统内部编号，如 WB20260912xxxx）',
  `user_id`             BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `warehouse_id`        BIGINT UNSIGNED NOT NULL                COMMENT '出库仓库ID（wtzy_warehouses.id）',
  `bill_type`           TINYINT         NOT NULL DEFAULT 1      COMMENT '转运方式 1原箱转运 2合箱 3分箱',
  `package_count`       INT             NOT NULL DEFAULT 1      COMMENT '包裹件数',
  `receiver_name`       VARCHAR(50)     NOT NULL                COMMENT '收件人姓名（下单时地址快照，防止后续改地址影响历史单）',
  `receiver_phone`      VARCHAR(20)     NOT NULL                COMMENT '收件人电话',
  `dest_country`        CHAR(2)         NOT NULL                COMMENT '目的国码（ISO 3166-1 alpha-2）',
  `dest_address`        JSON            NOT NULL                COMMENT '目的地完整地址快照（state/city/address1/address2/zip）',
  `channel_id`          BIGINT UNSIGNED NOT NULL                COMMENT '物流渠道ID（wtzy_channels.id）',
  `carrier`             VARCHAR(50)     NULL                    COMMENT '承运商快照（渠道冗余字段，便于按承运商查询）',
  `carrier_tracking_no` VARCHAR(64)     NULL                    COMMENT '承运商运单号（物流商下单/面单接口返回）',
  `channel_order_no`    VARCHAR(64)     NULL                    COMMENT '物流商订单号',
  `label_url`           VARCHAR(255)    NULL                    COMMENT '面单文件地址',
  `actual_weight`       DECIMAL(10,3)   NULL                    COMMENT '打包后实际重量kg',
  `volume_weight`       DECIMAL(10,3)   NULL                    COMMENT '打包后体积重kg',
  `billable_weight`     DECIMAL(10,3)   NULL                    COMMENT '计费重kg（实际重与体积重取大，按渠道步进向上取整）',
  `pack_length`         DECIMAL(10,2)   NULL                    COMMENT '打包箱规-长cm（仓库打包时录入）',
  `pack_width`          DECIMAL(10,2)   NULL                    COMMENT '打包箱规-宽cm',
  `pack_height`         DECIMAL(10,2)   NULL                    COMMENT '打包箱规-高cm',
  `packed_at`           DATETIME        NULL                    COMMENT '仓库打包完成时间',
  `packed_admin_id`     BIGINT UNSIGNED NULL                    COMMENT '打包操作员ID（wtzy_admins.id）',
  `freight_amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '基础运费（首重+续重）',
  `fuel_fee`            DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '燃油附加费',
  `remote_fee`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '偏远地区附加费',
  `sensitive_fee`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '敏感货附加费',
  `service_amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '增值服务费合计',
  `insurance_amount`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '保价申报金额',
  `insurance_fee`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '保价费',
  `other_fee`           DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '其他费用（仓储费/操作费等）',
  `discount_amount`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '优惠金额（P1 优惠券/会员折扣）',
  `total_amount`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '应付总额（元）',
  `due_amount`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '待补差额（打包重算后余额不足挂起时记录，用户补缴后清零）',
  `currency`            CHAR(3)         NOT NULL DEFAULT 'CNY'  COMMENT '计价币种（ISO 4217）',
  `status`              TINYINT         NOT NULL DEFAULT 1      COMMENT '运单状态 0待提交(草稿) 1待称重 2待支付 3已支付 4待出库 5已出库 6运输中 7清关中 8派送中 9已签收 10售后中 11已取消 12已拦截',
  `clear_status`        TINYINT         NOT NULL DEFAULT 0      COMMENT '清关状态 0无 1待清关 2清关中 3已放行 4清关异常 5已完税',
  `pay_method`          TINYINT         NULL                    COMMENT '支付方式 1余额 2支付宝 3微信 4PayPal 5信用卡（线上直付 P1）',
  `paid_at`             DATETIME        NULL                    COMMENT '支付时间',
  `outbound_at`         DATETIME        NULL                    COMMENT '出库交接时间',
  `signed_at`           DATETIME        NULL                    COMMENT '签收时间',
  `auto_signed`         TINYINT         NOT NULL DEFAULT 0      COMMENT '是否系统自动签收 0手动 1自动',
  `refunded_at`         DATETIME        NULL                    COMMENT '运费退回时间（幂等标记，非空表示已退过）',
  `cancel_reason`       VARCHAR(255)    NULL                    COMMENT '取消/拦截原因',
  `fee_detail`          JSON            NULL                    COMMENT '费用明细快照（试算时各计费项JSON，用于对账与前端展示）',
  `remark`              VARCHAR(500)    NULL                    COMMENT '用户备注',
  `admin_remark`        VARCHAR(500)    NULL                    COMMENT '客服/仓库内部备注',
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at`          DATETIME        NULL                    COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_waybill_no` (`waybill_no`),
  KEY `idx_user_status` (`user_id`, `status`),
  KEY `idx_channel` (`channel_id`),
  KEY `idx_carrier_tracking` (`carrier_tracking_no`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_status_outbound` (`status`, `outbound_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='运单表（转运订单）';

-- 3.2 运单-包裹关联（合箱多对一、分箱一对多均走此表）
CREATE TABLE `wtzy_waybill_packages` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `waybill_id`  BIGINT UNSIGNED NOT NULL                COMMENT '运单ID（wtzy_waybills.id）',
  `package_id`  BIGINT UNSIGNED NOT NULL                COMMENT '包裹ID（wtzy_packages.id）',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '关联时间',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_waybill_package` (`waybill_id`, `package_id`),
  KEY `idx_package` (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='运单包裹关联表';

-- 3.3 增值服务字典
CREATE TABLE `wtzy_value_added_services` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `service_code` VARCHAR(32)     NOT NULL                COMMENT '服务代码（photo/reinforce/fill/invoice/rebox/label）',
  `name`         VARCHAR(50)     NOT NULL                COMMENT '服务名称（拍照/加固/填充/取发票/换箱/贴标等）',
  `description`  VARCHAR(255)    NULL                    COMMENT '服务说明',
  `price_mode`   TINYINT         NOT NULL DEFAULT 1      COMMENT '计价方式 1按次 2按包裹 3按运单',
  `price`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '单价',
  `status`       TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `sort`         INT             NOT NULL DEFAULT 0      COMMENT '排序值',
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_service_code` (`service_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='增值服务字典';

-- 3.4 运单已选增值服务（价格快照，防止字典改价影响历史单）
CREATE TABLE `wtzy_waybill_services` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `waybill_id`    BIGINT UNSIGNED NOT NULL                COMMENT '运单ID（wtzy_waybills.id）',
  `service_id`    BIGINT UNSIGNED NOT NULL                COMMENT '服务ID（wtzy_value_added_services.id）',
  `service_name`  VARCHAR(50)     NOT NULL                COMMENT '服务名称快照',
  `price`         DECIMAL(12,2)   NOT NULL                COMMENT '单价快照',
  `qty`           INT             NOT NULL DEFAULT 1      COMMENT '数量（如拍照张数）',
  `amount`        DECIMAL(12,2)   NOT NULL                COMMENT '小计 = 单价 × 数量',
  `status`        TINYINT         NOT NULL DEFAULT 0      COMMENT '执行状态 0待处理 1已完成 2已取消',
  `operator_id`   BIGINT UNSIGNED NULL                    COMMENT '执行员工ID（wtzy_admins.id）',
  `completed_at`  DATETIME        NULL                    COMMENT '完成时间',
  `remark`        VARCHAR(255)    NULL                    COMMENT '执行备注',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_waybill` (`waybill_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='运单增值服务表';

-- 3.5 运单状态流转日志（状态机审计）
CREATE TABLE `wtzy_waybill_status_logs` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `waybill_id`    BIGINT UNSIGNED NOT NULL                COMMENT '运单ID（wtzy_waybills.id）',
  `from_status`   TINYINT         NULL                    COMMENT '原状态（新建流水为空）',
  `to_status`     TINYINT         NOT NULL                COMMENT '目标状态',
  `operator_type` TINYINT         NOT NULL                COMMENT '操作方 1用户 2仓库员工 3管理端 4系统自动',
  `operator_id`   BIGINT UNSIGNED NULL                    COMMENT '操作人ID（系统操作为空）',
  `remark`        VARCHAR(255)    NULL                    COMMENT '流转说明',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '流转时间',
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_waybill` (`waybill_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='运单状态流转日志表';

-- 3.6 物流轨迹
CREATE TABLE `wtzy_tracking_records` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `waybill_id`  BIGINT UNSIGNED NOT NULL                COMMENT '运单ID（wtzy_waybills.id）',
  `event_time`  DATETIME        NOT NULL                COMMENT '轨迹发生时间',
  `status_desc` VARCHAR(100)    NOT NULL                COMMENT '轨迹描述（如 已离开分拨中心）',
  `location`    VARCHAR(100)    NULL                    COMMENT '轨迹地点',
  `source`      TINYINT         NOT NULL DEFAULT 1      COMMENT '来源 1人工录入 2物流商API 3清关行回传',
  `raw_data`    JSON            NULL                    COMMENT 'API 原始报文（对账/排障用）',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_waybill` (`waybill_id`, `event_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='物流轨迹表';

-- 3.7 报关明细
CREATE TABLE `wtzy_declare_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `waybill_id` BIGINT UNSIGNED NOT NULL                COMMENT '运单ID（wtzy_waybills.id）',
  `name_cn`    VARCHAR(100)    NOT NULL                COMMENT '申报品名（中文）',
  `name_en`    VARCHAR(100)    NOT NULL                COMMENT '申报品名（英文）',
  `hs_code`    VARCHAR(20)     NULL                    COMMENT 'HS 编码',
  `qty`        INT             NOT NULL DEFAULT 1      COMMENT '申报数量',
  `unit`       VARCHAR(10)     NOT NULL DEFAULT '件'   COMMENT '申报单位',
  `unit_price` DECIMAL(12,2)   NOT NULL                COMMENT '申报单价',
  `currency`   CHAR(3)         NOT NULL DEFAULT 'USD'  COMMENT '申报币种（按目的国习惯）',
  `brand`      VARCHAR(50)     NULL                    COMMENT '品牌',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_waybill` (`waybill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报关明细表';

-- ============================================================
-- 四、物流渠道与计费域（4 表）
-- ============================================================

-- 4.1 物流渠道
CREATE TABLE `wtzy_channels` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '渠道ID',
  `channel_code`    VARCHAR(32)     NOT NULL                COMMENT '渠道代码（如 US-DHL-EXP）',
  `name`            VARCHAR(50)     NOT NULL                COMMENT '渠道名称（如 美国DHL特快专线）',
  `carrier`         VARCHAR(50)     NOT NULL                COMMENT '承运商/线路（DHL/FedEx/UPS/美国专线等）',
  `goods_type`      TINYINT         NOT NULL DEFAULT 1      COMMENT '货物类型 1普货 2敏感货',
  `volume_divisor`  INT             NOT NULL DEFAULT 5000   COMMENT '体积重系数（体积重kg = 长×宽×高cm³ ÷ 该值）',
  `aging_desc`      VARCHAR(50)     NULL                    COMMENT '时效说明（如 7-12 个工作日）',
  `dest_countries`  JSON            NULL                    COMMENT '可达目的国码JSON数组（如 ["US","CA"]）',
  `tax_mode`        TINYINT         NOT NULL DEFAULT 1      COMMENT '关税模式 1到付 2预付 3双清包税',
  `label_template`  VARCHAR(50)     NULL                    COMMENT '面单模板标识',
  `track_api_code`  VARCHAR(50)     NULL                    COMMENT '轨迹对接接口标识（对应 Services/Integrations 适配器）',
  `status`          TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `sort`            INT             NOT NULL DEFAULT 0      COMMENT '排序值',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at`      DATETIME        NULL                    COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_channel_code` (`channel_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='物流渠道表';

-- 4.2 渠道分区（不同国家组适用不同价格）
CREATE TABLE `wtzy_channel_zones` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `channel_id`  BIGINT UNSIGNED NOT NULL                COMMENT '渠道ID（wtzy_channels.id）',
  `zone_name`   VARCHAR(20)     NOT NULL                COMMENT '分区名称（如 1区/2区/A区）',
  `countries`   JSON            NOT NULL                COMMENT '该区包含的国家码JSON数组',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_channel` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='渠道分区表';

-- 4.3 渠道价格（分区 × 生效期，支持调价留档）
CREATE TABLE `wtzy_channel_prices` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `channel_id`     BIGINT UNSIGNED NOT NULL                COMMENT '渠道ID（wtzy_channels.id）',
  `zone_id`        BIGINT UNSIGNED NOT NULL                COMMENT '分区ID（wtzy_channel_zones.id）',
  `first_weight`   DECIMAL(10,3)   NOT NULL                COMMENT '首重kg',
  `first_fee`      DECIMAL(12,2)   NOT NULL                COMMENT '首重费用',
  `step_weight`    DECIMAL(10,3)   NOT NULL DEFAULT 0.500  COMMENT '续重步进kg（如 0.5，计费重按步进向上取整）',
  `step_fee`       DECIMAL(12,2)   NOT NULL                COMMENT '每步进续重费用',
  `effective_from` DATE            NOT NULL                COMMENT '生效日期',
  `effective_to`   DATE            NULL                    COMMENT '失效日期（空 = 长期有效）',
  `status`         TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_channel_zone` (`channel_id`, `zone_id`, `effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='渠道价格表（首重续重计费）';

-- 4.4 渠道附加费（燃油/偏远/敏感货等）
CREATE TABLE `wtzy_channel_surcharges` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `channel_id`      BIGINT UNSIGNED NOT NULL                COMMENT '渠道ID（wtzy_channels.id）',
  `fee_type`        TINYINT         NOT NULL                COMMENT '附加费类型 1燃油 2偏远 3敏感货 4操作处理 5住宅派送 6其他',
  `name`            VARCHAR(50)     NOT NULL                COMMENT '附加费名称',
  `calc_mode`       TINYINT         NOT NULL                COMMENT '计算方式 1固定金额 2每kg 3按运费百分比 4按货值百分比',
  `fee_value`       DECIMAL(12,2)   NOT NULL                COMMENT '计算基准值（金额 / 每kg金额 / 百分比数值）',
  `condition_params` JSON           NULL                    COMMENT '触发条件（如偏远邮编列表、起征条件等JSON）',
  `status`          TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_channel` (`channel_id`, `fee_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='渠道附加费表';

-- ============================================================
-- 五、财务域（4 表）
-- ============================================================

-- 5.1 用户账户（余额）
CREATE TABLE `wtzy_user_accounts` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`        BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id，一对一）',
  `balance`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '可用余额',
  `frozen_amount`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '冻结金额（支付处理中的在途资金）',
  `currency`       CHAR(3)         NOT NULL DEFAULT 'CNY'  COMMENT '账户币种（P0 统一 CNY，多币种 P1 扩展）',
  `total_recharge` DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '累计充值',
  `total_consume`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '累计消费',
  `status`         TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0冻结 1正常',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '开户时间',
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户余额账户表';

-- 5.2 资金流水（账户唯一账本，余额只允许经流水变动）
CREATE TABLE `wtzy_fund_flows` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `flow_no`        VARCHAR(32)     NOT NULL                COMMENT '流水号（如 FF20260912xxxx）',
  `user_id`        BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `flow_type`      TINYINT         NOT NULL                COMMENT '流水类型 1充值 2运费支付 3退款 4仓储费 5理赔赔偿 6人工调整 7提现(P1) 8返利(P1)',
  `direction`      TINYINT         NOT NULL                COMMENT '方向 1收入（余额增加） 2支出（余额减少）',
  `amount`         DECIMAL(12,2)   NOT NULL                COMMENT '发生金额',
  `balance_before` DECIMAL(12,2)   NOT NULL                COMMENT '变动前余额',
  `balance_after`  DECIMAL(12,2)   NOT NULL                COMMENT '变动后余额',
  `biz_type`       TINYINT         NULL                    COMMENT '关联业务类型 1充值单 2运单 3理赔工单 4系统调整',
  `biz_no`         VARCHAR(32)     NULL                    COMMENT '关联业务单号（充值单号/运单号等）',
  `operator_id`    BIGINT UNSIGNED NULL                    COMMENT '操作员工ID（用户自助/系统自动为空）',
  `remark`         VARCHAR(255)    NULL                    COMMENT '备注',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发生时间',
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_flow_no` (`flow_no`),
  KEY `idx_user_created` (`user_id`, `created_at`),
  KEY `idx_biz` (`biz_type`, `biz_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资金流水表';

-- 5.3 充值订单（含第三方支付信息）
CREATE TABLE `wtzy_recharge_orders` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no`         VARCHAR(32)     NOT NULL                COMMENT '充值单号（如 RC20260912xxxx）',
  `user_id`          BIGINT UNSIGNED NOT NULL                COMMENT '用户ID（wtzy_users.id）',
  `amount`           DECIMAL(12,2)   NOT NULL                COMMENT '充值金额',
  `pay_method`       TINYINT         NOT NULL                COMMENT '支付方式 1支付宝 2微信支付 3PayPal 4Stripe信用卡 5银行转账 6后台人工入账 7QQ钱包 8TRX-TRC20',
  `status`           TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0待支付 1已支付 2支付失败 3已退款 4已关闭 5待入账（用户已付款待确认）',
  `trade_no`         VARCHAR(64)     NULL                    COMMENT '第三方支付流水号',
  `gateway`          VARCHAR(20)     NULL                    COMMENT '支付网关 epay=易支付（空表示未走在线网关）',
  `channel_code`     VARCHAR(20)     NULL                    COMMENT '网关支付方式 alipay/wxpay/qqpay/tron.trx',
  `gateway_trade_no` VARCHAR(64)     NULL                    COMMENT '网关平台订单号（易支付 trade_no）',
  `api_trade_no`     VARCHAR(64)     NULL                    COMMENT '第三方（微信/支付宝）交易单号',
  `pay_type`         VARCHAR(20)     NULL                    COMMENT '网关返回的发起支付类型 jump/qrcode/html/urlscheme',
  `buyer`            VARCHAR(64)     NULL                    COMMENT '支付用户标识（一般为 openid）',
  `refund_no`        VARCHAR(64)     NULL                    COMMENT '网关退款单号',
  `refund_money`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00   COMMENT '累计已退款金额',
  `refund_at`        DATETIME        NULL                    COMMENT '最近退款时间',
  `paid_at`          DATETIME        NULL                    COMMENT '支付成功时间',
  `confirm_admin_id` BIGINT UNSIGNED NULL                    COMMENT '人工确认管理员ID（银行转账/人工入账）',
  `notify_raw`       TEXT            NULL                    COMMENT '支付回调原始报文（对账/排障用，含敏感字段需脱敏展示）',
  `remark`           VARCHAR(255)    NULL                    COMMENT '备注',
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`, `created_at`),
  KEY `idx_status` (`status`, `created_at`),
  KEY `idx_gateway_trade` (`gateway_trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='充值订单表';

-- 5.4 汇率
CREATE TABLE `wtzy_exchange_rates` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `base_currency`  CHAR(3)         NOT NULL                COMMENT '基准币种（ISO 4217）',
  `quote_currency` CHAR(3)         NOT NULL                COMMENT '目标币种（ISO 4217）',
  `rate`           DECIMAL(12,6)   NOT NULL                COMMENT '汇率（1 基准币 = rate 目标币）',
  `source`         VARCHAR(30)     NULL                    COMMENT '汇率来源（manual 手工维护 / API 名称）',
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pair` (`base_currency`, `quote_currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='汇率表';

-- ============================================================
-- 六、消息与工单域（3 表）
-- ============================================================

-- 6.1 消息通知（站内信/公告，短信邮件推送的发送记录）
CREATE TABLE `wtzy_messages` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`     BIGINT UNSIGNED NULL                    COMMENT '接收用户ID（NULL = 全员公告）',
  `msg_type`    TINYINT         NOT NULL DEFAULT 1      COMMENT '渠道 1站内信 2短信 3邮件 4APP推送',
  `notice_type` TINYINT         NOT NULL DEFAULT 1      COMMENT '公告类型 1普通 2重要（仅全员公告使用）',
  `title`       VARCHAR(100)    NOT NULL                COMMENT '标题',
  `content`     TEXT            NOT NULL                COMMENT '内容',
  `biz_type`    TINYINT         NULL                    COMMENT '关联业务类型 1包裹 2运单 3工单 4公告',
  `biz_no`      VARCHAR(32)     NULL                    COMMENT '关联业务单号（客户端跳转用）',
  `is_read`     TINYINT(1)      NOT NULL DEFAULT 0      COMMENT '是否已读 0否 1是（全员公告忽略此字段）',
  `read_at`     DATETIME        NULL                    COMMENT '阅读时间',
  `send_status` TINYINT         NOT NULL DEFAULT 1      COMMENT '发送状态 0待发送 1已发送 2发送失败',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`, `is_read`),
  KEY `idx_biz` (`biz_type`, `biz_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息通知表';

-- 6.2 客服工单
CREATE TABLE `wtzy_tickets` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `ticket_no`       VARCHAR(32)     NOT NULL                COMMENT '工单号（如 TK20260912xxxx）',
  `user_id`         BIGINT UNSIGNED NOT NULL                COMMENT '提交用户ID（wtzy_users.id）',
  `category`        TINYINT         NOT NULL DEFAULT 4      COMMENT '类型 1包裹异常 2运输理赔 3费用咨询 4其他',
  `waybill_id`      BIGINT UNSIGNED NULL                    COMMENT '关联运单ID',
  `package_id`      BIGINT UNSIGNED NULL                    COMMENT '关联包裹ID',
  `title`           VARCHAR(100)    NOT NULL                COMMENT '工单标题',
  `status`          TINYINT         NOT NULL DEFAULT 0      COMMENT '状态 0待受理 1处理中 2已回复(待用户确认) 3已关闭',
  `assign_admin_id` BIGINT UNSIGNED NULL                    COMMENT '受理客服ID（wtzy_admins.id）',
  `last_reply_at`   DATETIME        NULL                    COMMENT '最后回复时间',
  `close_admin_id`  BIGINT UNSIGNED NULL                    COMMENT '关闭操作员ID',
  `closed_at`       DATETIME        NULL                    COMMENT '关闭时间',
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ticket_no` (`ticket_no`),
  KEY `idx_user` (`user_id`, `status`),
  KEY `idx_status` (`status`, `last_reply_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='客服工单表';

-- 6.3 工单回复
CREATE TABLE `wtzy_ticket_replies` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `ticket_id`  BIGINT UNSIGNED NOT NULL                COMMENT '工单ID（wtzy_tickets.id）',
  `reply_side` TINYINT         NOT NULL                COMMENT '回复方 1用户 2客服',
  `admin_id`   BIGINT UNSIGNED NULL                    COMMENT '客服ID（用户回复为空）',
  `content`    TEXT            NOT NULL                COMMENT '回复内容',
  `images`     TEXT            NULL                    COMMENT '图片URL列表（JSON数组字符串）',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '回复时间',
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工单回复表';

-- ============================================================
-- 七、系统与权限域（6 表）
-- ============================================================

-- 7.1 员工账号（管理端 + 仓库端共用）
CREATE TABLE `wtzy_admins` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '员工ID',
  `username`      VARCHAR(32)     NOT NULL                COMMENT '登录账号',
  `password_hash` VARCHAR(255)    NOT NULL                COMMENT '密码哈希（password_hash 生成）',
  `real_name`     VARCHAR(30)     NOT NULL                COMMENT '姓名',
  `phone`         VARCHAR(20)     NULL                    COMMENT '手机号',
  `role_id`       BIGINT UNSIGNED NOT NULL                COMMENT '角色ID（wtzy_roles.id）',
  `warehouse_id`  BIGINT UNSIGNED NULL                    COMMENT '所属仓库ID（仓库端员工必填，管理端为空）',
  `staff_type`    TINYINT         NOT NULL DEFAULT 1      COMMENT '岗位端别 1管理端 2仓库端',
  `status`        TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0禁用 1启用',
  `last_login_at` DATETIME        NULL                    COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(45)     NULL                    COMMENT '最后登录IP（长度兼容 IPv6）',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `deleted_at`    DATETIME        NULL                    COMMENT '删除时间（软删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_role` (`role_id`),
  KEY `idx_warehouse` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工账号表（管理端/仓库端）';

-- 7.2 角色
CREATE TABLE `wtzy_roles` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '角色ID',
  `name`       VARCHAR(30)     NOT NULL                COMMENT '角色名称',
  `code`       VARCHAR(30)     NOT NULL                COMMENT '角色代码（super_admin/admin/finance/customer_service/warehouse_supervisor/warehouse_operator/packer/outbound）',
  `remark`     VARCHAR(255)    NULL                    COMMENT '备注',
  `status`     TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表（RBAC）';

-- 7.3 权限点
CREATE TABLE `wtzy_permissions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '权限ID',
  `perm_code`  VARCHAR(64)     NOT NULL                COMMENT '权限代码（模块.操作，如 admin.users.disable / wh.inbound.scan）',
  `name`       VARCHAR(50)     NOT NULL                COMMENT '权限名称',
  `group_name` VARCHAR(30)     NOT NULL                COMMENT '所属模块组（用户管理/订单管理/仓库作业/财务中心等）',
  `sort`       INT             NOT NULL DEFAULT 0      COMMENT '排序值',
  `status`     TINYINT         NOT NULL DEFAULT 1      COMMENT '状态 0停用 1启用',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perm_code` (`perm_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限点表（RBAC）';

-- 7.4 角色-权限关联
CREATE TABLE `wtzy_role_permissions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `role_id`       BIGINT UNSIGNED NOT NULL                COMMENT '角色ID（wtzy_roles.id）',
  `permission_id` BIGINT UNSIGNED NOT NULL                COMMENT '权限ID（wtzy_permissions.id）',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '授权时间',
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_perm` (`role_id`, `permission_id`),
  KEY `idx_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限关联表（RBAC）';

-- 7.5 员工操作日志（管理端/仓库端审计）
CREATE TABLE `wtzy_admin_operation_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `admin_id`    BIGINT UNSIGNED NOT NULL                COMMENT '操作员工ID（wtzy_admins.id）',
  `admin_name`  VARCHAR(30)     NOT NULL                COMMENT '操作人姓名快照（员工删除后仍可追溯）',
  `module`      VARCHAR(30)     NOT NULL                COMMENT '模块（user/order/package/channel/finance/system 等）',
  `action`      VARCHAR(30)     NOT NULL                COMMENT '动作（create/update/delete/approve/refund/disable 等）',
  `target_type` VARCHAR(30)     NULL                    COMMENT '目标对象类型（如 package/waybill/user_account）',
  `target_id`   BIGINT UNSIGNED NULL                    COMMENT '目标对象ID',
  `before_data` JSON            NULL                    COMMENT '变更前数据快照',
  `after_data`  JSON            NULL                    COMMENT '变更后数据快照',
  `ip`          VARCHAR(45)     NULL                    COMMENT '操作IP（长度兼容 IPv6）',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_admin_created` (`admin_id`, `created_at`),
  KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工操作日志表';

-- 7.6 系统配置（键值对）
CREATE TABLE `wtzy_settings` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `setting_key`   VARCHAR(64)     NOT NULL                COMMENT '配置键',
  `setting_value` TEXT            NOT NULL                COMMENT '配置值（标量或 JSON）',
  `group_name`    VARCHAR(30)     NOT NULL DEFAULT 'base' COMMENT '配置组（base/storage/pay/notify 等）',
  `remark`        VARCHAR(255)    NULL                    COMMENT '说明',
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- ============================================================
-- 八、种子数据（安装时导入；管理员账号由安装向导第三步创建，不在此处）
-- ============================================================

-- 8.1 预置角色（对应需求角色划分：仓库端四岗位 + 管理端四岗位）
INSERT INTO `wtzy_roles` (`id`, `name`, `code`, `remark`) VALUES
(1, '超级管理员', 'super_admin',          '全部权限，代码层不受 RBAC 限制'),
(2, '管理员',     'admin',                '系统管理与全局配置'),
(3, '财务',       'finance',              '财务中心、充值退款对账、汇率发票'),
(4, '客服',       'customer_service',     '工单受理、售后理赔、用户咨询'),
(5, '仓库主管',   'warehouse_supervisor', '仓库任务分配、异常处理审核、绩效查看'),
(6, '仓库操作员', 'warehouse_operator',   '收货入库、扫码上架、库存盘点'),
(7, '打包员',     'packer',               '拣货、打包、拍照、增值服务执行'),
(8, '出库员',     'outbound',             '复核称重、面单打印、出库交接');

-- 8.2 预置增值服务（需求：拍照、加固、填充、取发票、换箱、贴标）
INSERT INTO `wtzy_value_added_services` (`service_code`, `name`, `description`, `price_mode`, `price`, `sort`) VALUES
('photo',     '拍照',   '包裹拍照留档（按张计费）',        2, 3.00,  1),
('reinforce', '加固',   '外箱加固包装（打木架/缠膜另询）', 3, 10.00, 2),
('fill',      '填充',   '箱内填充防震材料',                3, 8.00,  3),
('invoice',   '取发票', '取出箱内发票/单据扫描回传',       2, 5.00,  4),
('rebox',     '换箱',   '更换为指定规格外箱',              3, 15.00, 5),
('label',     '贴标',   '按要求贴标/去除原标签',           2, 3.00,  6);

-- 8.3 基础系统配置
INSERT INTO `wtzy_settings` (`setting_key`, `setting_value`, `group_name`, `remark`) VALUES
('site_name',                '环球领航 GlobePilot', 'site', '站点名称（官网与后台展示的品牌名）'),
('site_description',         '环球领航 GlobePilot：国际空运、海运、快递一站式转运服务，覆盖 220+ 国家与地区，免费合箱质检，全程可视化追踪。', 'site', '网站介绍（搜索引擎摘要）'),
('site_company',             '环球领航国际物流有限公司', 'site', '公司全称（页脚版权行）'),
('site_phone',               '400-888-6688',     'site', '客服电话（页头、页脚展示）'),
('site_email',               'service@globepilot.com', 'site', '客服邮箱（页头、页脚展示）'),
('site_hours',               '周一至周日 8:00 - 24:00', 'site', '服务时间'),
('site_address',             '深圳市南山区科苑南路 318 号环球领航大厦 22F', 'site', '公司地址'),
('site_icp',                 '粤ICP备20268866号-1 · 深圳市公安局备案 44030502088888号', 'site', '备案信息（页脚展示）'),
('warehouse_address_prefix', 'WTZY',           'site', '海外仓地址前缀（面单收件人名称前缀，仅字母数字）'),
('content_faq',              '国际转运一般需要多久？
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
入库后在我的仓库勾选需要合并的包裹提交「合箱」即可，仓库会免费质检、拍照、去物称重并重新打包。包裹免费仓储 {days} 天，足够你从容凑单。', 'site', '常见问题（后台「营销内容 → 常见问题」可改；空行分段，首行问题、其余答案；{days} 按仓库免费仓储天数替换）'),
('default_currency',         'CNY',           'base',    '默认计价币种（ISO 4217）'),
('default_lang',             'zh-CN',         'base',    '默认语言'),
('register_email_verify',    '0',             'site',    '开启邮箱验证注册（1 开启 0 关闭；开启后注册必须邮箱+验证码）'),
('realname_required',        '0',             'site',    '强制实名认证（1 开启 0 关闭；开启后未实名用户仅可改资料/实名/退出）'),
('storage_fee_per_kg_day',   '0.50',          'storage', '历史仓储费单价（元/kg/天，已停用，改为按件/天计费）'),
('storage_fee_per_item_day', '1.00',          'storage', '超期仓储费单价（元/件/天，超免费期后计收）'),
('storage_grace_days',       '3',             'storage', '超免费期后的宽限天数'),
('volume_divisor_default',   '5000',          'storage', '默认体积重系数（渠道未配置时使用）'),
('insurance_rate',           '3',             'pay',     '保价费率（占申报金额百分比）'),
('insurance_max_amount',     '50000',         'pay',     '单票最高保价金额'),
('min_recharge_amount',      '100',           'pay',     '单笔最低充值金额'),
('balance_pay_enabled',      '1',             'pay',     '余额支付开关 0关 1开'),
('invoice_enabled',          '1',             'pay',     '发票申请开关（关闭后用户端不再提供开票入口与申请提交，已提交的申请不受影响）'),
('points_per_yuan',          '1',             'member',  '每消费 1 元赠送的积分（基数取实付基础运费）'),
('points_award_delay_days',  '7',             'member',  '积分到账缓冲天数（签收后 T+N 天到账，0 表示签收即到账）'),
('points_negative_block',    '1',             'member',  '负积分限制下单（1 限制 0 不限制）'),
('auto_sign_days',           '15',            'order',   '自动签收天数（出库后 N 天未手动签收则系统自动签收）'),
('task_lazy_minutes',        '10',            'order',   '页面懒执行自动任务的间隔分钟数（无 cron 时的兜底频率）');

-- 8.4 默认汇率（安装后可在财务中心维护）
INSERT INTO `wtzy_exchange_rates` (`base_currency`, `quote_currency`, `rate`, `source`) VALUES
('CNY', 'USD', 0.140000, 'manual');

-- 8.5 权限点（管理端 18 个 + 仓库端 6 个，RBAC 授权基础）
INSERT INTO `wtzy_permissions` (`perm_code`, `name`, `group_name`, `sort`) VALUES
('user.view',            '用户查看',                   '用户管理',   10),
('user.edit',            '用户编辑',                   '用户管理',   14),
('user.kyc',             '实名审核',                   '用户管理',   15),
('user.audit',           '用户审核',                   '用户管理',   11),
('user.balance.adjust',  '余额调整',                   '用户管理',   12),
('user.risk.set',        '风控设置',                   '用户管理',   13),
('package.view',         '包裹查看',                   '仓库管理',   16),
('order.view',           '运单查看',                   '订单管理',   20),
('order.manage',         '运单操作（取消/拦截/退款）', '订单管理',   21),
('warehouse.config',     '仓库配置',                   '仓库管理',   30),
('warehouse.report',     '仓库报表',                   '仓库管理',   31),
('channel.view',         '渠道查看',                   '物流渠道',   40),
('channel.manage',       '渠道管理',                   '物流渠道',   41),
('customs.manage',       '清关管理',                   '清关管理',   50),
('finance.view',         '财务查看',                   '财务中心',   60),
('finance.manage',       '财务操作（充值/退款/对账）', '财务中心',   61),
('ticket.view',          '工单查看',                   '客服工单',   70),
('ticket.handle',        '工单处理',                   '客服工单',   71),
('claim.view',           '理赔查看',                   '理赔处理',   72),
('claim.review',         '理赔初审',                   '理赔处理',   73),
('claim.finance_audit',  '理赔财务审核',               '理赔处理',   74),
('claim.admin_audit',    '理赔管理员终审',             '理赔处理',   75),
('claim.pay',            '理赔赔付执行',               '理赔处理',   76),
('marketing.manage',     '营销内容管理',               '营销与内容', 80),
('report.view',          '数据报表',                   '数据报表',   90),
('member.manage',        '会员与营销管理',             '会员与营销', 85),
('system.manage',        '系统设置',                   '系统设置',  100),
('staff.view',           '员工查看',                   '系统设置',  101),
('staff.manage',         '员工管理操作',               '系统设置',  102),
('wh.inbound',           '收货入库',                   '仓库作业',  110),
('wh.storage',           '仓储管理',                   '仓库作业',  111),
('wh.packing',           '打包作业',                   '仓库作业',  112),
('wh.outbound',          '出库交接',                   '仓库作业',  113),
('wh.abnormal',          '异常处理',                   '仓库作业',  114),
('wh.task.assign',       '任务分配',                   '仓库作业',  115);

-- 8.6 角色-权限映射（超级管理员代码层跳过 RBAC，无需映射）
INSERT INTO `wtzy_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `wtzy_roles` r
JOIN `wtzy_permissions` p ON p.status = 1
WHERE r.code = 'admin'
  AND p.perm_code NOT LIKE 'wh.%';

INSERT INTO `wtzy_role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `wtzy_roles` r
JOIN `wtzy_permissions` p ON p.status = 1
WHERE (r.code = 'finance' AND p.perm_code IN ('user.view', 'user.balance.adjust', 'order.view', 'finance.view', 'finance.manage', 'report.view'))
   OR (r.code = 'customer_service' AND p.perm_code IN ('user.view', 'package.view', 'order.view', 'ticket.view', 'ticket.handle'))
   OR (r.code = 'warehouse_supervisor' AND p.perm_code LIKE 'wh.%')
   OR (r.code = 'warehouse_operator' AND p.perm_code IN ('wh.inbound', 'wh.storage', 'wh.abnormal'))
   OR (r.code = 'packer' AND p.perm_code = 'wh.packing')
   OR (r.code = 'outbound' AND p.perm_code = 'wh.outbound');

-- 8.7 默认仓库（注册用户自动分配海外仓地址的前提；生产环境在后台维护）
INSERT INTO `wtzy_warehouses`
(`warehouse_no`, `name`, `code`, `country`, `state`, `city`, `address`, `zip_code`, `contact_phone`, `timezone`, `free_storage_days`, `status`, `sort`) VALUES
('WH001', '美国加州仓', 'CA01', 'US', 'California', 'Los Angeles', '1234 E Commerce Way, Los Angeles', '90058', '+1-800-000-0000', 'America/Los_Angeles', 30, 1, 1);

-- 8.8 编号序列（用户编号从 U100001 起分配，注册时原子取号）
INSERT INTO `wtzy_uid_sequences` (`name`, `prefix`, `last_value`, `min_digits`, `remark`) VALUES
('user', 'U', 100000, 6, '用户编号（对外展示的 UID）');

-- 8.9 会员等级（与用户端会员中心、运费折扣联动）
INSERT INTO `wtzy_member_levels` (`level`, `name`, `min_points`, `discount`, `extra_storage_days`, `remark`) VALUES
(1, '普通用户', 0,    1.00, 0, '注册即得，基础权益'),
(2, '白银会员', 1000, 0.98, 3, '运费 98 折，免费仓储期延长 3 天'),
(3, '黄金会员', 5000, 0.95, 7, '运费 95 折，免费仓储期延长 7 天，优先出库'),
(4, '钻石会员', 20000, 0.92, 15, '运费 92 折，免费仓储期延长 15 天，专属客服');
