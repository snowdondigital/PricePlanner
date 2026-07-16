<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_permission('pricelist_export');
$id = (int)($_GET['id'] ?? 0);
$format = (string)($_GET['format'] ?? 'csv');
$list = fetch_price_list($id);
if (!$list) { http_response_code(404); exit('Price list not found.'); }
$items = fetch_price_list_items($id);
$columns = selected_price_list_columns($list['columns_json'] ?? null);
$labels = price_list_columns();

if ($format === 'pdf') {
    $lines = [$list['title'], 'Status: ' . ucfirst($list['status']) . ' | Valid: ' . ($list['valid_from'] ?: 'Open') . ' to ' . ($list['valid_to'] ?: 'Open'), ''];
    $lines[] = implode(' | ', array_map(fn($key) => $labels[$key], $columns));
    foreach ($items as $item) {
        $line = price_list_line($item, $list, $item['custom_discount'] === null ? null : (float)$item['custom_discount']);
        $lines[] = implode(' | ', array_map(fn($key) => format_price_list_value($key, $line[$key] ?? null), $columns));
    }
    output_simple_pdf($list['title'], $lines);
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="price-list-' . $id . '-' . date('Y-m-d') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'wb');
fputcsv($out, array_map(fn($key) => $labels[$key], $columns));
foreach ($items as $item) {
    $line = price_list_line($item, $list, $item['custom_discount'] === null ? null : (float)$item['custom_discount']);
    fputcsv($out, array_map(fn($key) => format_price_list_value($key, $line[$key] ?? null), $columns));
}
fclose($out);
exit;

function output_simple_pdf(string $title, array $lines): never
{
    $pages = array_chunk($lines, 34);
    $objects = [];
    $catalogId = 1;
    $pagesId = 2;
    $fontId = 3;
    $nextId = 4;
    $pageIds = [];
    foreach ($pages as $pageLines) {
        $content = "BT\n/F1 9 Tf\n40 555 Td\n14 TL\n";
        foreach ($pageLines as $line) {
            $content .= '(' . pdf_escape(substr($line, 0, 150)) . ") Tj\nT*\n";
        }
        $content .= "ET\n";
        $contentId = $nextId++;
        $pageId = $nextId++;
        $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n$content" . "endstream";
        $objects[$pageId] = "<< /Type /Page /Parent $pagesId 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 $fontId 0 R >> >> /Contents $contentId 0 R >>";
        $pageIds[] = $pageId;
    }
    $objects[$catalogId] = "<< /Type /Catalog /Pages $pagesId 0 R >>";
    $objects[$pagesId] = "<< /Type /Pages /Kids [" . implode(' ', array_map(fn($id) => "$id 0 R", $pageIds)) . "] /Count " . count($pageIds) . " >>";
    $objects[$fontId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= max(array_keys($objects)); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root $catalogId 0 R /Title (" . pdf_escape($title) . ") >>\nstartxref\n$xref\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="price-list-' . date('Y-m-d') . '.pdf"');
    echo $pdf;
    exit;
}

function pdf_escape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}
