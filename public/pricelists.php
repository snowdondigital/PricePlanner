<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_permission('pricelists');
$stmt = db()->query('SELECT pl.*,COUNT(pli.id) item_count FROM price_lists pl LEFT JOIN price_list_items pli ON pli.price_list_id=pl.id GROUP BY pl.id ORDER BY pl.updated_at DESC');
$lists = $stmt->fetchAll();
page_header('Price lists');
?>
<div class="page-title"><div><h1>Price lists</h1><p class="muted"><?= number_format(count($lists)) ?> saved lists</p></div><?php if(can('edit')): ?><a class="button primary" href="<?= e(url('pricelist.php')) ?>">Create price list</a><?php endif ?></div>
<div class="card table-wrap"><table><thead><tr><th>Title</th><th>Status</th><th>Valid dates</th><th>Items</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach($lists as $list): ?>
<tr>
 <td><a href="<?= e(url('pricelist.php?id='.$list['id'])) ?>"><?= e($list['title']) ?></a></td>
 <td><span class="badge status-<?= e($list['status']) ?>"><?= e(ucfirst($list['status'])) ?></span></td>
 <td><?= e(($list['valid_from'] ?: 'Open') . ' to ' . ($list['valid_to'] ?: 'Open')) ?></td>
 <td><?= number_format((int)$list['item_count']) ?></td>
 <td><?= e(date('j M Y H:i', strtotime($list['updated_at']))) ?></td>
 <td><a href="<?= e(url('pricelist.php?id='.$list['id'])) ?>">Open</a></td>
</tr>
<?php endforeach ?>
</tbody></table></div>
<?php page_footer(); ?>
