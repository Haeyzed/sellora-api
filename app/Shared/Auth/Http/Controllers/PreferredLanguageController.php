<?php

declare(strict_types=1);

namespace App\Shared\Auth\Http\Controllers;

use App\Shared\Auth\Actions\ChangePreferredLanguage;
use App\Shared\Auth\Http\Requests\ChangePreferredLanguageRequest;
use App\Shared\Auth\Http\SignedInAccount;
use App\Shared\Http\Controller;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Response;
use LogicException;

/**
 * The language the signed-in staff member or platform admin wants what is sent to them in.
 */
final class PreferredLanguageController extends Controller
{
    /**
     * Choose my language.
     *
     * The language alerts and other messages sent outside a request are
     * written in; null goes back to the default (the store's default language
     * for staff, the platform's for platform admins). Each request's own
     * language still comes from its Accept-Language header. The profile (me)
     * shows the choice.
     */
    public function update(ChangePreferredLanguageRequest $request, ChangePreferredLanguage $changePreferredLanguage, SignedInAccount $signedInAccount): Response
    {
        $account = $signedInAccount->from($request);

        if (! $account instanceof HasLocalePreference) {
            throw new LogicException('Only accounts with a preferred language may use this route.');
        }

        $changePreferredLanguage->handle($account, $request->locale());

        return response()->noContent();
    }
}
