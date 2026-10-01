<?php

declare(strict_types=1);

namespace EidCloud\FakeData;

use EidCloud\FakeData\Generators\StreamInterface;
use EidCloud\FakeData\Providers\ArabicProvider;
use EidCloud\FakeData\Providers\EnglishProvider;
use EidCloud\FakeData\Providers\FinancialProvider;
use EidCloud\FakeData\Schema\ColumnDefinition;
use EidCloud\FakeData\Schema\DatabaseSchema;
use EidCloud\FakeData\Schema\TableDefinition;
use EidCloud\FakeData\Topology\DependencyResolver;

/**
 * Main Relational-Aware Synthetic Data Generator.
 * Resolves schema dependencies and generates massive volumes while preserving FK referential integrity.
 */
class FakeDataGenerator
{
    /**
     * Cache of primary key values generated per table to feed foreign key lookups.
     * table_name => [pk_value1, pk_value2, ...]
     * @var array<string, array<mixed>>
     */
    private array $primaryKeyPool = [];

    /**
     * Max pool size to prevent unbounded memory growth during 1M+ record runs.
     */
    private int $maxPoolSize = 50000;

    /**
     * Locale preference ('ar', 'en', 'mixed')
     */
    private string $locale = 'mixed';

    public function __construct(string $locale = 'mixed', int $maxPoolSize = 50000)
    {
        $this->locale = $locale;
        $this->maxPoolSize = $maxPoolSize;
    }

    public function generate(DatabaseSchema $schema, StreamInterface $stream, ?int $globalCount = null, ?callable $progressCallback = null): void
    {
        $resolver = new DependencyResolver();
        $sortedTables = $resolver->resolve($schema);

        $stream->begin();

        foreach ($sortedTables as $table) {
            $stream->startTable($table);

            $targetRows = $globalCount !== null ? $globalCount : $table->rowCount;
            $this->primaryKeyPool[$table->name] = [];

            $pkCol = $table->getPrimaryKey();
            $autoIncCounter = 1;

            for ($i = 1; $i <= $targetRows; $i++) {
                $row = [];

                foreach ($table->columns as $colName => $column) {
                    $row[$colName] = $this->generateColumnValue($column, $table, $autoIncCounter, $i);
                }

                if ($pkCol !== null) {
                    $pkVal = $row[$pkCol->name];
                    // Keep sample pool for referencing foreign keys
                    if (count($this->primaryKeyPool[$table->name]) < $this->maxPoolSize) {
                        $this->primaryKeyPool[$table->name][] = $pkVal;
                    } else {
                        // Reservoir sampling to maintain diverse distribution
                        $replaceIdx = mt_rand(0, $i);
                        if ($replaceIdx < $this->maxPoolSize) {
                            $this->primaryKeyPool[$table->name][$replaceIdx] = $pkVal;
                        }
                    }
                }

                $autoIncCounter++;
                $stream->writeRow($table, $row);

                if ($progressCallback !== null && $i % 1000 === 0) {
                    $progressCallback($table->name, $i, $targetRows);
                }
            }

            if ($progressCallback !== null) {
                $progressCallback($table->name, $targetRows, $targetRows);
            }

            $stream->endTable($table);
        }

        $stream->finish();
    }

    public function generateColumnValue(ColumnDefinition $col, TableDefinition $table, int $autoIncCounter, int $rowIndex): mixed
    {
        // 1. Foreign Key Lookup (Maintains strict referential integrity)
        if ($col->isForeignKey() && $col->foreignTable !== null) {
            return $this->resolveForeignKeyValue($col);
        }

        // 2. Primary Key Auto Increment
        if ($col->primaryKey && $col->autoIncrement) {
            return $autoIncCounter;
        }

        // 3. Explicit provider type
        if ($col->providerType !== null) {
            return $this->callProvider($col->providerType, $col->options);
        }

        // 4. Intelligent heuristic based on column name & SQL/Data type
        return $this->generateHeuristicValue($col, $rowIndex);
    }

    private function resolveForeignKeyValue(ColumnDefinition $col): mixed
    {
        $foreignTable = $col->foreignTable;
        if (!isset($this->primaryKeyPool[$foreignTable]) || empty($this->primaryKeyPool[$foreignTable])) {
            if ($col->nullable) {
                return null;
            }
            // Fallback default if parent table had no records
            return 1;
        }

        $pool = $this->primaryKeyPool[$foreignTable];
        return $pool[array_rand($pool)];
    }

