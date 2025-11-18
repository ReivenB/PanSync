<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'yield_per_sack', 'stock_pcs'];

    public function productionItems()
    {
        return $this->hasMany(ProductionItem::class);
    }
}
