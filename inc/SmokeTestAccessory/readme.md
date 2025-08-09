# SmokeTestAccessory

A minimal, dev-only accessory to verify the Accessory plumbing end-to-end. It renders simple output and terminates the request. Not intended for production use.

## Components

- `SmokeTestAccessory` (interface)
  - Contract for a simple smoke test provider.
  - `test(): array` should return the lines to print.
- `SmokeTestAccessoryManager`
  - Calls `test()` on objects implementing `SmokeTestAccessory`.
  - Echoes each returned line wrapped in `<pre>` and then calls `wp_die()`.
- `SmokeTestAccessoryAttribute`
  - Tiny demo class that echoes a header and calls `wp_die()` (useful for manual checks).

## Usage (dev-only)

Implement the interface on any object and invoke the manager during initialization:

```php
<?php
use Ran\PluginLib\SmokeTestAccessory\SmokeTestAccessory;
use Ran\PluginLib\SmokeTestAccessory\SmokeTestAccessoryManager;

final class MySmokeProvider implements SmokeTestAccessory {
    public function test(): array {
        return array(
            'Smoke test: OK',
            'Environment healthy',
        );
    }
}

// Somewhere in your bootstrap code (dev only)
$manager = new SmokeTestAccessoryManager();
$manager->init(new MySmokeProvider());
```

Expected behavior:

- Output is printed inside a `<pre>` block with `<br>` line breaks
- Request terminates via `wp_die()`

## Tests

See `Tests/Unit/SmokeTestAccessory/SmokeTestAccessoryManagerTest.php` for behavior verification:

- Emits expected lines and calls `wp_die()` for valid providers
- Does nothing for non-`SmokeTestAccessory` objects

## Notes

- This is intentionally side-effectful (echo + `wp_die()`) to catch wiring issues early.
- If you need a non-terminating diagnostic, adapt the manager to log or return content instead of calling `wp_die()`.
- Keep this module disabled in production builds.
