# SchemaSync — DTO → SQL schema/migrations (MySQL/PostgreSQL)

English | Русский (ниже)

English

Overview
SchemaSync takes your PHP DTOs (typed properties, PHP ≥ 8.1) and turns them into:

- CREATE TABLE SQL for MySQL or PostgreSQL
- ALTER TABLE migration steps to bring an existing DB schema in sync with a DTO
- A diff report with risk levels (INFO/WARNING/DANGER) highlighting potentially destructive changes

It also provides a CLI (bin/schemasync) and a programmatic API.

Key features (MVP)
- Input sources: PHP DTO classes with typed properties and optional PHP 8.1 attributes, or sample array/JSON (basic support).
- SQL generation:
    - CREATE TABLE for a new DTO.
    - ALTER TABLE steps for schema diff.
- DB verification: compares against a live database (information_schema) and prints diff + risk warnings.
- CLI:
    - generate <FQCN> --dialect=mysql|pgsql — print CREATE TABLE.
    - diff <FQCN> --dialect=... --dsn=... [--user=] [--pass=] [--table=] — print ALTER steps and risk report.
- Attributes support on properties, e.g. name, length, precision/scale, nullable, default.

Requirements
- PHP >= 8.1
- PDO extension and a MySQL or PostgreSQL database (for diff against live DB)
- Composer (for autoload)

Installation
- As a project dependency or a local package:
- Install dependencies:
```shell script
# install vendor deps
composer install
```

- Make CLI executable:
```shell script
chmod +x bin/schemasync
```


Quick start
1) Define a DTO
```php
<?php
namespace App\DTO;

use App\Attributes\Column;

final class UserDto
{
    public int $id;

    #[Column(length: 150)]
    public string $email;

    public ?string $name = null;

    public bool $is_active = true;

    public array $meta = [];
}
```


2) Generate CREATE TABLE
```shell script
./bin/schemasync generate App\\DTO\\UserDto --dialect=mysql
# or
./bin/schemasync generate App\\DTO\\UserDto --dialect=pgsql
```


3) Diff against a live database
```shell script
# MySQL example
./bin/schemasync diff App\\DTO\\UserDto \
  --dialect=mysql \
  --dsn="mysql:host=127.0.0.1;dbname=test;charset=utf8mb4" \
  --user=root \
  --pass=secret

# PostgreSQL example
./bin/schemasync diff App\\DTO\\UserDto \
  --dialect=pgsql \
  --dsn="pgsql:host=127.0.0.1;port=5432;dbname=test" \
  --user=postgres \
  --pass=secret
```


Mapping rules (MVP)
- int → MySQL INT; PostgreSQL INTEGER
- float → MySQL DOUBLE; PostgreSQL DOUBLE PRECISION
- bool → MySQL TINYINT(1) (or BOOLEAN if supported); PostgreSQL BOOLEAN
- string → VARCHAR(length), default length 255; heuristic path to TEXT can be added
- ?string → nullable VARCHAR(...)
- array/object → MySQL JSON; PostgreSQL JSONB
- DateTimeInterface/DateTimeImmutable → DATETIME (MySQL) / TIMESTAMP (PostgreSQL)
- Enum (backed): string-backed → VARCHAR; int-backed → INT
- mixed → TEXT/JSON fallback
- Primary key heuristic: property named id with int type → AUTO_INCREMENT (MySQL) / SERIAL (PostgreSQL)

Risk detection (MVP)
Severity: INFO | WARNING | DANGER
- Drop column (present in DB but missing in DTO) — DANGER (data loss)
- Incompatible type change (e.g., string→int, float→int, text/json→int) — WARNING (at least)
- VARCHAR length reduction (e.g., 255→50) — DANGER (truncation)
- Nullable true → false without default — DANGER (existing NULL values break)
- Default removed — WARNING
- Signed/unsigned, precision/scale changes — WARNING
- Rename appears as drop+add; if types and nullability match, tool suggests “rename candidate”

