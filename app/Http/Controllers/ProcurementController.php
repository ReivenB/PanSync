<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProcurementStoreRequest;
use App\Models\Procurement;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

final class ProcurementController extends Controller
{
    /**
     * Show a simple procure form for a given material.
     * Route: GET inventory/raw/{raw_material}/procure  (name: inventory.raw.procure.create)
     */
    public function create(RawMaterial $raw_material)
    {
        // View expects $material
        return view('raw.procure', ['material' => $raw_material]);
    }

    /**
     * Store the procurement and increment raw material quantity.
     * Route: POST inventory/procurements (name: inventory.procurements.store)
     */
    public function store(ProcurementStoreRequest $request)
    {
        $qty = (float) $request->input('qty');

        DB::transaction(function () use ($request, $qty) {
            $material = RawMaterial::whereKey($request->integer('material_id'))
                ->lockForUpdate()
                ->firstOrFail();

            // increment stock
            $material->increment('quantity', $qty);

            // audit record
            Procurement::create([
                'material_id' => $material->id,
                'qty'         => $qty,
                'note'        => (string) $request->input('note', ''),
                'user_id'     => (int) auth()->id(),
                'created_at'  => now(),
            ]);
        });

        return redirect()->route('inventory.raw.index')->with('ok', 'Procurement recorded.');
    }
}
