<?php
namespace DBykov\SchemaSync;

use PDO;

final class DbInspector
{
    private PDO $pdo;
    private string $dialect;
    private string $schema; // for pgsql

    public function __construct(PDO $pdo, string $dialect = 'mysql', ?string $schema = null)
    {
        $this->pdo = $pdo;
        $this->dialect = $dialect;
        $this->schema = $schema ?? 'public';
    }

    /**
     * Return ['table'=>'users', 'columns'=>['id'=>['type'=>'INTEGER','nullable'=>false,'length'=>null], ...]]
     */
    public function introspect(string $table): array
    {
        if ($this->dialect === 'mysql') {
            // information_schema.columns
            $stmt = $this->pdo->prepare("SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table");
            $stmt->execute(['table'=>$table]);
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cols[$r['COLUMN_NAME']] = [
                    'type' => strtoupper($r['DATA_TYPE']),
                    'nullable' => $r['IS_NULLABLE'] === 'YES',
                    'length' => $r['CHARACTER_MAXIMUM_LENGTH'] ? (int)$r['CHARACTER_MAXIMUM_LENGTH'] : null
                ];
            }
            return ['table'=>$table,'columns'=>$cols];
        } else {
            // pg_catalog
            $stmt = $this->pdo->prepare("SELECT column_name, is_nullable, data_type, character_maximum_length FROM information_schema.columns WHERE table_schema = :schema AND table_name = :table");
            $stmt->execute(['schema'=>$this->schema,'table'=>$table]);
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cols[$r['column_name']] = [
                    'type' => strtoupper($r['data_type']),
                    'nullable' => $r['is_nullable'] === 'YES',
                    'length' => $r['character_maximum_length'] ? (int)$r['character_maximum_length'] : null
                ];
            }
            return ['table'=>$table,'columns'=>$cols];
        }
    }
}