Programmatic API (MVP)
- Parse DTO → internal schema
```php
<?php
use DBykov\SchemaSync\DTOParser;

$parser = new DTOParser();
$dto = $parser->parse(App\DTO\UserDto::class);
// $dto => ['table' => 'users', 'columns' => [...]]
```


- Generate CREATE TABLE
```php
<?php
use DBykov\SchemaSync\SqlGenerator;

$gen = new SqlGenerator();
$sql = $gen->createTableSql($dto, 'mysql'); // or 'pgsql'
echo $sql;
```


- Diff with current schema
```php
<?php
use DBykov\SchemaSync\SqlGenerator;

$ops = (new SqlGenerator())->diffToAlter($currentSchema, $dto, 'pgsql');
foreach ($ops as $op) {
    echo strtoupper($op['severity']) . ': ' . $op['reason'] . PHP_EOL;
    echo $op['sql'] . PHP_EOL . PHP_EOL;
}
```


CLI usage
```shell script
# Show help
./bin/schemasync
# Usage:
#   schemasync generate <FQCN> --dialect=mysql|pgsql
#   schemasync diff <FQCN> --dialect=mysql|pgsql --dsn=... [--user=] [--pass=] [--table=]
```


Examples
- CREATE TABLE (MySQL)
```sql
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `name` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `meta` JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```


- CREATE TABLE (PostgreSQL)
```sql
CREATE TABLE "users" (
  "id" SERIAL PRIMARY KEY,
  "email" VARCHAR(150) NOT NULL,
  "name" VARCHAR(255) NULL DEFAULT NULL,
  "is_active" BOOLEAN NOT NULL DEFAULT 1,
  "meta" JSONB NOT NULL
);
```


Limitations (MVP)
- No indexes/unique/foreign keys yet
- No composite primary keys
- Simplified enum and DateTime mapping
- MySQL ALTER for nullability/type often requires a full column definition in MODIFY; review generated statements
- Rename detection is heuristic (drop+add with hint)

Contributing
- PRs and issues are welcome. Please add PHPUnit tests for new logic.

License
- MIT

Русский

Описание
SchemaSync берёт ваши PHP DTO (типизированные свойства, PHP ≥ 8.1) и генерирует:

- SQL для CREATE TABLE (MySQL/PostgreSQL)
- ALTER TABLE шаги миграции, чтобы привести существующую БД к схеме DTO
- Отчёт diff + предупреждения о рисках (INFO/WARNING/DANGER) при потенциально разрушительных изменениях

Есть CLI (bin/schemasync) и программатический API.

Ключевой функционал (MVP)
- Источники: PHP DTO с типами и опциональными атрибутами PHP 8.1, либо пример массива/JSON (базово).
- Генерация SQL:
    - CREATE TABLE для нового DTO.
    - ALTER TABLE для синхронизации схемы.
- Проверка на реальной БД: сравнение с information_schema, вывод diff + предупреждений.
- CLI:
    - generate <FQCN> --dialect=mysql|pgsql — печатает CREATE TABLE.
    - diff <FQCN> --dialect=... --dsn=... [--user=] [--pass=] [--table=] — печатает ALTER-операции и отчёт о рисках.
- Поддержка атрибутов у свойств: имя колонки, длина, precision/scale, nullable, default.

Требования
- PHP >= 8.1
- Расширение PDO и MySQL или PostgreSQL (для diff с живой БД)
- Composer (autoload)

Установка
- Как зависимость проекта или локальный пакет.
- Установка зависимостей:
```shell script
composer install
```

- Сделать CLI исполняемым:
```shell script
chmod +x bin/schemasync
```


Быстрый старт
1) DTO
```php
<?php
namespace App\DTO;

use App\Attributes\Column;

final class UserDto
{
    public int $id;

    #[Column(length: 150)]
    public string $email;

    public ?string $name = null;

    public bool $is_active = true;

    public array $meta = [];
}
```


