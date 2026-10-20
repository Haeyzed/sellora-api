<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Models;

use App\Tenant\Payments\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * How the store has set up one payment method paid by hand: whether it is offered, how long a buyer has to pay, and what they are told. A method never set up has no row and is off, with the defaults in config/payments.php.
 *
 * @property int $id
 * @property PaymentMethod $method
 * @property bool $enabled
 * @property int $deadline_hours
 * @property int|null $collection_alert_hours Cash on delivery only.
 * @property string|null $instructions
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class PaymentMethodSetting extends Model implements AuditableContract
{
    use Auditable;

    /** Times are saved with their offset, so a time in any timezone means the same moment. */
    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @var list<string>
     */
    protected $auditExclude = ['id', 'updated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'enabled' => 'boolean',
            'deadline_hours' => 'integer',
            'collection_alert_hours' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
