<?php
namespace DBykov\Examples;

use DBykov\SchemaSync\Attributes\Column;

class UserDto
{
    public int $id;
    #[Column(length:150)]
    public string $email;
    public ?string $name = null;
    public bool $is_active = true;
    public array $meta = [];
}
