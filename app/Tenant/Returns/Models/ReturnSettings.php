<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * How the store takes returns. One row, absent until staff first change it: until then the defaults in config/returns.php apply.
 *
 * @property int $id Always 1.
 * @property int $window_days How many days after a line's delivery a buyer may ask to return it; 0 means only staff can start returns.
 * @property bool $customer_requests Whether customers and guests may ask for returns themselves; staff always can.
 * @property bool $photos_required Whether a buyer must add a photo when the reason is damaged or the wrong item.
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
     * The store's settings as saved, or the defaults when it never changed them (not saved).
     */
    public static function current(): self
    {
        return self::query()->find(1) ?? (new self)->forceFill([
            'id' => 1,
            'window_days' => config()->integer('returns.window_days'),
            'customer_requests' => config()->boolean('returns.customer_requests'),
            'photos_required' => config()->boolean('returns.photos_required'),
        ]);
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
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
