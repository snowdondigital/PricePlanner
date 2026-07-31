<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();
$id = (int)($_GET['id'] ?? 0); $product = null; $errors = [];
if ($id) { $s=db()->prepare('SELECT * FROM products WHERE id=?');$s->execute([$id]);$product=$s->fetch();if(!$product){http_response_code(404);exit('Product not found.');} }
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verify_csrf(); require_permission('edit');
 $action=$_POST['action']??'save';
 if($action==='archive' && $id){db()->prepare('UPDATE products SET archived_at=NOW(),updated_by=? WHERE id=?')->execute([user()['id'],$id]);audit($id,'archive',$product,[]);flash('success','Product archived.');redirect('products.php');}
 if($action==='duplicate' && $id){$copy=$product;$copy['product_name'].=' (Copy)';$copy['sku']=null;$new=save_product(clean_product($copy));flash('success','Product duplicated.');redirect('product.php?id='.$new);}
 if(!$id && (string)($_POST['target_margin_changed'] ?? '0') !== '1') unset($_POST['target_margin']);
 $product=clean_product($_POST);$errors=validate_product($product);
 if(!$errors){$saved=save_product($product,$id?:null);flash('success','Product saved.');redirect('product.php?id='.$saved);}
}
$product = $product ?: clean_product([]);
$groups=db()->query('SELECT id,name,preferred_margin FROM product_groups ORDER BY name')->fetchAll();
$calc=calculate_pricing($product);
page_header($id?'Edit product':'Add product');
?>
<div class="page-title"><div><h1><?= $id?'Edit product':'Add product' ?></h1><p class="muted"><?= e($product['sku']??'Create a new pricing record') ?></p></div></div>
<?php foreach($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach ?>
<form method="post" id="product-form" data-vat="<?= e((string)setting('vat_rate',.2)) ?>" data-new-product="<?= $id ? '0' : '1' ?>"><?= csrf_field() ?><input type="hidden" name="target_margin_changed" value="0">
<div class="product-planner-grid">
<div class="planner-inputs">
<section class="card"><h2>Basic information</h2>
 <label>Product name<input name="product_name" value="<?= e($product['product_name']) ?>" required></label>
 <div class="fields"><label>Group<select name="group_id"><option value="">No group</option><?php foreach($groups as $g): ?><option value="<?= $g['id'] ?>" data-preferred-margin="<?= e($g['preferred_margin'] === null ? '' : (string)$g['preferred_margin']) ?>" <?= (int)$product['group_id']===(int)$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach ?></select></label><label>SKU<input name="sku" value="<?= e($product['sku']) ?>"></label><label>Product code<input name="product_code" value="<?= e($product['product_code']) ?>"></label><label class="check"><input type="checkbox" name="is_wholesale" value="1" <?= $product['is_wholesale']?'checked':'' ?>> Wholesale product</label></div>
</section>
<section class="card"><h2>Costs</h2><div class="fields"><label>Unit cost<input type="number" step="0.00001" min="0" name="unit_cost" value="<?= e($product['unit_cost']) ?>"></label><label>Labour cost<input type="number" step="0.00001" min="0" name="labour_cost" value="<?= e($product['labour_cost']) ?>"></label></div></section>
<section class="card"><h2>Pricing</h2><div class="fields"><label>Target margin %<input class="percent-input" type="number" step=".01" min="0" max="99.99" name="target_margin_pct" data-target="target_margin" value="<?= e((string)((float)$product['target_margin']*100)) ?>"><input type="hidden" name="target_margin" value="<?= e($product['target_margin']) ?>"></label><label>Retail price excl. VAT<input type="number" step=".0001" min="0" name="retail_price" data-suggested-price="preferred_sell_price" value="<?= e($product['retail_price']) ?>"><small class="field-hint" data-price-status="retail_price"></small></label><label>MSRP<input type="number" step=".0001" min="0" name="msrp" value="<?= e($product['msrp']) ?>"></label><label>Preferred override<input type="number" step=".0001" min="0" name="preferred_price_override" value="<?= e($product['preferred_price_override']) ?>"></label></div></section>
<section class="card"><h2>Trade pricing</h2><div class="fields"><label>Target trade discount %<input class="percent-input" type="number" step=".01" min="0" max="99.99" name="trade_discount_pct" data-target="trade_discount" value="<?= e((string)((float)$product['trade_discount']*100)) ?>"><input type="hidden" name="trade_discount" value="<?= e($product['trade_discount']) ?>"></label><label>Actual trade price<input type="number" step=".0001" min="0" name="trade_price" data-suggested-price="suggested_trade_price" value="<?= e($product['trade_price']) ?>"><small class="field-hint" data-price-status="trade_price"></small></label><label>Minimum margin %<input class="percent-input" type="number" step=".01" min="0" max="99.99" name="minimum_margin_pct" data-target="minimum_margin" value="<?= e((string)((float)$product['minimum_margin']*100)) ?>"><input type="hidden" name="minimum_margin" value="<?= e($product['minimum_margin']) ?>"></label></div></section>
<section class="card"><h2>Competitor pricing</h2><label>Average competitor price<input type="number" step=".0001" min="0" name="competitor_price" value="<?= e($product['competitor_price']) ?>"></label></section>
</div>
<aside class="planner-summary">
<section class="card results primary-results"><h2>Prices and margins</h2><p class="muted">Suggested prices fill the price fields automatically. Type in either field to override it.</p><dl><div><dt>Total cost</dt><dd data-result="total_cost"><?= e(money($calc['total_cost'])) ?></dd></div><div><dt>Preferred sell price</dt><dd data-result="preferred_sell_price"><?= e(money($calc['preferred_sell_price'])) ?></dd></div><div class="final-price"><dt>Retail price excl. VAT</dt><dd data-result="retail_price"><?= e(money($product['retail_price'] === null ? null : (float)$product['retail_price'])) ?></dd></div><div class="final-price"><dt>Retail price incl. VAT</dt><dd data-result="retail_price_inc_vat"><?= e(money($calc['retail_price_inc_vat'])) ?></dd></div><div><dt>Suggested trade price</dt><dd data-result="suggested_trade_price"><?= e(money($calc['suggested_trade_price'])) ?></dd></div><div class="final-price"><dt>Final trade price</dt><dd data-result="trade_price"><?= e(money($product['trade_price'] === null ? null : (float)$product['trade_price'])) ?></dd></div><div><dt>Actual trade discount</dt><dd data-result="actual_trade_discount"><?= e(percent($calc['actual_trade_discount'])) ?></dd></div><div><dt>Retail margin</dt><dd data-result="retail_margin"><?= e(percent($calc['retail_margin'])) ?></dd></div><div><dt>Trade margin</dt><dd data-result="trade_margin"><?= e(percent($calc['trade_margin'])) ?></dd></div><div><dt>Minimum price</dt><dd data-result="minimum_price"><?= e(money($calc['minimum_price'])) ?></dd></div></dl></section>
</aside>
</div>
<?php if(can('edit')): ?><div class="form-actions"><button class="button primary" name="action" value="save">Save product</button><?php if($id): ?><button class="button" name="action" value="duplicate">Duplicate</button><button class="button danger" name="action" value="archive" data-confirm="Archive this product?">Archive</button><?php endif ?></div><?php endif ?>
</form>
<?php page_footer(); ?>
