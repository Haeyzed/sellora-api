<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Models;

use App\Shared\Concerns\HasPublicId;
use App\Tenant\Settings\Concerns\HasStoreTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\Tenant\AttributeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Something the store's variants differ by, such as "Size" or "Colour", with its name in the store's languages.
 *
 * Attributes are shared by the whole store: a product chooses which ones its
 * variants differ by (its options), and each variant has one of each
 * option's values. An attribute or value still used by a product or variant,
 * even one in the trash, can't be deleted, so old variants keep their labels.
 *
 * @property int $id
 * @property string $public_id
 * @property array<string, string> $name By language, such as {"en": "Size"}; read one language with getTranslation().
 * @property int $position Its place in the store's list of attributes, from 0.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, AttributeValue> $values
 */
final class Attribute extends Model implements AuditableContract
{
    use Auditable;

    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    use HasPublicId;
    use HasStoreTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    protected $fillable = [
        'name',
    ];

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'public_id'];

    /**
     * Its values, such as S, M and L, in their order.
     *
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position')->orderBy('id');
    }

    protected static function newFactory(): AttributeFactory
    {
        return AttributeFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
