<?php declare(strict_types=1);
/**
 * @file app/Services/EpayService.php
 * @description 易支付（聚合支付平台）V2 接口对接：RSA 签名/验签、统一下单、订单查询、退款、关闭订单
 * @author AI
 * @version 0.6.1
 * @date 2026-09-12
 *
 * V2 与 V1 的差别：签名算法为 SHA256WithRSA（商户私钥签名 / 平台公钥验签）、
 * 接口地址改为 /api/pay/*、新增 timestamp 入参、成功码为 code=0。
 */

namespace App\Services;

use App\Core\Config;
use App\Core\Crypto;
use App\Models\SettingsModel;
use RuntimeException;

final class EpayService
{
    /** 网关成功码（V2 为 0，V1 为 1，此处只认 V2） */
    private const SUCCESS_CODE = 0;

    private const TIMEOUT = 15;

    /** 支持的支付方式（易支付 支付方式列表） */
    public const TYPES = [
        'alipay'   => '支付宝',
        'wxpay'    => '微信支付',
        'qqpay'    => 'QQ钱包',
        'tron.trx' => 'TRX-TRC20',
    ];

    /** 发起支付方式：jump=页面跳转支付，qrcode=弹出扫码付款 */
    public const MODES = [
        'jump'   => '页面跳转支付（跳转到支付页完成付款）',
        'qrcode' => '弹出扫码付款（站内弹窗展示二维码）',
    ];

    /** 本地支付方式编码 → 易支付支付方式 */
    public const CODE_TO_TYPE = [1 => 'alipay', 2 => 'wxpay', 7 => 'qqpay', 8 => 'tron.trx'];

    /**
     * 网关配置（后台「充值设置 → 易支付」维护；商户私钥不进此结构，只回传是否已设置）
     * 接口地址由用户自己的支付平台提供，未配置时留空
     * @return array{enabled: bool, api_url: string, pid: string, mode: string,
     *               has_private: bool, has_public: bool, public_key: string}
     */
    public static function config(): array
    {
        $mode = SettingsModel::get('epay_pay_mode', 'qrcode');
        return [
            'enabled'     => SettingsModel::get('epay_enabled', '0') === '1',
            'api_url'     => rtrim(SettingsModel::get('epay_api_url', ''), '/'),
            'pid'         => SettingsModel::get('epay_pid', ''),
            'mode'        => isset(self::MODES[$mode]) ? $mode : 'qrcode',
            'has_private' => SettingsModel::get('epay_private_key', '') !== '',
            'has_public'  => SettingsModel::get('epay_public_key', '') !== '',
            // 公钥无需保密，回传以便后台核对
            'public_key'  => SettingsModel::get('epay_public_key', ''),
        ];
    }

    /**
     * 是否可用于发起支付：开关开启 + 接口地址 + 商户号 + 商户私钥齐备
     */
    public static function isConfigured(): bool
    {
        $config = self::config();
        return $config['enabled'] && $config['api_url'] !== ''
            && $config['pid'] !== '' && $config['has_private'];
    }

    /**
     * 该支付方式是否已开通（后台勾选的在线渠道 ∩ 网关支持的方式）
     */
    public static function enabledTypes(): array
    {
        $enabled = array_values(array_filter(array_map('intval', explode(',', SettingsModel::get('recharge_online_methods', '')))));
        $types = [];
        foreach ($enabled as $code) {
            if (isset(self::CODE_TO_TYPE[$code])) {
                $types[$code] = self::CODE_TO_TYPE[$code];
            }
        }
        return $types;
    }

