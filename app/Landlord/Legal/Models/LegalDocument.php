<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Models;

use App\Landlord\Legal\Enums\LegalDocumentType;
use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Landlord\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One version of one of Sellora's legal documents, such as version "2026-10" of the terms of service.
 *
 * A version is a draft until it is published, and can't change after that;
 * changes need a new version. The version in force for a type is the
 * published one with the latest effective date that has arrived, so a
 * version published to take effect next month leaves the current one in
 * force until then.
 *
 * @property int $id
 * @property string $public_id
 * @property LegalDocumentType $type
 * @property string $version
 * @property string $title
 * @property string $body
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $effective_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class LegalDocument extends Model
{
    use CentralConnection;

    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'type',
        'version',
        'title',
        'body',
    ];

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * The version of each type in force now. Types with no version in force yet are missing.
     *
     * @return array<string, self> Keyed by type.
     */
    public static function inForce(): array
    {
        $inForce = [];

        foreach (LegalDocumentType::cases() as $type) {
            $document = self::query()->inForceFor($type)->first();

            if ($document !== null) {
                $inForce[$type->value] = $document;
            }
        }

        return $inForce;
    }

    /**
     * The version of a type in force now, first.
     *
     * @param  Builder<self>  $query
     */
    protected function scopeInForceFor(Builder $query, LegalDocumentType $type): void
    {
        $query->where('type', $type)
            ->whereNotNull('published_at')
            ->where('effective_at', '<=', CarbonImmutable::now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id');
    }

    protected static function newFactory(): LegalDocumentFactory
    {
        return LegalDocumentFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'published_at' => 'immutable_datetime',
            'effective_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
