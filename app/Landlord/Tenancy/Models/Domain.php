<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Models;

use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * A web address that opens a store, such as its platform subdomain or a custom domain.
 *
 * Lives in the central database, because the store has to be found from its
 * domain before its own database is known.
 *
 * @property int $id
 * @property string $domain
 * @property string $tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Domain extends BaseDomain {}
