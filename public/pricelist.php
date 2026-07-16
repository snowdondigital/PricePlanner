<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_permission('pricelists');
$id = (int)($_GET['id'] ?? 0);
$list = $id ? fetch_price_list($id) : null;
if ($id && !$list) { http_response_code(404); exit('Price list not found.'); }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_permission('edit');
    $list = clean_price_list($_POST);
    $productIds = array_values(array_unique(array_map('intval', $_POST['product_ids'] ?? [])));
    $discountPcts = $_POST['custom_discount_pct'] ?? [];
    $items = [];
    foreach ($productIds as $productId) {
        if ($productId <= 0) continue;
        $raw = $discountPcts[$productId] ?? '';
        $items[] = [
            'product_id' => $productId,
            'custom_discount' => $list['custom_pricing_enabled'] && $raw !== '' && is_numeric($raw) ? max(0.0, min(0.9999, (float)$raw / 100)) : null,
        ];
    }
    $errors = validate_price_list($list, $productIds);
    if (!$errors) {
        $saved = save_price_list($list, $items, $id ?: null);
        flash('success', 'Price list saved.');
        redirect('pricelist.php?id=' . $saved);
    }
} elseif (!$list) {
    $list = clean_price_list(['status' => 'draft']);
}

$selectedItems = $id ? fetch_price_list_items($id) : [];
$selected = [];
foreach ($selectedItems as $item) $selected[(int)$item['product_id']] = $item;
$products = db()->query('SELECT p.*,g.name group_name FROM products p LEFT JOIN product_groups g ON g.id=p.group_id WHERE p.archived_at IS NULL ORDER BY g.name,p.product_name')->fetchAll();
$columns = price_list_columns();
$selectedColumns = selected_price_list_columns($list['columns_json'] ?? null);
page_header($id ? 'Edit price list' : 'Create price list');
?>
<div class="page-title"><div><h1><?= $id ? 'Edit price list' : 'Create price list' ?></h1><p class="muted"><?= e($list['title'] ?: 'Quick customer, salesman, range or meeting list') ?></p></div></div>
<?php foreach($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach ?>
<form method="post" id="price-list-form" data-vat="<?= e((string)setting('vat_rate', .2)) ?>">
<?= csrf_field() ?>
<section class="card">
 <h2>List details</h2>
 <div class="fields">
  <label>Title<input name="title" value="<?= e($list['title']) ?>" required></label>
  <label>Status<select name="status"><?php foreach(['draft'=>'Draft','internal'=>'Internal','live'=>'Live'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $list['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
  <label>Valid from<input type="date" name="valid_from" value="<?= e($list['valid_from']) ?>"></label>
  <label>Valid to<input type="date" name="valid_to" value="<?= e($list['valid_to']) ?>"></label>
 </div>
</section>
<section class="card">
 <h2>Custom pricing</h2>
 <label class="check"><input type="checkbox" name="custom_pricing_enabled" value="1" data-toggle-custom-pricing <?= !empty($list['custom_pricing_enabled'])?'checked':'' ?>> Enable custom pricing</label>
 <div class="custom-pricing-panel">
  <label>Global discount %<input class="percent-input" type="number" step=".01" min="0" max="99.99" name="global_discount_pct" data-target="global_discount" value="<?= e($list['global_discount'] === null ? '' : (string)((float)$list['global_discount'] * 100)) ?>"><input type="hidden" name="global_discount" value="<?= e($list['global_discount']) ?>"></label>
 </div>
</section>
<section class="card">
 <h2>Columns</h2>
 <div class="columns-grid"><?php foreach($columns as $key=>$label): ?><label class="check"><input type="checkbox" name="columns[]" value="<?= e($key) ?>" <?= in_array($key,$selectedColumns,true)?'checked':'' ?>> <?= e($label) ?></label><?php endforeach ?></div>
</section>
<section class="card table-wrap product-picker">
 <div class="card-head"><h2>Products</h2><div class="product-picker-actions"><input type="search" placeholder="Filter products" data-product-filter><button class="button" type="button" data-select-products>Select all</button></div></div>
 <table><thead><tr><th>Add</th><th>Group</th><th>SKU</th><th>Product</th><th>Retail ex VAT</th><th>Discount override %</th><th>Final ex VAT</th><th>Margin</th></tr></thead><tbody>
 <?php foreach($products as $p): $isSelected=isset($selected[(int)$p['id']]); $lineDiscount=$isSelected ? $selected[(int)$p['id']]['custom_discount'] : null; $line=price_list_line($p,$list,$lineDiscount === null ? null : (float)$lineDiscount); ?>
  <tr data-price-list-row data-filter-text="<?= e(strtolower(trim(($p['group_name'] ?? '') . ' ' . ($p['sku'] ?? '') . ' ' . $p['product_name'] . ' ' . ($p['product_code'] ?? '')))) ?>" data-retail="<?= e($p['retail_price'] ?? '') ?>" data-cost="<?= e((string)($line['total_cost'] ?? '')) ?>">
   <td><input type="checkbox" name="product_ids[]" value="<?= e($p['id']) ?>" <?= $isSelected?'checked':'' ?>></td>
   <td><?= e($p['group_name'] ?: '') ?></td>
   <td><?= e($p['sku'] ?: '') ?></td>
   <td><?= e($p['product_name']) ?></td>
   <td><?= e(money($p['retail_price'] === null ? null : (float)$p['retail_price'])) ?></td>
   <td><input class="line-discount" type="number" step=".01" min="0" max="99.99" name="custom_discount_pct[<?= e($p['id']) ?>]" value="<?= e($lineDiscount === null ? '' : (string)((float)$lineDiscount * 100)) ?>"></td>
   <td data-final-price><?= e(money($line['final_price'])) ?></td>
   <td data-final-margin><?= e(percent($line['margin'])) ?></td>
  </tr>
 <?php endforeach ?>
 </tbody></table>
</section>
<?php if($id): ?>
<section class="card table-wrap">
 <div class="card-head"><h2>Preview</h2><?php if(can('pricelist_export')): ?><div><a class="button" href="<?= e(url('pricelist_export.php?id='.$id.'&format=csv')) ?>">CSV</a> <a class="button" href="<?= e(url('pricelist_export.php?id='.$id.'&format=pdf')) ?>">PDF</a></div><?php endif ?></div>
 <table><thead><tr><?php foreach($selectedColumns as $key): ?><th><?= e($columns[$key]) ?></th><?php endforeach ?></tr></thead><tbody>
 <?php foreach($selectedItems as $item): $line=price_list_line($item,$list,$item['custom_discount'] === null ? null : (float)$item['custom_discount']); ?><tr><?php foreach($selectedColumns as $key): ?><td><?= e(format_price_list_value($key,$line[$key] ?? null)) ?></td><?php endforeach ?></tr><?php endforeach ?>
 </tbody></table>
</section>
<?php endif ?>
<?php if(can('edit')): ?><div class="form-actions"><button class="button primary">Save price list</button><a class="button" href="<?= e(url('pricelists.php')) ?>">Back</a></div><?php endif ?>
</form>
<?php page_footer(); ?>
