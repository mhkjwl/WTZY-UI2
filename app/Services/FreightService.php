<?php declare(strict_types=1);
/**
 * @file app/Services/FreightService.php
 * @description 运费试算：首重续重 + 分区 + 附加费 + 增值服务 + 保价 + 会员折扣
 * @author AI
 * @version 0.6.2
 * @date 2026-09-13
 */

namespace App\Services;

use App\Core\Database;
use App\Models\ChannelModel;
use App\Models\MemberLevelModel;
use App\Models\SettingsModel;
use App\Models\ValueAddedServiceModel;
use InvalidArgumentException;
use PDO;

final class FreightService
{
    /**
     * 运费试算（结果同时用于下单落库，保证「所见即所付」）
     *
     * @param array $params channel_id, dest_country, weight, declared_value, goods_type,
     *                      services(serviceId=>qty), insurance_amount, member_level
     * @return array{ok: bool, message: string, detail: array}
     */
    public static function quote(array $params): array
    {
        $channel = self::channel((int) ($params['channel_id'] ?? 0));
        if ($channel === null) {
            return ['ok' => false, 'message' => '请选择有效的物流渠道', 'detail' => []];
        }
        $country = strtoupper(trim((string) ($params['dest_country'] ?? '')));
        $weight = round(max(0.001, (float) ($params['weight'] ?? 0)), 3);
        $declaredValue = round(max(0, (float) ($params['declared_value'] ?? 0)), 2);
        $goodsType = (int) ($params['goods_type'] ?? 1);
        $insuranceAmount = round(max(0, (float) ($params['insurance_amount'] ?? 0)), 2);

        // 分区与价格
        $zoneId = self::matchZone((int) $channel['id'], $country);
        if ($zoneId === null) {
            return ['ok' => false, 'message' => '该渠道暂不支持寄往 ' . $country, 'detail' => []];
        }
        $price = ChannelModel::priceFor((int) $channel['id'], $zoneId);
        if ($price === null) {
            return ['ok' => false, 'message' => '该渠道分区尚未配置价格，请联系客服', 'detail' => []];
        }

        $firstWeight = (float) $price['first_weight'];
        $firstFee = (float) $price['first_fee'];
        $stepWeight = max(0.001, (float) $price['step_weight']);
        $stepFee = (float) $price['step_fee'];

        // 首重续重：超出首重部分按 step_weight 向上取整计费
        $freight = $firstFee;
        $overWeight = max(0, $weight - $firstWeight);
        $steps = $overWeight > 0 ? (int) ceil($overWeight / $stepWeight) : 0;
        $freight += $steps * $stepFee;

        // 体积重系数来自渠道，由调用方传入实际重量与体积重的较大者，此处只按传入计费重量计算
        $surcharges = [];
        $fuel = 0.0;
        $remote = 0.0;
        $sensitive = 0.0;
        // 操作处理 / 住宅派送 / 其他三类没有独立的展示字段，统一并入「其他附加费」计入应付总额
        $otherSurcharge = 0.0;
        foreach (ChannelModel::activeSurcharges((int) $channel['id']) as $row) {
            $amount = self::surchargeAmount($row, $freight, $weight, $declaredValue, $goodsType);
            if ($amount <= 0) {
                continue;
            }
            $surcharges[] = ['name' => $row['name'], 'type' => (int) $row['fee_type'], 'amount' => round($amount, 2)];
            match ((int) $row['fee_type']) {
                1 => $fuel += $amount,
                2 => $remote += $amount,
                3 => $sensitive += $amount,
                default => $otherSurcharge += $amount,
            };
        }

        // 增值服务
        $serviceAmount = 0.0;
        $serviceRows = [];
        $selected = is_array($params['services'] ?? null) ? $params['services'] : [];
        if ($selected !== []) {
            $services = ValueAddedServiceModel::findEnabledByIds(array_map('intval', array_keys($selected)));
            foreach ($services as $svc) {
                $qty = max(1, (int) ($selected[$svc['id']] ?? 1));
                $amount = round((float) $svc['price'] * $qty, 2);
                $serviceAmount += $amount;
                $serviceRows[] = ['id' => (int) $svc['id'], 'name' => $svc['name'], 'price' => (float) $svc['price'], 'qty' => $qty, 'amount' => $amount];
            }
        }

        // 保价费：费率取自系统配置，赔付上限受配置约束
        $insuranceFee = 0.0;
        if ($insuranceAmount > 0) {
            $maxAmount = (float) SettingsModel::get('insurance_max_amount', '50000');
            if ($insuranceAmount > $maxAmount) {
                return ['ok' => false, 'message' => '保价金额不得超过 ' . number_format($maxAmount, 0) . ' 元', 'detail' => []];
            }
            $rate = (float) SettingsModel::get('insurance_rate', '3');
            $insuranceFee = round($insuranceAmount * $rate / 100, 2);
        }

        $gross = $freight + $fuel + $remote + $sensitive + $otherSurcharge + $serviceAmount + $insuranceFee;

        // 会员折扣
        $level = MemberLevelModel::find((int) ($params['member_level'] ?? 1));
        $discountRate = $level === null ? 1.0 : (float) $level['discount'];
        $discountAmount = round($gross * (1 - $discountRate), 2);
        $total = round($gross - $discountAmount, 2);

        return [
            'ok' => true,
            'message' => '',
            'detail' => [
                'channel_id'      => (int) $channel['id'],
                'channel_name'    => $channel['name'],
                'zone_id'         => $zoneId,
                'weight'          => $weight,
                'first_weight'    => $firstWeight,
                'first_fee'       => round($firstFee, 2),
                'step_weight'     => $stepWeight,
                'step_fee'        => round($stepFee, 2),
                'step_count'      => $steps,
                'freight_amount'  => round($freight, 2),
                'fuel_fee'        => round($fuel, 2),
                'remote_fee'      => round($remote, 2),
                'sensitive_fee'   => round($sensitive, 2),
                'other_surcharge_fee' => round($otherSurcharge, 2),
                'surcharges'      => $surcharges,
                'services'        => $serviceRows,
                'service_amount'  => round($serviceAmount, 2),
                'insurance_amount' => $insuranceAmount,
                'insurance_fee'   => round($insuranceFee, 2),
                'discount_rate'   => $discountRate,
                'discount_amount' => $discountAmount,
                'total_amount'    => $total,
                'currency'        => 'CNY',
            ],
        ];
    }

