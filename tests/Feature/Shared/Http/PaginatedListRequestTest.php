<?php

declare(strict_types=1);

use App\Shared\Http\PaginatedListRequest;
use Illuminate\Support\Facades\Route;

final class ListSampleRecordsRequest extends PaginatedListRequest {}

beforeEach(function (): void {
    config(['api.pagination.default_per_page' => 25, 'api.pagination.max_per_page' => 100]);

    Route::get('/api/v1/sample-records', static fn (ListSampleRecordsRequest $request): array => ['per_page' => $request->perPage()]);
});

it('uses the default page size when the client does not ask for one', function (): void {
    $this->getJson('/api/v1/sample-records')->assertOk()->assertExactJson(['per_page' => 25]);
});

it('uses the page size the client asks for within the maximum', function (): void {
    $this->getJson('/api/v1/sample-records?per_page=100')->assertOk()->assertExactJson(['per_page' => 100]);
});

it('rejects a page size above the maximum instead of returning an unbounded list', function (): void {
    $this->getJson('/api/v1/sample-records?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});
