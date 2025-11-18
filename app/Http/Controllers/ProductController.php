<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ActivityLog;   // activity history
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->string('sort')->toString();
        $dir  = $request->string('dir')->toString() ?: 'asc';

        // Map UI sort keys to columns
        $sortColumn = match ($sort) {
            'stock' => 'stock_pcs',
            'yield' => 'yield_per_sack',
            default => 'code', // default arrangement same as production form
        };
        $dir = $dir === 'desc' ? 'desc' : 'asc';

        // ✅ No pagination: show all products, default order by code (A→Z)
        $products = Product::query()
            ->orderBy($sortColumn, $dir)
            ->get();

        // Keep logs paginated (ok lang may pages dito)
        $logs = ActivityLog::with('user')
            ->latest()
            ->paginate(10, ['*'], 'logs_page')
            ->withQueryString();

        return view('inventory.finished.index', [
            'products' => $products,
            'sort'     => $sort ?: 'code',
            'dir'      => $dir,
            'logs'     => $logs,
        ]);
    }

    public function create()
    {
        return view('inventory.finished.form', [
            'mode'    => 'create',
            'product' => new Product(),
        ]);
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validatedWithCode();

        Product::create($data);

        return redirect()
            ->route('inventory.finished.index')
            ->with('ok', 'Product added.');
    }

    public function edit(Product $product)
    {
        return view('inventory.finished.form', [
            'mode'    => 'edit',
            'product' => $product,
        ]);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validatedWithCode();

        // capture BEFOREs
        $beforeStock = (int) $product->stock_pcs;
        $beforeName  = (string) $product->name;

        // save
        $product->update($data);
        $product->refresh();

        // capture AFTERs
        $afterStock = (int) $product->stock_pcs;
        $afterName  = (string) $product->name;

        // log only if something meaningful changed
        if ($beforeStock !== $afterStock || $beforeName !== $afterName) {
            $title = 'Updated Item "'.$afterName.'"';
            $sub   = 'Stock: '.$beforeStock.' → '.$afterStock;

            ActivityLog::create([
                'user_id'     => auth()->id(),
                'description' => $title."\n".$sub,
                'meta'        => [
                    'type'         => 'product_update',
                    'product_id'   => $product->id,
                    'before_stock' => $beforeStock,
                    'after_stock'  => $afterStock,
                    'before_name'  => $beforeName,
                    'after_name'   => $afterName,
                ],
            ]);
        }

        return redirect()
            ->route('inventory.finished.index')
            ->with('ok', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        try {
            $product->delete();

            return redirect()
                ->route('inventory.finished.index')
                ->with('ok', 'Product deleted.');
        } catch (QueryException $e) {
            return redirect()
                ->route('inventory.finished.index')
                ->with('err', 'Cannot delete: product is used by other records.');
        }
    }
}