    private static function channel(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, channel_code, name, carrier, goods_type, volume_divisor, tax_mode, status
             FROM ' . Database::table('channels') . ' WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false || (int) $row['status'] !== 1 ? null : $row;
    }

    /**
     * 目的地命中哪个分区（countries 为 JSON 数组）
     */
    private static function matchZone(int $channelId, string $country): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, countries FROM ' . Database::table('channel_zones') . ' WHERE channel_id = :cid ORDER BY id ASC'
        );
        $stmt->execute([':cid' => $channelId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $zone) {
            $countries = json_decode((string) $zone['countries'], true);
            if (is_array($countries) && in_array($country, array_map('strtoupper', $countries), true)) {
                return (int) $zone['id'];
            }
        }
        return null;
    }

    /**
     * 附加费计算：1固定 2每kg 3按运费百分比 4按货值百分比
     */
    private static function surchargeAmount(array $row, float $freight, float $weight, float $declaredValue, int $goodsType): float
    {
        $value = (float) $row['fee_value'];
        return match ((int) $row['calc_mode']) {
            1 => $value,
            2 => $value * $weight,
            3 => $freight * $value / 100,
            4 => $declaredValue * $value / 100,
            default => 0.0,
        };
    }

    /**
     * 渠道可寄目的地国家清单（供下单页联动）
     */
    public static function channelCountries(int $channelId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT countries FROM ' . Database::table('channel_zones') . ' WHERE channel_id = :cid'
        );
        $stmt->execute([':cid' => $channelId]);
        $all = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $list = json_decode((string) $row['countries'], true);
            if (is_array($list)) {
                $all = array_merge($all, array_map('strtoupper', $list));
            }
        }
        return array_values(array_unique($all));
    }
}
