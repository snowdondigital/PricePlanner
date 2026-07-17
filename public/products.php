<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['bulk_action'] ?? '');
    require_permission($action === 'update' ? 'edit' : 'delete');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['product_ids'] ?? [])), fn(int $id): bool => $id > 0)));

    if (!$ids) {
        flash('error', 'Select at least one product.');
    } elseif (!in_array($action, ['update', 'archive', 'delete'], true)) {
        flash('error', 'Choose a valid bulk action.');
    } else {
        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $select = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND archived_at IS NULL");
        $select->execute($ids);
        $selected = $select->fetchAll();

        try {
            $bulkUpdates = [];
            if ($action === 'update') {
                $definitions = [
                    'group_id' => ['type' => 'group'],
                    'unit_cost' => ['type' => 'nullable_number'],
                    'labour_cost' => ['type' => 'number'],
                    'retail_price' => ['type' => 'nullable_number'],
                    'trade_price' => ['type' => 'nullable_number'],
                    'msrp' => ['type' => 'nullable_number'],
                    'preferred_price_override' => ['type' => 'nullable_number'],
                    'competitor_price' => ['type' => 'nullable_number'],
                    'target_margin' => ['type' => 'percent'],
                    'trade_discount' => ['type' => 'percent'],
                    'minimum_margin' => ['type' => 'percent'],
                    'is_wholesale' => ['type' => 'boolean'],
                ];
                foreach ($definitions as $field => $definition) {
                    if (empty($_POST['change'][$field])) continue;
                    $raw = trim((string)($_POST['bulk'][$field] ?? ''));
                    if ($definition['type'] === 'group') {
                        if ($raw !== '' && (!ctype_digit($raw) || (int)$raw < 1)) throw new InvalidArgumentException('Choose a valid product group.');
                        if ($raw !== '') {
                            $groupCheck = $pdo->prepare('SELECT COUNT(*) FROM product_groups WHERE id = ?');
                            $groupCheck->execute([(int)$raw]);
                            if (!(int)$groupCheck->fetchColumn()) throw new InvalidArgumentException('The selected product group no longer exists.');
                        }
                        $bulkUpdates[$field] = $raw === '' ? null : (int)$raw;
                    } elseif ($definition['type'] === 'boolean') {
                        if (!in_array($raw, ['0', '1'], true)) throw new InvalidArgumentException('Choose a valid wholesale status.');
                        $bulkUpdates[$field] = (int)$raw;
                    } elseif ($definition['type'] === 'nullable_number' && $raw === '') {
                        $bulkUpdates[$field] = null;
                    } else {
                        if ($raw === '' || !is_numeric($raw)) throw new InvalidArgumentException('Enter a valid number for every field being changed.');
                        $value = (float)$raw;
                        if ($value < 0) throw new InvalidArgumentException('Bulk values cannot be negative.');
                        if ($definition['type'] === 'percent') {
                            if ($value >= 100) throw new InvalidArgumentException('Margins and discounts must be below 100%.');
                            $value /= 100;
                        }
                        $bulkUpdates[$field] = $value;
                    }
                }
                if (!$bulkUpdates) throw new InvalidArgumentException('Choose at least one field to change.');
            }

            $pdo->beginTransaction();
            if ($action === 'update') {
                foreach ($selected as $product) {
                    $updated = $product;
                    foreach ($bulkUpdates as $field => $value) $updated[$field] = $value;
                    save_product(array_intersect_key($updated, array_flip(PRODUCT_FIELDS)), (int)$product['id']);
                }
                $message = count($selected) . ' product' . (count($selected) === 1 ? '' : 's') . ' updated.';
            } elseif ($action === 'archive') {
                $update = $pdo->prepare("UPDATE products SET archived_at = NOW(), updated_by = ? WHERE id IN ($placeholders) AND archived_at IS NULL");
                $update->execute(array_merge([user()['id']], $ids));
                foreach ($selected as $product) audit((int)$product['id'], 'archive', $product, []);
                $message = count($selected) . ' product' . (count($selected) === 1 ? '' : 's') . ' archived.';
            } else {
                foreach ($selected as $product) audit((int)$product['id'], 'delete', $product, []);
                $delete = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders) AND archived_at IS NULL");
                $delete->execute($ids);
                $message = count($selected) . ' product' . (count($selected) === 1 ? '' : 's') . ' permanently deleted.';
            }
            $pdo->commit();
            flash('success', $message);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'The bulk action could not be completed.');
        }
    }
    $returnQuery = trim((string)($_POST['return_query'] ?? ''));
    redirect('products.php' . ($returnQuery === '' ? '' : '?' . $returnQuery));
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$q = trim((string)($_GET['q'] ?? ''));
$group = (int)($_GET['group'] ?? 0);
$wholesale = in_array((string)($_GET['wholesale'] ?? ''), ['yes', 'no'], true) ? (string)$_GET['wholesale'] : '';
$missingCost = (string)($_GET['missing_cost'] ?? '') === '1';
$missingRetail = (string)($_GET['missing_retail'] ?? '') === '1';
$missingTrade = (string)($_GET['missing_trade'] ?? '') === '1';
$vatRate = (float)setting('vat_rate', 0.20);
$cost = '(p.unit_cost + p.labour_cost)';
$sorts = [
    'group' => 'g.name',
    'sku' => 'p.sku',
    'name' => 'p.product_name',
    'code' => 'p.product_code',
    'cost' => $cost,
    'target' => 'p.target_margin',
    'preferred' => "CASE WHEN p.target_margin < 1 THEN $cost / (1 - p.target_margin) END",
    'retail' => 'p.retail_price',
    'retail_vat' => 'p.retail_price * ' . (1 + $vatRate),
    'trade' => 'p.trade_price',
    'retail_margin' => "CASE WHEN p.retail_price <> 0 THEN (p.retail_price - $cost) / p.retail_price END",
    'trade_margin' => "CASE WHEN p.trade_price <> 0 THEN (p.trade_price - $cost) / p.trade_price END",
    'minimum' => "CASE WHEN p.minimum_margin < 1 THEN $cost / (1 - p.minimum_margin) END",
];
$sortKey = array_key_exists((string)($_GET['sort'] ?? ''), $sorts) ? (string)$_GET['sort'] : 'updated';
$sort = $sortKey === 'updated' ? 'p.updated_at' : $sorts[$sortKey];
$direction = (string)($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$dirSql = strtoupper($direction);

$sortUrl = static function (string $key) use ($sortKey, $direction): string {
    $nextDirection = $sortKey === $key && $direction === 'asc' ? 'desc' : 'asc';
    return '?' . http_build_query(array_merge($_GET, ['sort' => $key, 'dir' => $nextDirection, 'page' => 1]));
};
$sortLabel = static function (string $key, string $label) use ($sortKey, $direction, $sortUrl): string {
    $indicator = $sortKey === $key ? ($direction === 'asc' ? ' ↑' : ' ↓') : '';
    return '<a href="' . e($sortUrl($key)) . '">' . e($label . $indicator) . '</a>';
};

$where = ['p.archived_at IS NULL'];
$params = [];
if ($q !== '') {
    $where[] = '(p.product_name LIKE ? OR p.sku LIKE ? OR p.product_code LIKE ?)';
    $params = array_fill(0, 3, "%$q%");
}
if ($group) {
    $where[] = 'p.group_id = ?';
    $params[] = $group;
}
if ($wholesale !== '') {
    $where[] = 'p.is_wholesale = ?';
    $params[] = $wholesale === 'yes' ? 1 : 0;
}
if ($missingCost) $where[] = 'p.unit_cost IS NULL';
if ($missingRetail) $where[] = 'p.retail_price IS NULL';
if ($missingTrade) $where[] = 'p.trade_price IS NULL';
$whereSql = implode(' AND ', $where);
$count = db()->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$stmt = db()->prepare("SELECT p.*, g.name group_name FROM products p LEFT JOIN product_groups g ON g.id = p.group_id WHERE $whereSql ORDER BY $sort $dirSql LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();
$groups = db()->query('SELECT id,name FROM product_groups ORDER BY name')->fetchAll();

page_header('Products');
?>
<div class="page-title">
    <div><h1>Products</h1><p class="muted"><?= number_format($total) ?> active products</p></div>
    <?php if (can('edit')): ?><a class="button primary" href="<?= e(url('product.php')) ?>">Add product</a><?php endif ?>
</div>
<form class="toolbar card" method="get">
    <input name="q" value="<?= e($q) ?>" placeholder="Search name, SKU or code">
    <select name="group"><option value="">All groups</option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $group === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach ?></select>
    <select name="wholesale"><option value="">All product types</option><option value="yes" <?= $wholesale === 'yes' ? 'selected' : '' ?>>Wholesale products</option><option value="no" <?= $wholesale === 'no' ? 'selected' : '' ?>>Non-wholesale products</option></select>
    <label class="filter-check"><input type="checkbox" name="missing_cost" value="1" <?= $missingCost ? 'checked' : '' ?>> Missing cost</label>
    <label class="filter-check"><input type="checkbox" name="missing_retail" value="1" <?= $missingRetail ? 'checked' : '' ?>> Missing retail price</label>
    <label class="filter-check"><input type="checkbox" name="missing_trade" value="1" <?= $missingTrade ? 'checked' : '' ?>> Missing trade price</label>
    <button class="button">Filter</button><a href="<?= e(url('products.php')) ?>">Clear</a>
</form>
<form method="post" id="bulk-products-form">
    <?= csrf_field() ?>
    <input type="hidden" name="return_query" value="<?= e(http_build_query($_GET)) ?>">
    <?php if (can('edit') || can('delete')): ?>
        <div class="bulk-actions card" data-bulk-actions hidden>
            <span><strong data-selection-count>0</strong> selected</span>
            <?php if (can('edit')): ?><button class="button primary" type="button" data-bulk-edit-toggle>Edit selected</button><?php endif ?>
            <?php if (can('delete')): ?>
                <button class="button" name="bulk_action" value="archive" data-bulk-confirm="Archive the selected products?">Archive selected</button>
                <button class="button danger" name="bulk_action" value="delete" data-bulk-confirm="Permanently delete the selected products? This cannot be undone.">Delete permanently</button>
            <?php endif ?>
        </div>
    <?php endif ?>
    <?php if (can('edit')): ?>
        <section class="bulk-edit card" data-bulk-edit hidden>
            <div class="card-head"><div><h2>Edit selected products</h2><small>Tick each field you want to replace. Unticked fields stay unchanged.</small></div><button class="button" type="button" data-bulk-edit-close>Close</button></div>
            <div class="bulk-edit-grid">
                <label><span><input type="checkbox" name="change[group_id]" value="1"> Product group</span><select name="bulk[group_id]"><option value="">No group</option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach ?></select></label>
                <?php foreach ([
                    'unit_cost' => ['Unit cost', '.00001'], 'labour_cost' => ['Labour cost', '.00001'],
                    'retail_price' => ['Retail price excl. VAT', '.0001'], 'trade_price' => ['Trade price', '.0001'],
                    'msrp' => ['MSRP', '.0001'], 'preferred_price_override' => ['Preferred override', '.0001'],
                    'competitor_price' => ['Competitor price', '.0001'], 'target_margin' => ['Target margin %', '.01'],
                    'trade_discount' => ['Trade discount %', '.01'], 'minimum_margin' => ['Minimum margin %', '.01'],
                ] as $field => [$label, $step]): ?>
                    <label><span><input type="checkbox" name="change[<?= e($field) ?>]" value="1"> <?= e($label) ?></span><input type="number" min="0" <?= in_array($field, ['target_margin','trade_discount','minimum_margin'], true) ? 'max="99.99"' : '' ?> step="<?= e($step) ?>" name="bulk[<?= e($field) ?>]" placeholder="<?= $field === 'labour_cost' || in_array($field, ['target_margin','trade_discount','minimum_margin'], true) ? 'Enter value' : 'Blank clears value' ?>"></label>
                <?php endforeach ?>
                <label><span><input type="checkbox" name="change[is_wholesale]" value="1"> Wholesale status</span><select name="bulk[is_wholesale]"><option value="1">Wholesale</option><option value="0">Not wholesale</option></select></label>
            </div>
            <button class="button primary" name="bulk_action" value="update" data-bulk-update>Apply changes</button>
        </section>
    <?php endif ?>
    <div class="card table-wrap spreadsheet"><table><thead><tr>
        <?php if (can('edit') || can('delete')): ?><th class="select-column"><input type="checkbox" data-select-all aria-label="Select all products on this page"></th><?php endif ?>
        <th><?= $sortLabel('group', 'Group') ?></th><th><?= $sortLabel('sku', 'SKU') ?></th><th><?= $sortLabel('name', 'Product') ?></th><th><?= $sortLabel('code', 'Code') ?></th>
        <th><?= $sortLabel('cost', 'Cost') ?></th><th><?= $sortLabel('target', 'Target') ?></th><th><?= $sortLabel('preferred', 'Preferred') ?></th><th><?= $sortLabel('retail', 'Retail ex VAT') ?></th>
        <th><?= $sortLabel('retail_vat', 'Retail inc VAT') ?></th><th><?= $sortLabel('trade', 'Trade') ?></th><th><?= $sortLabel('retail_margin', 'Retail margin') ?></th>
        <th><?= $sortLabel('trade_margin', 'Trade margin') ?></th><th><?= $sortLabel('minimum', 'Min price') ?></th><th></th>
    </tr></thead><tbody>
    <?php foreach ($products as $p): $c = calculate_pricing($p); ?><tr class="<?= $c['retail_margin'] !== null && $c['retail_margin'] < (float)$p['minimum_margin'] ? 'low-margin' : '' ?>">
        <?php if (can('edit') || can('delete')): ?><td class="select-column"><input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" aria-label="Select <?= e($p['product_name']) ?>"></td><?php endif ?>
        <td><?= e($p['group_name'] ?: '—') ?></td><td><?= e($p['sku'] ?: '—') ?></td><td><a href="<?= e(url('product.php?id=' . $p['id'])) ?>"><?= e($p['product_name']) ?></a></td><td><?= e($p['product_code'] ?: '—') ?></td>
        <td><?= e(money($c['total_cost'])) ?></td><td><?= e(percent((float)$p['target_margin'])) ?></td><td><?= e(money($c['preferred_sell_price'])) ?></td><td><?= e(money($p['retail_price'] === null ? null : (float)$p['retail_price'])) ?></td><td><?= e(money($c['retail_price_inc_vat'])) ?></td><td><?= e(money($p['trade_price'] === null ? null : (float)$p['trade_price'])) ?></td><td><?= e(percent($c['retail_margin'])) ?></td><td><?= e(percent($c['trade_margin'])) ?></td><td><?= e(money($c['minimum_price'])) ?></td><td><a href="<?= e(url('product.php?id=' . $p['id'])) ?>">Open</a></td>
    </tr><?php endforeach ?>
    <?php if (!$products): ?><tr><td colspan="<?= (can('edit') || can('delete')) ? 15 : 14 ?>">No products match this filter.</td></tr><?php endif ?>
    </tbody></table></div>
</form>
<?php $pages = max(1, (int)ceil($total / $perPage)); if ($pages > 1): ?><nav class="pagination"><?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a><?php endfor ?></nav><?php endif ?>
<?php page_footer(); ?>
