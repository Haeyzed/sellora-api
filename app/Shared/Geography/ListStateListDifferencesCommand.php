<?php

declare(strict_types=1);

namespace App\Shared\Geography;

use Illuminate\Console\Command;

/**
 * Lists the countries whose states list (nnjeim/world) and address data (commerceguys/addressing) disagree, where a state is therefore never required.
 *
 * Run it after updating either package, to see which countries still need a decision.
 */
final class ListStateListDifferencesCommand extends Command
{
    protected $signature = 'geography:state-differences';

    protected $description = 'List countries whose states list and address data disagree about their states';

    public function handle(AddressFormats $addressFormats): int
    {
        $differences = $addressFormats->stateListDifferences();

        $this->table(
            ['Country', 'Address data requires a state', 'Only in the states list', 'Only in the address data'],
            array_map(static fn (StateListDifference $difference): array => [
                $difference->countryCode,
                $difference->addressDataRequiresState ? 'yes' : 'no',
                implode(', ', $difference->onlyInStatesList),
                implode(', ', $difference->onlyInAddressData),
            ], $differences),
        );

        $this->components->info(count($differences).' countries differ; their addresses never require a state.');

        return self::SUCCESS;
    }
}
