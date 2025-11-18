<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\ProductionBatch;
use App\Models\ProductionItem;
use App\Models\Distribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function index()
    {
        $now       = now();
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd   = $now->copy()->endOfDay();

        // --- Products / Finished goods ---
        $productCount = (int) Product::count();

        // Expected Yield across all finished goods (sacks)
        $finishedExpectedYield = (float) DB::table('products')
            ->selectRaw('COALESCE(SUM(stock_pcs / NULLIF(yield_per_sack,0)), 0) as sacks')
            ->value('sacks');

        // --- Raw materials on hand (case-insensitive names) ---
        $flourQty = (float) (RawMaterial::whereRaw('LOWER(name)=?', ['flour'])->value('quantity') ?? 0);
        $oilQty   = (float) (RawMaterial::whereRaw('LOWER(name)=?', ['oil'])->value('quantity') ?? 0);

        // --- Weekly (resets every Monday 00:00) ---
        $expectedWeek = (float) ProductionBatch::whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('expected_yield');

        $flourUsedWeek = (float) ProductionBatch::whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('actual_flour_used');

        $oilUsedWeek = (float) ProductionBatch::whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('oil_used');

        $batchesToday = (int) ProductionBatch::whereDate('created_at', $now->toDateString())->count();

        // Optional extras
        $producedTodayPcs = (int) ProductionItem::whereDate('created_at', $now->toDateString())
            ->sum('produced_qty');

        $producedWeekPcs = (int) ProductionItem::whereBetween('created_at', [$weekStart, $weekEnd])
            ->sum('produced_qty');

        // Orders
        $ordersPending = (int) Distribution::where('status', 'pending')->count();
        $ordersToday   = (int) Distribution::whereDate('dispatch_date', $now->toDateString())->count();

        // Lists
        $recentBatches = ProductionBatch::latest('created_at')->limit(5)->get();
        $recentOrders  = Distribution::latest('dispatch_date')->limit(5)->get();
        $lowStock      = Product::orderBy('stock_pcs', 'asc')->limit(5)->get();

        $stats = [
            'product_count'             => $productCount,
            'finished_expected_yield'   => $finishedExpectedYield, // sacks
            'flour_qty'                 => $flourQty,              // sacks on hand
            'oil_qty'                   => $oilQty,                // 20L ctrs on hand

            // Weekly metrics (reset Monday 00:00)
            'expected_yield_week'       => $expectedWeek,
            'flour_used_week'           => $flourUsedWeek,
            'oil_used_week'             => $oilUsedWeek,

            'batches_today'             => $batchesToday,
            'orders_pending'            => $ordersPending,
            'orders_today'              => $ordersToday,

            // extras
            'produced_today_pcs'        => $producedTodayPcs,
            'produced_week_pcs'         => $producedWeekPcs,

            // handy to show range if needed
            'week_start'                => $weekStart,
        ];

        return view('dashboard', compact('stats', 'recentBatches', 'recentOrders', 'lowStock'));
    }
}
