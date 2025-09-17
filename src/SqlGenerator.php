<?php
namespace DBykov\SchemaSync;

final class SqlGenerator
{
    private TypeMapper $mapper;
    public function __construct()
    {
        $this->mapper = new TypeMapper();
    }

    /**
     * Generate CREATE TABLE SQL
     * @param array $dto Parsed result from DTOParser
     * @param string $dialect 'mysql'|'pgsql'
     */
    public function createTableSql(array $dto, string $dialect): string
    {
        $table = $dto['table'];
        $columns = $dto['columns'];

        $lines = [];
        foreach ($columns as $prop => $meta) {
            $col = $meta['column'];
            $phpType = $meta['phpType'];
            $length = $meta['length'] ?? null;
            $map = $this->mapper->mapPhpToSql($phpType, $dialect, $length);
            $sqlType = $map['sql'];
            $nullable = $meta['nullable'] ? 'NULL' : 'NOT NULL';
            $default = '';
            if ($meta['default'] !== null) {
                $default = ' DEFAULT ' . $this->exportDefault($meta['default'], $map['type']);
            }
            // primary key handling
            if ($meta['primary']) {
                if ($dialect === 'mysql' && $map['type'] === 'INT') {
                    $lines[] = "  `{$col}` INT AUTO_INCREMENT PRIMARY KEY";
                    continue;
                }
                if ($dialect === 'pgsql' && $map['type'] === 'INTEGER') {
                    $lines[] = "  \"{$col}\" SERIAL PRIMARY KEY";
                    continue;
                }
            }
            if ($dialect === 'mysql') {
                $lines[] = "  `{$col}` {$sqlType} {$nullable}{$default}";
            } else {
                $lines[] = "  \"{$col}\" {$sqlType} {$nullable}{$default}";
            }
        }

        if ($dialect === 'mysql') {
            return "CREATE TABLE `{$table}` (\n" . implode(",\n", $lines) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
        } else {
            return "CREATE TABLE \"{$table}\" (\n" . implode(",\n", $lines) . "\n);\n";
        }
    }

    private function exportDefault(mixed $v, string $sqlType): string
    {
        if (is_string($v)) return "'" . addslashes($v) . "'";
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_null($v)) return 'NULL';
        if (is_int($v) || is_float($v)) return (string)$v;
        return "'" . addslashes(json_encode($v)) . "'";
    }

    /**
     * Generate basic ALTER statements to migrate old->new.
     * For safety, this returns operations array with 'sql' and 'severity'.
     *
     * @param array $currentSchema (from DbInspector->introspect)
     * @param array $dtoParsed
     * @param string $dialect
     * @return array list of ['sql'=>string,'severity'=>'INFO'|'WARNING'|'DANGER','reason'=>string]
     */
    public function diffToAlter(array $currentSchema, array $dtoParsed, string $dialect): array
    {
        // currentSchema example: ['table' => 'users', 'columns' => ['id'=>['type'=>'INTEGER','nullable'=>false,'length'=>null], ...]]
        $table = $dtoParsed['table'];
        $ops = [];
        $curCols = $currentSchema['columns'] ?? [];
        $newCols = [];
        foreach ($dtoParsed['columns'] as $p => $m) {
            $newCols[$m['column']] = $m;
        }

        // detect drops
        foreach ($curCols as $cName => $cMeta) {
            if (!array_key_exists($cName, $newCols)) {
                $ops[] = ['sql' => $this->dropColumnSql($table, $cName, $dialect), 'severity'=>'DANGER', 'reason'=>"Column {$cName} will be dropped (data loss)"];
            }
        }

        // detect adds and changes
        foreach ($newCols as $col => $meta) {
            if (!array_key_exists($col, $curCols)) {
                // add column
                $map = $this->mapper->mapPhpToSql($meta['phpType'], $dialect, $meta['length'] ?? null);
                $nullable = $meta['nullable'] ? 'NULL' : 'NOT NULL';
                $default = $meta['default'] !== null ? ' DEFAULT ' . $this->exportDefault($meta['default'], $map['type']) : '';
                $sql = $dialect === 'mysql'
                    ? "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$map['sql']} {$nullable}{$default};"
                    : "ALTER TABLE \"{$table}\" ADD COLUMN \"{$col}\" {$map['sql']} {$nullable}{$default};";
                $ops[] = ['sql'=>$sql,'severity'=>'INFO','reason'=>"Add column {$col}"];
                continue;
            }
            // exists -> compare
            $cur = $curCols[$col];
            $curType = strtoupper($cur['type']);
            $map = $this->mapper->mapPhpToSql($meta['phpType'], $dialect, $meta['length'] ?? null);
            $newType = strtoupper($map['type']);
            // simplistic checks:
            if ($curType !== $newType) {
                $ops[] = ['sql'=>$this->alterColumnTypeSql($table,$col,$map['sql'],$dialect),'severity'=>'WARNING','reason'=>"Type change {$curType} -> {$newType} for {$col}"];
            }
            // nullable change
            if (($cur['nullable'] ?? false) && !$meta['nullable']) {
                // going from nullable to not-null: dangerous if data contains null
                $ops[] = ['sql'=>$this->alterColumnNullableSql($table,$col,false,$dialect),'severity'=>'DANGER','reason'=>"Column {$col} becomes NOT NULL (existing nulls may break)"];
            } elseif (!($cur['nullable'] ?? false) && $meta['nullable']) {
                $ops[] = ['sql'=>$this->alterColumnNullableSql($table,$col,true,$dialect),'severity'=>'INFO','reason'=>"Column {$col} becomes NULLABLE"];
            }
            // length shrink
            if (!empty($cur['length']) && !empty($meta['length']) && $meta['length'] < $cur['length']) {
                $ops[] = ['sql'=>$this->alterColumnTypeSql($table,$col,$map['sql'],$dialect),'severity'=>'DANGER','reason'=>"Length shrink {$cur['length']} -> {$meta['length']} for {$col} (possible truncation)"];
            }
        }

        return $ops;
    }

    private function dropColumnSql(string $table, string $col, string $dialect): string
    {
        return $dialect === 'mysql'
            ? "ALTER TABLE `{$table}` DROP COLUMN `{$col}`;"
            : "ALTER TABLE \"{$table}\" DROP COLUMN \"{$col}\";";
    }

    private function alterColumnTypeSql(string $table,string $col,string $sqlType,string $dialect): string
    {
        if ($dialect === 'mysql') {
            return "ALTER TABLE `{$table}` MODIFY `{$col}` {$sqlType};";
        } else {
            return "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$col}\" TYPE {$sqlType};";
        }
    }

    private function alterColumnNullableSql(string $table,string $col,bool $nullable,string $dialect): string
    {
        if ($dialect === 'mysql') {
            // MySQL needs full column definition in MODIFY; but MVP: issue ALTER to set/not set NULL via MODIFY is complex — warn user to review.
            return "-- NOTE: adjust nullability manually for `{$col}` in table `{$table}` (MySQL needs full column definition) --";
        } else {
            return $nullable ? "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$col}\" DROP NOT NULL;" : "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$col}\" SET NOT NULL;";
        }
    }
}
