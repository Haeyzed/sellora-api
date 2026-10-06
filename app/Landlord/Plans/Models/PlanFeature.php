<?php

declare(strict_types=1);

namespace App\Landlord\Plans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One module or integration a plan includes, such as "hr" on the Growth plan.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $feature_key
 * @property-read Plan $plan
 */
final class PlanFeature extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $fillable = [
        'feature_key',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
