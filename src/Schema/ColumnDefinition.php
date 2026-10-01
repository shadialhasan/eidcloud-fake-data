<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Schema;

class ColumnDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public ?string $foreignTable = null,
        public ?string $foreignColumn = null,
        public ?string $providerType = null,
        public array $options = []
    ) {
    }

    public function isForeignKey(): bool
    {
        return $this->foreignTable !== null && $this->foreignColumn !== null;
    }
}
