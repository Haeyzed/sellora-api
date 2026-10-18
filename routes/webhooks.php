<?php

declare(strict_types=1);

use App\Landlord\Integrations\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhooks
|--------------------------------------------------------------------------
|
| Shared entry points for third-party webhooks, served on central domains
| only under /webhooks (CLAUDE.md section 9). A provider that signs with a
| platform-wide secret is checked first. One that signs with the store's own
| key is found by the random endpoint ID in the URL: Landlord\Integrations
| looks it up (an unknown ID is a 404, counted per IP address), the store's
| tenancy is initialized, and the store checks the signature before anything
| is parsed, stored or acted on.
|
*/

// Any endpoint ID reaches the controller, so every wrong guess is counted.
Route::post('payments/{endpoint}', PaymentWebhookController::class)->name('payments');
