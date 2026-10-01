<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Schema;

class TableDefinition
{
    /**
     * @param string $name
     * @param array<string, ColumnDefinition> $columns
     * @param int $rowCount
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public int $rowCount = 100
    ) {
    }

    public function addColumn(ColumnDefinition $column): self
    {
        $this->columns[$column->name] = $column;
        return $this;
    }

    public function getPrimaryKey(): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->primaryKey) {
                return $column;
            }
        }
        return null;
    }

    /**
     * @return array<string> List of table names this table depends on
     */
    public function getDependencies(): array
    {
        $deps = [];
        foreach ($this->columns as $column) {
            if ($column->isForeignKey() && $column->foreignTable !== null) {
                // Ignore self-references in dependency ordering or handle carefully
                if ($column->foreignTable !== $this->name && !in_array($column->foreignTable, $deps, true)) {
                    $deps[] = $column->foreignTable;
                }
            }
        }
        return $deps;
    }
}
