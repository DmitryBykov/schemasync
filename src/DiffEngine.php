<?php
namespace DBykov\SchemaSync;

final class DiffEngine
{
    private DTOParser $parser;
    private SqlGenerator $sqlGen;

    public function __construct()
    {
        $this->parser = new DTOParser();
        $this->sqlGen = new SqlGenerator();
    }

    public function generateCreate(string $fqcn, string $dialect): string
    {
        $dto = $this->parser->parse($fqcn);
        return $this->sqlGen->createTableSql($dto,$dialect);
    }

    /**
     * Compute diff between DB and DTO and return operations with severities.
     * @param string $fqcn
     * @param array $currentSchema (from DbInspector->introspect)
     * @param string $dialect
     * @return array
     */
    public function diff(string $fqcn, array $currentSchema, string $dialect): array
    {
        $dto = $this->parser->parse($fqcn);
        return $this->sqlGen->diffToAlter($currentSchema,$dto,$dialect);
    }
}
