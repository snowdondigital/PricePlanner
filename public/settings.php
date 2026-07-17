<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';

require_permission('settings');

$settingFields = [
    'vat_rate' => 'VAT rate',
    'target_margin' => 'Default target margin',
    'trade_discount' => 'Default trade discount',
    'minimum_margin' => 'Minimum margin',
];
$errors = [];

function percent_post_value(string $key, bool $allowBlank = false): ?float
{
    $raw = trim((string)($_POST[$key] ?? ''));
    if ($allowBlank && $raw === '') return null;
    return (float)$raw / 100;
}

function valid_margin(?float $value): bool
{
    return $value === null || ($value >= 0 && $value < 1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['delete_group_id']) ? 'group_delete' : (string)($_POST['action'] ?? 'settings');

    if ($action === 'group_delete') {
        $groupId = (int)($_POST['delete_group_id'] ?? 0);
        $stmt = db()->prepare('SELECT name FROM product_groups WHERE id = ?');
        $stmt->execute([$groupId]);
        $groupName = $stmt->fetchColumn();

        if ($groupId <= 0 || $groupName === false) {
            $errors[] = 'The product group no longer exists.';
        } else {
            try {
                db()->prepare('DELETE FROM product_groups WHERE id = ?')->execute([$groupId]);
                flash('success', 'Product group “' . $groupName . '” deleted. Its products now have no group.');
                redirect('settings.php');
            } catch (PDOException) {
                $errors[] = 'The product group could not be deleted.';
            }
        }
    }

    if ($action === 'settings') {
        $values = [];

        foreach ($settingFields as $key => $label) {
            $value = percent_post_value($key);
            if (!valid_margin($value)) {
                $errors[] = "$label must be between 0 and 99.99%.";
                break;
            }
            $values[$key] = $value;
        }

        if (!$errors) {
            $stmt = db()->prepare(
                'INSERT INTO pricing_settings(setting_key, setting_value, updated_by)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
            );
            foreach ($values as $key => $value) {
                $stmt->execute([$key, (string)$value, user()['id']]);
            }
            flash('success', 'Pricing settings updated.');
            redirect('settings.php');
        }
    }

    if ($action === 'group_create') {
        $name = trim((string)($_POST['group_name'] ?? ''));
        $margin = percent_post_value('preferred_margin', true);

        if ($name === '') {
            $errors[] = 'Product group name is required.';
        } elseif (!valid_margin($margin)) {
            $errors[] = 'Preferred margin must be blank or between 0 and 99.99%.';
        } else {
            try {
                db()->prepare('INSERT INTO product_groups(name, preferred_margin) VALUES (?, ?)')
                    ->execute([$name, $margin]);
                flash('success', 'Product group added.');
                redirect('settings.php');
            } catch (PDOException) {
                $errors[] = 'That product group already exists.';
            }
        }
    }

    if ($action === 'groups_update') {
        $names = $_POST['group_name'] ?? [];
        $margins = $_POST['preferred_margin'] ?? [];
        $updates = [];

        foreach ($names as $id => $name) {
            $id = (int)$id;
            $name = trim((string)$name);
            $rawMargin = trim((string)($margins[$id] ?? ''));
            $margin = $rawMargin === '' ? null : (float)$rawMargin / 100;

            if ($id <= 0 || $name === '') {
                $errors[] = 'Every product group must have a name.';
                break;
            }
            if (!valid_margin($margin)) {
                $errors[] = 'Preferred margins must be blank or between 0 and 99.99%.';
                break;
            }
            $updates[] = [$name, $margin, $id];
        }

        if (!$errors) {
            $pdo = db();
            $stmt = $pdo->prepare('UPDATE product_groups SET name = ?, preferred_margin = ? WHERE id = ?');
            try {
                $pdo->beginTransaction();
                foreach ($updates as $update) $stmt->execute($update);
                $pdo->commit();
                flash('success', 'Product groups updated.');
                redirect('settings.php');
            } catch (PDOException) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Product group names must be unique.';
            }
        }
    }
}

$groups = db()->query('SELECT g.id, g.name, g.preferred_margin, COUNT(p.id) product_count
    FROM product_groups g
    LEFT JOIN products p ON p.group_id = g.id
    GROUP BY g.id, g.name, g.preferred_margin
    ORDER BY g.name')->fetchAll();

page_header('Settings');
?>
<div class="page-title">
    <div>
        <h1>Pricing settings</h1>
        <p class="muted">Group preferred margins are used as defaults for new products and CSV imports. Existing product margins are not overwritten.</p>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endforeach ?>

<section class="card narrow">
    <h2>Global defaults</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="settings">
        <?php foreach ($settingFields as $key => $label): ?>
            <label><?= e($label) ?> %
                <input type="number" min="0" max="99.99" step=".01" name="<?= e($key) ?>" value="<?= e((string)((float)setting($key) * 100)) ?>" required>
            </label>
        <?php endforeach ?>
        <button class="button primary">Save settings</button>
    </form>
</section>

<section class="card">
    <h2>Add product group</h2>
    <form method="post" class="fields">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="group_create">
        <label>Group name<input name="group_name" required autocomplete="off"></label>
        <label>Preferred margin %
            <input type="number" min="0" max="99.99" step=".01" name="preferred_margin" placeholder="<?= e((string)((float)setting('target_margin') * 100)) ?>">
        </label>
        <button class="button primary">Add group</button>
    </form>
</section>

<section class="card">
    <h2>Product groups</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="groups_update">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Group name</th>
                    <th>Preferred margin %</th>
                    <th>Products</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td>
                            <input name="group_name[<?= (int)$group['id'] ?>]" value="<?= e($group['name']) ?>" required>
                        </td>
                        <td>
                            <input type="number" min="0" max="99.99" step=".01" name="preferred_margin[<?= (int)$group['id'] ?>]" value="<?= e($group['preferred_margin'] === null ? '' : (string)((float)$group['preferred_margin'] * 100)) ?>" placeholder="Use global default">
                        </td>
                        <td><?= number_format((int)$group['product_count']) ?></td>
                        <td>
                            <button class="button danger" name="delete_group_id" value="<?= (int)$group['id'] ?>" formnovalidate data-confirm="Delete the <?= e($group['name']) ?> group? <?= (int)$group['product_count'] ?> product<?= (int)$group['product_count'] === 1 ? '' : 's' ?> will be moved to No group.">Delete</button>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button class="button primary">Save product groups</button>
        </div>
    </form>
</section>
<?php page_footer(); ?>
