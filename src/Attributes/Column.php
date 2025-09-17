<?php
namespace DBykov\SchemaSync\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public ?string $name = null,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public ?bool $nullable = null,
        public mixed $default = null,
        public ?bool $primary = null
    ) {}
}
