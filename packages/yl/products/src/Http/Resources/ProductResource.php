<?php

namespace Yl\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource
 *
 * Transforms a Product model into the JSON shape returned by the API.
 * Keeps controller return values clean and decoupled from model internals.
 *
 * Example output:
 * {
 *   "id": 1,
 *   "name": "Widget Pro",
 *   "description": "...",
 *   "price": 29.99,
 *   "stock": 150,
 *   "status": "active",
 *   "created_at": "2024-01-01T10:00:00.000000Z",
 *   "updated_at": "2024-01-01T10:00:00.000000Z"
 * }
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => $this->price,
            'stock'       => $this->stock,
            'status'      => $this->status,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
