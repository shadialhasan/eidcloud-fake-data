<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Generators;

use EidCloud\FakeData\Schema\TableDefinition;

interface StreamInterface
{
    public function begin(): void;
    public function startTable(TableDefinition $table): void;
    public function writeRow(TableDefinition $table, array $row): void;
    public function endTable(TableDefinition $table): void;
    public function finish(): void;
}
