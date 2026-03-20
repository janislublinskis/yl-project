<?php

namespace Yl\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Yl\Products\Models\Product;

/**
 * UpdateProductRequest
 *
 * Validates the payload for PUT/PATCH /api/products/{id}.
 * All fields are optional (sometimes) to support partial updates.
 */
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'stock'       => ['sometimes', 'integer', 'min:0'],
            'status'      => ['sometimes', 'in:' . implode(',', Product::STATUSES)],
        ];
    }
}
