<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Schema;

use RuntimeException;

/**
 * Parses SQL DDL statements (CREATE TABLE, FOREIGN KEY, PRIMARY KEY) into DatabaseSchema.
 */
class DdlParser
{
    public function parse(string $sql): DatabaseSchema
    {
        $schema = new DatabaseSchema();

        // Strip comments
        $cleanSql = preg_replace('/--.*$/m', '', $sql);
        $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql);

        // Find CREATE TABLE statements taking nested parentheses into account
        $offset = 0;
        $len = strlen($cleanSql);

        while (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\s*\(/i', $cleanSql, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $tableName = $match[1][0];
            $bodyStart = $match[0][1] + strlen($match[0][0]);

            // Scan until matching closing parenthesis
            $depth = 1;
            $inQuote = false;
            $quoteChar = '';
            $i = $bodyStart;

            for (; $i < $len; $i++) {
                $char = $cleanSql[$i];

                if ($inQuote) {
                    if ($char === $quoteChar && ($i === 0 || $cleanSql[$i - 1] !== '\\')) {
                        $inQuote = false;
                    }
                    continue;
                }

                if ($char === "'" || $char === '"' || $char === '`') {
                    $inQuote = true;
                    $quoteChar = $char;
                    continue;
                }

                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }

            if ($depth === 0) {
                $tableBody = substr($cleanSql, $bodyStart, $i - $bodyStart);
                $table = new TableDefinition($tableName);
                $this->parseTableBody($table, $tableBody);
                $schema->addTable($table);
                $offset = $i + 1;
            } else {
                $offset = $bodyStart;
            }
        }

        // Also check for standalone ALTER TABLE ... ADD CONSTRAINT / FOREIGN KEY
        preg_match_all('/ALTER\s+TABLE\s+(?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\s+ADD\s+(?:CONSTRAINT\s+(?:`|"|\')?[a-zA-Z0-9_]+(?:`|"|\')?\s+)?FOREIGN\s+KEY\s*\((?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\)\s*REFERENCES\s+(?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\s*\((?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\)/i', $cleanSql, $fkMatches, PREG_SET_ORDER);

        foreach ($fkMatches as $fk) {
            $tName = $fk[1];
            $colName = $fk[2];
            $foreignTable = $fk[3];
            $foreignCol = $fk[4];

            $targetTable = $schema->getTable($tName);
            if ($targetTable !== null && isset($targetTable->columns[$colName])) {
                $targetTable->columns[$colName]->foreignTable = $foreignTable;
                $targetTable->columns[$colName]->foreignColumn = $foreignCol;
            }
        }

        return $schema;
    }

    private function parseTableBody(TableDefinition $table, string $body): void
    {
        // Split by commas taking parentheses into account
        $lines = $this->splitColumns($body);

        $foreignKeys = [];
        $primaryKeys = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Check PRIMARY KEY (col1, col2)
            if (preg_match('/^PRIMARY\s+KEY\s*\((.*?)\)/i', $line, $pkMatch)) {
                $pks = explode(',', $pkMatch[1]);
                foreach ($pks as $pk) {
                    $pk = trim($pk, " `\"'\t\n\r");
                    $primaryKeys[] = $pk;
                }
                continue;
            }

            // Check FOREIGN KEY (col) REFERENCES other_table(other_col)
            if (preg_match('/^(?:CONSTRAINT\s+(?:`|"|\')?[a-zA-Z0-9_]+(?:`|"|\')?\s+)?FOREIGN\s+KEY\s*\((?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\)\s*REFERENCES\s+(?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\s*\((?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\)/i', $line, $fkMatch)) {
                $foreignKeys[$fkMatch[1]] = [
                    'table' => $fkMatch[2],
                    'column' => $fkMatch[3]
                ];
                continue;
            }

            // Ignore KEY, UNIQUE KEY, INDEX definitions
            if (preg_match('/^(?:KEY|UNIQUE\s+KEY|INDEX|FULLTEXT|SPATIAL|CHECK)\b/i', $line)) {
                continue;
            }

            // Column line: `col_name` type [NOT NULL] [AUTO_INCREMENT] [PRIMARY KEY] [REFERENCES ...]
            if (preg_match('/^(?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\s+([a-zA-Z0-9_]+(?:\s*\([^)]*\))?)(.*)$/is', $line, $colMatch)) {
                $colName = $colMatch[1];
                $colType = trim($colMatch[2]);
                $modifiers = $colMatch[3];

                $nullable = !preg_match('/\bNOT\s+NULL\b/i', $modifiers);
                $isPk = (bool) preg_match('/\bPRIMARY\s+KEY\b/i', $modifiers);
                $autoInc = (bool) preg_match('/\b(AUTO_INCREMENT|AUTOINCREMENT|IDENTITY)\b/i', $modifiers);

                $foreignTable = null;
                $foreignCol = null;

                // Inline REFERENCES parent_table(parent_col)
                if (preg_match('/REFERENCES\s+(?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\s*\((?:`|"|\')?([a-zA-Z0-9_]+)(?:`|"|\')?\)/i', $modifiers, $inlineFk)) {
                    $foreignTable = $inlineFk[1];
                    $foreignCol = $inlineFk[2];
                }

                $column = new ColumnDefinition(
                    name: $colName,
                    type: $colType,
                    nullable: $nullable,
                    primaryKey: $isPk,
                    autoIncrement: $autoInc,
                    foreignTable: $foreignTable,
                    foreignColumn: $foreignCol
                );

                $table->addColumn($column);
            }
        }

        // Apply table-level primary keys
        foreach ($primaryKeys as $pkName) {
            if (isset($table->columns[$pkName])) {
                $table->columns[$pkName]->primaryKey = true;
            }
        }

        // Apply table-level foreign keys
        foreach ($foreignKeys as $colName => $fkInfo) {
            if (isset($table->columns[$colName])) {
                $table->columns[$colName]->foreignTable = $fkInfo['table'];
                $table->columns[$colName]->foreignColumn = $fkInfo['column'];
            }
        }
    }

    private function splitColumns(string $body): array
    {
        $tokens = [];
        $len = strlen($body);
        $buffer = '';
        $depth = 0;
        $inQuote = false;
        $quoteChar = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $body[$i];

            if ($inQuote) {
                $buffer .= $char;
                if ($char === $quoteChar && ($i === 0 || $body[$i - 1] !== '\\')) {
                    $inQuote = false;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $inQuote = true;
                $quoteChar = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $buffer .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $tokens[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $tokens[] = $buffer;
        }

        return $tokens;
    }
}
