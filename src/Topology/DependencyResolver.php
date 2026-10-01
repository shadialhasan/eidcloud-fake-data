<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Topology;

use EidCloud\FakeData\Schema\DatabaseSchema;
use EidCloud\FakeData\Schema\TableDefinition;
use RuntimeException;

/**
 * Resolves relational dependencies across tables using Directed Acyclic Graph (DAG) topological sorting.
 */
class DependencyResolver
{
    /**
     * Sorts tables so that parent tables precede child tables that reference them.
     *
     * @param DatabaseSchema $schema
     * @return array<TableDefinition>
     * @throws RuntimeException When a circular dependency is detected.
     */
    public function resolve(DatabaseSchema $schema): array
    {
        $tables = $schema->getTables();
        $inDegree = [];
        $adjList = [];

        foreach ($tables as $name => $table) {
            $inDegree[$name] = 0;
            $adjList[$name] = [];
        }

        // Build adjacency: if B depends on A (B has FK referencing A), edge A -> B
        foreach ($tables as $tableName => $table) {
            foreach ($table->getDependencies() as $parentName) {
                if (isset($tables[$parentName])) {
                    $adjList[$parentName][] = $tableName;
                    $inDegree[$tableName]++;
                }
            }
        }

        // Kahn's algorithm
        $queue = [];
        foreach ($inDegree as $name => $deg) {
            if ($deg === 0) {
                $queue[] = $name;
            }
        }

        $sortedNames = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sortedNames[] = $current;

            foreach ($adjList[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sortedNames) !== count($tables)) {
            $unresolved = array_diff(array_keys($tables), $sortedNames);
            throw new RuntimeException("Circular dependency detected among tables: " . implode(', ', $unresolved));
        }

        $result = [];
        foreach ($sortedNames as $name) {
            $result[] = $tables[$name];
        }

        return $result;
    }
}
