<?php
// app/Http/Requests/DistributionStoreRequest.php

namespace App\Http\Requests;

use App\Models\Distribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // employees + admins
    }

    public function rules(): array
    {
        return [
            'load_date'     => ['required', 'date'],
            'dispatch_date' => ['nullable', 'date', 'after_or_equal:load_date'],
            'location'      => ['required', Rule::in(Distribution::LOCATIONS)],
            'items'         => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.load_qty'   => ['nullable', 'integer', 'min:0'],
            // store stage has no returns yet, but accept zeros to be explicit
            'items.*.return_qty' => ['nullable', 'integer', 'min:0', 'lte:items.*.load_qty'],
            'items.*.bo_qty'     => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.return_qty.lte' => 'Return quantity cannot exceed load quantity.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalize empty inputs to zero
        if ($this->has('items') && is_array($this->items)) {
            $items = [];
            foreach ($this->items as $row) {
                $items[] = [
                    'product_id' => (int)($row['product_id'] ?? 0),
                    'load_qty'   => (int)($row['load_qty'] ?? 0),
                    'return_qty' => (int)($row['return_qty'] ?? 0),
                    'bo_qty'     => (int)($row['bo_qty'] ?? 0),
                ];
            }
            $this->merge(['items' => $items]);
        }
    }
}
