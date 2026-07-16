<?php
declare(strict_types=1);

function price_list_columns(): array
{
    return [
        'group_name' => 'Group',
        'sku' => 'SKU',
        'product_name' => 'Product',
        'product_code' => 'Code',
        'retail_price' => 'Retail ex VAT',
        'discount' => 'Discount',
        'final_price' => 'Final ex VAT',
        'final_price_inc_vat' => 'Final inc VAT',
        'total_cost' => 'Cost',
        'margin' => 'Margin',
        'trade_price' => 'Trade price',
        'minimum_price' => 'Minimum price',
        'competitor_price' => 'Competitor',
    ];
}

function default_price_list_columns(): array
{
    return ['sku', 'product_name', 'retail_price', 'discount', 'final_price', 'margin'];
}

function selected_price_list_columns(?string $json): array
{
    $valid = array_keys(price_list_columns());
    $decoded = json_decode((string)$json, true);
    $selected = is_array($decoded) ? array_values(array_intersect($decoded, $valid)) : [];
    return $selected ?: default_price_list_columns();
}

function clean_price_list(array $source): array
{
    $status = in_array(($source['status'] ?? 'draft'), ['draft', 'internal', 'live'], true) ? $source['status'] : 'draft';
    $enabled = !empty($source['custom_pricing_enabled']) ? 1 : 0;
    $global = $source['global_discount'] ?? null;
    $columns = $source['columns'] ?? default_price_list_columns();
    if (!is_array($columns)) $columns = default_price_list_columns();
    $columns = array_values(array_intersect($columns, array_keys(price_list_columns()))) ?: default_price_list_columns();

    return [
        'title' => trim((string)($source['title'] ?? '')),
        'valid_from' => ($source['valid_from'] ?? '') !== '' ? (string)$source['valid_from'] : null,
        'valid_to' => ($source['valid_to'] ?? '') !== '' ? (string)$source['valid_to'] : null,
        'status' => $status,
        'custom_pricing_enabled' => $enabled,
        'global_discount' => $enabled && $global !== '' && is_numeric($global) ? max(0.0, min(0.9999, (float)$global)) : null,
        'columns_json' => json_encode($columns),
    ];
}

function validate_price_list(array $list, array $productIds): array
{
    $errors = [];
    if ($list['title'] === '') $errors[] = 'Title is required.';
    if ($list['valid_from'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $list['valid_from'])) $errors[] = 'Valid from must be a valid date.';
    if ($list['valid_to'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $list['valid_to'])) $errors[] = 'Valid to must be a valid date.';
    if ($list['valid_from'] && $list['valid_to'] && $list['valid_to'] < $list['valid_from']) $errors[] = 'Valid to must be after valid from.';
    if (!$productIds) $errors[] = 'Select at least one product.';
    return $errors;
}

function save_price_list(array $list, array $items, ?int $id = null): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE price_lists SET title=:title, valid_from=:valid_from, valid_to=:valid_to, status=:status, custom_pricing_enabled=:custom_pricing_enabled, global_discount=:global_discount, columns_json=:columns_json, updated_by=:updated_by WHERE id=:id');
            $list['updated_by'] = user()['id'];
            $list['id'] = $id;
            $stmt->execute($list);
            $pdo->prepare('DELETE FROM price_list_items WHERE price_list_id = ?')->execute([$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO price_lists(title, valid_from, valid_to, status, custom_pricing_enabled, global_discount, columns_json, created_by, updated_by) VALUES(:title, :valid_from, :valid_to, :status, :custom_pricing_enabled, :global_discount, :columns_json, :created_by, :updated_by)');
            $list['created_by'] = user()['id'];
            $list['updated_by'] = user()['id'];
            $stmt->execute($list);
            $id = (int)$pdo->lastInsertId();
        }

        $line = $pdo->prepare('INSERT INTO price_list_items(price_list_id, product_id, custom_discount, sort_order) VALUES(?, ?, ?, ?)');
        $order = 1;
        foreach ($items as $item) {
            $line->execute([$id, $item['product_id'], $item['custom_discount'], $order++]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function fetch_price_list(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM price_lists WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function fetch_price_list_items(int $id): array
{
    $stmt = db()->prepare('SELECT pli.*,p.*,g.name group_name FROM price_list_items pli JOIN products p ON p.id=pli.product_id LEFT JOIN product_groups g ON g.id=p.group_id WHERE pli.price_list_id=? ORDER BY pli.sort_order, p.product_name');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function price_list_line(array $product, array $list, ?float $lineDiscount = null): array
{
    $calc = calculate_pricing($product);
    $retail = isset($product['retail_price']) && $product['retail_price'] !== '' ? (float)$product['retail_price'] : null;
    $discount = !empty($list['custom_pricing_enabled']) ? ($lineDiscount ?? ($list['global_discount'] !== null ? (float)$list['global_discount'] : 0.0)) : null;
    $final = $retail === null ? null : $retail * (1 - ($discount ?? 0.0));
    $cost = $calc['total_cost'];
    $vat = (float)setting('vat_rate', 0.20);

    return [
        'group_name' => $product['group_name'] ?? '',
        'sku' => $product['sku'] ?? '',
        'product_name' => $product['product_name'] ?? '',
        'product_code' => $product['product_code'] ?? '',
        'retail_price' => $retail,
        'discount' => $discount,
        'final_price' => $final,
        'final_price_inc_vat' => $final === null ? null : $final * (1 + $vat),
        'total_cost' => $cost,
        'margin' => ($final === null || $cost === null || $final == 0.0) ? null : ($final - $cost) / $final,
        'trade_price' => isset($product['trade_price']) && $product['trade_price'] !== '' ? (float)$product['trade_price'] : null,
        'minimum_price' => $calc['minimum_price'],
        'competitor_price' => isset($product['competitor_price']) && $product['competitor_price'] !== '' ? (float)$product['competitor_price'] : null,
    ];
}

function format_price_list_value(string $key, mixed $value): string
{
    if (in_array($key, ['retail_price', 'final_price', 'final_price_inc_vat', 'total_cost', 'trade_price', 'minimum_price', 'competitor_price'], true)) {
        return money($value === null ? null : (float)$value);
    }
    if (in_array($key, ['discount', 'margin'], true)) {
        return percent($value === null ? null : (float)$value);
    }
    return (string)($value ?? '');
}
