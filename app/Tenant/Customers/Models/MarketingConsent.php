<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Customers\Enums\MarketingChannel;
use App\Tenant\Customers\Enums\MarketingConsentSource;
use App\Tenant\Customers\Enums\MarketingConsentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One grant or withdrawal of marketing consent for a channel and contact, kept as evidence (section 3.3).
 *
 * Events are never changed, and only erasure deletes them; a database
 * trigger refuses anything else. A guest's event has no customer: consent
 * belongs to the contact (the email or phone), so the latest event for a
 * channel and contact is the current answer, whoever gave it.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $customer_id
 * @property MarketingChannel $channel
 * @property string $contact An email in lower case, or a phone in E.164.
 * @property MarketingConsentStatus $status
 * @property MarketingConsentSource $source
 * @property string|null $wording The exact text shown when the answer was given.
 * @property string|null $locale The wording's language.
 * @property int|null $recorded_by_staff_id
 * @property CarbonImmutable $created_at
 */
final class MarketingConsent extends Model
{
    use HasPublicId;

    public const null UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'channel',
        'contact',
        'status',
        'source',
        'wording',
        'locale',
        'recorded_by_staff_id',
    ];

    /**
     * The latest event for a contact on a channel: the current answer.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLatestFor(Builder $query, MarketingChannel $channel, string $contact): void
    {
        $query->where('channel', $channel->value)->where('contact', $contact)->orderByDesc('id')->limit(1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MarketingChannel::class,
            'status' => MarketingConsentStatus::class,
            'source' => MarketingConsentSource::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