2) Генерация CREATE TABLE
```shell script
./bin/schemasync generate App\\DTO\\UserDto --dialect=mysql
# или
./bin/schemasync generate App\\DTO\\UserDto --dialect=pgsql
```


3) Diff с живой БД
```shell script
# Пример для MySQL
./bin/schemasync diff App\\DTO\\UserDto \
  --dialect=mysql \
  --dsn="mysql:host=127.0.0.1;dbname=test;charset=utf8mb4" \
  --user=root \
  --pass=secret

# Пример для PostgreSQL
./bin/schemasync diff App\\DTO\\UserDto \
  --dialect=pgsql \
  --dsn="pgsql:host=127.0.0.1;port=5432;dbname=test" \
  --user=postgres \
  --pass=secret
```


Правила маппинга (MVP)
- int → MySQL INT; PostgreSQL INTEGER
- float → MySQL DOUBLE; PostgreSQL DOUBLE PRECISION
- bool → MySQL TINYINT(1) (или BOOLEAN при поддержке); PostgreSQL BOOLEAN
- string → VARCHAR(length), по умолчанию 255; возможна эвристика к TEXT
- ?string → nullable VARCHAR(...)
- array/object → MySQL JSON; PostgreSQL JSONB
- DateTimeInterface/DateTimeImmutable → DATETIME (MySQL) / TIMESTAMP (PostgreSQL)
- Enum (backed): string-backed → VARCHAR; int-backed → INT
- mixed → TEXT/JSON fallback
- PK-эвристика: свойство id типа int → AUTO_INCREMENT (MySQL) / SERIAL (PostgreSQL)

Детекция рисков (MVP)
Уровни: INFO | WARNING | DANGER
- Удаление колонки (есть в БД, нет в DTO) — DANGER (потеря данных)
- Несовместимая смена типа (string→int, float→int, text/json→int) — минимум WARNING
- Сужение длины VARCHAR (255→50) — DANGER (усечение)
- nullable: true → false без default — DANGER (существующие NULL нарушат ограничение)
- Удаление default — WARNING
- Signed/unsigned, precision/scale — WARNING
- Переименование выглядит как drop+add; при совпадении типа/nullable — подсказка “rename candidate”

 API (MVP)
- Разбор DTO → внутренняя схема
```php
<?php
use DBykov\SchemaSync\DTOParser;

$parser = new DTOParser();
$dto = $parser->parse(App\DTO\UserDto::class);
// $dto => ['table' => 'users', 'columns' => [...]]
```


- Генерация CREATE TABLE
```php
<?php
use DBykov\SchemaSync\SqlGenerator;

$gen = new SqlGenerator();
$sql = $gen->createTableSql($dto, 'mysql'); // или 'pgsql'
echo $sql;
```


CLI
```shell script
./bin/schemasync
# Usage:
#   schemasync generate <FQCN> --dialect=mysql|pgsql
#   schemasync diff <FQCN> --dialect=mysql|pgsql --dsn=... [--user=] [--pass=] [--table=]
```


Примеры
- CREATE TABLE (MySQL)
```sql
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `name` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `meta` JSON NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```


- CREATE TABLE (PostgreSQL)
```sql
CREATE TABLE "users" (
  "id" SERIAL PRIMARY KEY,
  "email" VARCHAR(150) NOT NULL,
  "name" VARCHAR(255) NULL DEFAULT NULL,
  "is_active" BOOLEAN NOT NULL DEFAULT 1,
  "meta" JSONB NOT NULL
);
```


Ограничения (MVP)
- Нет индексов/уникальных/внешних ключей
- Нет составных PK
- Упрощённая поддержка enum и DateTime
- Для MySQL изменение nullability/типа часто требует полного определения в MODIFY — проверяйте сгенерированные шаги
- Переименования — эвристика drop+add с подсказкой

Вклад
- PR и Issue приветствуются.

Лицензия
- MIT

