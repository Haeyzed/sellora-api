<?php

declare(strict_types=1);

namespace App\Shared\Auth\Actions;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;

/**
 * Saves the language a staff member or platform admin wants what is sent to them in (alerts sent from the queue or the scheduler), or forgets it to go back to the default.
 */
final readonly class ChangePreferredLanguage
{
    /**
     * @param  string|null  $locale  One of the application's languages (checked by the request), or null for the default.
     */
    public function handle(Model&HasLocalePreference $account, ?string $locale): void
    {
        $account->forceFill(['locale' => $locale])->save();
    }
}
