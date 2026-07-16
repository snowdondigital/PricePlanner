<?php
declare(strict_types=1);

$GLOBALS['app_config'] = require dirname(__DIR__) . '/config/config.php';
date_default_timezone_set($GLOBALS['app_config']['timezone']);

function config(?string $key = null, mixed $default = null): mixed
{
    $value = $GLOBALS['app_config'];
    if ($key === null) return $value;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return ($base === '/' ? '' : $base) . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = compact('type', 'message');
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Your session token has expired. Go back, refresh the page, and try again.');
    }
}

function setting(string $key, mixed $fallback = null): mixed
{
    static $settings;
    if ($settings === null) {
        try {
            $settings = db()->query('SELECT setting_key, setting_value FROM pricing_settings')
                ->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable) {
            $settings = [];
        }
    }
    return $settings[$key] ?? config('defaults.' . $key, $fallback);
}

function money(?float $value): string
{
    return $value === null ? '—' : '£' . number_format($value, 2);
}

function percent(?float $value): string
{
    return $value === null ? '—' : number_format($value * 100, 1) . '%';
}

require_once __DIR__ . '/database.php';
