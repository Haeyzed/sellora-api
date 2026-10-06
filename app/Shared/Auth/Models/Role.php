<?php

declare(strict_types=1);

namespace App\Shared\Auth\Models;

use App\Shared\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role on either side: a platform role (guard "platform", central database) or a store's staff role (guard "staff", that store's database).
 *
 * The permission package allows one role model for both, so this is it. It
 * adds a public ID for URLs, and an audit trail of its name and permissions.
 * Audits are only kept in stores' databases (there is no central audits
 * table), so platform role changes are not audited here.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $guard_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class Role extends SpatieRole implements AuditableContract
{
    use Auditable {
        readyForAuditing as private auditableIsReadyForAuditing;
    }
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $auditInclude = ['name'];

    /**
     * Audits only inside a store, where the audits table lives.
     */
    public function readyForAuditing(): bool
    {
        return tenancy()->initialized && $this->auditableIsReadyForAuditing();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
