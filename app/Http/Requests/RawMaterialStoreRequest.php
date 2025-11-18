<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RawMaterialStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', Rule::in(['Flour','Oil']), 'unique:raw_materials,name'],
            'quantity' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d)?$/'], // 1 dp
        ];
    }

    public function messages(): array
    {
        return [
            'name.in' => 'Only Flour or Oil are allowed.',
        ];
    }
}
