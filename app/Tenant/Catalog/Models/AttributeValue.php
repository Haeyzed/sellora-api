<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\AttributeValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One choice of an attribute, such as "M" for Size or "Blue" for Colour, with its label in the store's languages.
 *
 * @property int $id
 * @property string $public_id
 * @property int $attribute_id
 * @property array<string, string> $label By language, such as {"en": "Blue", "fr": "Bleu"}; read one language with getTranslation().
 * @property int $position Its place among the attribute's values, from 0.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Attribute $attribute
 */
final class AttributeValue extends Model implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory;

    use HasPublicId;
    use HasStoreTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['label'];

    protected $fillable = [
        'label',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * The attribute it is a value of.
     *
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    protected static function newFactory(): AttributeValueFactory
    {
        return AttributeValueFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attribute_id' => 'integer',
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
