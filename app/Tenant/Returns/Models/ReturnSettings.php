<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use App\Tenant\Returns\Exceptions\MissingReturnSettingsException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * How the store takes returns. One row, created with the store from the defaults in config/returns.php (ReturnSettingsDefaults), never on read.
 *
 * @property int $id Always 1.
 * @property int $window_days How many days after a line's delivery a buyer may ask to return it; 0 means only staff can start returns.
 * @property bool $customer_requests Whether customers and guests may ask for returns themselves; staff always can.
 * @property bool $photos_required Whether a buyer must add a photo when the reason claims the store was at fault.
 * @property bool $charge_shipping_on_exchanges Whether the buyer pays for an exchange's replacement delivery when the store wasn't at fault; always free when it was.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class ReturnSettings extends Model implements AuditableContract
{
    use Auditable;

    public $incrementing = false;

    protected $table = 'return_settings';

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'updated_at'];

    /**
     * The store's return settings.
     *
     * @throws MissingReturnSettingsException When the store has none: a bug, never filled in with defaults.
     */
    public static function current(): self
    {
        return self::query()->find(1) ?? throw new MissingReturnSettingsException;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'window_days' => 'integer',
            'customer_requests' => 'boolean',
            'photos_required' => 'boolean',
            'charge_shipping_on_exchanges' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
