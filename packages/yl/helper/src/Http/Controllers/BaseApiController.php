<?php

namespace Yl\Helper\Http\Controllers;

use HttpResponseException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Validator;
use Yl\Helper\Http\Response\ApiResponse;

/**
 * BaseApiController
 *
 * Abstract base class for every module controller.
 * Delegates to ApiResponse so controller methods stay concise:
 *
 *   return $this->success($resource, 'Product created');
 *   return $this->notFound('Product #42 does not exist');
 *
 * Extend this instead of Illuminate\Routing\Controller in each module.
 *
 * @OA\Info(
 * title="YL Laravel API",
 * version="1.0.0",
 * description="Modular Laravel REST API — Products and Posts modules"
 * )
 * @OA\Server(url="/", description="Current server")
 */
abstract class BaseApiController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;

    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function error(string $message = 'Error', mixed $errors = null, int $status = 400): JsonResponse
    {
        return ApiResponse::error($message, $errors, $status);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error('Validation failed', 422, $validator->errors()->toArray())
        );
    }
}