    private function generateHeuristicValue(ColumnDefinition $col, int $index): mixed
    {
        $name = strtolower($col->name);
        $type = strtolower($col->type);

        // UUID detection
        if (str_contains($name, 'uuid') || str_contains($type, 'uuid')) {
            return EnglishProvider::uuid();
        }

        // Name heuristics
        if ($name === 'first_name') {
            return $this->useArabic() ? ArabicProvider::firstName() : EnglishProvider::firstName();
        }
        if ($name === 'last_name') {
            return $this->useArabic() ? ArabicProvider::lastName() : EnglishProvider::lastName();
        }
        if (str_contains($name, 'name')) {
            return $this->useArabic() ? ArabicProvider::fullName() : EnglishProvider::fullName();
        }

        // Email heuristics
        if (str_contains($name, 'email') || str_contains($name, 'mail')) {
            return EnglishProvider::email();
        }

        // Phone heuristics
        if (str_contains($name, 'phone') || str_contains($name, 'mobile') || str_contains($name, 'tel')) {
            return $this->useArabic() ? ArabicProvider::phoneNumber(true) : EnglishProvider::phoneNumber();
        }

        // Tax / ZATCA / VAT heuristics
        if (str_contains($name, 'tax') || str_contains($name, 'zatca') || str_contains($name, 'vat')) {
            return ArabicProvider::zatcaTaxNumber();
        }

        // IBAN heuristics
        if (str_contains($name, 'iban')) {
            return ArabicProvider::iban();
        }

        // National ID / Iqama
        if (str_contains($name, 'national_id') || str_contains($name, 'iqama')) {
            return ArabicProvider::nationalId();
        }

        // Address / City / Country heuristics
        if (str_contains($name, 'city')) {
            return $this->useArabic() ? ArabicProvider::city() : EnglishProvider::city();
        }
        if (str_contains($name, 'district')) {
            return ArabicProvider::district();
        }
        if (str_contains($name, 'address')) {
            return $this->useArabic() ? ArabicProvider::address() : EnglishProvider::address();
        }
        if (str_contains($name, 'country')) {
            return $this->useArabic() ? 'المملكة العربية السعودية' : 'Saudi Arabia';
        }

        // Financial & Price heuristics
        if (str_contains($name, 'price') || str_contains($name, 'amount') || str_contains($name, 'total') || str_contains($name, 'subtotal') || str_contains($name, 'cost') || str_contains($name, 'balance')) {
            return FinancialProvider::amount(10.00, 10000.00, 2);
        }
        if (str_contains($name, 'tax_amount') || str_contains($name, 'discount')) {
            return FinancialProvider::amount(1.00, 250.00, 2);
        }
        if (str_contains($name, 'currency')) {
            return FinancialProvider::currency();
        }
        if (str_contains($name, 'payment_method') || str_contains($name, 'payment_type')) {
            return FinancialProvider::paymentMethod();
        }

        // Status heuristics
        if (str_contains($name, 'status')) {
            return FinancialProvider::status();
        }

        // Quantity heuristics
        if (str_contains($name, 'quantity') || str_contains($name, 'qty') || str_contains($name, 'count')) {
            return mt_rand(1, 20);
        }

        // Date & Timestamp heuristics
        if (str_contains($name, 'created_at') || str_contains($name, 'updated_at') || str_contains($type, 'timestamp') || str_contains($type, 'datetime')) {
            return FinancialProvider::dateTime('-1 year', 'now');
        }
        if (str_contains($name, 'date') || str_contains($type, 'date')) {
            return FinancialProvider::date('-1 year', 'now');
        }

        // Boolean heuristics
        if (str_starts_with($name, 'is_') || str_starts_with($name, 'has_') || str_contains($type, 'bool') || str_contains($type, 'tinyint(1)')) {
            return mt_rand(0, 1) === 1 ? 1 : 0;
        }

        // Fallback to basic SQL Type handling
        return $this->generateByType($type, $index);
    }

    private function generateByType(string $type, int $index): mixed
    {
        if (str_contains($type, 'int')) {
            return $index;
        }
        if (str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'double')) {
            return FinancialProvider::amount(5.00, 1000.00, 2);
        }
        if (str_contains($type, 'text')) {
            return EnglishProvider::text(12);
        }
        if (str_contains($type, 'json')) {
            return json_encode(['generated_at' => date('c'), 'index' => $index, 'valid' => true]);
        }

        return 'item_' . $index;
    }

    private function callProvider(string $provider, array $options): mixed
    {
        return match ($provider) {
            'arabic_name', 'ar_name' => ArabicProvider::fullName(),
            'arabic_first_name' => ArabicProvider::firstName(),
            'arabic_last_name' => ArabicProvider::lastName(),
            'arabic_phone', 'saudi_phone' => ArabicProvider::phoneNumber(true),
            'arabic_address' => ArabicProvider::address(),
            'arabic_city' => ArabicProvider::city(),
            'arabic_district' => ArabicProvider::district(),
            'zatca_tax_number', 'zatca_tin', 'tax_number' => ArabicProvider::zatcaTaxNumber(),
            'saudi_iban', 'iban' => ArabicProvider::iban(),
            'national_id', 'iqama' => ArabicProvider::nationalId(),

            'name', 'full_name' => EnglishProvider::fullName(),
            'first_name' => EnglishProvider::firstName(),
            'last_name' => EnglishProvider::lastName(),
            'email' => EnglishProvider::email(),
            'phone' => EnglishProvider::phoneNumber(),
            'uuid' => EnglishProvider::uuid(),
            'company' => EnglishProvider::company(),
            'city' => EnglishProvider::city(),
            'address' => EnglishProvider::address(),
            'text' => EnglishProvider::text($options['words'] ?? 6),

            'amount', 'price' => FinancialProvider::amount(
                (float) ($options['min'] ?? 10.0),
                (float) ($options['max'] ?? 50000.0),
                (int) ($options['decimals'] ?? 2)
            ),
            'currency' => FinancialProvider::currency(),
            'payment_method' => FinancialProvider::paymentMethod(),
            'date' => FinancialProvider::date($options['start'] ?? '-2 years', $options['end'] ?? 'now'),
            'datetime' => FinancialProvider::dateTime($options['start'] ?? '-2 years', $options['end'] ?? 'now'),
            'status' => FinancialProvider::status($options['values'] ?? ['active', 'inactive', 'pending']),

            default => 'val_' . mt_rand(1000, 9999)
        };
    }

    private function useArabic(): bool
    {
        if ($this->locale === 'ar') {
            return true;
        }
        if ($this->locale === 'en') {
            return false;
        }
        return mt_rand(0, 1) === 1;
    }
}
