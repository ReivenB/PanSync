<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductionBatch;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function productionUsage(Request $request)
    {
        // Defaults: last 7 days (inclusive)
        $start = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->copy()->subDays(6)->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->copy()->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $sets = ['A','B','C','D','E'];

        $rows = ProductionBatch::query()
            ->selectRaw('`set`, COALESCE(SUM(actual_flour_used),0) as flour_used, COALESCE(SUM(oil_used),0) as oil_used')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('set')
            ->get()
            ->keyBy('set');

        $flourBySet = [];
        $oilBySet   = [];
        foreach ($sets as $s) {
            $flourBySet[$s] = isset($rows[$s]) ? (float)$rows[$s]->flour_used : 0.0;
            $oilBySet[$s]   = isset($rows[$s]) ? (float)$rows[$s]->oil_used   : 0.0;
        }

        $totals = [
            'flour' => array_sum($flourBySet),
            'oil'   => array_sum($oilBySet),
        ];

        $hasData = ($totals['flour'] > 0) || ($totals['oil'] > 0);

        return view('reports.production-usage', [
            'title'       => 'Generate Production Usage Report',
            'start'       => $start,
            'end'         => $end,
            'sets'        => $sets,
            'flourBySet'  => $flourBySet,
            'oilBySet'    => $oilBySet,
            'totals'      => $totals,
            'hasData'     => $hasData,
        ]);
    }
}
