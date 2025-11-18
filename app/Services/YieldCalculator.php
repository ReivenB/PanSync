<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Yield calculations used across Production & Distribution.
 *
 * - Expected Flour Yield (sacks): Σ (produced_qty / product_yield)
 * - Load Yield (sacks):          Σ (load_qty     / product_yield)
 * - Return Yield (sacks):        Σ (return_qty   / product_yield)
 *
 * Input flexibility:
 *   Each $items element may be:
 *     - an array like ['product_yield' => 40, 'produced_qty' => 120]
 *     - an array with nested product: ['product' => Product{yield_per_sack=40}, 'load_qty' => 120]
 *     - a model that implements Arrayable or has array-accessible attributes with the same keys
 */
final class YieldCalculator
{
    /**
     * Expected flour yield (sacks) for a production batch.
     * Expects per-item qty key: produced_qty
     */
    public static function expectedFlourYield(Collection $items): float
    {
        return self::compute($items, qtyKey: 'produced_qty');
    }

    /**
     * Load yield (sacks) for a distribution.
     * Expects per-item qty key: load_qty
     */
    public static function loadYield(Collection $items): float
    {
        return self::compute($items, qtyKey: 'load_qty');
    }

    /**
     * Return yield (sacks) for a distribution.
     * Expects per-item qty key: return_qty
     */
    public static function returnYield(Collection $items): float
    {
        return self::compute($items, qtyKey: 'return_qty');
    }

    /**
     * Core calculator.
     *
     * Each row can carry yield in one of these shapes:
     *   - ['product_yield' => int, ...]
     *   - ['product' => Product(yield_per_sack=int), ...]
     */
    private static function compute(Collection $items, string $qtyKey): float
    {
        $sum = $items->reduce(function (float $carry, $row) use ($qtyKey): float {
            $data = self::rowToArray($row);

            $qty = (int) ($data[$qtyKey] ?? 0);
            $yieldPerSack = self::extractYield($data);

            if ($yieldPerSack <= 0) {
                return $carry; // ignore invalid yield rows
            }

            return $carry + ($qty / $yieldPerSack);
        }, 0.0);

        return round($sum, 1);
    }

    /**
     * Try to read the yield from supported shapes.
     */
    private static function extractYield(array $row): int
    {
        // Preferred explicit key
        if (isset($row['product_yield'])) {
            return max(0, (int) $row['product_yield']);
        }

        // Nested product relation/model
        if (isset($row['product'])) {
            $product = $row['product'];

            // Object with property
            if (is_object($product) && isset($product->yield_per_sack)) {
                return max(0, (int) $product->yield_per_sack);
            }

            // Arrayable / array shape
            if ($product instanceof Arrayable) {
                $arr = $product->toArray();
                return max(0, (int) ($arr['yield_per_sack'] ?? 0));
            }

            if (is_array($product)) {
                return max(0, (int) ($product['yield_per_sack'] ?? 0));
            }
        }

        // Fallback if caller provided a generic 'yield_per_sack' at top-level
        if (isset($row['yield_per_sack'])) {
            return max(0, (int) $row['yield_per_sack']);
        }

        return 0;
    }

    /**
     * Normalize a row (model/Arrayable/array) to array for safe access.
     *
     * @param  mixed  $row
     */
    private static function rowToArray(mixed $row): array
    {
        if ($row instanceof Arrayable) {
            return $row->toArray();
        }

        if (is_object($row)) {
            // Convert public properties only; models are Arrayable so above branch usually applies
            return get_object_vars($row);
        }

        return (array) $row;
    }
}
