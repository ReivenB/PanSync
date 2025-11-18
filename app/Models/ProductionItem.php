<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ProductionItem extends Model
{
    use HasFactory;

// App\Models\ProductionItem
protected $fillable = ['production_batch_id', 'product_id', 'produced_qty'];


    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
