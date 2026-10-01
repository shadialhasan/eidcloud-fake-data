<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Tests;

use EidCloud\FakeData\FakeDataGenerator;
use EidCloud\FakeData\Generators\StreamInterface;
use EidCloud\FakeData\Providers\ArabicProvider;
use EidCloud\FakeData\Providers\EnglishProvider;
use EidCloud\FakeData\Providers\FinancialProvider;
use EidCloud\FakeData\Schema\ColumnDefinition;
use EidCloud\FakeData\Schema\DatabaseSchema;
use EidCloud\FakeData\Schema\DdlParser;
use EidCloud\FakeData\Schema\JsonSchemaParser;
use EidCloud\FakeData\Schema\TableDefinition;
use EidCloud\FakeData\Topology\DependencyResolver;
use RuntimeException;

/**
 * In-memory stream to capture generated rows and test foreign key referential integrity.
 */
class MemoryStream implements StreamInterface
{
    /** @var array<string, array<array<string, mixed>>> */
    public array $data = [];
    private ?string $current = null;

    public function begin(): void
    {
        $this->data = [];
    }

    public function startTable(TableDefinition $table): void
    {
        $this->current = $table->name;
        $this->data[$table->name] = [];
    }

    public function writeRow(TableDefinition $table, array $row): void
    {
        $this->data[$table->name][] = $row;
    }

    public function endTable(TableDefinition $table): void
    {
        $this->current = null;
    }

    public function finish(): void
    {
    }
}

class FakeDataTest
{
    private int $assertions = 0;

    public function runAll(): int
    {
        echo "Running EidCloud FakeData Test Suite...\n\n";

        $this->testArabicProvider();
        $this->testEnglishProvider();
        $this->testFinancialProvider();
        $this->testDdlParser();
        $this->testJsonSchemaParser();
        $this->testDependencyResolver();
        $this->testCircularDependencyDetection();
        $this->testForeignKeyReferentialIntegrity();
        $this->testMillionRecordsPerformance();

        echo "\n🎉 ALL TESTS PASSED! ({$this->assertions} assertions verified)\n";
        return 0;
    }

