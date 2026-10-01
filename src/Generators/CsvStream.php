<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Generators;

use EidCloud\FakeData\Schema\TableDefinition;
use RuntimeException;

/**
 * Streams generated synthetic data directly to CSV files per table.
 */
class CsvStream implements StreamInterface
{
    private string $targetDir;
    /** @var resource|null */
    private $currentHandle = null;

    public function __construct(string $targetDir)
    {
        $this->targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR . '/');
        if (!is_dir($this->targetDir)) {
            if (!mkdir($this->targetDir, 0777, true) && !is_dir($this->targetDir)) {
                throw new RuntimeException("Directory \"{$this->targetDir}\" was not created");
            }
        }
    }

    public function begin(): void
    {
    }

    public function startTable(TableDefinition $table): void
    {
        $filePath = $this->targetDir . DIRECTORY_SEPARATOR . $table->name . '.csv';
        $h = fopen($filePath, 'wb');
        if ($h === false) {
            throw new RuntimeException("Unable to open CSV file for writing: {$filePath}");
        }
        $this->currentHandle = $h;

        // Write header
        $colNames = array_keys($table->columns);
        fputcsv($this->currentHandle, $colNames);
    }

    public function writeRow(TableDefinition $table, array $row): void
    {
        if ($this->currentHandle !== null) {
            fputcsv($this->currentHandle, $row);
        }
    }

    public function endTable(TableDefinition $table): void
    {
        if ($this->currentHandle !== null && is_resource($this->currentHandle)) {
            fclose($this->currentHandle);
            $this->currentHandle = null;
        }
    }

    public function finish(): void
    {
    }
}
