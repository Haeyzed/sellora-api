<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Models\Tenant;
use LogicException;
use Stancl\Tenancy\DatabaseConfig;

/**
 * Points a store's database connection at the server in the pool it was placed on.
 *
 * stancl/tenancy builds each store's connection from a template connection.
 * For a placed store, the template is a connection to its own server, built
 * at runtime from the server's record, so adding a server needs no config
 * change. A store not placed on a server (only in tests) uses the central
 * server.
 */
final class TenantDatabaseConfig extends DatabaseConfig
{
    public function getTemplateConnectionName(): string
    {
        $tenant = $this->tenant;

        if (! $tenant instanceof Tenant || $tenant->database_server_id === null) {
            return parent::getTemplateConnectionName();
        }

        // Loaded once per store instance; stancl asks for the connection several times per request.
        $databaseServer = $tenant->databaseServer ?? throw new LogicException("Store {$tenant->id} is placed on a database server that no longer exists.");
        $connectionName = 'database_server_'.$databaseServer->id;

        // Set every time, so changed credentials apply without restarting workers.
        config(["database.connections.{$connectionName}" => $databaseServer->connectionConfig(
            config()->array('database.connections.'.parent::getTemplateConnectionName()),
        )]);

        return $connectionName;
    }
}