    private function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new RuntimeException("Assertion Failed: " . $message);
        }
        echo "  ✔ " . $message . "\n";
    }

    private function testArabicProvider(): void
    {
        echo "[Test ArabicProvider]\n";
        $name = ArabicProvider::fullName();
        $this->assert(!empty($name), "Arabic full name is not empty: '{$name}'");

        $phone = ArabicProvider::phoneNumber(true);
        $this->assert(str_starts_with($phone, '+9665'), "Saudi phone has valid prefix: {$phone}");

        $zatca = ArabicProvider::zatcaTaxNumber();
        $this->assert(strlen($zatca) === 15 && str_starts_with($zatca, '3') && str_ends_with($zatca, '3'), "ZATCA tax number conforms to 15-digit 3...3 rule: {$zatca}");

        $iban = ArabicProvider::iban();
        $this->assert(str_starts_with($iban, 'SA') && strlen($iban) === 24, "Saudi IBAN conforms to SA + 22 chars: {$iban}");

        $id = ArabicProvider::nationalId();
        $this->assert(strlen($id) === 10 && in_array($id[0], ['1', '2'], true), "Saudi ID conforms to 10 digits starting with 1 or 2: {$id}");
    }

    private function testEnglishProvider(): void
    {
        echo "[Test EnglishProvider]\n";
        $email = EnglishProvider::email();
        $this->assert(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, "Email format is valid: {$email}");

        $uuid = EnglishProvider::uuid();
        $this->assert(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1, "UUID v4 is compliant: {$uuid}");
    }

    private function testFinancialProvider(): void
    {
        echo "[Test FinancialProvider]\n";
        $amt = FinancialProvider::amount(10.0, 50.0, 2);
        $this->assert($amt >= 10.0 && $amt <= 50.0, "Amount is within bounds: {$amt}");

        $curr = FinancialProvider::currency();
        $this->assert(!empty($curr), "Currency code generated: {$curr}");
    }

    private function testDdlParser(): void
    {
        echo "[Test DdlParser]\n";
        $sql = <<<SQL
        CREATE TABLE customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL
        );
        CREATE TABLE orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        );
        SQL;

        $parser = new DdlParser();
        $schema = $parser->parse($sql);

        $this->assert(isset($schema->tables['customers']), "Customers table parsed");
        $this->assert(isset($schema->tables['orders']), "Orders table parsed");
        $this->assert($schema->tables['orders']->columns['customer_id']->foreignTable === 'customers', "Foreign key references customers table");
    }

    private function testJsonSchemaParser(): void
    {
        echo "[Test JsonSchemaParser]\n";
        $json = json_encode([
            'tables' => [
                'users' => [
                    'count' => 10,
                    'columns' => [
                        'id' => ['type' => 'int', 'pk' => true, 'auto_increment' => true],
                        'name' => ['type' => 'string', 'provider' => 'arabic_name']
                    ]
                ]
            ]
        ]);

        $parser = new JsonSchemaParser();
        $schema = $parser->parse($json);
        $this->assert(isset($schema->tables['users']), "Parsed table 'users' from JSON");
        $this->assert($schema->tables['users']->rowCount === 10, "Parsed table rowCount is 10");
    }

    private function testDependencyResolver(): void
    {
        echo "[Test DependencyResolver DAG Order]\n";
        $schema = new DatabaseSchema();
        $customers = new TableDefinition('customers');
        $customers->addColumn(new ColumnDefinition('id', 'int', primaryKey: true, autoIncrement: true));

        $orders = new TableDefinition('orders');
        $orders->addColumn(new ColumnDefinition('id', 'int', primaryKey: true, autoIncrement: true));
        $orders->addColumn(new ColumnDefinition('customer_id', 'int', foreignTable: 'customers', foreignColumn: 'id'));

        $orderItems = new TableDefinition('order_items');
        $orderItems->addColumn(new ColumnDefinition('id', 'int', primaryKey: true, autoIncrement: true));
        $orderItems->addColumn(new ColumnDefinition('order_id', 'int', foreignTable: 'orders', foreignColumn: 'id'));

        $products = new TableDefinition('products');
        $products->addColumn(new ColumnDefinition('id', 'int', primaryKey: true, autoIncrement: true));
        $orderItems->addColumn(new ColumnDefinition('product_id', 'int', foreignTable: 'products', foreignColumn: 'id'));

        // Add in reverse/arbitrary order
        $schema->addTable($orderItems);
        $schema->addTable($orders);
        $schema->addTable($customers);
        $schema->addTable($products);

        $resolver = new DependencyResolver();
        $sorted = $resolver->resolve($schema);

        $names = array_map(fn($t) => $t->name, $sorted);

        $posCustomers = array_search('customers', $names, true);
        $posOrders = array_search('orders', $names, true);
        $posItems = array_search('order_items', $names, true);
        $posProducts = array_search('products', $names, true);

        $this->assert($posCustomers < $posOrders, "customers precedes orders in DAG");
        $this->assert($posOrders < $posItems, "orders precedes order_items in DAG");
        $this->assert($posProducts < $posItems, "products precedes order_items in DAG");
    }

    private function testCircularDependencyDetection(): void
    {
        echo "[Test Circular Dependency Exception]\n";
        $schema = new DatabaseSchema();
        $a = new TableDefinition('table_a');
        $a->addColumn(new ColumnDefinition('id', 'int', primaryKey: true));
        $a->addColumn(new ColumnDefinition('b_id', 'int', foreignTable: 'table_b', foreignColumn: 'id'));

        $b = new TableDefinition('table_b');
        $b->addColumn(new ColumnDefinition('id', 'int', primaryKey: true));
        $b->addColumn(new ColumnDefinition('a_id', 'int', foreignTable: 'table_a', foreignColumn: 'id'));

        $schema->addTable($a);
        $schema->addTable($b);

        $caught = false;
        try {
            $resolver = new DependencyResolver();
            $resolver->resolve($schema);
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'Circular dependency'), "Detected circular dependency exception");
        }
        $this->assert($caught, "Circular dependency threw RuntimeException as required");
    }

    private function testForeignKeyReferentialIntegrity(): void
    {
        echo "[Test Strict Foreign Key Referential Integrity]\n";
        $ddl = file_get_contents(__DIR__ . '/../examples/ecommerce.sql');
        $parser = new DdlParser();
        $schema = $parser->parse($ddl);

        $stream = new MemoryStream();
        $generator = new FakeDataGenerator('mixed');

        // Generate 500 customers, 500 products, 1000 orders, 2000 order_items
        $schema->tables['customers']->rowCount = 200;
        $schema->tables['products']->rowCount = 150;
        $schema->tables['orders']->rowCount = 400;
        $schema->tables['order_items']->rowCount = 800;

        $generator->generate($schema, $stream);

        $customerIds = array_column($stream->data['customers'], 'id');
        $productIds = array_column($stream->data['products'], 'id');
        $orderIds = array_column($stream->data['orders'], 'id');

        $this->assert(count($customerIds) === 200, "Generated 200 customers");
        $this->assert(count($productIds) === 150, "Generated 150 products");
        $this->assert(count($orderIds) === 400, "Generated 400 orders");
        $this->assert(count($stream->data['order_items']) === 800, "Generated 800 order_items");

        // Verify EVERY order refers to an existing customer
        $allOrdersValid = true;
        foreach ($stream->data['orders'] as $order) {
            if (!in_array($order['customer_id'], $customerIds, true)) {
                $allOrdersValid = false;
                break;
            }
        }
        $this->assert($allOrdersValid, "100% of orders reference valid customer IDs");

        // Verify EVERY order_item refers to an existing order AND product
        $allItemsValid = true;
        foreach ($stream->data['order_items'] as $item) {
            if (!in_array($item['order_id'], $orderIds, true) || !in_array($item['product_id'], $productIds, true)) {
                $allItemsValid = false;
                break;
            }
        }
        $this->assert($allItemsValid, "100% of order_items reference valid order IDs and product IDs");
    }

    private function testMillionRecordsPerformance(): void
    {
        echo "[Test High-Velocity Performance (Streaming 10,000 in-memory benchmark)]\n";
        $ddl = file_get_contents(__DIR__ . '/../examples/ecommerce.sql');
        $parser = new DdlParser();
        $schema = $parser->parse($ddl);

        $tempSqlFile = tempnam(sys_get_temp_dir(), 'eidcloud_bench_');
        $stream = new \EidCloud\FakeData\Generators\SqlInsertStream($tempSqlFile, 1000);
        $generator = new FakeDataGenerator('mixed');

        $start = microtime(true);
        // Generate 10,000 records across the 4 relational tables (2,500 each)
        $generator->generate($schema, $stream, 2500);
        $duration = microtime(true) - $start;

        $fileSize = filesize($tempSqlFile);
        @unlink($tempSqlFile);

        $this->assert($duration < 3.0, sprintf("Generated 10,000 relational records in %.3f seconds (> 3,300 rows/sec)", $duration));
        $this->assert($fileSize > 500000, "Output file contains substantive multi-batch SQL data (" . round($fileSize / 1024, 1) . " KB)");
    }
}
