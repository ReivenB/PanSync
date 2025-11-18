<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

final class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'SSS',  'name' => 'SSS',  'yield_per_sack' => 29],
            ['code' => '900',  'name' => '900',  'yield_per_sack' => 31],
            ['code' => '800',  'name' => '800',  'yield_per_sack' => 35],
            ['code' => 'SS',   'name' => 'SS',   'yield_per_sack' => 40],
            ['code' => 'S',    'name' => 'S',    'yield_per_sack' => 46],
            ['code' => '750',  'name' => '750',  'yield_per_sack' => 50],
            ['code' => 'HALF', 'name' => 'HALF', 'yield_per_sack' => 58],
            ['code' => '1/2',  'name' => '1/2',  'yield_per_sack' => 78],
            ['code' => 'BIG',  'name' => 'BIG',  'yield_per_sack' => 80],
            ['code' => 'X12',  'name' => 'X12',  'yield_per_sack' => 64],
            ['code' => 'X6',   'name' => 'X6',   'yield_per_sack' => 32],
            ['code' => 'X5',   'name' => 'X5',   'yield_per_sack' => 18],
        ];

        foreach ($rows as $row) {
            Product::updateOrCreate(
                ['code' => $row['code']],
                $row + ['stock_pcs' => 0]
            );
        }
    }
}
