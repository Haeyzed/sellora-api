<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use App\Shared\Privacy\Contracts\StoreExportFileSource;
use LogicException;

/**
 * Decides, column by column, what a full store export contains. It fails closed: a column nobody classified is never exported.
 *
 * Every domain and module that owns store tables classifies each of their
 * columns: included, included with secret values removed (JSON such as audit
 * records), or excluded with a reason. Secrets (password and PIN hashes,
 * tokens, 2FA secrets, recovery codes, gateway credentials) are always
 * excluded. A test fails when any column of any store table is unclassified,
 * and an export refuses to run if one slips through, so a new column is never
 * exported by accident. Domains with uploaded files add a file source.
 */
final class StoreExportRegistry
{
    /**
     * @var array<string, StoreExportTable>
     */
    private array $tables = [];

    /**
     * @var list<class-string<StoreExportFileSource>>
     */
    private array $fileSources = [];

    /**
     * @param  list<string>  $include  Columns exported as stored.
     * @param  array<string, string>  $exclude  Columns never exported, with the reason.
     * @param  array<string, string>  $redact  JSON columns exported with secret values removed, with the reason.
     *
     * @throws LogicException When the table is already classified, or a column is given two ways.
     */
    public function table(string $table, array $include, array $exclude = [], array $redact = []): void
    {
        $this->ensureNew($table);

        $columns = [...$include, ...array_keys($exclude), ...array_keys($redact)];

        if (count($columns) !== count(array_unique($columns))) {
            throw new LogicException("A column of the [{$table}] store table is classified more than one way.");
        }

        $this->tables[$table] = new StoreExportTable($table, $include, $redact, $exclude);
    }

    /**
     * Leaves a whole table out of exports, such as sign-in tokens.
     *
     * @throws LogicException When the table is already classified.
     */
    public function excludeTable(string $table, string $reason): void
    {
        $this->ensureNew($table);

        $this->tables[$table] = new StoreExportTable($table, excludedTableReason: $reason);
    }

    /**
     * @param  class-string<StoreExportFileSource>  $fileSource
     */
    public function addFileSource(string $fileSource): void
    {
        $this->fileSources[] = $fileSource;
    }

    /**
     * @return array<string, StoreExportTable>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    /**
     * @return list<class-string<StoreExportFileSource>>
     */
    public function fileSources(): array
    {
        return $this->fileSources;
    }

    /**
     * The columns of the given tables that nobody classified, as "table.column"; a table nobody registered is listed as "table.*".
     *
     * @param  array<string, list<string>>  $columnsByTable  The store database's real columns, keyed by table.
     * @return list<string>
     */
    public function unclassified(array $columnsByTable): array
    {
        $unclassified = [];

        foreach ($columnsByTable as $table => $columns) {
            $classification = $this->tables[$table] ?? null;

            if ($classification === null) {
                $unclassified[] = "{$table}.*";

                continue;
            }

            foreach ($columns as $column) {
                if (! $classification->classifies($column)) {
                    $unclassified[] = "{$table}.{$column}";
                }
            }
        }

        return $unclassified;
    }

    /**
     * @throws LogicException
     */
    private function ensureNew(string $table): void
    {
        if (isset($this->tables[$table])) {
            throw new LogicException("The [{$table}] store table is already classified for exports.");
        }
    }
}
