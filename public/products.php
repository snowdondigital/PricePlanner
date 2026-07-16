<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_permission('delete');
    $action = (string)($_POST['bulk_action'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['product_ids'] ?? [])), fn(int $id): bool => $id > 0)));

    if (!$ids) {
        flash('error', 'Select at least one product.');
    } elseif (!in_array($action, ['archive', 'delete'], true)) {
        flash('error', 'Choose a valid bulk action.');
    } else {
        $pdo = db();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $select = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND archived_at IS NULL");
        $select->execute($ids);
        $selected = $select->fetchAll();

        try {
            $pdo->beginTransaction();
            if ($action === 'archive') {
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
            flash('error', 'The bulk action could not be completed.');
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
    <button class="button">Filter</button><a href="<?= e(url('products.php')) ?>">Clear</a>
</form>
<form method="post" id="bulk-products-form">
    <?= csrf_field() ?>
    <input type="hidden" name="return_query" value="<?= e(http_build_query($_GET)) ?>">
    <?php if (can('delete')): ?>
        <div class="bulk-actions card">
            <span><strong data-selection-count>0</strong> selected</span>
            <button class="button" name="bulk_action" value="archive" data-bulk-confirm="Archive the selected products?">Archive selected</button>
            <button class="button danger" name="bulk_action" value="delete" data-bulk-confirm="Permanently delete the selected products? This cannot be undone.">Delete permanently</button>
        </div>
    <?php endif ?>
    <div class="card table-wrap spreadsheet"><table><thead><tr>
        <?php if (can('delete')): ?><th class="select-column"><input type="checkbox" data-select-all aria-label="Select all products on this page"></th><?php endif ?>
        <th><?= $sortLabel('group', 'Group') ?></th><th><?= $sortLabel('sku', 'SKU') ?></th><th><?= $sortLabel('name', 'Product') ?></th><th><?= $sortLabel('code', 'Code') ?></th>
        <th><?= $sortLabel('cost', 'Cost') ?></th><th><?= $sortLabel('target', 'Target') ?></th><th><?= $sortLabel('preferred', 'Preferred') ?></th><th><?= $sortLabel('retail', 'Retail ex VAT') ?></th>
        <th><?= $sortLabel('retail_vat', 'Retail inc VAT') ?></th><th><?= $sortLabel('trade', 'Trade') ?></th><th><?= $sortLabel('retail_margin', 'Retail margin') ?></th>
        <th><?= $sortLabel('trade_margin', 'Trade margin') ?></th><th><?= $sortLabel('minimum', 'Min price') ?></th><th></th>
    </tr></thead><tbody>
    <?php foreach ($products as $p): $c = calculate_pricing($p); ?><tr class="<?= $c['retail_margin'] !== null && $c['retail_margin'] < (float)$p['minimum_margin'] ? 'low-margin' : '' ?>">
        <?php if (can('delete')): ?><td class="select-column"><input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" aria-label="Select <?= e($p['product_name']) ?>"></td><?php endif ?>
        <td><?= e($p['group_name'] ?: '—') ?></td><td><?= e($p['sku'] ?: '—') ?></td><td><a href="<?= e(url('product.php?id=' . $p['id'])) ?>"><?= e($p['product_name']) ?></a></td><td><?= e($p['product_code'] ?: '—') ?></td>
        <td><?= e(money($c['total_cost'])) ?></td><td><?= e(percent((float)$p['target_margin'])) ?></td><td><?= e(money($c['preferred_sell_price'])) ?></td><td><?= e(money($p['retail_price'] === null ? null : (float)$p['retail_price'])) ?></td><td><?= e(money($c['retail_price_inc_vat'])) ?></td><td><?= e(money($p['trade_price'] === null ? null : (float)$p['trade_price'])) ?></td><td><?= e(percent($c['retail_margin'])) ?></td><td><?= e(percent($c['trade_margin'])) ?></td><td><?= e(money($c['minimum_price'])) ?></td><td><a href="<?= e(url('product.php?id=' . $p['id'])) ?>">Open</a></td>
    </tr><?php endforeach ?>
    <?php if (!$products): ?><tr><td colspan="<?= can('delete') ? 15 : 14 ?>">No products match this filter.</td></tr><?php endif ?>
    </tbody></table></div>
</form>
<?php $pages = max(1, (int)ceil($total / $perPage)); if ($pages > 1): ?><nav class="pagination"><?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a><?php endfor ?></nav><?php endif ?>
<?php page_footer(); ?>
