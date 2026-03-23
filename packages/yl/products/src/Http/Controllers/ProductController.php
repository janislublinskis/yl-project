<?php

namespace Yl\Products\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Yl\Helper\Http\Controllers\BaseApiController;
use Yl\Products\Http\Requests\StoreProductRequest;
use Yl\Products\Http\Requests\UpdateProductRequest;
use Yl\Products\Http\Resources\ProductResource;
use Yl\Products\Jobs\ProcessProductExportJob;
use Yl\Products\Models\Product;

/**
 * ProductController
 *
 * Handles all CRUD operations for the Products module.
 * Extends BaseApiController to inherit consistent ApiResponse helpers.
 *
 * Routes:
 *   GET    /api/products          → index
 *   POST   /api/products          → store
 *   GET    /api/products/{id}     → show
 *   PUT    /api/products/{id}     → update
 *   DELETE /api/products/{id}     → destroy
 */
class ProductController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     summary="List all products",
     *     tags={"Products"},
     *     @OA\Parameter(name="status", in="query", required=false,
     *         @OA\Schema(type="string", enum={"active","inactive","archived"})
     *     ),
     *     @OA\Response(response=200, description="Paginated product list")
     * )
     */
    public function index(): JsonResponse
    {
        $query = Product::recent();

        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        $paginator = $query->paginate(15);

        return $this->success([
            'data'         => ProductResource::collection($paginator->items())->toArray(request()),
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
        ], 'Products retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     summary="Create a product",
     *     tags={"Products"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","price","stock"},
     *             @OA\Property(property="name",        type="string",  example="Widget Pro"),
     *             @OA\Property(property="description", type="string",  example="A great widget"),
     *             @OA\Property(property="price",       type="number",  example=29.99),
     *             @OA\Property(property="stock",       type="integer", example=150),
     *             @OA\Property(property="status",      type="string",  enum={"active","inactive","archived"}, example="active")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Product created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        ProcessProductExportJob::dispatch($product);

        return $this->created(
            new ProductResource($product),
            'Product created successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     summary="Get a product",
     *     tags={"Products"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product found"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->notFound("Product #{$id} not found");
        }

        return $this->success(
            new ProductResource($product),
            'Product retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     summary="Update a product",
     *     tags={"Products"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name",   type="string",  example="Updated Widget"),
     *             @OA\Property(property="price",  type="number",  example=39.99),
     *             @OA\Property(property="stock",  type="integer", example=200),
     *             @OA\Property(property="status", type="string",  enum={"active","inactive","archived"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Product updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->notFound("Product #{$id} not found");
        }

        $product->update($request->validated());

        return $this->success(
            new ProductResource($product),
            'Product updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     summary="Delete a product",
     *     tags={"Products"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product deleted"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return $this->notFound("Product #{$id} not found");
        }

        $product->delete();

        return $this->success(null, 'Product deleted successfully');
    }
}
