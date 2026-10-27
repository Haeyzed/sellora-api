<?php

declare(strict_types=1);

namespace App\Shared\Database;

use Illuminate\Database\Connection;

/**
 * Reads a database's tables and columns, with the comments that describe them, from PostgreSQL's catalog.
 *
 * The comments are written by migrations (CLAUDE.md section 12), so this is
 * the field reference: nothing about the schema is written by hand twice.
 */
final readonly class TableDescriptions
{
    /**
     * Every table in the connection's schema, by name, with its comment and its columns in order.
     *
     * @return array<string, array{comment: string|null, columns: array<string, array{type: string, nullable: bool, comment: string|null}>}>
     */
    public function of(Connection $connection): array
    {
        $rows = $connection->select(<<<'SQL'
            select c.relname as table_name,
                   obj_description(c.oid, 'pg_class') as table_comment,
                   a.attname as column_name,
                   format_type(a.atttypid, a.atttypmod) as type,
                   not a.attnotnull as nullable,
                   col_description(c.oid, a.attnum) as column_comment
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace and n.nspname = current_schema()
            join pg_attribute a on a.attrelid = c.oid and a.attnum > 0 and not a.attisdropped
            where c.relkind in ('r', 'p')
            order by c.relname, a.attnum
            SQL);

        $tables = [];

        foreach ($rows as $row) {
            $tables[$row->table_name]['comment'] = $row->table_comment;
            $tables[$row->table_name]['columns'][$row->column_name] = [
                'type' => $row->type,
                'nullable' => (bool) $row->nullable,
                'comment' => $row->column_comment,
            ];
        }

        return $tables;
    }
}
