<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Controllers;

use App\Shared\Auth\Exceptions\IncorrectCurrentPasswordException;
use App\Shared\Auth\Exceptions\InvalidTwoFactorCodeException;
use App\Shared\Auth\Exceptions\TooManyIncorrectAttemptsException;
use App\Shared\Exceptions\DomainException;
use App\Shared\Http\Controller;
use App\Tenant\Settings\Actions\CloseOwnStore;
use App\Tenant\Settings\Http\Requests\CloseOwnStoreRequest;
use App\Tenant\Settings\Http\Resources\ClosedStoreResource;

/**
 * The owner closing their own store.
 */
final class StoreClosureController extends Controller
{
    /**
     * Close the store.
     *
     * Owner only. **Export your store's data first**: once the store is
     * closed, nobody can sign in to it, so you can no longer export it
     * yourself. Needs your current password, plus an authenticator code (or
     * a recovery code) when you use two-factor authentication.
     *
     * The store stops opening at once and everyone (staff, customers and
     * drivers) is signed out, you included. Its data is kept for 90 days and
     * then deleted for good; you are emailed the date. Until then, Sellora
     * support can restore the store.
     *
     * @throws IncorrectCurrentPasswordException
     * @throws InvalidTwoFactorCodeException
     * @throws TooManyIncorrectAttemptsException
     * @throws DomainException
     */
    public function store(CloseOwnStoreRequest $request, CloseOwnStore $closeOwnStore): ClosedStoreResource
    {
        return new ClosedStoreResource($closeOwnStore->handle(
            $request->actor(),
            $request->currentPassword(),
            $request->code(),
            $request->recoveryCode(),
            $request->reason(),
        ));
    }
}
