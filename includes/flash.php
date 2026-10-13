<?php
/** One-shot status messages shown after a redirect. */

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function flash_success(string $m): void { flash('success', $m); }
function flash_error(string $m): void { flash('error', $m); }
function flash_info(string $m): void { flash('info', $m); }
function flash_warning(string $m): void { flash('warning', $m); }

function take_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return is_array($flashes) ? $flashes : [];
}

function render_flashes(): string
{
    $html = '';
    foreach (take_flashes() as $f) {
        $type = in_array($f['type'] ?? '', ['success', 'error', 'warning', 'info'], true) ? $f['type'] : 'info';
        $iconName = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert', 'info' => 'info'][$type];
        $role = $type === 'error' ? 'alert' : 'status';
        $html .= '<div class="notice notice-' . $type . '" role="' . $role . '">' . icon($iconName, 'notice-icon', 18)
              . '<div class="notice-body">' . e((string) ($f['message'] ?? '')) . '</div></div>';
    }
    return $html;
}
