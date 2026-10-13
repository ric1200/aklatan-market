<?php
/** Discount codes: created in admin, validated on the server at checkout. */

function applied_discount(): ?array
{
    $code = $_SESSION['discount_code'] ?? null;
    if (!is_string($code) || $code === '') { return null; }
    return ['code' => $code];
}

function discount_clear(): void
{
    unset($_SESSION['discount_code']);
}

/**
 * Validate a code against the live database and the current basket.
 *
 * @return array{ok:bool,message:string,discount:float,code:?array}
 */
function discount_validate(string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    if ($code === '') { return ['ok' => false, 'message' => 'Enter a discount code.', 'discount' => 0.0, 'code' => null]; }

    $row = fetch_one('SELECT * FROM discount_codes WHERE code = :code', ['code' => $code]);
    if (!$row || (int) $row['is_active'] !== 1) {
        return ['ok' => false, 'message' => 'That discount code is not recognised.', 'discount' => 0.0, 'code' => null];
    }
    if (!empty($row['starts_at']) && strtotime((string) $row['starts_at']) > time()) {
        return ['ok' => false, 'message' => 'That discount code is not active yet.', 'discount' => 0.0, 'code' => null];
    }
    if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
        return ['ok' => false, 'message' => 'That discount code has expired.', 'discount' => 0.0, 'code' => null];
    }
    if ($row['max_uses'] !== null && (int) $row['used_count'] >= (int) $row['max_uses']) {
        return ['ok' => false, 'message' => 'That discount code has reached its usage limit.', 'discount' => 0.0, 'code' => null];
    }
    $minSubtotal = (float) $row['min_subtotal'];
    if ($minSubtotal > 0 && $subtotal < $minSubtotal) {
        return ['ok' => false, 'message' => 'That code needs a minimum basket of ' . money($minSubtotal) . '.', 'discount' => 0.0, 'code' => null];
    }
    if ($row['per_user_limit'] !== null && current_user_id() !== null) {
        $used = (int) fetch_value(
            'SELECT COUNT(*) FROM discount_redemptions WHERE code_id = :cid AND user_id = :uid',
            ['cid' => $row['id'], 'uid' => current_user_id()], 0);
        if ($used >= (int) $row['per_user_limit']) {
            return ['ok' => false, 'message' => 'You have already used that discount code.', 'discount' => 0.0, 'code' => null];
        }
    }

    $type = $row['type'] === 'percent' ? 'percent' : 'fixed';
    $value = (float) $row['value'];
    $discount = $type === 'percent' ? round($subtotal * ($value / 100), 2) : $value;
    $discount = min($discount, $subtotal);
    $discount = max(0.0, round($discount, 2));

    return [
        'ok' => true,
        'message' => 'Discount applied: ' . ($type === 'percent' ? rtrim(rtrim(number_format($value, 2), '0'), '.') . '% off' : money($value) . ' off'),
        'discount' => $discount,
        'code' => $row,
    ];
}

function discount_label(array $row): string
{
    if ($row['type'] === 'percent') {
        return rtrim(rtrim(number_format((float) $row['value'], 2), '0'), '.') . '% off';
    }
    return money($row['value']) . ' off';
}
