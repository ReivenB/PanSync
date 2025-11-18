<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RawMaterial extends Model
{
    public const UNIT_FLOUR = 'sack'; // EXACT enum value in DB
    public const UNIT_OIL   = '20L';

    protected $fillable = ['name', 'quantity', 'unit'];

    // (Optional) Centralize the auto-unit logic so controllers can't forget:
    protected static function booted(): void
    {
        static::saving(function (RawMaterial $m) {
            $m->unit = strcasecmp($m->name, 'Flour') === 0
                ? self::UNIT_FLOUR
                : self::UNIT_OIL;
        });
    }

    // (Optional) Nice label for views
    public function getUnitLabelAttribute(): string
    {
        return $this->unit === self::UNIT_FLOUR ? 'Sacks' : $this->unit;
    }
}
