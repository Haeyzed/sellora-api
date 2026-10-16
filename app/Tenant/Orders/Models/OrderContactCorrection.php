<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff's correction of an order's contact details, such as a mistyped email: a new version, never a change to what the order was placed with.
 *
 * Rows are only ever added; erasure alone may blank them. A corrected email
 * never links the order to an account and never changes whose first order it
 * was: those go by the email it was placed with.
 *
 * @property int $id
 * @property int $order_id
 * @property string|null $email In lower case; null once anonymised.
 * @property string|null $name
 * @property string|null $phone In E.164 format.
 * @property string $created_by_type Who corrected it, by morph name.
 * @property string $created_by_id
 * @property CarbonImmutable $created_at
 */
final class OrderContactCorrection extends Model
{
    public const ?string UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
