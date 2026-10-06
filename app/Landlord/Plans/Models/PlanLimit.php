<?php

declare(strict_types=1);

namespace App\Landlord\Plans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * How many of one limited thing a plan allows, such as 250 products. A null value means unlimited.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $limit_key One of the keys in config/features.php.
 * @property int|null $limit_value
 * @property-read Plan $plan
 */
final class PlanLimit extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $fillable = [
        'limit_key',
        'limit_value',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
        ];
    }
}
