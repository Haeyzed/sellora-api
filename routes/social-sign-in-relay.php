<?php

declare(strict_types=1);

use App\Landlord\Tenancy\Http\Controllers\SocialSignInRelayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Social sign-in relay (Step 10b)
|--------------------------------------------------------------------------
|
| The one address registered with each provider, on the central domain
| only. It sends the browser on to the store bound to the one-time state,
| never to an address from the request. Apple posts its answer here
| (response_mode=form_post); the others use GET.
|
*/

Route::match(['get', 'post'], '{provider}/callback', SocialSignInRelayController::class)
    ->whereIn('provider', ['google', 'apple'])
    ->middleware('throttle:social-sign-in-relay')
    ->name('relay');
