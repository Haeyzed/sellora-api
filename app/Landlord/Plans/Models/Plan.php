<?php

declare(strict_types=1);

namespace App\Landlord\Plans\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Database\Factories\Landlord\PlanFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A plan merchants subscribe to, such as "Starter" or "Growth": which modules and integrations it includes, and its usage limits.
 *
 * @property int $id
 * @property string $public_id
 * @property string $code Stable identifier used in config and by the platform team, such as "starter".
 * @property string $name
 * @property bool $is_active Whether stores can be put on this plan.
 * @property bool $is_public Whether merchants can see and choose this plan themselves.
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, PlanFeature> $features
 * @property-read Collection<int, PlanLimit> $limits
 */
final class Plan extends Model
{
    use CentralConnection;

    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use HasPublicId;

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'is_public',
        'sort_order',
    ];

    /**
     * The modules and integrations this plan includes.
     *
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * How many of each limited thing (products, staff accounts...) this plan allows.
     *
     * @return HasMany<PlanLimit, $this>
     */
    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
