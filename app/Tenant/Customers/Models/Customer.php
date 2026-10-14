<?php

declare(strict_types=1);

namespace App\Tenant\Customers\Models;

use App\Shared\Auth\PasswordResetLinkNotification;
use App\Shared\Concerns\HasPublicId;
use App\Tenant\Pricing\Data\TaxCustomer;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\CustomerFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A shopper with an account at one store. The same person at two stores has two separate accounts.
 *
 * Signs in on the store's storefront with the "customer" guard, and can only
 * ever see their own data.
 *
 * @property int $id
 * @property string $public_id
 * @property string|null $name Null only once erased.
 * @property string|null $email Null only once erased, which frees it for a new account.
 * @property string|null $phone In E.164 format, such as +2348012345678.
 * @property string|null $locale One of the store's content languages; emails to the customer use it.
 * @property CarbonImmutable|null $email_verified_at When the customer proved they receive mail at their current email.
 * @property bool $is_tax_exempt Set by staff only (customers.tax_exemption), after checking the tax ID.
 * @property string|null $tax_id Entered by the customer, normalised and format-checked only; never verified with a registry.
 * @property string|null $tax_id_country ISO 3166-1 alpha-2; set exactly when tax_id is (a database constraint).
 * @property int|null $default_shipping_address_id Always one of the customer's own addresses (a database constraint).
 * @property int|null $default_billing_address_id Always one of the customer's own addresses (a database constraint).
 * @property string|null $password Null only once erased.
 * @property CarbonImmutable|null $erased_at When the customer's personal data was erased; every personal field is then empty (a database constraint).
 * @property bool $is_active
 * @property CarbonImmutable|null $last_signed_in_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property string $search_text Name, email and phone in lower case, for staff search; kept current when saved.
 * @property-read Collection<int, CustomerEmailToken> $emailTokens
 * @property-read Collection<int, CustomerAddress> $addresses
 * @property-read Collection<int, CustomerNote> $notes
 * @property-read Collection<int, CustomerDataExport> $dataExports
 * @property-read Collection<int, CustomerGroup> $groups
 */
final class Customer extends User implements AuditableContract, HasApiTokensContract, HasLocalePreference
{
    use Auditable {
        readyForAuditing as private auditableIsReady;
    }
    use HasApiTokens;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use HasPublicId;
    use Notifiable;
    use SoftDeletes;

    public const string GUARD = 'customer';

    public const string PASSWORD_BROKER = 'customers';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'locale',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'search_text',
    ];

    /**
     * Only changes to whether the account may be used and whether it is tax-exempt are audited. Profile changes go in the activity log without personal data, so no audit record ever holds a customer's details or a secret.
     *
     * @var list<string>
     */
    protected $auditEvents = ['updated'];

    /**
     * @var list<string>
     */
    protected $auditInclude = ['is_active', 'is_tax_exempt'];

    /**
     * Emails a link into the storefront to choose a new password. An erased customer has no email, so the broker never finds one.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        if ($this->email !== null) {
            $this->notify(PasswordResetLinkNotification::forBroker(self::PASSWORD_BROKER, $token, $this->email));
        }
    }

    /**
     * The language emails to the customer are written in; without one, the language of the request that sends them.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * What tax needs to know about this customer when they buy.
     */
    public function taxCustomer(): TaxCustomer
    {
        return new TaxCustomer($this->is_tax_exempt, $this->tax_id, $this->tax_id_country);
    }

    public function isErased(): bool
    {
        return $this->erased_at !== null;
    }

    /**
     * @return HasMany<CustomerDataExport, $this>
     */
    public function dataExports(): HasMany
    {
        return $this->hasMany(CustomerDataExport::class);
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * @return HasMany<CustomerEmailToken, $this>
     */
    public function emailTokens(): HasMany
    {
        return $this->hasMany(CustomerEmailToken::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Audits an update only when an audited field changed, so signing in or editing the profile writes no empty audit record.
     */
    public function readyForAuditing(): bool
    {
        return $this->auditableIsReady() && $this->wasChanged($this->auditInclude);
    }

    /**
     * @return HasMany<CustomerNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * The groups staff put the customer in.
     *
     * @return BelongsToMany<CustomerGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_members');
    }

    /**
     * Customers whose name, email or phone contains every word, ignoring case.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMatching(Builder $query, string $words): void
    {
        foreach (preg_split('/\s+/', mb_strtolower(trim($words)), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $query->where('search_text', 'like', '%'.addcslashes($word, '%_\\').'%');
        }
    }

    protected static function booted(): void
    {
        self::saving(static function (self $customer): void {
            $customer->search_text = mb_strtolower(implode(' ', array_filter([$customer->name, $customer->email, $customer->phone])));
        });
    }

    /**
     * Trimmed and lower-cased like every account's (HasNormalisedEmail), and null once the customer's personal data is erased.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $email): ?string => $email === null ? null : mb_strtolower(trim($email)),
        );
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_tax_exempt' => 'boolean',
            'email_verified_at' => 'immutable_datetime',
            'erased_at' => 'immutable_datetime',
            'last_signed_in_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
