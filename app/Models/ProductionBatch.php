<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductionBatch extends Model
{
    use HasFactory;

    /** Allowed batch sets. */
    public const SETS = ['A', 'B', 'C', 'D', 'E'];

    protected $fillable = [
        'set',
        'date',
        'expected_yield',
        'actual_flour_used',
        'oil_used',
        'created_by',
    ];

    protected $casts = [
        'date'               => 'date',
        // Laravel's decimal cast returns a string; we cast to float where needed.
        'expected_yield'     => 'decimal:1',
        'actual_flour_used'  => 'decimal:1',
        'oil_used'           => 'decimal:1',
    ];

    /* -------------------------
       Relationships
    -------------------------- */

    public function items(): HasMany
    {
        return $this->hasMany(ProductionItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* -------------------------
       Query scopes
    -------------------------- */

    public function scopeBetweenDates($query, $start, $end)
    {
        // $start/$end can be Carbon|string; normalize to YYYY-MM-DD
        $s = is_string($start) ? $start : $start->toDateString();
        $e = is_string($end)   ? $end   : $end->toDateString();
        return $query->whereBetween('date', [$s, $e]);
    }

    public function scopeForSets($query, array $sets)
    {
        return $query->whereIn('set', $sets);
    }

    /* -------------------------
       Business logic helpers
    -------------------------- */

    /**
     * Recompute the expected flour yield from the current items using:
     *   Σ (produced_qty ÷ product.yield_per_sack)
     * Rounds to 1 decimal place to match DB/casts.
     */
    public function recomputeExpectedYield(): float
    {
        $this->loadMissing('items.product');

        $sum = 0.0;
        foreach ($this->items as $row) {
            $qty   = (int) ($row->produced_qty ?? 0);
            $yield = max(1, (int) ($row->product->yield_per_sack ?? 1));
            $sum  += $qty / $yield;
        }

        return round($sum, 1);
    }

    /**
     * Convenience: numeric variance (actual - expected).
     */
    public function getVarianceAttribute(): float
    {
        return (float) $this->actual_flour_used - (float) $this->expected_yield;
    }

    /**
     * Same color logic you had before (red/green/neutral).
     */
    public function varianceColor(): string
    {
        $expected = (float) $this->expected_yield;
        $actual   = (float) $this->actual_flour_used;

        if ($actual <= ($expected - 0.5)) {
            return 'red';
        }
        if ($actual >= ($expected + 1.0)) {
            return 'green';
        }
        return 'neutral';
    }
}
