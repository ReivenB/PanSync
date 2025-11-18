<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProcurementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // gate by routes/middleware already
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', 'exists:raw_materials,id'],
            'qty'         => ['required', 'numeric', 'gt:0'],
            'note'        => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'qty.gt' => 'Quantity must be greater than zero.',
        ];
    }
}
