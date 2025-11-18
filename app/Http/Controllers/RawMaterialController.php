<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RawMaterialStoreRequest;
use App\Http\Requests\RawMaterialUpdateRequest;
use App\Models\RawMaterial;
use Illuminate\Database\QueryException;

final class RawMaterialController extends Controller
{
    public function index()
    {
        $materials = RawMaterial::orderBy('name')->get();

        return view('inventory.raw.index', [
            'materials' => $materials,
            'total'     => $materials->sum('quantity'),
        ]);
    }

    public function create()
    {
        return view('inventory.raw.form', [
            'material' => new RawMaterial(),
            'mode'     => 'create',
        ]);
    }

    public function store(RawMaterialStoreRequest $request)
    {
        $data = $request->validated();
        // IMPORTANT: match DB enum exactly
        $data['unit'] = $data['name'] === 'Flour'
            ? RawMaterial::UNIT_FLOUR   // 'Sack'
            : RawMaterial::UNIT_OIL;    // '20L'

        RawMaterial::create($data);

        return redirect()
            ->route('inventory.raw.index')
            ->with('ok', 'Material added.');
    }

    public function edit(RawMaterial $raw_material)
    {
        return view('inventory.raw.form', [
            'material' => $raw_material,
            'mode'     => 'edit',
        ]);
    }

    public function update(RawMaterialUpdateRequest $request, RawMaterial $raw_material)
    {
        $data = $request->validated();
        // IMPORTANT: match DB enum exactly
        $data['unit'] = $data['name'] === 'Flour'
            ? RawMaterial::UNIT_FLOUR
            : RawMaterial::UNIT_OIL;

        $raw_material->update($data);

        return redirect()
            ->route('inventory.raw.index')
            ->with('ok', 'Material updated.');
    }

    public function destroy(RawMaterial $raw_material)
    {
        try {
            $raw_material->delete();

            return redirect()
                ->route('inventory.raw.index')
                ->with('ok', 'Material deleted.');
        } catch (QueryException) {
            return back()->with('err', 'Cannot delete: material is referenced.');
        }
    }
}
