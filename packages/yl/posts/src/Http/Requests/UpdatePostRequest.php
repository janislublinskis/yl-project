<?php

namespace Yl\Posts\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Yl\Helper\Http\Response\ApiResponse;
use Yl\Posts\Models\Post;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Exclude the current post's slug from the unique check
        // so a PUT with the same slug doesn't fail validation.
        $postId = $this->route('id');

        return [
            'title'        => ['sometimes', 'string', 'max:255'],
            'slug'         => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($postId)],
            'body'         => ['sometimes', 'string'],
            'status'       => ['sometimes', 'in:' . implode(',', Post::STATUSES)],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }
}
