<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ProductionBatch;

final class ProductionBatchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'set'   => ['required', 'string', Rule::in(ProductionBatch::SETS)],
            'date'  => ['required', 'date'],

            // must fit DECIMAL(*,1) in DB (usually 999.9 max)
            'actual_flour_used' => ['required','numeric','min:0','lt:1000','regex:/^\d+(\.\d)?$/'],
            'oil_used'          => ['required','numeric','min:0','lt:1000','regex:/^\d+(\.\d)?$/'],

            'items'                     => ['required','array','min:1'],
            'items.*.product_id'        => ['required','integer','exists:products,id'],
            'items.*.produced_qty'      => ['required','integer','min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_flour_used.lt' => 'Flour used must be less than 1000 (fits one decimal place).',
            'oil_used.lt'          => 'Oil used must be less than 1000 (fits one decimal place).',
            'actual_flour_used.regex' => 'Use at most one decimal place (e.g., 123.4).',
            'oil_used.regex'          => 'Use at most one decimal place (e.g., 12.3).',
        ];
    }
}
