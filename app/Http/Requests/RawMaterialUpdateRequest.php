<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RawMaterialUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $id = $this->route('raw_material')?->id ?? null;

        return [
            'name'     => ['required', Rule::in(['Flour','Oil']),
                           Rule::unique('raw_materials','name')->ignore($id)],
            'quantity' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d)?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.in' => 'Only Flour or Oil are allowed.',
        ];
    }
}
