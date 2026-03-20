<?php

namespace Yl\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Yl\Products\Models\Product;

/**
 * StoreProductRequest
 *
 * Validates the payload for POST /api/products.
 * Authorization is open (true) — add policy checks here if needed.
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'status'      => ['sometimes', 'in:' . implode(',', Product::STATUSES)],
        ];
    }
}
