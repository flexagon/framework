# Annotation Reference

Flexagon reads annotations attached to properties and methods at runtime and adjusts its behavior accordingly. Rules are declared right next to the values they apply to, without separate configuration files or mapping declarations.

Annotations can be written in two ways.

```php
// PHP 8 attribute — recommended
#[Encrypt]
private string $ssn = '';
```

```php
// PHPDoc tag — legacy syntax, still supported
/** @encrypt */
private string $ssn = '';
```

- [Attributes and PHPDoc](#attributes-and-phpdoc)
- [`Encrypt`](#encrypt)
- [`DbAutoTimestamp`](#dbautotimestamp)
- [`ExcludeFromGetArray`](#excludefromgetarray)
- [Creating Your Own](#creating-your-own)
- [PHPDoc Syntax Rules](#phpdoc-syntax-rules)
- [Limitations](#limitations)

---

## Attributes and PHPDoc

The two forms are **fully equivalent** and may be used together. Attributes are recommended for new code.

| | attribute | PHPDoc tag |
|---|---|---|
| Editor autocomplete | **O** (including automatic `use` statements) | ✗ |
| Go to definition · Rename | **O** | ✗ |
| Typo detection | **O** (reported as an unknown class) | ✗ (silently ignored) |
| `opcache.save_comments = 0` | **Unaffected** | **All ignored** |

The last row is the most important in practice. Because PHPDoc annotations are comments, they are **silently ignored** on servers configured to discard comments through OPcache. A column that should be encrypted, for example, could be stored as plaintext.

```
opcache.save_comments = 0
  /** @encrypt */  →  ignored
  #[Encrypt]       →  works normally
```

`flexagon check` verifies this setting.

### Usage

```php
use _Flexagon\Attributes\Encrypt;
use _Flexagon\Attributes\DbAutoTimestamp;
use _Flexagon\Attributes\ExcludeFromGetArray;

class UserModel extends BaseModel {
    #[Encrypt]
    private string $ssn = '';

    #[DbAutoTimestamp('insert')]
    private ?int $firstSeen = null;

    #[DbAutoTimestamp('insert', 'update')]
    private ?int $touched = null;

    #[ExcludeFromGetArray]
    public function getPasswordHash(): string { ... }
}
```

When multiple values are needed, list them as separate arguments or join them with `|` — `#[DbAutoTimestamp('insert', 'update')]` and `#[DbAutoTimestamp('insert|update')]` are equivalent.

### Where to Apply Them

| attribute | PHPDoc | Target |
|---|---|---|
| `#[Encrypt]` | `@encrypt` | **Property** |
| `#[DbAutoTimestamp]` | `@db_auto_timestamp` | **Property** |
| `#[ExcludeFromGetArray]` | `@exclude_from_get_array` | **Getter method** |

Note that only `ExcludeFromGetArray` is applied to a method because `getArray()` scans getters. Attributes declare `Attribute::TARGET_PROPERTY` or `TARGET_METHOD`, so **your editor can immediately flag an attribute applied to the wrong target.**

### Editor Setup

Attributes are real classes, so autocomplete works **without any additional configuration**.

If you continue using PHPDoc annotations, see the helper files included with the skeleton — VS Code can use `.vscode/flexagon.code-snippets` immediately, while PhpStorm only requires importing `docs/ide/phpstorm-live-templates.xml` once. See `docs/ide/README.md` for details.

## `Encrypt`

Encrypts property values when storing them in the database and decrypts them when query results are mapped back to objects.

```php
use _Flexagon\Attributes\Encrypt;

class UserModel extends BaseModel
{
    #[Encrypt]
    private string $residentNumber = '';

    public function getResidentNumber(): string { return $this->residentNumber; }
    public function setResidentNumber(string $v): void { $this->residentNumber = $v; }
}
```

The PHPDoc equivalent is `@encrypt`.

```php
/**
 * @var string
 * @encrypt
 */
private string $residentNumber = '';
```

### Configuration

```php
// application/_Config.php — at least 10 characters
_Global::$CLASS_PROPERTY_ENCRYPTION_PASSPHRASE = 'a sufficiently long and unpredictable value';
```

Models with no properties marked with `@encrypt` work without this setting.

### When It Runs

| Stage | Behavior |
|---|---|
| `_insert()` · `_update()` | `_getParams()` calls `encrypt()`, then binds the encrypted value |
| Query → object mapping | `getAllResultAsObject()` calls `decrypt()` to decrypt the value |

You can also call `$model->encrypt()` / `$model->decrypt()` directly.

### ⚠️ The Model Is Modified In Place

`encrypt()` **overwrites the property value with ciphertext.** If you read the model again after passing it to a DAO, you will get the encrypted value.

```php
$user->setResidentNumber('900101-1234567');
$userDAO->insert($user);

$user->getResidentNumber();   // ciphertext, not '900101-1234567'
$user->decrypt();             // decrypt it again if needed
$user->getResidentNumber();   // '900101-1234567'
```

### Column Size

Because AES-256-CBC + IV + HMAC is wrapped in base64, the ciphertext is considerably longer than the plaintext.

| Plaintext | Ciphertext |
|---|---|
| 1 ~ 15 bytes | 88 characters |
| 16 ~ 31 bytes | 108 characters |
| 50 bytes | 152 characters |
| 100 bytes | 216 characters |
| 255 bytes | 408 characters |

As a rule of thumb, allow roughly **`plaintext × 1.4 + 90`**. `VARCHAR(128)` is sufficient for a 14-byte resident registration number.

### Ciphertext Format

```
base64( VERSION(1) || IV(16) || HMAC-SHA256(32) || CIPHERTEXT )
```

- A new IV is generated for every call. The same plaintext produces different ciphertext each time, so identical values cannot be identified by looking at the database alone.
- Before decryption, the HMAC is verified with `hash_equals()`. Tampered values return an empty string.
- Keys are derived from the passphrase with `hash_hkdf()`, with separate keys for encryption and authentication.

### Limitations

- **Only string values are encrypted.** The annotation is ignored on `int`, `bool`, and `null` properties.
- Encrypted columns cannot be searched with `WHERE` or sorted. If searching is required, use a separate hash column.

---

## `DbAutoTimestamp`

Automatically fills the current Unix timestamp on INSERT and UPDATE.

```php
use _Flexagon\Attributes\DbAutoTimestamp;

#[DbAutoTimestamp('insert')]
private ?int $createdAt = null;

#[DbAutoTimestamp('insert', 'update')]
private ?int $updatedAt = null;
```

The PHPDoc equivalent is `@db_auto_timestamp`.

```php
/**
 * @var int|null
 * @db_auto_timestamp insert|update
 */
private ?int $updatedAt = null;
```

`BaseModel` already provides these two properties, so adding `created_at` and `updated_at` columns (INT) to the table is enough; no additional declarations are required. `BaseModel` itself uses attributes, so this behavior is unaffected by `opcache.save_comments`.

### Values

| Value | Applied On |
|---|---|
| `insert` | `_insert()` |
| `update` | `_insert()`, `_update()`, `_updateByQuery()` |

`_insert()` applies **both** `insert` and `update`, so `updated_at` is also populated for new rows.

### Disabling

```php
_Global::$USE_AUTO_CREATED_AT_TIMESTAMP = false;   // ignore the insert tag
_Global::$USE_AUTO_UPDATED_AT_TIMESTAMP = false;   // ignore the update tag
```

### Limitations

- The value is the **Unix timestamp (integer)** returned by `time()`. The column type must be `INT`. If you need `DATETIME`, use the database's `DEFAULT CURRENT_TIMESTAMP` instead of this annotation.
- The property must have a **setter**. The DAO assigns the value by calling `set<PropertyName>()` directly. A getter is also required for the value to be stored as a column.

Use the helper provided by `BaseModel` when you need a formatted string.

```php
$model->getCreatedAtDatetimeString();   // '2026-08-19 17:15:33'
```

---

## `ExcludeFromGetArray`

Apply it to a **getter method** to exclude the value from `getArray()` and `getJson()` output. Use it for values such as password hashes that should not be serialized.

```php
use _Flexagon\Attributes\ExcludeFromGetArray;

class UserModel extends BaseModel
{
    private string $passwordHash = '';

    #[ExcludeFromGetArray]
    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
}
```

The PHPDoc equivalent is `@exclude_from_get_array`.

```php
$user->getJson();          // passwordHash omitted
$user->getArray();         // passwordHash omitted
$user->getPasswordHash();  // direct calls work normally
```

### The Two `getArray()` Arguments

```php
public function getArray(bool $containsObject = true, bool $forceAll = false): array
```

| Argument | Meaning |
|---|---|
| `$containsObject` | When `false`, nested objects are flattened into arrays and enums are converted to `->value` |
| `$forceAll` | When `true`, ignores `@exclude_from_get_array` and includes everything |

Getters in the `is*()` form are handled the same way as `get*()`. With `$forceAll = true`, both forms ignore `@exclude_from_get_array` and are included.

> Before 3.5.0, `is*()` getters were **omitted** when `$forceAll = true`. See [UPGRADE.md](UPGRADE.md) for details.

**DAOs do not use this method when writing to the database.** They call the corresponding getter directly for each column declared by the schema (`is*()` if `get*()` is unavailable), so columns marked with `@exclude_from_get_array` are still used normally for storage and retrieval. This annotation affects **serialization only**.

### Always Excluded

`getJson()` and `getArray()` themselves are excluded even without annotations.

`getCreatedAtDatetimeString()` and `getUpdatedAtDatetimeString()` provided by `BaseModel` are not excluded, so keys such as `createdAtDatetimeString` appear in the result. They are provided for serialization convenience and are not used for database storage or retrieval.

---

## Creating Your Own

`PhpDocUtil` is not limited to framework annotations. Applications can define and query their own annotations. **Attributes can be queried without separate registration** — the tag name is the short class name.

```php
use _Flexagon\Libs\PhpDocUtil;

#[Attribute(Attribute::TARGET_PROPERTY)] class Searchable {}
#[Attribute(Attribute::TARGET_PROPERTY)] class Sortable {}
#[Attribute(Attribute::TARGET_PROPERTY)]
class AuditLog
{
    /** @var string[] */
    public readonly array $on;

    public function __construct(string ...$on) { $this->on = $on; }
}

class ProductModel extends BaseModel
{
    #[Searchable]
    private string $name = '';

    #[Searchable]
    #[Sortable]
    private ?int $price = null;

    #[AuditLog('create', 'update', 'delete')]
    private ?string $status = null;
}

$model = new ProductModel();

// List of properties with a specific tag
PhpDocUtil::findProperties($model, 'searchable');              // ['name', 'price']

// Filter by tag + value
PhpDocUtil::findProperties($model, 'audit_log', 'delete');     // ['status']

// Check individually
PhpDocUtil::existsPropertyTag($model, 'price', 'sortable');    // true
PhpDocUtil::getPropertyTagValues($model, 'status', 'audit_log');// ['create','update','delete']
```

### Main API

| Method | Purpose |
|---|---|
| `findProperties($obj, $tag, $value = '')` | Names of properties with the tag |
| `findMethods($obj, $tag, $value = '')` | Names of methods with the tag |
| `existsPropertyTag($obj, $property, $tag)` | Whether the property has the tag |
| `existsMethodTag($obj, $method, $tag)` | Whether the method has the tag |
| `existsPropertyTagAndValue($obj, $property, $tag, $value)` | Whether both the tag and value match |
| `getPropertyTagValues($obj, $property, $tag)` | Tag values |
| `getMethodTagValues($obj, $method, $tag)` | Tag values |
| `getPropertyTags($obj, $property)` | All property tags (keys preserve the **original notation**) |
| `getMethodTags($obj, $method)` | All method tags |

Only the array keys returned by `getPropertyTags()` and `getMethodTags()` preserve the original notation (the short class name for attributes). All other lookup methods ignore case and separators, so an attribute declared as `AuditLog` can be found using `audit_log`.

Attribute arguments are read without calling `newInstance()`. The constructor is never executed during lookup, and the model does not fail even if the attribute class cannot be loaded.

### Avoiding Framework Name Collisions

`Encrypt`·`DbAutoTimestamp`·`ExcludeFromGetArray` (and their PHPDoc equivalents) are reserved by the framework. Because attributes are compared by **short class name even across different namespaces**, `App\Attributes\Encrypt` is treated as the same annotation as Flexagon's `Encrypt`.

Separators and case are also ignored, so `@dbAutoTimestamp` and `#[DBAutoTimestamp]` also collide with the reserved name. Using a prefix for application-defined annotations is recommended.

```php
#[AppSearchable]      /** @app_searchable */
```

---

## PHPDoc Syntax Rules

The following rules apply **only to PHPDoc annotations**. Attributes use standard PHP syntax and are not subject to these rules.

The canonical notation is **snake_case**.

```php
/** @encrypt */
/** @db_auto_timestamp insert|update */
/** @exclude_from_get_array */
```

**Tag names are case-insensitive and ignore word separators (`_`, `-`).** All of the following are recognized as the same tag.

```php
@db_auto_timestamp    @DbAutoTimestamp    @DB_AUTO_TIMESTAMP    @dbautotimestamp    @db-auto-timestamp
```

**Tag values are case-insensitive only**; separators remain significant. `insert` and `INSERT` are equivalent, while `my_value` and `myvalue` are different. This prevents unrelated values from matching unintentionally.

Separate multiple values with `|`.

```php
/** @db_auto_timestamp insert|update */
```

Both single-line and multiline docblocks are supported.

```php
/** @db_auto_timestamp insert */
private ?int $createdAt = null;

/**
 * @var int|null
 * @db_auto_timestamp insert
 */
private ?int $createdAt = null;
```

### ⚠️ One Tag Per Line

Tag values are read **to the end of the line**. If more than one tag appears on the same line, the later tag is consumed as part of the preceding tag's value and **is not recognized as a tag**.

```php
/** @searchable @sortable */          // ✗ sortable becomes a value of searchable
/**
 * @searchable
 * @sortable                          // ✓
 */
```

The same applies when placing an annotation on the same line as another tag such as `@var`. Always put each annotation on its own line.

---


---

## Limitations

Everything below applies **only to PHPDoc annotations**. Attributes use PHP syntax and do not have these pitfalls.

**They do not work if comments are stripped by the opcode cache.** PHPDoc annotations are read from docblocks through runtime reflection, so with `opcache.save_comments = 0` they are **silently ignored without an error**. A column that should be encrypted, for example, could be stored as plaintext.

```ini
opcache.save_comments = 1
```

`flexagon check` verifies this setting. Migrating to attributes removes this dependency.

**Only one tag can appear on each line.** This is because value parsing continues to the end of the line. See [PHPDoc Syntax Rules](#phpdoc-syntax-rules) above.

**A period in a tag value truncates the value at that point.** `@tag v1.0` → `v1`. Do not use periods in tag values.

**If the same tag appears twice in one docblock, only the last one is retained.**

```php
/**
 * @db_auto_timestamp insert
 * @db_auto_timestamp update    ← overwrites the previous value
 */
```

Put multiple values on a single line separated by `|`. Attributes cannot be applied more than once unless `Attribute::IS_REPEATABLE` is specified, so this issue does not arise with attributes.

```php
/** @db_auto_timestamp insert|update */
#[DbAutoTimestamp('insert', 'update')]
```
