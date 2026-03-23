<?php

namespace Yl\Products\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Yl\Helper\Http\Response\ApiResponse;
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
            'price'       => ['sometimes', 'integer', 'min:0'],
            'stock'       => ['sometimes', 'integer', 'min:0'],
            'status'      => ['sometimes', 'in:' . implode(',', Product::STATUSES)],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }
}
