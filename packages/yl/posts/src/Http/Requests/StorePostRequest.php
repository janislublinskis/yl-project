<?php

namespace Yl\Posts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Yl\Posts\Models\Post;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'slug'         => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'body'         => ['required', 'string'],
            'status'       => ['sometimes', 'in:' . implode(',', Post::STATUSES)],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
