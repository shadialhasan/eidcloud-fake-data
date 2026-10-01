<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Schema;

class DatabaseSchema
{
    /**
     * @var array<string, TableDefinition>
     */
    public array $tables = [];

    public function addTable(TableDefinition $table): self
    {
        $this->tables[$table->name] = $table;
        return $this;
    }

    public function getTable(string $name): ?TableDefinition
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * @return array<string, TableDefinition>
     */
    public function getTables(): array
    {
        return $this->tables;
    }
}
