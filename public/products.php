<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 50; $offset = ($page - 1) * $perPage;
$q = trim((string)($_GET['q'] ?? '')); $group = (int)($_GET['group'] ?? 0);
$sorts = ['name'=>'p.product_name','sku'=>'p.sku','cost'=>'p.unit_cost','retail'=>'p.retail_price','updated'=>'p.updated_at'];
$sort = $sorts[$_GET['sort'] ?? 'updated'] ?? $sorts['updated']; $dir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$where = ['p.archived_at IS NULL']; $params = [];
if ($q !== '') { $where[] = '(p.product_name LIKE ? OR p.sku LIKE ? OR p.product_code LIKE ?)'; $params = array_fill(0, 3, "%$q%"); }
if ($group) { $where[] = 'p.group_id = ?'; $params[] = $group; }
$whereSql = implode(' AND ', $where);
$count = db()->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql"); $count->execute($params); $total = (int)$count->fetchColumn();
$stmt = db()->prepare("SELECT p.*,g.name group_name FROM products p LEFT JOIN product_groups g ON g.id=p.group_id WHERE $whereSql ORDER BY $sort $dir LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $products = $stmt->fetchAll();
$groups = db()->query('SELECT id,name FROM product_groups ORDER BY name')->fetchAll();
page_header('Products');
?>
<div class="page-title"><div><h1>Products</h1><p class="muted"><?= number_format($total) ?> active products</p></div><?php if (can('edit')): ?><a class="button primary" href="<?= e(url('product.php')) ?>">Add product</a><?php endif ?></div>
<form class="toolbar card" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Search name, SKU or code"><select name="group"><option value="">All groups</option><?php foreach($groups as $g): ?><option value="<?= $g['id'] ?>" <?= $group===$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach ?></select><button class="button">Filter</button><a href="<?= e(url('products.php')) ?>">Clear</a></form>
<div class="card table-wrap spreadsheet"><table><thead><tr><th>Group</th><th><a href="?sort=sku">SKU</a></th><th><a href="?sort=name">Product</a></th><th>Code</th><th>Cost</th><th>Target</th><th>Preferred</th><th>Retail ex VAT</th><th>Retail inc VAT</th><th>Trade</th><th>Retail margin</th><th>Trade margin</th><th>Min price</th><th></th></tr></thead><tbody>
<?php foreach($products as $p): $c=calculate_pricing($p); ?><tr class="<?= $c['retail_margin'] !== null && $c['retail_margin'] < (float)$p['minimum_margin'] ? 'low-margin':'' ?>">
 <td><?= e($p['group_name'] ?: '—') ?></td><td><?= e($p['sku'] ?: '—') ?></td><td><a href="<?= e(url('product.php?id='.$p['id'])) ?>"><?= e($p['product_name']) ?></a></td><td><?= e($p['product_code'] ?: '—') ?></td>
 <td><?= e(money($c['total_cost'])) ?></td><td><?= e(percent((float)$p['target_margin'])) ?></td><td><?= e(money($c['preferred_sell_price'])) ?></td><td><?= e(money($p['retail_price'] === null ? null:(float)$p['retail_price'])) ?></td><td><?= e(money($c['retail_price_inc_vat'])) ?></td><td><?= e(money($p['trade_price'] === null?null:(float)$p['trade_price'])) ?></td><td><?= e(percent($c['retail_margin'])) ?></td><td><?= e(percent($c['trade_margin'])) ?></td><td><?= e(money($c['minimum_price'])) ?></td><td><a href="<?= e(url('product.php?id='.$p['id'])) ?>">Open</a></td>
</tr><?php endforeach ?></tbody></table></div>
<?php $pages=max(1,(int)ceil($total/$perPage)); if($pages>1): ?><nav class="pagination"><?php for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?><a class="<?= $i===$page?'active':'' ?>" href="?<?= e(http_build_query(array_merge($_GET,['page'=>$i]))) ?>"><?= $i ?></a><?php endfor ?></nav><?php endif ?>
<?php page_footer(); ?>
