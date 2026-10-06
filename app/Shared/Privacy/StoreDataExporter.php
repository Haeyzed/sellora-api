<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use App\Shared\Privacy\Exceptions\UnclassifiedStoreDataException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use stdClass;

/**
 * Writes everything a store holds into a folder, as the registry classifies it: one JSON Lines file per table, the store's files, and a manifest.
 *
 * Runs while tenancy is initialized for the store, on its own database. It
 * checks the store's real tables first and refuses to export anything if a
 * column is unclassified, so a column added later is never exported by
 * accident. Tables are read in chunks, so a large store never fills memory.
 */
final readonly class StoreDataExporter
{
    public const int FORMAT_VERSION = 1;

    private const int CHUNK_SIZE = 500;

    public function __construct(
        private StoreExportRegistry $registry,
        private SecretRedactor $secretRedactor,
        private DatabaseManager $databases,
        private Container $container,
    ) {}

    /**
     * @param  string  $directory  An empty folder to write into.
     * @return array<string, mixed> The manifest, also written as manifest.json.
     *
     * @throws UnclassifiedStoreDataException When a store table or column isn't classified.
     * @throws RuntimeException When a file can't be written.
     */
    public function writeTo(string $directory): array
    {
        $connection = $this->databases->connection();
        $this->ensureEverythingIsClassified($connection);

        $exportedTables = [];

        foreach ($this->registry->tables() as $table) {
            if (! $table->isExcluded()) {
                $exportedTables[] = ['name' => $table->name, 'rows' => $this->writeTable($connection, $table, $directory), 'columns' => $table->exportedColumns()];
            }
        }

        $manifest = [
            'format_version' => self::FORMAT_VERSION,
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'tables' => $exportedTables,
            'files' => $this->writeFiles($directory),
            'left_out' => $this->leftOut(),
        ];

        $this->write($directory.'/manifest.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $manifest;
    }

    /**
     * The store's real tables and columns, by table.
     *
     * @return array<string, list<string>>
     */
    public function storeColumns(Connection $connection): array
    {
        $schema = $connection->getSchemaBuilder();
        $columnsByTable = [];

        foreach ($schema->getTableListing(schemaQualified: false) as $table) {
            $columnsByTable[$table] = $schema->getColumnListing($table);
        }

        return $columnsByTable;
    }

    /**
     * @throws UnclassifiedStoreDataException
     */
    private function ensureEverythingIsClassified(Connection $connection): void
    {
        $unclassified = $this->registry->unclassified($this->storeColumns($connection));

        if ($unclassified !== []) {
            throw new UnclassifiedStoreDataException($unclassified);
        }
    }

    /**
     * @return int How many rows were written.
     */
    private function writeTable(Connection $connection, StoreExportTable $table, string $directory): int
    {
        $path = $directory.'/tables/'.$table->name.'.jsonl';
        $this->ensureDirectory(dirname($path));
        $handle = fopen($path, 'wb') ?: throw new RuntimeException("Can't write {$path}.");
        $rows = 0;

        try {
            foreach ($this->rowsOf($connection, $table) as $row) {
                fwrite($handle, json_encode($this->exportedRow($table, (array) $row), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                $rows++;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * In chunks: by ID where the table has one, otherwise in the order of its exported columns (link tables, whose columns are all plain keys).
     *
     * @return LazyCollection<int, stdClass>
     */
    private function rowsOf(Connection $connection, StoreExportTable $table): LazyCollection
    {
        $query = $connection->table($table->name)->select($table->exportedColumns());

        if (in_array('id', $table->included, true)) {
            return $query->lazyById(self::CHUNK_SIZE);
        }

        foreach ($table->included as $column) {
            $query->orderBy($column);
        }

        return $query->lazy(self::CHUNK_SIZE);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function exportedRow(StoreExportTable $table, array $row): array
    {
        foreach (array_keys($table->redacted) as $column) {
            $value = $row[$column] ?? null;
            $row[$column] = $this->secretRedactor->redactJson(is_string($value) ? $value : null);
        }

        return $row;
    }

    /**
     * @return int How many files were written.
     */
    private function writeFiles(string $directory): int
    {
        $written = 0;

        foreach ($this->registry->fileSources() as $fileSourceClass) {
            foreach ($this->container->make($fileSourceClass)->files() as $file) {
                $this->copyFile($file, $directory.'/files/'.ltrim($file->path, '/'));
                $written++;
            }
        }

        return $written;
    }

    private function copyFile(ExportedFile $file, string $path): void
    {
        $this->ensureDirectory(dirname($path));
        $source = ($file->openStream)();
        $target = fopen($path, 'wb') ?: throw new RuntimeException("Can't write {$path}.");

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($target);
            fclose($source);
        }
    }

    /**
     * What the export leaves out and why, so whoever reads it knows nothing is silently missing.
     *
     * @return array<string, string|array<string, string>>
     */
    private function leftOut(): array
    {
        $leftOut = [];

        foreach ($this->registry->tables() as $table) {
            if ($table->isExcluded()) {
                $leftOut[$table->name] = (string) $table->excludedTableReason;
            } elseif ($table->excluded !== [] || $table->redacted !== []) {
                $leftOut[$table->name] = [...$table->excluded, ...array_map(static fn (string $reason): string => 'Secret values removed: '.$reason, $table->redacted)];
            }
        }

        return $leftOut;
    }

    private function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Can't write {$path}.");
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Can't create {$directory}.");
        }
    }
}
