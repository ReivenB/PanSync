<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Distribution extends Model
{
    /** Locations used in the form */
    public const LOCATIONS = [
        'Pasig','Meycauayan','Novaliches','Paco/Blumentritt','Sto. Niño',
        'Malabon','Pajo/Polo','Commonwealth','Balintawak','Other',
    ];

    protected $fillable = [
        'load_date', 'dispatch_date', 'location', 'status', 'created_by',
    ];

    protected $casts = [
        'load_date'     => 'date',
        'dispatch_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DistributionItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Sum(load_qty ÷ product.yield_per_sack), rounded to int (per UI) */
    public function loadYield(): int
    {
        $this->loadMissing('items.product');
        $sum = 0.0;
        foreach ($this->items as $it) {
            $yield = max(1, (int) ($it->product->yield_per_sack ?? 1));
            $sum += ((int) $it->load_qty) / $yield;
        }
        return (int) round($sum, 0);
    }

    /** Sum(return_qty ÷ product.yield_per_sack) */
    public function returnYield(): int
    {
        $this->loadMissing('items.product');
        $sum = 0.0;
        foreach ($this->items as $it) {
            $yield = max(1, (int) ($it->product->yield_per_sack ?? 1));
            $sum += ((int) $it->return_qty) / $yield;
        }
        return (int) round($sum, 0);
    }
}
