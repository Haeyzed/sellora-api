<?php

declare(strict_types=1);

namespace Tests\Fixtures\Translations;

use App\Tenant\Settings\Concerns\HasStoreTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in for a store model with customer-facing text, such as a product, on a table the test creates.
 *
 * @property array<string, string> $name
 */
final class TranslatedSample extends Model
{
    use HasStoreTranslations;

    public const string TABLE = 'translated_samples';

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    public $timestamps = false;

    protected $table = self::TABLE;

    protected $guarded = [];
}
