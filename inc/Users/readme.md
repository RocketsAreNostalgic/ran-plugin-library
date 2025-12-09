# Users API

Fluent utilities for creating users and managing plugin/theme‑scoped per‑user settings.

**Note:** This API is currently in beta and subject to change. Feedback is welcome.

- Namespace: `Ran\PluginLib\Users`
- Primary classes:
  - `User` (final): fluent builder to create/detect and configure a user
  - `UserResult` (final): immutable result (`id`, `email`, `login`, `created`, `messages`)
  - `UserBuilderInterface`: interface for the builder
  - `UserOptionsStore` (final) + `UserOptionsStoreInterface`: focused adapter around `RegisterOptions` for per-user settings

## Quick start

```php
use Ran\PluginLib\Users\User;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\Logger;

/** @var ConfigInterface $config */
$config  = /* obtain Config instance */ null;
$logger  = null; // optional
$builder = new User($config, $logger);

$result = $builder
    ->email('ada@example.com')
    ->name('Ada', 'Lovelace')
    ->role('subscriber')
    ->notify(true)
    ->create();
// $result->created === true for new user; false when attached to existing
```

## Fluent API (snake_case)

- Identity & profile: `email()`, `login()`, `name()`, `role()`, `password()`, `generate_password()`, `notify()`
- Options (via `UserOptionsStore`):
  - `user_scope(bool $global = false, string $storage = 'meta')` // storage: `meta` or `option`
  - `options(array $kv)` // values to persist
  - `schema(array $schema, bool $seed_defaults = false, bool $flush = false)` // register schema before values
  - `with_policy(WritePolicyInterface $policy)` // gate writes
- Existence policy + terminal: `on_exists('attach'|'fail'|'update-profile')`, `create(): UserResult`

### on_exists policies

- `attach` (default): no core profile mutation; applies plugin‑scoped settings
- `fail`: throws with context when user already exists
- `update-profile`: updates allowlisted fields (first name, last name, role), then applies settings

## Light examples

Minimal create:

```php
$result = $builder
  ->email('grace@example.com')
  ->create();
```

Attach, fail, update‑profile:

```php
$builder->email('grace@example.com')->on_exists('attach')->create();
// $builder->email('grace@example.com')->on_exists('fail')->create(); // throws if exists
$builder->email('grace@example.com')->name('Grace','Hopper')->role('editor')->on_exists('update-profile')->create();
```

Per‑user options (meta and option storage):

```php
$schema = ['theme' => ['default' => 'light']];
$builder
  ->email('linus@example.com')
  ->on_exists('attach')
  ->user_scope(false, 'meta')
  ->schema($schema, true, false)
  ->options(['theme' => 'dark'])
  ->create();

$allowAll = new class implements \Ran\PluginLib\Options\Policy\WritePolicyInterface {
  public function allow(string $op, array $ctx): bool { return true; }
};
$builder
  ->email('torvalds@example.com')
  ->on_exists('attach')
  ->user_scope(true, 'option')
  ->with_policy($allowAll)
  ->options(['alpha' => 1.0])
  ->create();
```

More complete snippets live under:

- `inc/Users/docs/examples/user-builder-basic.php`
- `inc/Users/docs/examples/user-builder-on-exists.php`
- `inc/Users/docs/examples/user-builder-options.php`

## Notes

- Options are applied under the plugin’s grouped option via `RegisterOptions` in user scope.
