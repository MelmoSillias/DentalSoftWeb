<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;

/**
 * N'exécute une requête que si l'objet visé est dans l'état attendu.
 * Les objets créés plus tôt dans la même migration sont pris en compte
 * avant que le SQL différé de Doctrine ne soit joué.
 */
trait GuardedSql
{
    /** @var array<string, true> */
    private array $guardTables = [];

    /** @var array<string, true> */
    private array $guardColumns = [];

    /** @var array<string, true> */
    private array $guardIndexes = [];

    /** @var array<string, true> */
    private array $guardForeignKeys = [];

    private bool $guardReady = false;

    private bool $guardConsumed = false;

    public function preUp(Schema $schema): void
    {
        $this->resetGuard();
    }

    public function preDown(Schema $schema): void
    {
        $this->resetGuard();
    }

    /**
     * @param array<int|string, mixed> $params
     * @param array<int|string, mixed> $types
     */
    protected function guardedSql(string $sql, array $params = [], array $types = []): void
    {
        $this->warmGuard();
        $statement = trim($sql);
        if ($statement === '') {
            return;
        }

        if (!$this->statementApplies($statement)) {
            if (!$this->guardConsumed) {
                $this->write('  -> skip: '.$this->summarizeSql($statement));
            }

            return;
        }

        $this->addSql($statement, $params, $types);
    }

    protected function columnExistsNow(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function resetGuard(): void
    {
        $this->guardTables = [];
        $this->guardColumns = [];
        $this->guardIndexes = [];
        $this->guardForeignKeys = [];
        $this->guardReady = false;
    }

    private function warmGuard(): void
    {
        if ($this->guardReady) {
            return;
        }

        $this->guardReady = true;

        $tables = $this->connection->fetchFirstColumn(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        foreach ($tables as $table) {
            $this->guardTables[(string) $table] = true;
        }

        $columns = $this->connection->fetchAllAssociative(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
        );
        foreach ($columns as $column) {
            $this->guardColumns[$column['TABLE_NAME'].'.'.$column['COLUMN_NAME']] = true;
        }

        $indexes = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()'
        );
        foreach ($indexes as $index) {
            $this->guardIndexes[$index['TABLE_NAME'].'.'.$index['INDEX_NAME']] = true;
        }

        $foreignKeys = $this->connection->fetchAllAssociative(
            "SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        foreach ($foreignKeys as $foreignKey) {
            $this->guardForeignKeys[$foreignKey['TABLE_NAME'].'.'.$foreignKey['CONSTRAINT_NAME']] = true;
        }
    }

    private function statementApplies(string $sql): bool
    {
        $this->guardConsumed = false;

        if (preg_match('/^CREATE\s+TABLE\s+`?(\w+)`?\s*\(/i', $sql, $matches) === 1) {
            $table = $matches[1];
            if ($this->tableWillExist($table)) {
                return false;
            }

            $this->rememberCreateTable($table, $sql);

            return true;
        }

        if (preg_match('/^DROP\s+TABLE\s+`?(\w+)`?/i', $sql, $matches) === 1) {
            $table = $matches[1];
            if (!$this->tableWillExist($table)) {
                return false;
            }

            $this->forgetTable($table);

            return true;
        }

        if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+`?(\w+)`?\s+ON\s+`?(\w+)`?\s*\(([^)]+)\)/i', $sql, $matches) === 1) {
            $index = $matches[1];
            $table = $matches[2];
            if (!$this->tableWillExist($table) || $this->indexWillExist($table, $index)) {
                return false;
            }

            foreach ($this->identifierList($matches[3]) as $column) {
                if (!$this->columnWillExist($table, $column)) {
                    return false;
                }
            }

            $this->guardIndexes[$table.'.'.$index] = true;

            return true;
        }

        if (preg_match('/^DROP\s+INDEX\s+`?(\w+)`?\s+ON\s+`?(\w+)`?/i', $sql, $matches) === 1) {
            $index = $matches[1];
            $table = $matches[2];
            if (!$this->indexWillExist($table, $index)) {
                return false;
            }

            unset($this->guardIndexes[$table.'.'.$index]);

            return true;
        }

        if (preg_match('/^ALTER\s+TABLE\s+`?(\w+)`?\s+/i', $sql, $matches) === 1) {
            return $this->alterApplies($matches[1], substr($sql, strlen($matches[0])));
        }

