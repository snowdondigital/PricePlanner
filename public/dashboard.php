<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();
$pdo = db();
$stats = $pdo->query("SELECT COUNT(*) total,
 SUM(unit_cost IS NULL) missing_costs,
 AVG(CASE WHEN retail_price > 0 AND unit_cost IS NOT NULL AND labour_cost IS NOT NULL THEN (retail_price-unit_cost-labour_cost)/retail_price END) avg_margin,
 SUM(CASE WHEN retail_price > 0 AND unit_cost IS NOT NULL AND labour_cost IS NOT NULL AND (retail_price-unit_cost-labour_cost)/retail_price < COALESCE(minimum_margin,0.2) THEN 1 ELSE 0 END) low_margin
 FROM products WHERE archived_at IS NULL")->fetch();
$recent = $pdo->query("SELECT id, product_name, sku, updated_at FROM products WHERE archived_at IS NULL ORDER BY updated_at DESC LIMIT 8")->fetchAll();
page_header('Dashboard');
?>
<div class="page-title"><div><h1>Dashboard</h1><p class="muted">Current pricing health at a glance.</p></div></div>
<section class="stats">
 <div class="stat"><span>Total products</span><strong><?= number_format((int)$stats['total']) ?></strong></div>
 <div class="stat"><span>Average retail margin</span><strong><?= e(percent($stats['avg_margin'] === null ? null : (float)$stats['avg_margin'])) ?></strong></div>
 <div class="stat warning"><span>Missing costs</span><strong><?= number_format((int)$stats['missing_costs']) ?></strong></div>
 <div class="stat danger"><span>Below minimum margin</span><strong><?= number_format((int)$stats['low_margin']) ?></strong></div>
</section>
<section class="card"><div class="card-head"><h2>Recently modified</h2><a href="<?= e(url('products.php')) ?>">View all</a></div>
 <div class="table-wrap"><table><thead><tr><th>Product</th><th>SKU</th><th>Updated</th></tr></thead><tbody>
 <?php foreach ($recent as $p): ?><tr><td><a href="<?= e(url('product.php?id=' . $p['id'])) ?>"><?= e($p['product_name']) ?></a></td><td><?= e($p['sku'] ?: '—') ?></td><td><?= e(date('j M Y H:i', strtotime($p['updated_at']))) ?></td></tr><?php endforeach ?>
 </tbody></table></div>
</section>
<?php page_footer(); ?>
