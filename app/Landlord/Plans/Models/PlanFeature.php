<?php

declare(strict_types=1);

namespace App\Landlord\Plans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One module or integration a plan includes, such as "hr" on the Growth plan.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $feature_key
 * @property-read Plan $plan
 */
final class PlanFeature extends Model implements AuditableContract
{
    use Auditable;
    use CentralConnection;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $auditInclude = ['feature_key'];

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
