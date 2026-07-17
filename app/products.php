<?php
declare(strict_types=1);

const PRODUCT_FIELDS = [
    'group_id', 'sku', 'product_name', 'product_code', 'unit_cost', 'labour_cost',
    'target_margin', 'preferred_price_override', 'msrp', 'competitor_price',
    'retail_price', 'trade_discount', 'trade_price', 'minimum_margin', 'is_wholesale',
];

function product_group_preferred_margin(?int $groupId): ?float
{
    if (!$groupId) return null;
    $stmt = db()->prepare('SELECT preferred_margin FROM product_groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null || $value === '' ? null : (float)$value;
}

function clean_product(array $source): array
{
    $text = ['sku', 'product_name', 'product_code'];
    $numbers = ['unit_cost', 'labour_cost', 'target_margin', 'preferred_price_override', 'msrp',
        'competitor_price', 'retail_price', 'trade_discount', 'trade_price', 'minimum_margin'];
    $out = [];
    $out['group_id'] = !empty($source['group_id']) ? (int)$source['group_id'] : null;
    foreach ($text as $key) $out[$key] = trim((string)($source[$key] ?? '')) ?: null;
    foreach ($numbers as $key) {
        $v = trim((string)($source[$key] ?? ''));
        $out[$key] = $v === '' ? null : (is_numeric($v) ? (float)$v : null);
    }
    $out['labour_cost'] ??= 0.0;
    $out['target_margin'] ??= product_group_preferred_margin($out['group_id']) ?? (float)setting('target_margin', 0.80);
    $out['trade_discount'] ??= (float)setting('trade_discount', 0.40);
    $out['minimum_margin'] ??= (float)setting('minimum_margin', 0.20);
    $out['is_wholesale'] = !empty($source['is_wholesale']) ? 1 : 0;
    return $out;
}

function validate_product(array $p): array
{
    $errors = [];
    if (!$p['product_name']) $errors[] = 'Product name is required.';
    foreach (['target_margin', 'trade_discount', 'minimum_margin'] as $f) {
        if ($p[$f] !== null && ($p[$f] < 0 || $p[$f] >= 1)) $errors[] = ucwords(str_replace('_', ' ', $f)) . ' must be at least 0 and below 100%.';
    }
    foreach (['unit_cost', 'labour_cost', 'retail_price', 'trade_price'] as $f) {
        if ($p[$f] !== null && $p[$f] < 0) $errors[] = ucwords(str_replace('_', ' ', $f)) . ' cannot be negative.';
    }
    return $errors;
}

function save_product(array $p, ?int $id = null): int
{
    $pdo = db();
    if ($id) {
        $beforeStmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $beforeStmt->execute([$id]);
        $before = $beforeStmt->fetch();
        $sets = implode(', ', array_map(fn($f) => "$f = :$f", PRODUCT_FIELDS));
        $stmt = $pdo->prepare("UPDATE products SET $sets, updated_by = :updated_by WHERE id = :id");
        $stmt->execute($p + ['updated_by' => user()['id'], 'id' => $id]);
        audit($id, 'update', $before, array_replace($before ?: [], $p));
        return $id;
    }
    $fields = PRODUCT_FIELDS;
    $sql = 'INSERT INTO products (' . implode(',', $fields) . ',created_by,updated_by) VALUES (:' .
        implode(',:', $fields) . ',:created_by,:updated_by)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p + ['created_by' => user()['id'], 'updated_by' => user()['id']]);
    $id = (int)$pdo->lastInsertId();
    audit($id, 'create', [], $p);
    return $id;
}
