<?php
namespace DBykov\SchemaSync;

final class TypeMapper
{
    public function mapPhpToSql(string $phpType, string $dialect, ?int $length = null): array
    {
        // returns ['type' => 'VARCHAR', 'sqlType' => 'VARCHAR(255)', 'nullable' => bool]
        $nullable = str_contains($phpType, 'null') || $phpType === 'mixed';

        // handle union simplistically: prefer first non-null
        if (str_contains($phpType, '|')) {
            $parts = explode('|', $phpType);
            foreach ($parts as $p) {
                if ($p !== 'null') { $phpType = $p; break; }
            }
        }

        $base = match($phpType) {
            'int' => 'int',
            'float' => 'float',
            'bool' => 'bool',
            'string' => 'string',
            'array' => 'json',
            'object' => 'json',
            'mixed' => 'text',
            default => (str_ends_with($phpType,'Interface') || str_ends_with($phpType,'able') || class_exists($phpType)) ? 'json' : 'text'
        };

        if ($dialect === 'mysql') {
            return match($base) {
                'int' => ['type'=>'INT','sql'=>"INT"],
                'float' => ['type'=>'DOUBLE','sql'=>"DOUBLE"],
                'bool' => ['type'=>'TINYINT','sql'=>"TINYINT(1)"],
                'string' => ['type'=>'VARCHAR','sql'=>"VARCHAR(" . ($length ?? 255) . ")"],
                'json' => ['type'=>'JSON','sql'=>"JSON"],
                'text' => ['type'=>'TEXT','sql'=>"TEXT"],
                default => ['type'=>'TEXT','sql'=>"TEXT"]
            };
        } else { // pgsql
            return match($base) {
                'int' => ['type'=>'INTEGER','sql'=>"INTEGER"],
                'float' => ['type'=>'DOUBLE PRECISION','sql'=>"DOUBLE PRECISION"],
                'bool' => ['type'=>'BOOLEAN','sql'=>"BOOLEAN"],
                'string' => ['type'=>'VARCHAR','sql'=>"VARCHAR(" . ($length ?? 255) . ")"],
                'json' => ['type'=>'JSONB','sql'=>"JSONB"],
                'text' => ['type'=>'TEXT','sql'=>"TEXT"],
                default => ['type'=>'TEXT','sql'=>"TEXT"]
            };
        }
    }
}
