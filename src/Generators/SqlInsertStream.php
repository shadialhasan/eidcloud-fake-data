<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Generators;

use EidCloud\FakeData\Schema\TableDefinition;

/**
 * High-speed SQL INSERT stream with multi-row batching.
 * Generates transactional, optimized SQL with chunked INSERT statements.
 */
class SqlInsertStream implements StreamInterface
{
    /** @var resource */
    private $handle;
    private bool $ownsHandle = false;
    private int $batchSize;
    private array $buffer = [];
    private ?TableDefinition $currentTable = null;

    /**
     * @param resource|string $target File path or open stream resource
     * @param int $batchSize Number of rows per INSERT INTO statement
     */
    public function __construct($target = STDOUT, int $batchSize = 500)
    {
        $this->batchSize = $batchSize;
        if (is_resource($target)) {
            $this->handle = $target;
        } else {
            $h = fopen($target, 'wb');
            if ($h === false) {
                throw new \RuntimeException("Unable to open output target: {$target}");
            }
            $this->handle = $h;
            $this->ownsHandle = true;
        }
    }

    public function begin(): void
    {
        fwrite($this->handle, "-- EidCloud Relational Synthetic Data Export\n");
        fwrite($this->handle, "-- Generated at: " . date('Y-m-d H:i:s') . "\n");
        fwrite($this->handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
        fwrite($this->handle, "SET NAMES utf8mb4;\n\n");
    }

    public function startTable(TableDefinition $table): void
    {
        $this->flush();
        $this->currentTable = $table;
        fwrite($this->handle, "-- --------------------------------------------------------\n");
        fwrite($this->handle, "-- Dumping data for table `{$table->name}`\n");
        fwrite($this->handle, "-- --------------------------------------------------------\n\n");
    }

    public function writeRow(TableDefinition $table, array $row): void
    {
        $this->buffer[] = $row;
        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function endTable(TableDefinition $table): void
    {
        $this->flush();
        fwrite($this->handle, "\n");
        $this->currentTable = null;
    }

    public function finish(): void
    {
        $this->flush();
        fwrite($this->handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fwrite($this->handle, "-- Generation complete.\n");

        if ($this->ownsHandle && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    private function flush(): void
    {
        if (empty($this->buffer) || $this->currentTable === null) {
            $this->buffer = [];
            return;
        }

        $cols = array_keys($this->buffer[0]);
        $escapedCols = array_map(fn($c) => "`" . str_replace("`", "``", $c) . "`", $cols);
        $header = "INSERT INTO `{$this->currentTable->name}` (" . implode(', ', $escapedCols) . ") VALUES\n";
        fwrite($this->handle, $header);

        $rowCount = count($this->buffer);
        for ($i = 0; $i < $rowCount; $i++) {
            $row = $this->buffer[$i];
            $values = [];
            foreach ($cols as $col) {
                $val = $row[$col] ?? null;
                $values[] = $this->quoteValue($val);
            }
            $line = "  (" . implode(', ', $values) . ")";
            $line .= ($i === $rowCount - 1) ? ";\n" : ",\n";
            fwrite($this->handle, $line);
        }

        $this->buffer = [];
    }

    private function quoteValue(mixed $val): string
    {
        if ($val === null) {
            return 'NULL';
        }
        if (is_int($val) || is_float($val)) {
            return (string) $val;
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }

        return "'" . addcslashes((string) $val, "\000\n\r\\'\"\032") . "'";
    }
}