        if (preg_match('/^(?:UPDATE|DELETE|INSERT)\b/i', $sql) === 1) {
            return $this->dmlApplies($sql);
        }

        return true;
    }

    private function alterApplies(string $table, string $rest): bool
    {
        $clauses = $this->splitSqlList($rest);
        $applicable = [];

        foreach ($clauses as $clause) {
            $clause = trim($clause);
            if ($clause === '') {
                continue;
            }

            if (preg_match('/^ADD\s+CONSTRAINT\s+`?(\w+)`?\s+FOREIGN\s+KEY\s*\(([^)]+)\)\s+REFERENCES\s+`?(\w+)`?\s*\(([^)]+)\)/i', $clause, $matches) === 1) {
                $name = $matches[1];
                $referencedTable = $matches[3];
                if (!$this->tableWillExist($table) || !$this->tableWillExist($referencedTable) || $this->foreignKeyWillExist($table, $name)) {
                    continue;
                }

                $ready = true;
                foreach ($this->identifierList($matches[2]) as $column) {
                    if (!$this->columnWillExist($table, $column)) {
                        $ready = false;
                        break;
                    }
                }
                foreach ($this->identifierList($matches[4]) as $column) {
                    if (!$this->columnWillExist($referencedTable, $column)) {
                        $ready = false;
                        break;
                    }
                }
                if (!$ready) {
                    continue;
                }

                $this->guardForeignKeys[$table.'.'.$name] = true;
                $applicable[] = $clause;
                continue;
            }

            if (preg_match('/^DROP\s+FOREIGN\s+KEY\s+`?(\w+)`?/i', $clause, $matches) === 1) {
                $name = $matches[1];
                if (!$this->foreignKeyWillExist($table, $name)) {
                    continue;
                }

                unset($this->guardForeignKeys[$table.'.'.$name]);
                $applicable[] = $clause;
                continue;
            }

            if (preg_match('/^ADD\s+(?:CONSTRAINT|INDEX|KEY|UNIQUE|PRIMARY|FULLTEXT|SPATIAL)\b/i', $clause) === 1) {
                $applicable[] = $clause;
                continue;
            }

            if (preg_match('/^ADD\s+`?(\w+)`?\s+/i', $clause, $matches) === 1) {
                $column = $matches[1];
                if (!$this->tableWillExist($table) || $this->columnWillExist($table, $column)) {
                    continue;
                }

                $this->guardColumns[$table.'.'.$column] = true;
                $applicable[] = $clause;
                continue;
            }

            if (preg_match('/^DROP\s+`?(\w+)`?$/i', $clause, $matches) === 1) {
                $column = $matches[1];
                if (!$this->columnWillExist($table, $column)) {
                    continue;
                }

                unset($this->guardColumns[$table.'.'.$column]);
                $applicable[] = $clause;
                continue;
            }

            $applicable[] = $clause;
        }

        if ($applicable === []) {
            return false;
        }

        $presentClauses = array_values(array_filter(
            $clauses,
            static fn (string $clause): bool => trim($clause) !== ''
        ));
        if (count($applicable) !== count($presentClauses)) {
            $this->addSql('ALTER TABLE `'.$table.'` '.implode(', ', $applicable));
            $this->guardConsumed = true;

            return false;
        }

        return true;
    }

    private function dmlApplies(string $sql): bool
    {
        /** @var array<string, string> $aliases */
        $aliases = [];

        if (preg_match('/^(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+`?(\w+)`?(?:\s+(?:AS\s+)?(\w+))?/i', $sql, $matches) === 1) {
            $table = $matches[1];
            $alias = $matches[2] ?? $table;
            if (in_array(strtoupper($alias), ['SET', 'WHERE', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'ON'], true)) {
                $alias = $table;
            }
            $aliases[$alias] = $table;
            if (!$this->tableWillExist($table)) {
                return false;
            }
        }

        if (preg_match_all('/(?:JOIN|FROM)\s+`?(\w+)`?(?:\s+(?:AS\s+)?(\w+))?/i', $sql, $joined, PREG_SET_ORDER) > 0) {
            foreach ($joined as $match) {
                $table = $match[1];
                $alias = $match[2] ?? $table;
                if (in_array(strtoupper($alias), ['ON', 'WHERE', 'SET', 'INNER', 'LEFT', 'RIGHT', 'JOIN'], true)) {
                    $alias = $table;
                }
                $aliases[$alias] = $table;
                if (!$this->tableWillExist($table)) {
                    return false;
                }
            }
        }

        if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\.`?([A-Za-z_][A-Za-z0-9_]*)`?/', $sql, $qualified, PREG_SET_ORDER) > 0) {
            foreach ($qualified as $match) {
                $table = $aliases[$match[1]] ?? null;
                if ($table === null || !$this->columnWillExist($table, $match[2])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function rememberCreateTable(string $table, string $sql): void
    {
        $this->guardTables[$table] = true;
        $body = $this->createTableBody($sql);
        if ($body === null) {
            return;
        }

        foreach ($this->splitSqlList($body) as $part) {
            $part = trim($part);
            if (preg_match('/^(?:UNIQUE\s+)?(?:INDEX|KEY)\s+`?(\w+)`?/i', $part, $matches) === 1) {
                $this->guardIndexes[$table.'.'.$matches[1]] = true;
                continue;
            }
            if (preg_match('/^CONSTRAINT\s+`?(\w+)`?/i', $part, $matches) === 1) {
                $this->guardForeignKeys[$table.'.'.$matches[1]] = true;
                continue;
            }
            if (preg_match('/^(?:PRIMARY\s+KEY|UNIQUE\s+KEY|UNIQUE\s+INDEX|KEY|INDEX|CONSTRAINT|CHECK)\b/i', $part) === 1) {
                continue;
            }
            if (preg_match('/^`?(\w+)`?\s+/', $part, $matches) === 1) {
                $this->guardColumns[$table.'.'.$matches[1]] = true;
            }
        }
    }

    private function createTableBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $quote = '';
        $length = strlen($sql);
        for ($i = $open; $i < $length; ++$i) {
            $character = $sql[$i];
            if ($inString) {
                if ($character === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $sql[$i + 1] === "'") {
                        ++$i;
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }
            if ($character === "'" || $character === '"') {
                $inString = true;
                $quote = $character;
                continue;
            }
            if ($character === '(') {
                ++$depth;
                continue;
            }
            if ($character === ')') {
                --$depth;
                if ($depth === 0) {
                    return substr($sql, $open + 1, $i - $open - 1);
                }
            }
        }

        return null;
    }

    private function forgetTable(string $table): void
    {
        unset($this->guardTables[$table]);
        $prefix = $table.'.';
        $this->guardColumns = $this->forgetPrefixed($this->guardColumns, $prefix);
        $this->guardIndexes = $this->forgetPrefixed($this->guardIndexes, $prefix);
        $this->guardForeignKeys = $this->forgetPrefixed($this->guardForeignKeys, $prefix);
    }

    /**
     * @param array<string, true> $values
     *
     * @return array<string, true>
     */
    private function forgetPrefixed(array $values, string $prefix): array
    {
        foreach (array_keys($values) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($values[$key]);
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function identifierList(string $list): array
    {
        $names = [];
        foreach (explode(',', $list) as $piece) {
            $name = trim($piece, " \t\n\r\0\x0B`");
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function tableWillExist(string $table): bool
    {
        return isset($this->guardTables[$table]);
    }

    private function columnWillExist(string $table, string $column): bool
    {
        return isset($this->guardColumns[$table.'.'.$column]);
    }

    private function indexWillExist(string $table, string $index): bool
    {
        return isset($this->guardIndexes[$table.'.'.$index]);
    }

    private function foreignKeyWillExist(string $table, string $name): bool
    {
        return $this->tableWillExist($table) && isset($this->guardForeignKeys[$table.'.'.$name]);
    }

    /**
     * @return list<string>
     */
    private function splitSqlList(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $quote = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; ++$i) {
            $character = $sql[$i];
            if ($inString) {
                $buffer .= $character;
                if ($character === $quote) {
                    if ($quote === "'" && $i + 1 < $length && $sql[$i + 1] === "'") {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }

            if ($character === "'" || $character === '"') {
                $inString = true;
                $quote = $character;
                $buffer .= $character;
                continue;
            }

            if ($character === '(') {
                ++$depth;
                $buffer .= $character;
                continue;
            }

            if ($character === ')') {
                --$depth;
                $buffer .= $character;
                continue;
            }

            if ($character === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $character;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    private function summarizeSql(string $sql): string
    {
        $oneLine = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return strlen($oneLine) > 140 ? substr($oneLine, 0, 137).'...' : $oneLine;
    }
}
