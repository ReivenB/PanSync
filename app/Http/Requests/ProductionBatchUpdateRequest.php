<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductionBatchUpdateRequest extends ProductionBatchStoreRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'set'   => ['sometimes', Rule::in(['A','B','C','D','E'])],
            'date'  => ['required', 'date'],

            'actual_flour_used' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d)?$/'],
            'oil_used'          => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d)?$/'],

            'items'                        => ['required', 'array', 'min:1'],
            'items.*.product_id'           => ['required', 'integer', 'exists:products,id'],
            'items.*.produced_qty'         => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $items = collect($this->input('items', []));
            if ($items->sum('produced_qty') <= 0) {
                $v->errors()->add('items', 'Enter at least one produced quantity.');
            }
        });
    }
}
