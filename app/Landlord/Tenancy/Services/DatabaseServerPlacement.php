<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Services;

use App\Landlord\Tenancy\Exceptions\NoDatabaseServerAvailableException;
use App\Landlord\Tenancy\Models\DatabaseServer;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Chooses the database server a new store's database goes on: the least full server in the store's region that is accepting new stores.
 *
 * A server's place is claimed with one conditional update, so two stores
 * registering at once can never push a server past its capacity, and no
 * other server is locked while a choice is made.
 */
final readonly class DatabaseServerPlacement
{
    /** How many times to choose again when another store claimed the last place on the chosen server first. */
    private const int MAX_CLAIM_ATTEMPTS = 5;

    /**
     * Whether a new store could be placed in the region now.
     */
    public function hasRoomIn(string $region): bool
    {
        return $this->serversWithRoomIn($region)->exists();
    }

    /**
     * Places the store on a server, unless it is already on one, so running it again is safe.
     *
     * @throws NoDatabaseServerAvailableException When no server in the region has room.
     */
    public function place(Tenant $tenant): void
    {
        if ($tenant->database_server_id !== null) {
            return;
        }

        for ($attempt = 1; $attempt <= self::MAX_CLAIM_ATTEMPTS; $attempt++) {
            $databaseServer = $this->serversWithRoomIn($tenant->hosting_region)
                ->orderByRaw('tenant_count::numeric / capacity')
                ->orderBy('id')
                ->first();

            if ($databaseServer === null) {
                break;
            }

            if ($this->claimPlaceOn($databaseServer, $tenant)) {
                return;
            }
        }

        throw new NoDatabaseServerAvailableException($tenant->hosting_region);
    }

    /**
     * Takes one place on the server for the store, if the server still has room. Both changes commit together.
     */
    private function claimPlaceOn(DatabaseServer $databaseServer, Tenant $tenant): bool
    {
        return $databaseServer->getConnection()->transaction(function () use ($databaseServer, $tenant): bool {
            $claimed = $this->serversWithRoomIn($tenant->hosting_region)
                ->whereKey($databaseServer->id)
                ->increment('tenant_count');

            if ($claimed !== 1) {
                return false;
            }

            $tenant->forceFill(['database_server_id' => $databaseServer->id])->save();
            $tenant->setRelation('databaseServer', $databaseServer->refresh());

            return true;
        });
    }

    /**
     * @return Builder<DatabaseServer>
     */
    private function serversWithRoomIn(string $region): Builder
    {
        return DatabaseServer::query()
            ->where('region', $region)
            ->where('accepting_new_tenants', true)
            ->whereColumn('tenant_count', '<', 'capacity');
    }
}
