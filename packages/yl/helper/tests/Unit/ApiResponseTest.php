<?php

namespace Yl\Helper\Tests\Unit;

use Illuminate\Http\JsonResponse;
use Yl\Helper\Http\Response\ApiResponse;
use Yl\Helper\Tests\TestCase;

/**
 * ApiResponseTest
 *
 * Verifies that ApiResponse always produces the correct JSON envelope
 * shape, HTTP status codes, and field values.
 *
 * These are pure unit tests — no database access needed.
 */
class ApiResponseTest extends TestCase
{
    // ── success ────────────────────────────────────────────────────

    /** @test */
    public function success_returns_200_with_correct_envelope(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Done');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertTrue($body['success']);
        $this->assertSame('Done', $body['message']);
        $this->assertSame(['id' => 1], $body['data']);
        $this->assertNull($body['errors']);
    }

    /** @test */
    public function success_accepts_a_custom_status_code(): void
    {
        $response = ApiResponse::success(null, 'OK', 202);

        $this->assertSame(202, $response->getStatusCode());
    }

    /** @test */
    public function success_works_with_null_data(): void
    {
        $response = ApiResponse::success();

        $body = $response->getData(true);
        $this->assertTrue($body['success']);
        $this->assertNull($body['data']);
    }

    // ── created ────────────────────────────────────────────────────

    /** @test */
    public function created_returns_201(): void
    {
        $response = ApiResponse::created(['id' => 5], 'Resource created');

        $this->assertSame(201, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertTrue($body['success']);
        $this->assertSame(['id' => 5], $body['data']);
    }

    // ── error ──────────────────────────────────────────────────────

    /** @test */
    public function error_returns_400_with_correct_envelope(): void
    {
        $response = ApiResponse::error('Something went wrong', ['field' => 'bad'], 400);

        $this->assertSame(400, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertFalse($body['success']);
        $this->assertSame('Something went wrong', $body['message']);
        $this->assertNull($body['data']);
        $this->assertSame(['field' => 'bad'], $body['errors']);
    }

    /** @test */
    public function error_accepts_custom_status_codes(): void
    {
        $this->assertSame(500, ApiResponse::error('Server error', null, 500)->getStatusCode());
        $this->assertSame(403, ApiResponse::error('Forbidden',    null, 403)->getStatusCode());
    }

    // ── notFound ───────────────────────────────────────────────────

    /** @test */
    public function not_found_returns_404(): void
    {
        $response = ApiResponse::notFound('Item missing');

        $this->assertSame(404, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertFalse($body['success']);
        $this->assertSame('Item missing', $body['message']);
    }

    // ── validationError ────────────────────────────────────────────

    /** @test */
    public function validation_error_returns_422_with_errors(): void
    {
        $errors = ['name' => ['The name field is required.']];

        $response = ApiResponse::validationError($errors);

        $this->assertSame(422, $response->getStatusCode());

        $body = $response->getData(true);
        $this->assertFalse($body['success']);
        $this->assertSame($errors, $body['errors']);
        $this->assertSame('Validation failed', $body['message']);
    }

    // ── envelope shape guarantee ───────────────────────────────────

    /** @test */
    public function every_response_always_contains_all_four_keys(): void
    {
        $requiredKeys = ['success', 'message', 'data', 'errors'];

        foreach ([
            ApiResponse::success(),
            ApiResponse::created(),
            ApiResponse::error('err'),
            ApiResponse::notFound(),
            ApiResponse::validationError([]),
        ] as $response) {
            $body = $response->getData(true);
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $body, "Key '{$key}' missing from response envelope.");
            }
        }
    }
}
