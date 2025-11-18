<?php
// app/Http/Controllers/ProductionBatchController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\ProductionBatchStoreRequest;
use App\Http\Requests\ProductionBatchUpdateRequest;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionItem;
use App\Models\RawMaterial;
use App\Services\InventoryService;
use App\Services\YieldCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProductionBatchController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index()
    {
        $batches = ProductionBatch::withCount('items')
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(15);

        return view('production.index', compact('batches'));
    }

    public function create(Request $request)
    {
        $set = strtoupper((string) $request->query('set', 'A'));
        $set = in_array($set, ProductionBatch::SETS, true) ? $set : 'A';

        $products    = Product::orderBy('id')->get();
        $flourAvail  = (float) (RawMaterial::whereRaw('LOWER(name)=?',['flour'])->value('quantity') ?? 0.0);
        $oilAvail    = (float) (RawMaterial::whereRaw('LOWER(name)=?',['oil'])->value('quantity') ?? 0.0);

        return view('production.form', [
            'mode'        => 'create',
            'set'         => $set,
            'products'    => $products,
            'batch'       => null,
            'existing'    => null,
            'expected'    => null,
            'flourAvail'  => round($flourAvail, 1),
            'oilAvail'    => round($oilAvail, 1),
        ]);
    }

    public function store(ProductionBatchStoreRequest $request)
    {
        $userId = (int) auth()->id();

        // Calculate expected yield from requested items
        $items      = collect($request->input('items', []));
        $productIds = $items->pluck('product_id')->all();

        $productMap = Product::whereIn('id', $productIds)
            ->get(['id','yield_per_sack'])
            ->keyBy('id');

        $expected = YieldCalculator::expectedFlourYield(
            $items->map(function ($row) use ($productMap) {
                $pid = (int) $row['product_id'];
                return [
                    'product_yield' => (int) ($productMap[$pid]->yield_per_sack ?? 1),
                    'produced_qty'  => (int) $row['produced_qty'],
                ];
            })
        );
        $expected = round((float)$expected, 1);

        try {
            DB::transaction(function () use ($request, $userId, $expected, $items) {
                /** @var ProductionBatch $batch */
                $batch = ProductionBatch::create([
                    'set'               => $request->string('set'),
                    'date'              => $request->date('date')->toDateString(),
                    'expected_yield'    => $expected,
                    'actual_flour_used' => (float) round((float) $request->input('actual_flour_used'), 1),
                    'oil_used'          => (float) round((float) $request->input('oil_used'), 1),
                    'created_by'        => $userId,
                ]);

                foreach ($items as $row) {
                    ProductionItem::create([
                        'production_batch_id' => $batch->id,
                        'product_id'          => (int) $row['product_id'],
                        'produced_qty'        => (int) $row['produced_qty'],
                    ]);
                }

                $batch->load('items.product');
                app(InventoryService::class)->onProductionCreated($batch);
            });
        } catch (InsufficientStockException $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'actual_flour_used' => strcasecmp($e->materialName, 'Flour') === 0 ? $e->getMessage() : null,
                    'oil_used'          => strcasecmp($e->materialName, 'Oil')   === 0 ? $e->getMessage() : null,
                ]);
        }

        return redirect()->route('production.index')->with('ok', 'Batch saved.');
    }

    public function edit(ProductionBatch $production)
    {
        $products = Product::orderBy('id')->get();
        $existing = $production->items->keyBy('product_id');

        // For edit, allow up to (current stock + old usage), since old usage will be reconciled.
        $flourStock = (float) (RawMaterial::whereRaw('LOWER(name)=?',['flour'])->value('quantity') ?? 0.0);
        $oilStock   = (float) (RawMaterial::whereRaw('LOWER(name)=?',['oil'])->value('quantity') ?? 0.0);

        $flourAvail = round($flourStock + (float)$production->actual_flour_used, 1);
        $oilAvail   = round($oilStock   + (float)$production->oil_used, 1);

        return view('production.form', [
            'mode'        => 'edit',
            'set'         => $production->set,
            'products'    => $products,
            'batch'       => $production,
            'existing'    => $existing,
            'expected'    => (float) $production->expected_yield,
            'flourAvail'  => $flourAvail,
            'oilAvail'    => $oilAvail,
        ]);
    }

    public function update(ProductionBatchUpdateRequest $request, ProductionBatch $production)
    {
        // Snapshot old to reconcile inventory
        $old = [
            'flour' => (float) $production->actual_flour_used,
            'oil'   => (float) $production->oil_used,
            'items' => $production->items()->pluck('produced_qty', 'product_id')->toArray(),
        ];

        // Recompute expected
        $items      = collect($request->input('items', []));
        $productIds = $items->pluck('product_id')->all();
        $productMap = Product::whereIn('id', $productIds)
            ->get(['id','yield_per_sack'])
            ->keyBy('id');

        $expected = YieldCalculator::expectedFlourYield(
            $items->map(function ($row) use ($productMap) {
                $pid = (int) $row['product_id'];
                return [
                    'product_yield' => (int) ($productMap[$pid]->yield_per_sack ?? 1),
                    'produced_qty'  => (int) $row['produced_qty'],
                ];
            })
        );
        $expected = round((float)$expected, 1);

        try {
            DB::transaction(function () use ($request, $production, $expected, $items, $old) {
                $production->update([
                    'date'              => $request->date('date')->toDateString(),
                    'expected_yield'    => $expected,
                    'actual_flour_used' => (float) round((float) $request->input('actual_flour_used'), 1),
                    'oil_used'          => (float) round((float) $request->input('oil_used'), 1),
                ]);

                // Replace items
                $production->items()->delete();

                foreach ($items as $row) {
                    ProductionItem::create([
                        'production_batch_id' => $production->id,
                        'product_id'          => (int) $row['product_id'],
                        'produced_qty'        => (int) $row['produced_qty'],
                    ]);
                }

                $production->load('items');
                app(InventoryService::class)->onProductionUpdated($production, $old);
            });
        } catch (InsufficientStockException $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'actual_flour_used' => strcasecmp($e->materialName, 'Flour') === 0 ? $e->getMessage() : null,
                    'oil_used'          => strcasecmp($e->materialName, 'Oil')   === 0 ? $e->getMessage() : null,
                ]);
        }

        return redirect()->route('production.index')->with('ok', 'Batch updated.');
    }

    public function destroy(ProductionBatch $production)
    {
        $production->load('items');

        DB::transaction(function () use ($production) {
            app(InventoryService::class)->onProductionDeleted($production);
            $production->delete();
        });

        return redirect()->route('production.index')->with('ok', 'Batch removed.');
    }
}
