<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Actions;

use App\Shared\Money\PricedRecordsRegistry;
use App\Tenant\Settings\Exceptions\MissingStoreSettingsException;
use App\Tenant\Settings\Exceptions\PricingSettingsLockedException;
use App\Tenant\Settings\Jobs\SyncStoreProfile;
use App\Tenant\Settings\Models\StoreSettings;

/**
 * Changes the store's settings. Each change is audited field by field.
 *
 * The base currency and tax mode can't change once anything is priced
 * (section 3.3). Changing the country changes nothing else: country
 * defaults apply only at sign-up. Once the change has committed, the
 * platform's copy of the name, country, currency, timezone and language is
 * updated by a queued job that retries until it succeeds (section 6).
 */
final readonly class UpdateStoreSettings
{
    public function __construct(
        private FindStoreSettings $findStoreSettings,
        private PricedRecordsRegistry $pricedRecordsRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  Validated settings, by column.
     *
     * @throws PricingSettingsLockedException When the base currency or tax mode would change while something is priced.
     * @throws MissingStoreSettingsException When the store has no settings row, which is a bug.
     */
    public function handle(array $changes): StoreSettings
    {
        return StoreSettings::query()->getConnection()->transaction(function () use ($changes): StoreSettings {
            $settings = $this->findStoreSettings->forUpdate();
            $changesFooter = array_key_exists('invoice_footer', $changes);
            $footer = $changes['invoice_footer'] ?? null;
            unset($changes['invoice_footer']);
            $settings->fill($changes);

            if (is_array($footer)) {
                /** @var array<string, string|null> $footer */
                $settings->changeTranslations('invoice_footer', $footer);
            } elseif ($changesFooter) {
                $settings->forgetTranslations('invoice_footer', asNull: true);
            }

            if ($settings->isDirty(StoreSettings::PRICING_FIELDS) && $this->pricedRecordsRegistry->anyExist()) {
                throw new PricingSettingsLockedException;
            }

            $settings->save();

            if ($settings->wasChanged(StoreSettings::PROFILE_FIELDS)) {
                dispatch(new SyncStoreProfile)->afterCommit();
            }

            return $settings;
        });
    }
}
