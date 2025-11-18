<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

final class InventoryService
{
    /** Lock+fetch material by name (lowercase compare). */
    private function lockedMaterial(string $name): ?RawMaterial
    {
        return RawMaterial::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->lockForUpdate()
            ->first();
    }

    /**
     * Apply a +/- delta to a raw material.
     * - If delta < 0, ensure enough stock or throw InsufficientStockException.
     * - If record missing and delta < 0 -> throw (you can’t consume what you don’t have).
     * - If record missing and delta > 0 -> create then add.
     */
    private function applyMaterial(string $name, float $delta): void
    {
        $rm = $this->lockedMaterial($name);

        if (!$rm) {
            if ($delta < 0) {
                throw new InsufficientStockException(ucfirst($name), 0.0, abs($delta));
            }
            // create for positive adjustments
            $unit = (mb_strtolower($name) === 'flour') ? RawMaterial::UNIT_FLOUR : RawMaterial::UNIT_OIL;
            $rm = RawMaterial::create([
                'name'     => ucfirst($name),
                'quantity' => 0,
                'unit'     => $unit,
            ])->refresh();

            // lock the newly created row
            $rm = RawMaterial::whereKey($rm->id)->lockForUpdate()->first();
        }

        $newQty = round(((float)$rm->quantity) + $delta, 1);
        if ($newQty < 0) {
            // not enough stock
            $available = round((float)$rm->quantity, 1);
            $required  = round(abs($delta), 1);
            throw new InsufficientStockException($rm->name, $available, $required);
        }

        $rm->update(['quantity' => $newQty]);
    }

    /** +/- pieces on finished goods, clamped to >= 0. */
    private function applyFinishedGoods(int $productId, int $deltaPieces): void
    {
        $p = Product::lockForUpdate()->find($productId);
        if (!$p) return;
        $new = max(0, (int)$p->stock_pcs + (int)$deltaPieces);
        $p->update(['stock_pcs' => $new]);
    }

    /** Create: deduct raw materials, then add finished goods. */
    public function onProductionCreated(ProductionBatch $batch): void
    {
        $batch->loadMissing('items');

        DB::transaction(function () use ($batch) {
            // 1) Deduct materials (may throw if insufficient)
            $this->applyMaterial('flour', -round((float)$batch->actual_flour_used, 1));
            $this->applyMaterial('oil',   -round((float)$batch->oil_used, 1));

            // 2) Add finished goods
            foreach ($batch->items as $item) {
                $this->applyFinishedGoods((int)$item->product_id, +(int)$item->produced_qty);
            }
        });
    }

    /** Update: apply deltas. */
    public function onProductionUpdated(ProductionBatch $batch, array $old): void
    {
        $batch->loadMissing('items');

        DB::transaction(function () use ($batch, $old) {
            // materials delta (new - old). Positive delta => need more -> deduct (negative applyMaterial).
            $flourDelta = round((float)$batch->actual_flour_used - (float)($old['flour'] ?? 0.0), 1);
            $oilDelta   = round((float)$batch->oil_used          - (float)($old['oil'] ?? 0.0), 1);

            if ($flourDelta !== 0.0) $this->applyMaterial('flour', -$flourDelta);
            if ($oilDelta   !== 0.0) $this->applyMaterial('oil',   -$oilDelta);

            // finished goods delta by product
            $newMap = [];
            foreach ($batch->items as $row) {
                $pid = (int)$row->product_id;
                $newMap[$pid] = ($newMap[$pid] ?? 0) + (int)$row->produced_qty;
            }
            $oldMap = $old['items'] ?? [];
            $allIds = array_unique(array_merge(array_keys($newMap), array_keys($oldMap)));

            foreach ($allIds as $pid) {
                $delta = (int)($newMap[$pid] ?? 0) - (int)($oldMap[$pid] ?? 0);
                if ($delta !== 0) $this->applyFinishedGoods($pid, $delta);
            }
        });
    }

    /** Delete: restore materials, remove finished goods. */
    public function onProductionDeleted(ProductionBatch $batch): void
    {
        $batch->loadMissing('items');

        DB::transaction(function () use ($batch) {
            // restore materials (can’t fail)
            $this->applyMaterial('flour', +round((float)$batch->actual_flour_used, 1));
            $this->applyMaterial('oil',   +round((float)$batch->oil_used, 1));

            // remove finished goods
            foreach ($batch->items as $item) {
                $this->applyFinishedGoods((int)$item->product_id, -(int)$item->produced_qty);
            }
        });
    }
}
