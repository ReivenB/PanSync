<?php
// app/Http/Requests/DistributionUpdateRequest.php

namespace App\Http\Requests;

use App\Models\Distribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'load_date'     => ['required', 'date'],
            'dispatch_date' => ['nullable', 'date', 'after_or_equal:load_date'],
            'location'      => ['required', Rule::in(Distribution::LOCATIONS)],
            'status'        => ['required', Rule::in([Distribution::STATUS_PENDING, Distribution::STATUS_COMPLETE])],
            'items'         => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.load_qty'   => ['required', 'integer', 'min:0'],
            'items.*.return_qty' => ['required', 'integer', 'min:0', 'lte:items.*.load_qty'],
            'items.*.bo_qty'     => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
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
