<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Distribution;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DistributionController extends Controller
{
    /** List orders */
    public function index()
    {
        $orders = Distribution::with('items.product')
            ->latest('load_date')
            ->paginate(10);

        return view('distribution.index', compact('orders'));
    }

    /** Show create form */
    public function create()
    {
        return view('distribution.form', [
            'mode'      => 'create',
            'products'  => Product::orderBy('code')->get(),
            'locations' => Distribution::LOCATIONS,
        ]);
    }

    /** Store new order (deduct loads from inventory) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'load_date'                 => ['required', 'date'],
            'dispatch_date'             => ['nullable', 'date'],
            'location'                  => ['required', 'string', 'max:100'],
            'items'                     => ['array'],
            'items.*.product_id'        => ['required', 'integer', 'exists:products,id'],
            'items.*.load_qty'          => ['nullable', 'integer', 'min:0'],
        ]);

        $items = $data['items'] ?? [];

        // Aggregate loads per product (pcs)
        $loads = [];
        foreach ($items as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['load_qty']   ?? 0);
            if ($pid && $qty > 0) {
                $loads[$pid] = ($loads[$pid] ?? 0) + $qty;
            }
        }

        DB::transaction(function () use ($data, $items, $loads) {
            /** @var \App\Models\Distribution $order */
            $order = Distribution::create([
                'load_date'     => $data['load_date'],
                'dispatch_date' => $data['dispatch_date'] ?? null,
                'location'      => $data['location'],
                'status'        => 'pending',
                'created_by'    => auth()->id(),
            ]);

            // Create items
            foreach ($items as $row) {
                $load = (int) ($row['load_qty'] ?? 0);
                if ($load > 0) {
                    $order->items()->create([
                        'product_id' => (int) $row['product_id'],
                        'load_qty'   => $load,
                        'return_qty' => 0,
                        'bo_qty'     => 0,
                    ]);
                }
            }

            // Deduct stock for loads (pcs)
            $this->applyProductDeltas($loads, -1);
        });

        return redirect()->route('distribution.index')->with('ok', 'Order created.');
    }

    /** Show edit form */
    public function edit(Distribution $distribution)
    {
        $distribution->load('items');

        return view('distribution.form', [
            'mode'      => 'edit',
            'order'     => $distribution,
            'products'  => Product::orderBy('code')->get(),
            'existing'  => $distribution->items->keyBy('product_id'),
            'locations' => Distribution::LOCATIONS,
        ]);
    }

    /**
     * Update order
     * - Always apply delta on loads (subtract)
     * - Apply returns only if status === 'complete' (and undo if toggled back to pending)
     */
    public function update(Request $request, Distribution $distribution)
    {
        $data = $request->validate([
            'load_date'                 => ['required', 'date'],
            'dispatch_date'             => ['nullable', 'date'],
            'location'                  => ['required', 'string', 'max:100'],
            'status'                    => ['required', 'in:pending,complete'],
            'items'                     => ['array'],
            'items.*.product_id'        => ['required', 'integer', 'exists:products,id'],
            'items.*.load_qty'          => ['nullable', 'integer', 'min:0'],
            'items.*.return_qty'        => ['nullable', 'integer', 'min:0'],
            'items.*.bo_qty'            => ['nullable', 'integer', 'min:0'],
        ]);

        $items = $data['items'] ?? [];

        DB::transaction(function () use ($distribution, $data, $items) {
            // Snapshot previous state (for delta math)
            $distribution->load('items');
            $oldStatus = $distribution->status;

            $oldLoads   = []; // pcs
            $oldReturns = []; // pcs
            foreach ($distribution->items as $it) {
                $pid = (int) $it->product_id;
                $oldLoads[$pid]   = ($oldLoads[$pid]   ?? 0) + (int) $it->load_qty;
                $oldReturns[$pid] = ($oldReturns[$pid] ?? 0) + (int) $it->return_qty;
            }

            // Build new aggregates from request
            $newLoads   = [];
            $newReturns = [];
            $newItems   = [];

            foreach ($items as $row) {
                $pid = (int) ($row['product_id'] ?? 0);
                $l   = (int) ($row['load_qty']   ?? 0);
                $r   = (int) ($row['return_qty'] ?? 0);
                $b   = (int) ($row['bo_qty']     ?? 0);

                if ($pid && ($l > 0 || $r > 0 || $b > 0)) {
                    $newLoads[$pid]   = ($newLoads[$pid]   ?? 0) + $l;
                    $newReturns[$pid] = ($newReturns[$pid] ?? 0) + $r;
                    $newItems[] = ['product_id' => $pid, 'load_qty' => $l, 'return_qty' => $r, 'bo_qty' => $b];
                }
            }

            // Compute per-product stock deltas (pcs)
            // Loads always affect stock immediately.
            // Returns count only when status is complete.
            $deltaStock = []; // + means add to stock, - means deduct
            $factorOld = ($oldStatus === 'complete') ? 1 : 0;
            $factorNew = ($data['status'] === 'complete') ? 1 : 0;

            $allPids = array_unique(array_merge(array_keys($oldLoads), array_keys($newLoads), array_keys($oldReturns), array_keys($newReturns)));

            foreach ($allPids as $pid) {
                $oldL = $oldLoads[$pid]   ?? 0;
                $newL = $newLoads[$pid]   ?? 0;
                $oldR = $oldReturns[$pid] ?? 0;
                $newR = $newReturns[$pid] ?? 0;

                // Load delta (subtract from stock)
                $dLoad = $newL - $oldL; // pcs
                $stockChange = -$dLoad;

                // Return delta (add to stock only if status is complete)
                $effectiveOldR = $factorOld * $oldR;
                $effectiveNewR = $factorNew * $newR;
                $dReturnEffective = $effectiveNewR - $effectiveOldR; // pcs
                $stockChange += $dReturnEffective;

                if ($stockChange !== 0) {
                    $deltaStock[$pid] = ($deltaStock[$pid] ?? 0) + $stockChange;
                }
            }

            // Apply stock changes with row locks
            $this->applyProductDeltaMap($deltaStock);

            // Persist new header + items
            $distribution->update([
                'load_date'     => $data['load_date'],
                'dispatch_date' => $data['dispatch_date'] ?? null,
                'location'      => $data['location'],
                'status'        => $data['status'],
            ]);

            $distribution->items()->delete();
            foreach ($newItems as $row) {
                $distribution->items()->create($row);
            }
        });

        return redirect()->route('distribution.index')->with('ok', 'Order updated.');
    }

    /** Delete order: undo its inventory effects */
    public function destroy(Distribution $distribution)
    {
        DB::transaction(function () use ($distribution) {
            $distribution->load('items');

            $loads   = [];
            $returns = [];

            foreach ($distribution->items as $it) {
                $pid = (int) $it->product_id;
                $loads[$pid]   = ($loads[$pid]   ?? 0) + (int) $it->load_qty;
                $returns[$pid] = ($returns[$pid] ?? 0) + (int) $it->return_qty;
            }

            // Reverse effects:
            // +loads (give them back)
            // -returns only if order had been 'complete' (we previously added them)
            $delta = [];
            foreach ($loads as $pid => $pcs) {
                $delta[$pid] = ($delta[$pid] ?? 0) + $pcs;
            }
            if ($distribution->status === 'complete') {
                foreach ($returns as $pid => $pcs) {
                    $delta[$pid] = ($delta[$pid] ?? 0) - $pcs;
                }
            }

            $this->applyProductDeltaMap($delta);

            $distribution->items()->delete();
            $distribution->delete();
        });

        return back()->with('ok', 'Order deleted.');
    }

    /**
     * Apply product stock deltas with row-level locking.
     * $deltaMap: [product_id => +N (add) | -N (deduct)]
     */
    private function applyProductDeltaMap(array $deltaMap): void
    {
        if (empty($deltaMap)) {
            return;
        }

        // Lock products in a consistent order
        ksort($deltaMap);

        foreach ($deltaMap as $pid => $delta) {
            if ($delta === 0) continue;

            /** @var Product $p */
            $p = Product::whereKey((int) $pid)->lockForUpdate()->first();
            if (!$p) {
                throw ValidationException::withMessages([
                    'items' => ["Invalid product: {$pid}."],
                ]);
            }

            $newStock = (int) $p->stock_pcs + (int) $delta;
            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'items' => ["Insufficient stock for {$p->code} (would go negative)."],
                ]);
            }

            $p->stock_pcs = $newStock;
            $p->save();
        }
    }

    /**
     * Convenience: apply the same +/- factor to a [pid => qty] list.
     * e.g., applyProductDeltas([12=>30, 15=>5], -1) will deduct those pcs.
     */
    private function applyProductDeltas(array $map, int $factor): void
    {
        if (empty($map) || $factor === 0) return;

        $delta = [];
        foreach ($map as $pid => $pcs) {
            if ($pcs !== 0) {
                $delta[(int) $pid] = ($delta[(int) $pid] ?? 0) + ($pcs * $factor);
            }
        }
        $this->applyProductDeltaMap($delta);
    }
}
