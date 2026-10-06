<?php

declare(strict_types=1);

namespace App\Landlord\Plans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
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
final class PlanLimit extends Model implements AuditableContract
{
    use Auditable;
    use CentralConnection;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $auditInclude = ['limit_key', 'limit_value'];

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
