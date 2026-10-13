<?php
/** Store settings live in the database so the owner can edit them from admin. */

function settings_all(): array
{
    static $settings = null;
    if ($settings !== null) { return $settings; }
    $settings = [];
    try {
        foreach (fetch_all('SELECT setting_key, setting_value FROM store_settings') as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $ex) {
        log_error('settings load failed: ' . $ex->getMessage());
        $settings = [];
    }
    return $settings;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    $value = array_key_exists($key, $all) ? $all[$key] : null;
    if ($value === null || $value === '') { return $default; }
    return $value;
}

function setting_int(string $key, int $default = 0): int
{
    $value = setting($key, null);
    return is_numeric($value) ? (int) $value : $default;
}

function setting_float(string $key, float $default = 0.0): float
{
    $value = setting($key, null);
    return is_numeric($value) ? (float) $value : $default;
}

function setting_bool(string $key, bool $default = false): bool
{
    $value = setting($key, null);
    if ($value === null) { return $default; }
    return in_array(strtolower((string) $value), ['1', 'yes', 'true', 'on'], true);
}

function setting_set(string $key, $value): void
{
    q('INSERT INTO store_settings (setting_key, setting_value) VALUES (:k, :v)
       ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        ['k' => $key, 'v' => (string) $value]);
    $GLOBALS['aklatan_settings_dirty'] = true;
}

/** Shipping fee for a given subtotal, honouring the free-shipping threshold. */
function shipping_fee_for(float $subtotal, string $method = 'cod', float $weightless = 0.0): float
{
    if ($method === 'pickup') { return 0.0; }
    $fee = setting_float('shipping_fee', 99.00);
    $threshold = setting_float('free_shipping_threshold', 1500.00);
    if ($threshold > 0 && $subtotal >= $threshold) { return 0.0; }
    return $fee;
}
