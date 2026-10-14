<?php

declare(strict_types=1);

namespace App\Shared\Geography;

use Illuminate\Console\Command;
use JsonException;

/**
 * Lists the subdivision codes the installed commerceguys/addressing adds or removes compared with the recorded ones (section 3.3).
 *
 * Store addresses, customer addresses and tax rates keep these codes, so a
 * code the package renames or removes would leave saved rows that no longer
 * match. A test fails while this lists anything. Before recording the new
 * codes with --record, write a store migration that moves saved rows from
 * each removed code to its replacement.
 */
final class ListSubdivisionChangesCommand extends Command
{
    protected $signature = 'geography:subdivision-changes {--record : Record the installed codes as the ones stores may have saved}';

    protected $description = 'List subdivision codes the address data changed since they were recorded';

    /**
     * @throws JsonException
     */
    public function handle(Geography $geography): int
    {
        $path = config()->string('geography.subdivision_codes_path');
        $installed = $geography->subdivisionCodes();

        if ($this->option('record')) {
            file_put_contents($path, json_encode($installed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
            $this->components->info('Recorded the subdivision codes of '.count($installed).' countries.');

            return self::SUCCESS;
        }

        /** @var array<string, list<string>> $recorded */
        $recorded = is_file($path) ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : [];
        $rows = [];

        foreach (array_unique([...array_keys($recorded), ...array_keys($installed)]) as $countryCode) {
            $removed = array_values(array_diff($recorded[$countryCode] ?? [], $installed[$countryCode] ?? []));
            $added = array_values(array_diff($installed[$countryCode] ?? [], $recorded[$countryCode] ?? []));

            if ($removed !== [] || $added !== []) {
                $rows[] = [$countryCode, implode(', ', $removed) ?: '-', implode(', ', $added) ?: '-'];
            }
        }

        if ($rows === []) {
            $this->components->info('No subdivision codes changed.');

            return self::SUCCESS;
        }

        sort($rows);
        $this->table(['Country', 'Removed (saved rows need migrating)', 'Added'], $rows);
        $this->components->error(count($rows).' countries changed. Migrate saved rows from removed codes, then run with --record.');

        return self::FAILURE;
    }
}
