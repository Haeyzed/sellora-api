<?php

declare(strict_types=1);

use App\Shared\Http\CursorListRequest;
use App\Shared\Http\PageListRequest;
use Illuminate\Support\Facades\Route;

final class ListSamplePagesRequest extends PageListRequest {}

final class ListSampleCursorRequest extends CursorListRequest {}

beforeEach(function (): void {
    config(['api.pagination.default_per_page' => 25, 'api.pagination.max_per_page' => 100]);

    Route::get('/api/v1/sample-pages', static fn (ListSamplePagesRequest $request): array => ['per_page' => $request->perPage()]);
    Route::get('/api/v1/sample-cursor', static fn (ListSampleCursorRequest $request): array => ['per_page' => $request->perPage()]);
});

it('uses the default page size when the client does not ask for one', function (): void {
    $this->getJson('/api/v1/sample-pages')->assertOk()->assertExactJson(['per_page' => 25]);
    $this->getJson('/api/v1/sample-cursor')->assertOk()->assertExactJson(['per_page' => 25]);
});

it('uses the page size the client asks for within the maximum', function (): void {
    $this->getJson('/api/v1/sample-pages?per_page=100')->assertOk()->assertExactJson(['per_page' => 100]);
    $this->getJson('/api/v1/sample-cursor?per_page=100')->assertOk()->assertExactJson(['per_page' => 100]);
});

it('rejects a page size above the maximum instead of returning an unbounded list', function (): void {
    $this->getJson('/api/v1/sample-pages?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/api/v1/sample-cursor?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('takes a page number from 1 on page-number lists and a cursor on cursor lists', function (): void {
    $this->getJson('/api/v1/sample-pages?page=2')->assertOk();
    $this->getJson('/api/v1/sample-pages?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    $this->getJson('/api/v1/sample-pages?page=two')->assertUnprocessable()->assertJsonValidationErrors('page');
    $this->getJson('/api/v1/sample-cursor?cursor='.str_repeat('a', 513))->assertUnprocessable()->assertJsonValidationErrors('cursor');
});
