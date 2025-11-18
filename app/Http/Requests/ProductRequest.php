<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // gate with 'role:admin' at routes
    }

    public function rules(): array
    {
        // On update, ignore the current product for unique code
        $id = $this->route('product')?->id;

        return [
            'name'            => ['required', 'string', 'max:64'],
            'yield_per_sack'  => ['required', 'integer', 'min:1', 'max:65535'],
            'stock_pcs'       => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * We’ll derive 'code' from name (UPPERCASE) to match your UI.
     */
    public function validatedWithCode(): array
    {
        $data = $this->validated();

        // Derive a code from the name (e.g., "SSS", "900", "X12")
        $data['code'] = strtoupper(trim((string) $data['name']));

        return $data;
    }
}
