# phpdot/env

Typed, schema-validated, immutable `.env` configuration for modern PHP.

## Install

```bash
composer require phpdot/env
```

Zero dependencies. Pure PHP 8.3+.

## Quick Start

```php
use PHPdot\Env\Env;

$env = Env::create(
    schema: __DIR__ . '/env.schema.php',
    paths: __DIR__ . '/.env',
);

$env->get('APP_PORT');    // int(8080)
$env->get('APP_DEBUG');   // bool(false)
$env->get('APP_ENV');     // AppEnv::PRODUCTION
$env->get('DB_HOST');     // string("localhost")
$env->get('ORIGINS');     // ['http://localhost', 'https://example.com']
```

Every value is typed. Every key is validated. Every access is a pure array lookup.

---

## Architecture

```
.env file(s)
     │
     ▼
┌──────────────────────┐
│  Lexer               │  Character-by-character tokenizer
│  Handles quotes,     │  Escapes, multiline, BOM, export
│  comments, escapes   │
└──────────────────────┘
     │
     ▼
┌──────────────────────┐
│  Resolver            │  ${VAR} and $VAR interpolation
│  Circular detection  │  Cross-file references
└──────────────────────┘
     │
     ▼
┌──────────────────────┐
│  EnvSchema           │  Type casting + constraint validation
│  STRING, INT, FLOAT, │  Required, min/max, allowed, pattern
│  BOOL, ENUM, LIST,   │
│  JSON                │
└──────────────────────┘
     │
     ▼
┌──────────────────────┐
│  Env                 │  Immutable. readonly arrays.
│  All values eagerly  │  get() = pure array lookup.
│  cast at boot.       │  Zero computation per request.
└──────────────────────┘
```

---

## Schema

The schema is the source of truth. Every env var must be declared.

```php
// env.schema.php
use PHPdot\Env\Enum\EnvType;
use PHPdot\Env\Enum\AppEnv;

return [
    'APP_ENV' => [
        'enum'     => AppEnv::class,
        'required' => true,
        'default'  => AppEnv::DEVELOPMENT,
    ],
    'APP_DEBUG' => [
        'type'    => EnvType::BOOL,
        'default' => false,
    ],
    'APP_PORT' => [
        'type'    => EnvType::INT,
        'default' => 8080,
        'min'     => 1,
        'max'     => 65535,
    ],
    'APP_KEY' => [
        'type'      => EnvType::STRING,
        'required'  => true,
        'not_empty' => true,
        'sensitive' => true,
    ],
    'ALLOWED_ORIGINS' => [
        'type'    => EnvType::LIST,
        'default' => [],
    ],
    'FEATURE_CONFIG' => [
        'type'    => EnvType::JSON,
        'default' => [],
    ],
    'LOG_LEVEL' => [
        'default' => 'info',
        'allowed' => ['debug', 'info', 'warning', 'error'],
    ],
];
```

### Type System

| Type | PHP return | Example |
|------|-----------|---------|
| `STRING` | `string` | `APP_NAME=MyApp` → `"MyApp"` |
| `INT` | `int` | `PORT=8080` → `8080` |
| `FLOAT` | `float` | `RATE=1.5` → `1.5` |
| `BOOL` | `bool` | `DEBUG=true` → `true` |
| `ENUM` | `BackedEnum` | `ENV=production` → `AppEnv::PRODUCTION` |
| `LIST` | `list<string>` | `IPS=a,b,c` → `["a","b","c"]` |
| `JSON` | `mixed` | `CFG={"a":1}` → `["a" => 1]` |

Bool recognizes (case-insensitive): `true/false`, `1/0`, `yes/no`, `on/off`.

### Constraints

| Constraint | Applies to | Example |
|-----------|-----------|---------|
| `required` | All | Key must exist or have default |
| `not_empty` | All | `''` after trim fails |
| `min` | INT, FLOAT | `'min' => 1` |
| `max` | INT, FLOAT | `'max' => 65535` |
| `allowed` | STRING | `'allowed' => ['debug', 'info']` |
| `pattern` | STRING | `'pattern' => '/^https?:\/\//'` |
| `sensitive` | All | Masked in `allMasked()` |

---

## Multi-File Loading

Files load in order. Later files override earlier ones.

```php
$env = Env::create(
    schema: __DIR__ . '/env.schema.php',
    paths: [
        __DIR__ . '/.env',        // base
        __DIR__ . '/.env.local',  // overrides (gitignored)
    ],
);
```

Cross-file interpolation works:

```
# .env
BASE_URL=https://example.com

# .env.local
API_URL=${BASE_URL}/api    → https://example.com/api
```

---

## .env Syntax

### Values

