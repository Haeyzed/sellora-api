<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * The record that someone accepted one version of a legal document: who, for which store, when, and from where.
 *
 * Never changed after it is written; it is evidence of the contract.
 *
 * @property int $id
 * @property int $legal_document_id
 * @property int|null $store_registration_id Set while the sign-up hasn't become a store.
 * @property string|null $tenant_id
 * @property string $accepted_by_name
 * @property string $accepted_by_email
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $accepted_at
 * @property-read LegalDocument $legalDocument
 */
final class LegalAcceptance extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $fillable = [
        'legal_document_id',
        'store_registration_id',
        'accepted_by_name',
        'accepted_by_email',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    /**
     * @return BelongsTo<LegalDocument, $this>
     */
    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
