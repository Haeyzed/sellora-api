<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A label staff put on an order to find it again, such as "gift" or "wholesale", in lower case.
 *
 * @property int $order_id
 * @property string $tag
 */
final class OrderTag extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'tag';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['order_id' => 'integer'];
    }
}
