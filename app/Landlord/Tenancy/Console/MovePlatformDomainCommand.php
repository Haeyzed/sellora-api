<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Console;

use App\Landlord\Tenancy\Models\Domain;
use App\Landlord\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Moves every store's platform subdomain from an old platform domain to the current one (platform.domain), such as after PLATFORM_DOMAIN changes.
 *
 * A store's subdomain is saved with the platform domain of the day it
 * registered ("ada.sellora.test"), and stores are identified by exactly
 * that host, so changing the platform domain would otherwise leave every
 * existing store unreachable. Custom domains are never touched. Refuses the
 * whole move when any new address is already taken, and changes nothing
 * with --dry-run. Safe to run again.
 */
final class MovePlatformDomainCommand extends Command
{
    protected $signature = 'stores:move-platform-domain
        {from : The old platform domain, such as "sellora.test"}
        {--dry-run : Only list what would change}';

    protected $description = 'Move every store subdomain from an old platform domain to the current one';

    public function handle(): int
    {
        $argument = $this->argument('from');
        $from = is_string($argument) ? strtolower(trim($argument, " \t\n\r\0\x0B.")) : '';
        $to = config()->string('platform.domain');

        if ($from === '' || $from === $to) {
            $this->components->error("Give the old platform domain; the current one is {$to}.");

            return self::FAILURE;
        }

        $moves = $this->movesFrom($from, $to);

        if ($moves === []) {
            $this->components->info("No store subdomain is on {$from}.");

            return self::SUCCESS;
        }

        $taken = Domain::query()->whereIn('domain', array_values($moves))->pluck('domain')->all();

        if ($taken !== []) {
            $this->components->error('These addresses are already in use, so nothing was moved: '.implode(', ', $taken).'.');

            return self::FAILURE;
        }

        foreach ($moves as $old => $new) {
            $this->components->twoColumnDetail($old, $new);
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        Domain::query()->getConnection()->transaction(function () use ($moves): void {
            foreach ($moves as $old => $new) {
                $domain = Domain::query()->where('domain', $old)->sole();
                $domain->forceFill(['domain' => $new])->save();

                activity('stores')
                    ->performedOn(Tenant::query()->findOrFail($domain->tenant_id))
                    ->event('store_domain_moved')
                    ->withProperties(['from' => $old, 'to' => $new])
                    ->log("Moved the store's address from {$old} to {$new}");
            }
        });

        $this->components->info('Moved '.count($moves)." store addresses to {$to}.");

        return self::SUCCESS;
    }

    /**
     * Each store subdomain on the old platform domain, with its new address. Only one level deep, so custom domains such as shop.example.com are never matched.
     *
     * @return array<string, string>
     */
    private function movesFrom(string $from, string $to): array
    {
        $moves = [];

        foreach (Domain::query()->where('domain', 'like', '%.'.$from)->orderBy('id')->pluck('domain') as $domain) {
            $domain = (string) $domain;

            if (! str_ends_with($domain, '.'.$from)) {
                continue;
            }

            $subdomain = substr($domain, 0, -strlen('.'.$from));

            if ($subdomain !== '' && ! str_contains($subdomain, '.')) {
                $moves[$domain] = "{$subdomain}.{$to}";
            }
        }

        return $moves;
    }
}
