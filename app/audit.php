<?php
declare(strict_types=1);

function audit(int $productId, string $action, ?array $before, ?array $after): void
{
    $keys = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));
    $stmt = db()->prepare('INSERT INTO audit_log (user_id, product_id, action, field_name, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($keys as $key) {
        $old = $before[$key] ?? null;
        $new = $after[$key] ?? null;
        if ((string)$old === (string)$new) continue;
        $stmt->execute([user()['id'], $productId ?: null, $action, $key, $old, $new, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
