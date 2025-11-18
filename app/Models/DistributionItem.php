<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DistributionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'distribution_id',
        'product_id',
        'load_qty',
        'return_qty',
        'bo_qty',
    ];

    protected $casts = [
        'distribution_id' => 'integer',
        'product_id'      => 'integer',
        'load_qty'        => 'integer',
        'return_qty'      => 'integer',
        'bo_qty'          => 'integer',
    ];

    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
