<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy;

use App\Landlord\Tenancy\Models\DatabaseServer;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Adds a PostgreSQL server to the pool that new stores' databases are placed on.
 *
 * The password is always typed at a hidden prompt, never passed as an option,
 * so it never ends up in shell history or process lists; it is stored
 * encrypted. The server is only added once a test connection succeeds, so a
 * typo can't send new stores to a server that doesn't work.
 */
final class AddDatabaseServerCommand extends Command
{
    protected $signature = 'platform:add-database-server
        {--name= : A unique name, such as "eu-1"}
        {--region= : The hosting region it serves, from config/platform.php}
        {--host= : Host name or IP address}
        {--port=5432 : Port}
        {--username= : A user allowed to create databases}
        {--capacity= : How many store databases it may hold}
        {--not-accepting : Add it without placing new stores on it yet}';

    protected $description = 'Add a database server to the pool that new stores are placed on';

    private const string TEST_CONNECTION = 'database_server_test';

    public function handle(DatabaseManager $databaseManager): int
    {
        $details = [
            'name' => $this->stringOption('name') ?? text('Name, such as "eu-1"', required: true),
            'region' => $this->stringOption('region') ?? text('Hosting region ('.implode(', ', config()->array('platform.regions')).')', required: true),
            'host' => $this->stringOption('host') ?? text('Host', required: true),
            'port' => $this->stringOption('port') ?? '5432',
            'username' => $this->stringOption('username') ?? text('Username', required: true),
            'capacity' => $this->stringOption('capacity') ?? text('How many store databases it may hold', required: true),
        ];

        $validator = Validator::make($details, [
            'name' => ['required', 'string', 'max:64', Rule::unique(DatabaseServer::class, 'name')],
            'region' => ['required', Rule::in(config()->array('platform.regions'))],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:128'],
            'capacity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $databaseServer = new DatabaseServer([
            'name' => $details['name'],
            'region' => $details['region'],
            'host' => $details['host'],
            'port' => (int) $details['port'],
            'username' => $details['username'],
            'password' => password('Password', required: true),
            'capacity' => (int) $details['capacity'],
            'accepting_new_tenants' => $this->option('not-accepting') !== true,
        ]);

        if (! $this->connects($databaseManager, $databaseServer)) {
            return self::FAILURE;
        }

        $databaseServer->save();

        activity('stores')
            ->performedOn($databaseServer)
            ->event('database_server_added')
            ->withProperties(['region' => $databaseServer->region, 'capacity' => $databaseServer->capacity])
            ->log("Added the database server {$databaseServer->name}");

        $this->components->info("Database server {$databaseServer->name} added to the {$databaseServer->region} region".($databaseServer->accepting_new_tenants ? ', accepting new stores.' : ', not accepting new stores yet.'));

        return self::SUCCESS;
    }

    /**
     * Tries the server with the details given, so only a working server joins the pool.
     */
    private function connects(DatabaseManager $databaseManager, DatabaseServer $databaseServer): bool
    {
        config(['database.connections.'.self::TEST_CONNECTION => $databaseServer->connectionConfig(
            config()->array('database.connections.'.config()->string('tenancy.database.central_connection')),
        )]);

        try {
            $databaseManager->connection(self::TEST_CONNECTION)->select('select 1');

            return true;
        } catch (Throwable $exception) {
            $this->components->error('Could not connect to the server: '.$exception->getMessage());

            return false;
        } finally {
            $databaseManager->purge(self::TEST_CONNECTION);
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
