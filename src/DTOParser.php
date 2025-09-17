<?php
namespace DBykov\SchemaSync;

use ReflectionClass;
use DBykov\SchemaSync\Attributes\Column;
use DBykov\SchemaSync\Exception\SchemaSyncException;

final class DTOParser
{
    /**
     * Parse FQCN DTO and return array of columns metadata
     *
     * return [
     *   'columns' => [
     *     'propName' => [
     *         'column' => 'col_name',
     *         'phpType' => 'int|string|null',
     *         'nullable' => bool,
     *         'default' => mixed,
     *         'primary' => bool,
     *         'length' => int|null
     *     ],
     *     ...
     *   ],
     *   'table' => 'inferred_table_name'
     * ]
     */
    public function parse(string $fqcn): array
    {
        if (!class_exists($fqcn)) {
            throw new SchemaSyncException("Class {$fqcn} not found (autoload it).");
        }

        $rc = new ReflectionClass($fqcn);
        $props = $rc->getProperties();

        $columns = [];
        foreach ($props as $p) {
            // skip static
            if ($p->isStatic()) continue;

            $name = $p->getName();

            $type = $p->getType();
            $phpType = $type ? $this->typeToString($type) : 'mixed';
            $nullable = $type ? $type->allowsNull() : true;

            $attrs = $p->getAttributes(Column::class);
            $colAttr = $attrs ? $attrs[0]->newInstance() : null;

            $colName = $colAttr && $colAttr->name ? $colAttr->name : $name;
            $length = $colAttr?->length ?? null;
            $default = null;
            // attempt to read default value if property has default (since PHP 8.1 can have default)
            if ($p->hasDefaultValue()) {
                $default = $p->getDefaultValue();
            } elseif ($colAttr?->default !== null) {
                $default = $colAttr->default;
            }

            $primary = $colAttr?->primary ?? ($name === 'id' && str_contains($phpType, 'int'));

            $columns[$name] = [
                'column' => $colName,
                'phpType' => $phpType,
                'nullable' => $nullable,
                'default' => $default,
                'primary' => $primary,
                'length' => $length
            ];
        }

        // default table name: class short name snake_case pluralized (simple heuristic)
        $short = $rc->getShortName();
        $table = $this->toSnakeCase($short);
        if (!str_ends_with($table, 's')) {
            $table .= 's';
        }

        return ['columns' => $columns, 'table' => $table];
    }

    private function typeToString(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }
        if ($type instanceof \ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $t) {
                $parts[] = $t->getName();
            }
            return implode('|', $parts);
        }
        return 'mixed';
    }

    private function toSnakeCase(string $s): string
    {
        $s = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $s);
        return strtolower($s);
    }
}
