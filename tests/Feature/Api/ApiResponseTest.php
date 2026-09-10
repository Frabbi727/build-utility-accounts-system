<?php

namespace Tests\Feature\Api;

use App\Http\Responses\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envelope_structure(): void
    {
        $response = ApiResponse::success(['id' => 10], 'Data retrieved successfully');
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('Data retrieved successfully', $data['message']);
        $this->assertSame(10, $data['data']['id']);
    }

    public function test_paginated_envelope_structure(): void
    {
        $items = collect([['id' => 1], ['id' => 2]]);
        $paginator = new LengthAwarePaginator($items, 20, 2, 1, [
            'path' => 'http://localhost/api/v1/test',
        ]);

        $response = ApiResponse::paginated($paginator, 'Items listed');
        $data = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertSame('Items listed', $data['message']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(1, $data['meta']['current_page']);
        $this->assertSame(10, $data['meta']['last_page']);
        $this->assertSame(2, $data['meta']['per_page']);
        $this->assertSame(20, $data['meta']['total']);
        $this->assertArrayHasKey('first', $data['links']);
        $this->assertArrayHasKey('last', $data['links']);
        $this->assertArrayHasKey('prev', $data['links']);
        $this->assertArrayHasKey('next', $data['links']);
    }

    public function test_error_envelope_structure(): void
    {
        $response = ApiResponse::error('Validation failed', 422, ['phone' => ['Invalid phone']]);
        $data = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertSame('Validation failed', $data['message']);
        $this->assertSame(['Invalid phone'], $data['errors']['phone']);
    }
}
