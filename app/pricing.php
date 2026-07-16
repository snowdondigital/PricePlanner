<?php
declare(strict_types=1);

/**
 * Faithful equivalents of workbook columns G, I, M, O, Q, R, S and U.
 * Null represents Excel's empty-string result. Inputs and outputs are ex VAT
 * except retail_price_inc_vat.
 */
function calculate_pricing(array $p, ?float $vatRate = null): array
{
    $vatRate ??= (float)setting('vat_rate', 0.20);
    $n = static fn(string $key): ?float =>
        isset($p[$key]) && $p[$key] !== '' && is_numeric($p[$key]) ? (float)$p[$key] : null;

    $unit = $n('unit_cost');
    $labour = $n('labour_cost');
    $cost = ($unit === null || $labour === null) ? null : $unit + $labour;
    $target = $n('target_margin');
    $retail = $n('retail_price');
    $tradeDiscount = $n('trade_discount');
    $trade = $n('trade_price');
    $minimumMargin = $n('minimum_margin');

    return [
        'total_cost' => $cost,
        'preferred_sell_price' => ($cost === null || $target === null || $target >= 1) ? null : $cost / (1 - $target),
        'retail_price_inc_vat' => $retail === null ? null : $retail * (1 + $vatRate),
        'suggested_trade_price' => ($retail === null || $tradeDiscount === null) ? null : $retail * (1 - $tradeDiscount),
        'actual_trade_discount' => ($retail === null || $trade === null || $retail == 0.0) ? null : 1 - ($trade / $retail),
        'retail_margin' => ($retail === null || $cost === null || $retail == 0.0) ? null : ($retail - $cost) / $retail,
        'trade_margin' => ($trade === null || $cost === null || $trade == 0.0) ? null : ($trade - $cost) / $trade,
        'minimum_price' => ($cost === null || $minimumMargin === null || $minimumMargin >= 1) ? null : $cost / (1 - $minimumMargin),
    ];
}
