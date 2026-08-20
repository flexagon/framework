# Flexagon

[![PHP](https://img.shields.io/badge/php-%3E%3D8.3-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](#license)

Flexagon is a **lightweight, high-performance PHP web framework**.

It embraces PHP's inherent simplicity over complex abstractions, keeping conventions to a minimum so you can build applications quickly.

-   **Simple, flexible routing** --- The file structure maps directly to the URL structure, keeping routing intuitive and straightforward.
-   **Independent request execution** --- Each request leaves no unnecessary state behind, keeping behavior stable and predictable.
-   **Simple, idiomatic PHP development** --- Build with PHP in its natural form, without introducing a separate template language.

## Why Flexagon?

**If you know PHP, you can get started right away.**  
Pages are `.php` files, views are written directly in PHP and HTML, and you can use the SQL you already know. There is no routing syntax, service container, or query DSL to learn before you start building.

**Zero runtime dependencies.**  
Flexagon runs with PHP 8.3 and the `pdo`, `openssl`, and `json` extensions. There is no need to install dozens of third-party packages just to use the framework.

**Small and fast.**  
In the same environment, framework bootstrap takes **1.09 ms, 2 MB of memory, and 15 loaded files**. The framework itself remains compact at 39 PHP files and 7,148 lines of code.

|                    |         Flexagon 3.5.0 | Laravel 13.26.1 |
| ------------------ | ---------------------: | --------------: |
| Runtime dependencies      |                **0** |     77 packages |
| Installed size          | **532KB** (PHAR 237KB) |            42MB |
| Bootstrap time          |             **1.09ms** |         12.85ms |
| Bootstrap memory        |                **2MB** |            20MB |
| Files loaded at bootstrap  |               **15** |           336 |

**Simple deployment.**  
The entire framework can be deployed as a single 237 KB PHAR file. The target server does not need Composer or a separate cache-warming step.

## Design Principles

### URLs map directly to file paths

A request to `/user/profile` maps directly to `public/user/profile.php`.

There is no routing table to maintain. Create a file and it gets a URL; move the file and the URL moves with it. The URL itself makes it easy to see which file will be executed.

### The database is the source of truth for the schema

On the first query, a DAO runs `DESCRIBE` to read column, primary-key, and `auto_increment` metadata, then reuses it for the rest of the request.

A schema already defined in the database does not need to be duplicated in PHP. When the schema changes, there is no separate mapping definition to keep in sync.

### Global configuration stays explicit

Configuration is collected in the static `_Global` class.

There is no DI container or service locator between you and the configuration, so it is easy to see where settings live and which values are in use. Type `_Global::` in your IDE to discover the available settings through autocompletion.

### Minimal overhead per request

Framework bootstrap consists of defining constants, registering the autoloader, and loading configuration files.

Flexagon does not eagerly create unnecessary objects or services, and `DataSourceManager` reuses database connections within each request.

---

## Requirements

| Item | Version / Notes |
|---|---|
| PHP | 8.3 or later |
| Extensions | `pdo_mysql`, `openssl`, `json` |
| Web server | Apache (`mod_rewrite`) or nginx |
| Database | MySQL / MariaDB, MS SQL Server |

---

## Installation

Flexagon is distributed as two Composer packages.

| Package | Purpose |
|---|---|
| [`flexagon/framework`](https://packagist.org/packages/flexagon/framework) | Framework core (library) |
| [`flexagon/skeleton`](https://packagist.org/packages/flexagon/skeleton) | Application skeleton (project template) |

### New Project

```bash
composer create-project flexagon/skeleton myapp
cd myapp
```

```
myapp/
├── application/          # Domain code
├── public/               # ← document root
├── script/               # CLI jobs
├── flexagon.php
├── composer.json
└── vendor/
    └── flexagon/framework/   # Framework core
```

You can update the framework independently.

```bash
composer update flexagon/framework
```

### Add to an Existing Project

```bash
composer require flexagon/framework
composer exec flexagon init      # or php vendor/bin/flexagon init
```

`init` creates the 18 files that make up the project structure, including `public/`, `application/`, `script/`, and `flexagon.php`. **Existing files are left untouched**, so you can also use it after an upgrade to add only newly introduced structure files. Use `--force` to overwrite existing files.

```
create   flexagon.php
create   application/_Global.php
...
keep     public/index.php        ← keep existing file
```

> Installing only `flexagon/framework` gives you immediate access to utility classes such as `StringUtil`, `ValidUtil`, and `CryptoUtil`, as well as `BaseModel`. However, **the DAO layer requires the application to define `_Global`.** Without it, `BaseGlobal` defaults are used, so no fatal error occurs, but the empty data-source configuration prevents database connections. For real applications, start with the skeleton package.

`flexagon.php` checks three layouts in order, so the entry-point code remains the same regardless of how the framework is installed.

| Order | Path | Scenario |
|---|---|---|
| 1 | `vendor/flexagon/framework/Bootstrap.php` | Composer installation |
| 2 | `application/_Flexagon.phar` (file) | Packaged as a single PHAR |
| 3 | `application/_Flexagon.phar/Bootstrap.php` | Source directory |

`Bootstrap.php` finds the nearest parent directory containing `application/_Global.php` and uses it as `PROJECT_ROOT`. Application path resolution therefore works the same whether the framework lives under `vendor/` or inside a PHAR.

### Autoloading

Application classes are mapped to `application/` through PSR-4 in `composer.json`.

```
ExampleUser\UserDAO  →  application/ExampleUser/UserDAO.php
```

To add a namespace, simply create a directory with the same name. There is no need to modify `composer.json`.

The framework automatically loads the `_Flexagon\` namespace, global helpers (`_echo`, `_t`, `___`), and `TemplateLoader`. No additional configuration is required.

### Docker

The included `docker-compose.yml` provides a PHP 8.3 + Apache + Xdebug environment.

```bash
docker compose up -d
# http://127.0.0.1:9001
```

### Database Configuration

Edit `application/_Config.php`.

```php
<?php
use _Flexagon\Models\DataSourceModel;

_Global::$DATA_SOURCES['default'] = new DataSourceModel(
    'localhost',   // host
    3306,          // port
    'username',
    'password',
    'dbname'
);
```

### Your First Page

```php
<?php
// public/hello.php
_Global::$SITE_TITLE = 'Hello';

TemplateLoader::show('head');
echo '<h1>Hello, Flexagon</h1>';
TemplateLoader::show('tail');
```

Open `http://127.0.0.1:9001/hello` to render the page.

### Environment Check

Verify that the project layout and runtime configuration are consistent.

```bash
php script/flexagon.php check     # works with any installation type
php vendor/bin/flexagon check     # also available with Composer installations
```

```
ok       php 8.3.33
ok       opcache.save_comments
ok       project layout
ok       bootstrap
warn     data sources  default still contains template placeholders
ok       display_errors  off
```

**It catches configuration mistakes that might otherwise fail silently.** If `opcache.save_comments` is disabled, `@encrypt` and `@db_auto_timestamp` are ignored without an error. If `_Config.php` is placed under `public/`, database credentials may be exposed over the web. The command exits with status 1 when it finds a problem, making it suitable for deployment pipelines.

See [Framework CLI](#프레임워크-cli) for the complete list of checks.

---

## Directory Structure

```
myapp/
├── application/                 # Domain code
│   ├── _Global.php              #   Application globals (extends BaseGlobal)
│   ├── _Const.php               #   Application constants
│   ├── _Config.php              #   Base configuration, including data sources
│   ├── _Config/                 #   Stage-specific configuration (alpha · beta · production)
│   └── <Namespace>/             #   Models and DAOs
├── public/                      # ← document root
│   ├── .htaccess
│   ├── __flexagon.php           #   Front controller for all requests
│   ├── _entry.php               #   Request-start hook
│   ├── _prepare.php             #   Post-session hook
│   ├── _router.php              #   Dispatch point
│   ├── _Template/               #   Shared templates (head · tail · error404)
│   └── **/*.php                 #   Pages mapped 1:1 to URLs
├── script/                      # CLI jobs
├── composer.json
├── flexagon.php                 # Bootstrap loader
└── vendor/
    └── flexagon/framework/      # Framework core
```

**Under `application/`, namespaces map directly to directory paths.** `ExampleUser\UserDAO` → `application/ExampleUser/UserDAO.php`.

The `_Flexagon\` namespace resolves to the framework itself and normally does not need to be defined by the application.

**Expose only `public/` to the web.** `application/_Config.php` contains database credentials; if the document root points to the project root, those files may be served directly.

---

## Request Lifecycle

```
Browser
  │
  ├─ .htaccess          rewrites all .php requests to __flexagon.php
  │
  ├─ __flexagon.php ──► flexagon.php ──► Bootstrap.php
  │                                        │
  │                                        ├─ defines constants (PROJECT_ROOT, PUBLIC_ROOT, _FLEXAGON_ROOT ...)
  │                                        ├─ registers the autoloader
  │                                        ├─ loads _Const.php / _Global.php / _Config.php
  │                                        └─ parses the URL → _Global::$URL_PARAM
  │
  ├─ public/_entry.php      ① request-start hook (site title, shared headers, etc.)
  ├─ [automatic session start] ② when $SESSION_AUTO_START is true
  ├─ public/_prepare.php    ③ post-session hook (authorization checks, etc.)
  └─ public/_router.php     ④ dispatch — default implementation uses TemplateLoader::content()
                                 → executes public/<URL path>.php
```

The three hooks run at distinct stages of the request lifecycle.

| File | Stage | Purpose |
|---|---|---|
| `_entry.php` | **Before** session | Site metadata, response headers, locale |
| `_prepare.php` | **After** session | Authentication/authorization checks, shared data loading |
| `_router.php` | Dispatch | Executes the page. Replace this file when custom routing is needed |

### URL Mapping Rules

| Request URL | Executed file |
|---|---|
| `/` | `public/index.php` |
| `/hello` | `public/hello.php` |
| `/user/profile` | `public/user/profile.php` |
| `/user/` | `public/user/index.php` |
| `/user/profile?id=3` | `public/user/profile.php` |

Request paths are normalized first by `HttpUtil::normalizePath()`. It removes NUL bytes, converts backslashes to `/`, and removes empty segments, `.`, and `..`, preventing traversal to parent directories.

`TemplateLoader` then resolves the final path with `realpath()` and includes it **only after verifying that it remains inside** `public/` (or `_Template/`). Paths outside that boundary are rejected with a 404. This protection is enforced by the framework itself rather than relying solely on web-server path normalization.

The parsed result is stored in `_Global::$URL_PARAM` (`UrlParamsModel`).

```php
_Global::$URL_PARAM->filePath;       // 'user/profile'
_Global::$URL_PARAM->filePathArray;  // ['user', 'profile']
_Global::$URL_PARAM->filePathEnd;    // 'profile'
_Global::$URL_PARAM->params;         // '?id=3'
```

### Custom Routing

To dispatch requests manually instead of using file mapping, replace `public/_router.php`.

```php
<?php
// public/_router.php
$path = _Global::$URL_PARAM->filePath;

if (str_starts_with($path, 'api/')) {
    header('Content-Type: application/json');
    include_once PUBLIC_ROOT . '/api/_dispatch.php';
} else {
    TemplateLoader::content();
}
```

---

## Configuration

### `_Global`

All global configuration values are static properties of the `_Global` class, which extends `BaseGlobal`.

```php
<?php
// application/_Global.php
use _Flexagon\Base\BaseGlobal;

class _Global extends BaseGlobal {
    // Add application-specific globals here
    public static ?\Session\SessionUserModel $SESSION_USER_MODEL = null;
}
```

Key configuration options:

| Property | Default | Description |
|---|---|---|
| `$SITE_TITLE` | `'Flexagon'` | Site title |
| `$TIMEZONE` | `'Asia/Seoul'` | Passed to `date_default_timezone_set()` |
| `$DATA_SOURCES` | `[]` | Data-source map (see below) |
| `$DATA_SOURCE_ID` | `'default'` | Default data-source ID |
| `$USE_COMPOSER` | `true` | `vendor/autoload.php` auto-load |
| `$USE_OUTPUT_BUFFER` | `true` | Wrap the entire request with `ob_start()` |
| `$DB_STATEMENT_CACHE_SIZE` | `64` | Number of prepared statements cached per connection. `0` prepares every time |
| `$DB_CONNECT_FAILURE_FATAL` | `true` | Abort the request on DB connection failure. If `false`, `connect()` returns `false` |
| `$ENABLE_EXTENSION_PHP` | `false` | If `true`, URLs must explicitly include `.php` |
| `$USE_AUTO_CREATED_AT_TIMESTAMP` | `true` | `@db_auto_timestamp insert` automatic timestamping |
| `$USE_AUTO_UPDATED_AT_TIMESTAMP` | `true` | `@db_auto_timestamp update` automatic timestamping |
| `$RUNNING_MODE` | `FLEXAGON_CONST::RUNNING_MODE_PRODUCTION` | Runtime mode. The framework does not interpret this value; applications may use it for branching |
| `$DEBUG_ON` | `false` | Debug flag for application use |
| `$DEBUG_QUERY_BREAKPOINT` | `false` | after each query `Mysql::debugQueryAfter()` call (for debugger breakpoints) |
| `$SITE_CONFIG['web']['header']` | — | HTTP headers added to every response |
| `$SESSION_AUTO_START` | `false` | Restore session automatically during bootstrap |
| `$SESSION_NAME` | `'session_data'` | Session cookie name |
| `$SESSION_DOMAIN` | `''` | Cookie domain (`.example.com`) |
| `$SESSION_COOKIE_SECURE` | `null` | Cookie Secure flag. `null` enables it automatically for HTTPS requests |
| `$SESSION_COOKIE_HTTPONLY` | `true` | Cookie HttpOnly flag |
| `$SESSION_COOKIE_SAMESITE` | `'Lax'` | `'Lax'` · `'Strict'` · `'None'`. `'None'`automatically enables Secure |
| `$SESSION_MODEL_CLASSES` | `[]` | Classes allowed in session cookies. If empty, only `BaseModel` inheritance is checked |
| `$SESSION_TIMEOUT_SECONDS` | `86400` | Session lifetime (seconds) |
| `$SESSION_REQUIRE_EXPIRY` | `false` | Reject legacy cookies without `exp`. Set to `true` after the migration period |
| `$SESSION_NOT_BEFORE` | `0` | Invalidate all sessions issued before this timestamp (stateless forced logout) |
| `$SESSION_ENCRYPTION_STRING` | `''` | Session encryption key (**10characters or more**) |
| `$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE` | `''` | `@encrypt` property encryption key (**10characters or more**) |

### Data Sources

```php
new DataSourceModel(
    string $host,
    int    $port,
    string $username,
    string $password,
    string $dbName,
    ?string $charset = 'utf8mb4',
    ?bool   $usePrepareStatement = true
);
```

You can register multiple data sources.

```php
_Global::$DATA_SOURCES['default']   = new DataSourceModel('db-master', 3306, 'app', '****', 'service');
_Global::$DATA_SOURCES['analytics'] = new DataSourceModel('db-replica', 3306, 'ro',  '****', 'stats');
```

Each data source receives a unique ID based on `sha1(username@host/dbName)`, and `DataSourceManager` reuses connections by that ID. Multiple DAOs pointing to the same database therefore share a single connection within the request.

### Stage-specific Configuration

Place stage-specific configuration in `application/_Config/<stage>/_Config.php` and select it from the CLI with the `runtimeStage` argument.

```bash
php script/batch.php runtimeStage=production
```

The default stage directories are `alpha`, `beta`, and `production`.

---

## Models and DAOs

### Models

Extending `BaseModel` provides array/JSON conversion, encryption/decryption, and automatic timestamps. Declare properties as `private` with getters and setters; mapping is based on **method names**.

```php
<?php
namespace ExampleUser;

use _Flexagon\Base\BaseModel;

class UserModel extends BaseModel {
    private ?int    $id       = null;
    private string  $name;
    private ?string $address  = null;

    public function getId(): ?int          { return $this->id; }
    public function setId(?int $id): void  { $this->id = $id; }

    public function getName(): string             { return $this->name; }
    public function setName(string $name): void   { $this->name = $name; }

    public function getAddress(): ?string             { return $this->address; }
    public function setAddress(?string $a): void      { $this->address = $a; }
}
```

Methods provided by `BaseModel`:

| Method | Description |
|---|---|
| `getArray(bool $containsObject = true, bool $forceAll = false)` | Convert getters to an associative array |
| `setByArray(array $data)` | Populate through setters; both snake_case and camelCase keys are accepted |
| `getJson(int $flags = JSON_UNESCAPED_UNICODE)` | JSON serialization (nested objects are flattened to arrays) |
| `encrypt()` / `decrypt()` | `@encrypt` Encrypt/decrypt annotated properties |
| `getCreatedAt()` / `getUpdatedAt()` | Unix timestamp |
| `getCreatedAtDatetimeString()` | `Y-m-d H:i:s` string |

Because `BaseModel` implements `JsonSerializable`, `json_encode($model)` works directly.

`setByArray()` uses reflection to reconcile common type mismatches. Empty strings become `null` for nullable properties, and scalar values are converted to matching cases for backed-enum properties. This makes it practical to pass form input directly into a model.

### DAOs

Extend `BaseMySqlDAO` and specify `$tableName` to get the core CRUD operations.

```php
<?php
namespace ExampleUser;

use _Flexagon\Base\BaseMySqlDAO;

class UserDAO extends BaseMySqlDAO {
    protected string $tableName = 'users';

    public function insert(UserModel $user): int|false { return $this->_insert($user); }
    public function update(UserModel $user): bool      { return $this->_update($user); }
    public function delete(UserModel $user): bool      { return $this->_delete($user); }

    public function findById(int $id): ?UserModel {
        return $this->_select(['id' => $id], new UserModel());
    }

    /** @return UserModel[] */
    public function findByCity(string $city, int $page = 1, int $perPage = 20): array {
        return $this->_selectList(
            new UserModel(), $page, $perPage,
            '`address` = :CITY', ['CITY' => $city],
            '`created_at` DESC'
        );
    }

    public function countByCity(string $city): int {
        return $this->_selectTotalCount('`address` = :CITY', ['CITY' => $city]);
    }
}
```

The CRUD methods are `protected` by design. Each DAO explicitly chooses which operations to expose, keeping its public interface narrow and aligned with the domain.

| Method | Description |
|---|---|
| `_insert($modelOrArray)` | INSERT. Returns the auto-increment value (`false` on failure) |
| `_update($modelOrArray)` | UPDATE by primary key |
| `_updateByQuery($modelOrArray, $whereSql, $params)` | UPDATE with a custom condition |
| `_select($modelOrArray, ?object $vo)` | Fetch one row; returns an object when `$vo` is provided, otherwise an array |
| `_selectByQuery($whereSql, $params, ?object $vo)` | Fetch one row with a custom condition |
| `_selectList(?object $vo, $page, $perPage, $whereSql, $params, $orderSql)` | Fetch a paginated list |
| `_selectTotalCount($whereSql, $params)` | Count matching rows |
| `_delete($modelOrArray)` | DELETE by primary key |
| `_deleteByQuery($whereSql, $params)` | DELETE with a custom condition |
| `_insertAll($modelList, $chunkSize = 500)` | Bulk INSERT using one statement per chunk; returns the number of inserted rows |
| `_truncate()` | TRUNCATE |

When using SQL Server, `BaseMsSqlDAO` provides **the same method surface**. Both DAO implementations extend `BaseSqlDAO`, where CRUD operations, schema caching, and transaction handling are shared. Subclasses contain only dialect-specific behavior: identifier quoting (`` `name` `` / `[name]`), schema inspection (`DESCRIBE` / `INFORMATION_SCHEMA`), and pagination (`LIMIT` / `OFFSET·FETCH`).

When you need direct SQL, use `$this->db` (a `Mysql` instance).

```php
public function findTopSpenders(int $limit): array {
    $this->db->executeQuery(
        'SELECT u.* FROM users u JOIN orders o ON o.user_id = u.id
         GROUP BY u.id ORDER BY SUM(o.amount) DESC LIMIT :LIMIT',
        ['LIMIT' => $limit]
    );
    return $this->db->getAllResultAsObject(new UserModel());
}
```

### Column ↔ Property Mapping

Database `snake_case` columns are mapped automatically to PHP `camelCase` properties.

| DB column | Getter / Setter | Property |
|---|---|---|
| `id` | `getId()` / `setId()` | `$id` |
| `user_name` | `getUserName()` / `setUserName()` | `$userName` |
| `created_at` | `getCreatedAt()` / `setCreatedAt()` | `$createdAt` |

### Parameter Binding

Queries use PDO prepared statements with named placeholders. Framework-generated queries use the **uppercase column name** as the placeholder (`user_name` → `:USER_NAME`).

If the same placeholder appears multiple times in a query, `SqlUtil::expandQueryParams()` automatically expands it into distinct parameters, avoiding PDO's duplicate-binding limitation.

```php
$this->_selectList(new UserModel(), 1, 20,
    '`name` LIKE :KEYWORD OR `address` LIKE :KEYWORD',   // may appear more than once
    ['KEYWORD' => "%{$keyword}%"]
);
```

### Debugging

```php
echo $userDAO->debugQuery();   // actual SQL with parameters substituted
echo $userDAO->getError();     // last error message
$userDAO->debugDumpParams();   // PDOStatement::debugDumpParams()
```

When `_Global::$DEBUG_QUERY_BREAKPOINT` is enabled, `Mysql::debugQueryAfter()` is called after every query. The method is intentionally empty, making it a convenient IDE breakpoint for inspecting executed queries.

---

## Annotations

Flexagon reads PHPDoc tags at runtime to modify behavior (`PhpDocUtil`).

The canonical notation is **snake_case**.

```php
/** @encrypt */
/** @db_auto_timestamp insert|update */
/** @exclude_from_get_array */
```

**Tag names are case-insensitive and separator-insensitive.** The following forms are equivalent.

```php
@db_auto_timestamp    @DbAutoTimestamp    @DB_AUTO_TIMESTAMP    @dbautotimestamp
```

Tag **values** are case-insensitive, but separators remain significant (`insert` = `INSERT`, while `my_value` ≠ `myvalue`).

Both single-line docblocks (`/** @db_auto_timestamp insert */`) and multiline docblocks are supported.

### `@db_auto_timestamp`

Automatically fills Unix timestamps on INSERT and UPDATE. This is already applied to `createdAt` and `updatedAt` in `BaseModel`.

```php
/**
 * @var int|null
 * @db_auto_timestamp insert
 */
protected ?int $createdAt = null;

/**
 * @var int|null
 * @db_auto_timestamp insert|update
 */
protected ?int $updatedAt = null;
```

### `@encrypt`

Automatically encrypts and decrypts properties with AES-256-CBC. Values are encrypted before being stored in the database and decrypted when query results are mapped back to objects.

Ciphertext uses the format `base64(version || IV || HMAC-SHA256 || ciphertext)`. A fresh IV is generated for every operation, so identical plaintext produces different ciphertext each time. Before decryption, the HMAC is verified with `hash_equals()` to reject tampered data. Encryption and authentication keys are derived separately from the passphrase using `hash_hkdf()`.

```php
/**
 * @var string
 * @encrypt
 */
private string $residentNumber = '';
```

`_Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE` must be at least **10 characters** long or an exception is thrown. Make sure the database column is large enough to hold the ciphertext.

### `@exclude_from_get_array`

Excludes a getter from `getArray()` / `getJson()` output. Use it for values such as password hashes that should not be serialized.

```php
/**
 * @exclude_from_get_array
 */
public function getPasswordHash(): string { return $this->passwordHash; }
```

Calling `getArray(forceAll: true)` ignores exclusions and returns all values. DAOs use this mode internally when writing to the database.

---

See **[ANNOTATIONS.md](ANNOTATIONS.md)** for the complete list, constraints, and instructions for defining custom annotations.

---

## Transactions and Multiple Data Sources

### Single DAO

```php
$userDAO->startTransaction();
try {
    $userDAO->insert($user);
    $userDAO->commit();
} catch (Throwable $e) {
    $userDAO->rollback();
    throw $e;
}
```

### Transactions Across Multiple DAOs

Start a global transaction with `DataSourceManager`, and every data source used afterward automatically participates in the transaction.

```php
use _Flexagon\Libs\DataSourceManager;

DataSourceManager::startTransaction();
try {
    $userDAO->insert($user);
    $orderDAO->insert($order);
    $pointDAO->update($point);
    DataSourceManager::commit();
} catch (Throwable $e) {
    DataSourceManager::rollback();
    throw $e;
}
```

Commits and rollbacks are performed in the **reverse order** in which connections were registered.

### Assigning a Data Source to a DAO

Override the `DATA_SOURCE_ID` constant.

```php
class StatsDAO extends BaseMySqlDAO {
    const DATA_SOURCE_ID = 'analytics';   // _Global::$DATA_SOURCES['analytics']
    protected string $tableName = 'daily_stats';
}
```

---

## Sessions

Flexagon sessions serialize model objects into **encrypted cookies** rather than server-side files. Because there is no shared session store, horizontally scaling across multiple web servers requires no additional session-storage configuration.

### Configuration

```php
// application/_Config.php
_Global::$SESSION_AUTO_START      = true;
_Global::$SESSION_ENCRYPTION_STRING = 'change-this-to-a-long-random-string';
_Global::$SESSION_DOMAIN          = '.example.com';
_Global::$SESSION_TIMEOUT_SECONDS = 86400;

// Cookie security attributes — the defaults are already secure and usually do not need to be changed.
_Global::$SESSION_COOKIE_SECURE   = null;    // null = automatically enabled for HTTPS requests
_Global::$SESSION_COOKIE_HTTPONLY = true;    // prevent JavaScript from accessing the session cookie
_Global::$SESSION_COOKIE_SAMESITE = 'Lax';
```

Session cookies carry authentication state, so `HttpOnly` and `SameSite=Lax` are enabled by default, and `Secure` is added automatically for HTTPS requests. HTTPS detection checks `$_SERVER['HTTPS']`, port 443, and then `X-Forwarded-Proto`.

### Session Model and DAO

```php
<?php
namespace Session;

use _Flexagon\Base\BaseModel;

class SessionUserModel extends BaseModel {
    private string  $role     = 'USER';
    private ?string $username = '';
    private ?string $email    = '';
    // 게터 / 세터 ...
}
```

```php
<?php
namespace Session;

use _Flexagon\Base\BaseSessionDAO;

class SessionDAO extends BaseSessionDAO {}
```

### Usage

```php
// 로그인
$sessionUser = new SessionUserModel();
$sessionUser->setUsername($user->getUsername());
$sessionUser->setRole('ADMIN');

$sessionDAO = new SessionDAO();
$sessionDAO->makeSession($sessionUser);

// 로그아웃
$sessionDAO->cleanSession();
```

When `$SESSION_AUTO_START` is enabled, the session is restored during bootstrap and exposed in two locations.

```php
_Global::$SESSION_MODEL;        // always available here
_Global::$SESSION_USER_MODEL;   // class-name-based global (see below)
```

The second form is derived by converting the model class name to UPPER_SNAKE_CASE (`SessionUserModel` → `SESSION_USER_MODEL`). To access it with a type hint, declare the corresponding static property in `_Global` **in advance**.

```php
class _Global extends BaseGlobal {
    public static ?\Session\SessionUserModel $SESSION_USER_MODEL = null;
}
```

A common pattern is to perform authorization checks in `_prepare.php`.

```php
<?php
// public/_prepare.php
if (str_starts_with(_Global::$URL_PARAM->filePath, 'admin/')) {
    if (_Global::$SESSION_USER_MODEL?->getRole() !== 'ADMIN') {
        \_Flexagon\Libs\HtmlUtil::redirectPage('/login');
    }
}
```

> **Note:** A session-model property marked with `@encrypt` is encrypted twice inside the cookie. Because the entire session cookie is already encrypted with `$SESSION_ENCRYPTION_STRING`, the additional annotation is usually unnecessary.

---

## Security

### What the Framework Provides

| Item | Description |
|---|---|
| SQL injection | All DAO queries use PDO prepared statements |
| Path traversal | Router include paths cannot escape `public/` |
| Session integrity | AES-256-CBC + HMAC-SHA256, expiration verified **server-side** |
| Session cookies | `HttpOnly`·`SameSite` enabled by default; `Secure` automatic |
| Column encryption | `@encrypt` — random IV, HMAC verification, HKDF key derivation |
| Forced logout | `$SESSION_NOT_BEFORE`invalidates all sessions (stateless) |

### Application Responsibilities

**These features are intentionally not provided by the framework.** Do not assume Flexagon handles them automatically.

```php
// CSRF — no token mechanism is provided. Validate state-changing requests yourself.
// Output escaping — templates do not provide automatic escaping.
echo htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8');

// Mass assignment — passing $_POST directly may overwrite fields such as id and role.
$user->setByArray(array_intersect_key($_POST, array_flip(['name', 'email'])));

// Serialization — getArray()/getJson() include all getters by default.
/** @exclude_from_get_array */
public function getPasswordHash(): string { ... }
```

The application is also responsible for validating redirect targets, preventing SSRF, sanitizing HTML, and setting appropriate security headers.

> The `HtmlUtil::stripSpecificTag()` family is regex-based and **is not an XSS filter**. These methods are convenience helpers for cleaning up tags only.

---

## Templates and Assets

### TemplateLoader

```php
TemplateLoader::show('head', ['title' => 'Product List']);   // public/_Template/head.php
TemplateLoader::content();                                   // execute the page mapped to the URL
TemplateLoader::entryDir('_layout');                         // _layout.php in the current URL directory
TemplateLoader::entryRoot('_footer');                        // public/_footer.php
```

The supplied array is passed through `extract()` and exposed as local variables inside the template.

```php
<!-- public/_Template/head.php -->
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title ?? _Global::$SITE_TITLE) ?></title>
    <?php \_Flexagon\Libs\AssetLoader::printPreloads('    ') ?>
    <?php \_Flexagon\Libs\AssetLoader::printStyles('    ') ?>
</head>
<body>
```

### AssetLoader

Collect CSS and JavaScript assets and render them together at the desired location. A version query string can be added for cache invalidation.

```php
use _Flexagon\Libs\AssetLoader;

AssetLoader::setVersion('3.4.13');          // append ?_=3.4.13 to all asset URLs
AssetLoader::setAssetRootPath('/assets/');

AssetLoader::setCss('main.css');                        // /assets/css/main.css (includes preload)
AssetLoader::setJs('app.js', isModule: true);           // /assets/js/app.js
AssetLoader::setJs('https://cdn.example.com/lib.js');   // external URLs remain unchanged

echo AssetLoader::getImageHtml('logo.png', 120, 40, 'Logo');
echo AssetLoader::getAssetUrl('fonts/pretendard.woff2');
```

- Relative paths are prefixed with `assetRootPath + dirPath`; URLs beginning with `http://`, `https://`, or `//` are left unchanged.
- Preload hints are rendered separately with `printPreloads()`. Place them in `<head>` while leaving script tags at the `printScripts()` location (typically just before `</body>`) so scripts can be fetched while the document body is being parsed.
- Preload hints are not added to absolute URLs because a mismatched `crossorigin` setting may cause the browser to download the resource twice.
- Each `print*()` method renders one asset type, and the template controls placement: `printPreloads()` and `printStyles()` belong in `<head>`, while `printScripts()` typically goes just before `</body>`.
- Use `unsetJs('analytics.js')` to remove a previously registered script.

---

## Utility Libraries

All utilities use static methods and have no external dependencies. Their namespace is `_Flexagon\Libs`.

| Class | Key Features |
|---|---|
| `StringUtil` | case conversion, UTF-8 cleanup, hex encoding, random string generation |
| `ArrayUtil` | camelCase key conversion, array diff, nested-array key lookup |
| `ValidUtil` | email, integer, float, alphanumeric, and ID validation |
| `CryptoUtil` | AES-256-CBC encryption/decryption (strings / arrays, random IV support) |
| `FileUtil` | directory traversal, recursive creation, extension detection |
| `HttpUtil` | URL parsing, query-string parsing, request-path resolution |
| `NetUtil` | GET/POST HTTP client, JSON response parsing, file uploads |
| `HtmlUtil` | redirects, HTML tag/attribute removal, image URL extraction |
| `TimeDateUtil` | timestamp conversion, timezone offsets, relative time formatting |
| `SqlUtil` | parameter normalization/expansion, SQL escaping, SELECT → COUNT conversion |
| `ClassUtil` | class name, path, property, and caller inspection |
| `PhpDocUtil` | PHPDoc tag parsing (basis of annotation support) |
| `AssetLoader` | CSS/JS management |
| `TemplateLoader` | template loading |
| `DataSourceManager` | connection reuse and global transactions |

### Global Helper Functions

The three functions defined by `Libs/_Util.php` are available globally without a namespace.

```php
_echo('Hello, {name}', ['name' => $user->getName()]);   // substitute and output
$msg = _t('{count} results found', ['count' => 42]);    // substitute and return
```

When gettext (`__()` or `_()`) is available, translation is applied before placeholder substitution.

### Enum Helpers

Add the `EnumSupporter` trait to a backed enum to enable convenience methods.

```php
use _Flexagon\Supporters\EnumSupporter;

enum UserRole: string {
    use EnumSupporter;

    case ADMIN = 'ADMIN';
    case USER  = 'USER';
}

UserRole::keys();            // ['ADMIN', 'USER']
UserRole::values();          // ['ADMIN', 'USER']
UserRole::toArray();         // ['ADMIN' => 'ADMIN', 'USER' => 'USER']
UserRole::toCommaString();   // 'ADMIN,USER'
```

When generating DDL for an ENUM column, use `toQuotedCommaString()`. It quotes each value and escapes embedded quotes by doubling them.

```php
UserRole::toQuotedCommaString();      // "'ADMIN','USER'"
UserRole::toQuotedCommaString('"');   // '"ADMIN","USER"'

// Safe even when values contain quotes or commas
// case A = "it's";  case B = 'a,b';
//   toCommaString()        → it's,a,b          (breaks DDL)
//   toQuotedCommaString()  → 'it''s','a,b'
```

For pure enums without backing values, `keys()` and `toCommaString()` return case names, while `values()` and `toArray()` return `null` values.

Enum-typed properties are also handled automatically by DAO mapping. Scalar database values are converted to matching enum cases when models are hydrated, and `->value` is bound when values are stored.

---

## CLI Scripts

Place batch jobs in the `script/` directory.

```php
<?php
// script/cleanup.php
require_once '__flexagon.php';

$deleted = (new \ExampleUser\UserDAO())->deleteExpired();
echo "Deleted: {$deleted} rows\n";
```

```bash
php script/cleanup.php runtimeStage=production days=30
```

Arguments in `key=value` form are parsed into both `$_PARAMS` and `$_REQUEST`. `runtimeStage` is reserved and selects the configuration directory to load.

When running from the CLI, `FLEXAGON_ENV::$_RUNTIME_ENV` is set to `RUNTIME_ENV_SCRIPT`, bypassing the web request pipeline entirely, including sessions, routing, and output buffering.

### Framework CLI

The framework itself provides two commands.

```bash
php script/flexagon.php init [--path=DIR] [--force]
php script/flexagon.php check [--path=DIR]
```

| Invocation | Supported installation types |
|---|---|
| `php script/flexagon.php` | All (Composer · PHAR · source) |
| `php vendor/bin/flexagon` | Composer installation |

> A PHAR cannot be passed to the PHP CLI as a `phar://...` script path, so `script/flexagon.php` acts as the entry point. The PHAR does not contain templates for `init`, so only `check` is available in that installation mode.

#### `init`

Creates the 18 files that make up the project structure. If you started with `composer create-project flexagon/skeleton`, these files already exist. This command is primarily useful when **adding `flexagon/framework` to an existing project with `composer require`**.

**Existing files are left untouched.** You can also run the command after an upgrade to add only newly introduced structure files.

```
create   script/flexagon.php
keep     public/index.php
1 created, 17 kept, 0 failed
```

Use `--force` if you need to overwrite existing files. Be careful: modified files will be replaced with the template versions.

#### `check`

Checks that the project layout and runtime configuration are consistent and returns **exit code 1 when a problem is found**, making it suitable for deployment pipelines.

| Item | Check | Result |
|---|---|---|
| `php` | 8.3 or later | Error |
| `ext-json` · `ext-openssl` · `ext-pdo` | Loaded | Error |
| `opcache.save_comments` | Enabled | Error |
| `project layout` | `flexagon.php` · `application/_Global.php` · `public/__flexagon.php` · `public/_router.php` | Error |
| `config placement` | `_Config.php` and `application/` are not exposed under `public/` | Error |
| `bootstrap` | Framework boots successfully | Error |
| `session key` | `$SESSION_ENCRYPTION_STRING` length when `$SESSION_AUTO_START` is enabled | Error |
| `data sources` | Configured; no template placeholders remain | Warning |
| `display_errors` | Enabled in production mode | Warning |

The following checks are especially important because these issues can otherwise **fail silently**.

- If `opcache.save_comments` is disabled, `@encrypt`, `@db_auto_timestamp`, and `@exclude_from_get_array` are all ignored. A column that should be encrypted may be stored in plaintext without any exception.
- If `_Config.php` is placed under `public/`, database credentials may be served directly if PHP handling is unavailable or misconfigured.
- If a data source still contains the default `username` / `dbname` placeholders, the connection simply fails.

```
$ php script/flexagon.php check

  ok       php 8.3.33
  ok       ext-json
  ok       opcache.save_comments
  ok       project layout
  ok       bootstrap
  warn     data sources  default still contains template placeholders
  ok       session  auto start off
  ok       display_errors  off

  0 problem(s), 1 warning(s)
```

## License

[MIT License](LICENSE)

Copyright (c) 2010-2026 Younghwan Yong