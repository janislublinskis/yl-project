<?php

namespace Yl\Helper\Http\Response;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse
 *
 * Enforces a consistent JSON envelope across every module:
 *
 * {
 *   "success": true | false,
 *   "message": "Human-readable status",
 *   "data":    <payload> | null,
 *   "errors":  <error detail> | null
 * }
 *
 * Usage (static):
 *   ApiResponse::success($resource, 'Product created');
 *   ApiResponse::error('Validation failed', $e->errors(), 422);
 *
 * Usage (via BaseApiController helpers):
 *   return $this->success($resource);
 *   return $this->notFound();
 */
class ApiResponse
{
    /**
     * 200 OK — successful operation.
     */
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ], $status);
    }

    /**
     * 201 Created — resource was stored.
     */
    public static function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return static::success($data, $message, 201);
    }

    /**
     * Generic error response.
     *
     * @param  mixed  $errors  Validation error bag, exception message, etc.
     */
    public static function error(string $message = 'An error occurred', mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $status);
    }

    /**
     * 404 Not Found.
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return static::error($message, null, 404);
    }

    /**
     * 422 Unprocessable Entity — form validation failed.
     */
    public static function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return static::error($message, $errors, 422);
    }
}