```bash
SIMPLE=value
DOUBLE="value with spaces"
SINGLE='literal ${no-interpolation}'
EMPTY=
```

### Escapes (double-quoted only)

```bash
NEWLINE="hello\nworld"
TAB="col1\tcol2"
BACKSLASH="back\\slash"
QUOTE="say\"hi\""
DOLLAR="cost\$5"
```

### Comments

```bash
# Full line comment
KEY=value # inline comment
HASH=color#fff           # no space before # = part of value
QUOTED="value # kept"    # inside quotes = part of value
```

### Multiline

```bash
RSA_KEY="-----BEGIN RSA KEY-----
MIIBogIBAAJBALRiMLAH
-----END RSA KEY-----"
```

### Interpolation

```bash
BASE=/app
DATA=${BASE}/data        # /app/data
LOGS=$BASE/logs          # /app/logs
NESTED=${DATA}/cache     # /app/data/cache
LITERAL='${BASE}/raw'   # ${BASE}/raw (no interpolation)
```

### Export Prefix

```bash
export FOO=bar           # FOO=bar (export stripped)
```

---

## Safe Loading

For Docker/k8s where `.env` may not exist:

```php
$env = Env::safeCreate(
    schema: __DIR__ . '/env.schema.php',
    paths: __DIR__ . '/.env',
);
```

Missing files are silently skipped. Schema defaults are used.

---

## Testing

```php
$env = Env::createForTesting(
    schema: [
        'DB_HOST' => ['required' => true],
        'DB_PORT' => ['type' => EnvType::INT, 'default' => 5432],
    ],
    values: ['DB_HOST' => 'localhost'],
);

$env->get('DB_HOST');  // 'localhost'
$env->get('DB_PORT');  // 5432
```

---

## Sensitive Values

```php
$env->get('API_KEY');     // "actual-secret-key"
$env->allMasked();        // ['API_KEY' => '***', 'DB_HOST' => 'localhost', ...]
```

`allMasked()` is safe for logging and error reports.

---

## Config Caching

For production — skip parsing on every worker boot:

```php
// Deploy script (run once)
$env = Env::create(schema: ..., paths: ...);
$env->compile(__DIR__ . '/cache/env.php');

// Application boot (every worker)
$env = Env::createFromCache(
    schema: __DIR__ . '/env.schema.php',
    cache: __DIR__ . '/cache/env.php',
);
```

Opcache caches the compiled file. Zero disk I/O, zero parsing per worker.

---

## EnvEditor (CLI Only)

Write tool for setup wizards and deployment scripts.

```php
use PHPdot\Env\EnvEditor;
use PHPdot\Env\Schema\EnvSchema;
use PHPdot\Env\Enum\AppEnv;

$editor = new EnvEditor(__DIR__ . '/.env', new EnvSchema($schema));

$editor->set('DB_HOST', 'new-host.example.com');
$editor->set('APP_ENV', AppEnv::STAGING);
$editor->remove('LOG_LEVEL');
$editor->save();
```

Preserves comments, blank lines, and key order.

---

## Parsing a String

```php
$values = Env::parseString("FOO=bar\nBAZ=\"\${FOO}/qux\"");
// ['FOO' => 'bar', 'BAZ' => 'bar/qux']
```

---

## Swoole Safety

`Env` is immutable. `readonly` arrays. Zero mutation methods. Register as a singleton — all coroutines share the same instance safely.

```php
Env::class => singleton(fn() => Env::create(
    schema: __DIR__ . '/env.schema.php',
    paths: __DIR__ . '/.env',
)),
```

---

## Package Structure

```
src/
├── Env.php                    Main read-only facade
├── EnvEditor.php              CLI-only write tool
├── Schema/
│   ├── EnvSchema.php          Type casting + validation
│   └── Definition.php         Variable definition value object
├── Parser/
│   ├── Parser.php             Orchestrator
│   ├── Lexer.php              Character-by-character tokenizer
│   ├── Entry.php              Parsed entry value object
│   └── Resolver.php           Variable interpolation
├── Enum/
│   ├── EnvType.php            STRING, INT, FLOAT, BOOL, ENUM, LIST, JSON
│   └── AppEnv.php             DEVELOPMENT, STAGING, PRODUCTION
└── Exception/
    ├── EnvException.php       Base exception
    ├── FileNotFoundException.php
    ├── EncodingException.php
    ├── ParseException.php
    ├── SchemaException.php
    ├── ValidationException.php
    └── WriteException.php
```

---

## Development

```bash
composer test        # PHPUnit (135 tests)
composer analyse     # PHPStan level 10
composer cs-fix      # PHP-CS-Fixer
composer check       # All three
```

## License

MIT