    /**
     * 生成签名：非空参数按参数名 ASCII 升序拼接 k=v&k=v（值不做 urlencode），
     * 剔除 sign / sign_type 后使用商户私钥做 SHA256WithRSA 签名
     */
    public static function sign(array $params): string
    {
        $privateKey = self::privateKey();
        if ($privateKey === '') {
            throw new RuntimeException('易支付商户私钥未配置，请先在「充值设置」中填写');
        }
        $content = self::signContent($params);
        $signature = '';
        if (openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA256) !== true) {
            throw new RuntimeException('签名失败，请检查商户私钥格式');
        }
        return base64_encode($signature);
    }

    /**
     * 验签：接口返回与异步通知都必须校验；平台未返回 sign 时视为不可信
     */
    public static function verify(array $params): bool
    {
        $sign = (string) ($params['sign'] ?? '');
        if ($sign === '') {
            return false;
        }
        $publicKey = self::publicKey();
        if ($publicKey === '') {
            return false;
        }
        $result = openssl_verify(self::signContent($params), base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * 待签名字符串（签名与验签共用同一构造规则）
     */
    private static function signContent(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if ($key === 'sign' || $key === 'sign_type') {
                continue;
            }
            if (is_array($value) || $value === null || $value === '') {
                continue;
            }
            $pairs[(string) $key] = (string) $value;
        }
        ksort($pairs, SORT_STRING);
        $parts = [];
        foreach ($pairs as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode('&', $parts);
    }

    /**
     * 统一下单：返回 pay_type 与 pay_info，由前端决定跳转还是展示二维码
     * @return array{pay_type: string, pay_info: string, trade_no: string}
     */
    public static function create(array $order, string $device, string $notifyUrl, string $returnUrl, string $clientIp): array
    {
        $config = self::config();
        $params = [
            'pid'          => $config['pid'],
            'method'       => $config['mode'] === 'qrcode' ? 'web' : 'jump',
            'device'       => $device,
            'type'         => $order['channel_code'],
            'out_trade_no' => $order['order_no'],
            'notify_url'   => $notifyUrl,
            'return_url'   => $returnUrl,
            'name'         => $order['name'],
            'money'        => number_format((float) $order['amount'], 2, '.', ''),
            'clientip'     => $clientIp,
            'timestamp'    => (string) time(),
        ];
        $result = self::request('/api/pay/create', $params);
        $payType = (string) ($result['data']['pay_type'] ?? '');
        $payInfo = (string) ($result['data']['pay_info'] ?? '');
        if ($payType === '' || $payInfo === '') {
            throw new RuntimeException('支付网关未返回有效的支付信息，请稍后重试');
        }
        return [
            'pay_type' => $payType,
            'pay_info' => $payInfo,
            'trade_no' => (string) ($result['data']['trade_no'] ?? ''),
        ];
    }

    /**
     * 订单查询（用于回跳确认与后台手动同步）
     * @return array{status: int, api_trade_no: string, money: string, refundmoney: string, raw: array}
     */
    public static function query(string $orderNo): array
    {
        $result = self::request('/api/pay/query', [
            'pid'          => self::config()['pid'],
            'out_trade_no' => $orderNo,
            'timestamp'    => (string) time(),
        ]);
        return [
            'status'       => (int) ($result['data']['status'] ?? 0),
            'api_trade_no' => (string) ($result['data']['api_trade_no'] ?? ''),
            'money'        => (string) ($result['data']['money'] ?? ''),
            'refundmoney'  => (string) ($result['data']['refundmoney'] ?? ''),
            'raw'          => $result['data'],
        ];
    }

    /**
     * 订单退款
     * @return array{refund_no: string, reducemoney: string}
     */
    public static function refund(string $orderNo, string $money, string $outRefundNo): array
    {
        $result = self::request('/api/pay/refund', [
            'pid'           => self::config()['pid'],
            'out_trade_no'  => $orderNo,
            'money'         => number_format((float) $money, 2, '.', ''),
            'out_refund_no' => $outRefundNo,
            'timestamp'     => (string) time(),
        ]);
        return [
            'refund_no'   => (string) ($result['data']['refund_no'] ?? ''),
            'reducemoney' => (string) ($result['data']['reducemoney'] ?? ''),
        ];
    }

    /**
     * 关闭网关订单（仅部分通道支持，失败不阻断本地关单）
     */
    public static function close(string $orderNo): void
    {
        self::request('/api/pay/close', [
            'pid'          => self::config()['pid'],
            'out_trade_no' => $orderNo,
            'timestamp'    => (string) time(),
        ]);
    }

    /**
     * 发起请求：签名 → POST 表单 → 校验返回签名
     * @return array{code: int, msg: string, data: array, verified: bool}
     */
    private static function request(string $path, array $params): array
    {
        $config = self::config();
        if ($config['pid'] === '') {
            throw new RuntimeException('易支付商户号未配置，请先在「充值设置」中填写');
        }
        if ($config['api_url'] === '') {
            throw new RuntimeException('易支付接口地址未配置，请先在「充值设置」中填写平台提供的地址');
        }
        $params['sign'] = self::sign($params);
        $params['sign_type'] = 'RSA';

        $raw = self::post($config['api_url'] . $path, $params);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('支付网关返回格式异常：' . mb_substr($raw, 0, 120));
        }
        $code = (int) ($decoded['code'] ?? -1);
        if ($code !== self::SUCCESS_CODE) {
            $message = (string) ($decoded['msg'] ?? '支付网关处理失败');
            throw new RuntimeException('支付网关返回：' . $message);
        }
        // 平台公钥已配置时强制验签；未配置时放行但标记为未验签，由后台提示补齐公钥
        $verified = $config['has_public'] ? self::verify($decoded) : false;
        if ($config['has_public'] && !$verified) {
            throw new RuntimeException('支付网关返回数据验签失败，请检查平台公钥配置');
        }
        return ['code' => $code, 'msg' => (string) ($decoded['msg'] ?? ''), 'data' => $decoded, 'verified' => $verified];
    }

    /**
     * POST 表单请求（application/x-www-form-urlencoded）
     */
    private static function post(string $url, array $params): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('服务器未启用 curl 扩展，无法请求支付网关');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        // 主机未配置 CA 时指定系统或项目内置的证书包，否则 HTTPS 校验会直接失败
        $caFile = self::caBundle();
        if ($caFile !== '') {
            curl_setopt($ch, CURLOPT_CAINFO, $caFile);
        }
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            // 支付接口必须走 HTTPS 且校验证书，此处给出可操作的排查方向
            if (stripos($error, 'certificate') !== false || stripos($error, 'SSL') !== false) {
                throw new RuntimeException(
                    '支付网关 HTTPS 校验失败：' . $error
                    . '（服务器缺少 CA 证书：请在 php.ini 设置 curl.cainfo / openssl.cafile，'
                    . '或把 CA 证书放到 storage/cacert.pem）'
                );
            }
            throw new RuntimeException('支付网关连接失败：' . $error);
        }
        if ($status !== 200) {
            throw new RuntimeException('支付网关响应异常（HTTP ' . $status . '）');
        }
        return (string) $body;
    }

    /**
     * 定位可用的 CA 证书包：php.ini 配置 → 项目内置 → 系统常见路径
     */
    private static function caBundle(): string
    {
        $configured = (string) ini_get('curl.cainfo');
        if ($configured === '') {
            $configured = (string) ini_get('openssl.cafile');
        }
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }
        // 入口未定义 BASE_PATH 时（如命令行脚本）按本文件位置回推项目根目录
        $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        foreach ([
            $root . '/storage/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return '';
    }

    /**
     * 异步通知 / 回跳参数收集（通知为 GET 传参）
     */
    public static function notifyParams(): array
    {
        $params = [];
        foreach (($_GET ?? []) as $key => $value) {
            if (is_scalar($value)) {
                $params[(string) $key] = (string) $value;
            }
        }
        return $params;
    }

    /**
     * 商户私钥（AES 密文入库，读取时解密）并补全 PEM 头尾，便于直接粘贴后台给出的 base64
     */
    private static function privateKey(): string
    {
        $stored = SettingsModel::get('epay_private_key', '');
        if ($stored === '') {
            return '';
        }
        $key = Crypto::decrypt($stored);
        if ($key === '') {
            return '';
        }
        return self::toPem($key, 'PRIVATE KEY');
    }

    /**
     * 平台公钥（明文入库；公钥无需保密，便于后台核对）
     */
    private static function publicKey(): string
    {
        $key = SettingsModel::get('epay_public_key', '');
        return $key === '' ? '' : self::toPem($key, 'PUBLIC KEY');
    }

    /**
     * 已是 PEM 则原样返回，否则按 64 字符分行补上头尾（商户后台复制出来的通常是裸 base64）
     */
    private static function toPem(string $key, string $label): string
    {
        $key = trim($key);
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }
        $key = preg_replace('/\s+/', '', $key) ?? '';
        return "-----BEGIN {$label}-----\n" . chunk_split($key, 64, "\n") . "-----END {$label}-----";
    }

    /**
     * 通知地址（网关回调），必须为外网可访问的绝对地址
     */
    public static function url(string $path): string
    {
        return rtrim((string) Config::get('app.url', ''), '/') . $path;
    }
}
