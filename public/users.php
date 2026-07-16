<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_permission('users');

$errors = [];

function user_exists(int $id): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() > 0;
}

function audit_user_change(int $targetUserId, string $action, string $field, ?string $oldValue, ?string $newValue): void
{
    db()->prepare(
        'INSERT INTO audit_log (user_id, product_id, action, field_name, old_value, new_value, ip_address)
         VALUES (?, NULL, ?, ?, ?, ?, ?)'
    )->execute([
        user()['id'],
        $action . ':user:' . $targetUserId,
        $field,
        $oldValue,
        $newValue,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'create');

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id !== (int)user()['id'] && user_exists($id)) {
            $before = db()->prepare('SELECT is_active FROM users WHERE id = ?');
            $before->execute([$id]);
            $oldStatus = (string)$before->fetchColumn();

            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);

            $after = db()->prepare('SELECT is_active FROM users WHERE id = ?');
            $after->execute([$id]);
            audit_user_change($id, 'user_status', 'is_active', $oldStatus, (string)$after->fetchColumn());
        }
        flash('success', 'User status updated.');
        redirect('users.php');
    }

    if ($action === 'password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirm'] ?? '');

        if (!user_exists($id)) {
            $errors[] = 'The selected user no longer exists.';
        } elseif ($password !== $confirmation) {
            $errors[] = 'The new password and confirmation do not match.';
        } elseif (strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            audit_user_change($id, 'password_reset', 'password_hash', '[reset]', '[reset]');
            flash('success', 'Password reset. Use the exact password entered in both reset fields.');
            redirect('users.php');
        }
    }

    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = (string)($_POST['role'] ?? 'viewer');
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirm'] ?? '');

        if ($password !== $confirmation) {
            $errors[] = 'The temporary password and confirmation do not match.';
        } elseif (!$username || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || !in_array($role, ['admin', 'manager', 'editor', 'viewer'], true)) {
            $errors[] = 'Enter a username, valid email, role and password of at least 10 characters.';
        } else {
            try {
                db()->prepare('INSERT INTO users(username, email, password_hash, role) VALUES (?, ?, ?, ?)')
                    ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash('success', 'User created.');
                redirect('users.php');
            } catch (PDOException) {
                $errors[] = 'That username or email is already used.';
            }
        }
    }
}

$users = db()->query('SELECT id, username, email, role, is_active, last_login_at, created_at FROM users ORDER BY username')->fetchAll();

page_header('Users');
?>
<div class="page-title"><h1>Users</h1></div>

<?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endforeach ?>

<section class="card">
    <h2>Create user</h2>
    <form method="post" class="fields">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label>Username<input name="username" required autocomplete="off"></label>
        <label>Email<input type="email" name="email" required autocomplete="off"></label>
        <label>Role
            <select name="role">
                <?php foreach (['admin', 'manager', 'editor', 'viewer'] as $role): ?>
                    <option value="<?= e($role) ?>"><?= e(ucfirst($role)) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>Temporary password<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
        <label>Confirm temporary password<input type="password" name="password_confirm" minlength="10" required autocomplete="new-password"></label>
        <button class="button primary">Create</button>
    </form>
</section>

<section class="card table-wrap">
    <table>
        <thead>
        <tr>
            <th>User</th>
            <th>Role</th>
            <th>Status</th>
            <th>Last login</th>
            <th>Reset password</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $account): ?>
            <tr>
                <td><?= e($account['username']) ?><small><?= e($account['email']) ?></small></td>
                <td><?= e(ucfirst($account['role'])) ?></td>
                <td><?= $account['is_active'] ? 'Active' : 'Disabled' ?></td>
                <td><?= e($account['last_login_at'] ?? 'Never') ?></td>
                <td>
                    <form method="post" class="inline" autocomplete="off">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="password">
                        <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                        <input type="password" name="password" minlength="10" placeholder="New password" required autocomplete="new-password">
                        <input type="password" name="password_confirm" minlength="10" placeholder="Confirm password" required autocomplete="new-password">
                        <button class="button">Reset</button>
                    </form>
                </td>
                <td>
                    <?php if ((int)$account['id'] !== (int)user()['id']): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                            <button class="button"><?= $account['is_active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</section>
<?php page_footer(); ?>
