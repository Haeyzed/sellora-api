<?php

declare(strict_types=1);

use App\Shared\Tenancy\Http\Middleware\EnsureCentralDomain;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(EnsureCentralDomain::class)->get('/platform-only', static fn (): string => 'platform');
});

it('serves platform-only endpoints on a central domain', function (): void {
    $this->get('http://localhost/platform-only')->assertOk();
});

it('returns 404 for platform-only endpoints on a store domain', function (): void {
    $this->get('http://mystore.'.config('platform.domain').'/platform-only')->assertNotFound();
});
