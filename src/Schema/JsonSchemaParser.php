<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Schema;

use InvalidArgumentException;

/**
 * Parses JSON Schema definition for database tables and relations.
 */
class JsonSchemaParser
{
    public function parse(string $jsonString): DatabaseSchema
    {
        $data = json_decode($jsonString, true);
        if (!is_array($data)) {
            throw new InvalidArgumentException("Invalid JSON schema provided.");
        }

        $schema = new DatabaseSchema();
        $tables = $data['tables'] ?? $data;

        foreach ($tables as $key => $tableDef) {
            $tableName = $tableDef['name'] ?? (is_string($key) ? $key : "table_{$key}");
            $count = (int) ($tableDef['count'] ?? $tableDef['rows'] ?? 100);

            $table = new TableDefinition($tableName, [], $count);

            $columns = $tableDef['columns'] ?? [];
            foreach ($columns as $colKey => $colDef) {
                $colName = $colDef['name'] ?? (is_string($colKey) ? $colKey : "col_{$colKey}");
                $type = $colDef['type'] ?? 'string';
                $nullable = (bool) ($colDef['nullable'] ?? false);
                $isPk = (bool) ($colDef['primary_key'] ?? $colDef['pk'] ?? false);
                $autoInc = (bool) ($colDef['auto_increment'] ?? false);
                $foreignTable = $colDef['foreign_table'] ?? $colDef['references']['table'] ?? null;
                $foreignCol = $colDef['foreign_column'] ?? $colDef['references']['column'] ?? null;
                $provider = $colDef['provider'] ?? $colDef['faker'] ?? null;
                $options = $colDef['options'] ?? [];

                $column = new ColumnDefinition(
                    name: $colName,
                    type: $type,
                    nullable: $nullable,
                    primaryKey: $isPk,
                    autoIncrement: $autoInc,
                    foreignTable: $foreignTable,
                    foreignColumn: $foreignCol,
                    providerType: $provider,
                    options: $options
                );

                $table->addColumn($column);
            }

            $schema->addTable($table);
        }

        return $schema;
    }
}
