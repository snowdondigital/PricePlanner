<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name((string)config('session.name'));
    session_set_cookie_params([
        'lifetime' => (int)config('session.lifetime'),
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function user(): ?array
{
    static $cached = false;
    if ($cached !== false) return $cached;
    if (empty($_SESSION['user_id'])) return $cached = null;
    $s = db()->prepare('SELECT id, username, email, role FROM users WHERE id = ? AND is_active = 1');
    $s->execute([$_SESSION['user_id']]);
    return $cached = ($s->fetch() ?: null);
}

function login(string $identity, string $password): bool
{
    $s = db()->prepare('SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1');
    $s->execute([$identity, $identity]);
    $account = $s->fetch();
    if (!$account || !password_verify($password, $account['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['user_id'] = $account['id'];
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$account['id']]);
    return true;
}

function require_login(): void
{
    if (!user()) redirect('login.php');
}

function can(string $action): bool
{
    $role = user()['role'] ?? '';
    $rules = [
        'view' => ['admin', 'manager', 'editor', 'viewer'],
        'edit' => ['admin', 'manager', 'editor'],
        'pricelists' => ['admin', 'manager', 'editor', 'viewer'],
        'pricelist_export' => ['admin', 'manager', 'editor'],
        'delete' => ['admin'],
        'import' => ['admin', 'manager'],
        'export' => ['admin', 'manager'],
        'settings' => ['admin'],
        'users' => ['admin'],
    ];
    return in_array($role, $rules[$action] ?? [], true);
}

function require_permission(string $action): void
{
    require_login();
    if (!can($action)) {
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
}
