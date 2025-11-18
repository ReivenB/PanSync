<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Procurement extends Model
{
    public $timestamps = false; // created_at only per schema

    protected $fillable = ['material_id', 'qty', 'note', 'user_id', 'created_at'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'material_id');
    }
}
