<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

/**
 * How one store table appears in a store export: the columns exported as they are, those exported with secret values removed, and those left out with the reason why.
 *
 * A table left out entirely has a reason and no columns, which covers every
 * column it has now or gains later.
 */
final readonly class StoreExportTable
{
    /**
     * @param  list<string>  $included  Exported as stored.
     * @param  array<string, string>  $redacted  JSON columns exported with secret values removed, keyed by column, with the reason.
     * @param  array<string, string>  $excluded  Never exported, keyed by column, with the reason.
     * @param  string|null  $excludedTableReason  Set when the whole table is left out.
     */
    public function __construct(
        public string $name,
        public array $included = [],
        public array $redacted = [],
        public array $excluded = [],
        public ?string $excludedTableReason = null,
    ) {}

    public function isExcluded(): bool
    {
        return $this->excludedTableReason !== null;
    }

    public function classifies(string $column): bool
    {
        return $this->isExcluded()
            || in_array($column, $this->included, true)
            || array_key_exists($column, $this->redacted)
            || array_key_exists($column, $this->excluded);
    }

    /**
     * The columns read for the export, in the order written.
     *
     * @return list<string>
     */
    public function exportedColumns(): array
    {
        return [...$this->included, ...array_keys($this->redacted)];
    }
}
