<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A store on the platform. Each store has its own database and one or more domains.
 *
 * Lives in the central database. Store data itself never lives here.
 *
 * @property string $id
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;
}
