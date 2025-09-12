<?php
/**
 * WordPress Options Registration and Management.
 *
 * This class manages Plugin/Theme options by storing them as a single array.
 * It uses a storage adapter to handle scope-aware persistence.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Options\Storage\OptionStorageInterface;
use Ran\PluginLib\Options\Storage\SiteOptionStorage;
use Ran\PluginLib\Options\Storage\NetworkOptionStorage;
use Ran\PluginLib\Options\Storage\BlogOptionStorage;
use Ran\PluginLib\Options\Storage\UserMetaStorage;
use Ran\PluginLib\Options\Storage\UserOptionStorage;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy;

/**
 * Manages grouped settings via a scope-aware storage adapter (site, network, blog, or user).
 * Settings are grouped under one main option key, yielding a single stored row per scope context.
 *
 * This class provides methods for registering, retrieving, and updating settings grouped under
 * one main option key. Grouping improves organization and reduces the number of discrete rows,
 * while the actual storage location is selected by the current scope and adapter.
 *
 * Important semantics and recommendations:
 * - Schema merges are shallow: register_schema() performs a per-key shallow merge of rules, and default
 *   seeding replaces the entire value when seeding a missing key. For nested structures that require
 *   deep/conditional merging, perform an explicit read–modify–write using this sequence:
 *     1) Read current value: `$current = $options->get_option('my_key', array());`
 *     2) Merge with your patch (caller-defined):
 *        - Simple deep merge: `$merged = array_replace_recursive($current, $patch);`
 *        - Or custom logic for precise add/remove/transform semantics
 *     3) Write back: `$options->set_option('my_key', $merged);`
 *     4) Persist once (batch-friendly): `$options->flush(false);`
 *   Prefer flat keys where possible, and for disjoint top-level keys use
 *   `$options->add_options([...])` then `$options->flush(true)` to reduce churn.
 *
 * Storage and scope:
 * - Storage is adapter-backed and scope-aware:
 *   - Site scope: site option storage
 *   - Network scope: network option storage
 *   - Blog scope: per-blog option storage
 *   - User scope: user meta or user option storage
 * - See `_make_storage()` and `StorageContext` for how adapters are selected.
 * - Public passthrough on this class:
 *   - `supports_autoload(): bool` — whether current storage supports autoload
 *
 * In-memory vs persistence:
 * - Constructor/registration may seed in-memory state (schema defaults, initial merges)
 * - Persistence is explicit (set/update/add + `flush()`)
 * - `flush(true)` performs a top-level shallow merge with DB to reduce lost updates for disjoint keys.
 *   Nested structures are replaced wholesale; for deep merges, use read–modify–write pattern, then `flush(false)`.
 */
class RegisterOptions {
	use WPWrappersTrait;

	/**
	 * The in-memory store for all plugin options.
	 * Structure: ['option_key' => mixed]
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * The main WordPress option name under which all plugin settings are stored as an array.
	 *
	 * @var string
	 */
	private string $main_wp_option_name;

	/**
	 * Whether the main WordPress option group should be autoloaded by WordPress.
	 *
	 * @var bool
	 */
	private bool $main_option_autoload;

	/**
	 * Logger instance.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

	/**
	 * Internal storage adapter (scope-aware).
	 *
	 * @var OptionStorageInterface|null
	 */
	private ?OptionStorageInterface $storage = null;

	/**
	 * Typed storage context (preferred over stringly $storage_args).
	 * When null, defaults to Site on first storage access.
	 */
	private ?StorageContext $storage_context = null;

	/**
	 * Option schema map for sanitization, validation, and defaults.
	 * Keys are normalized option keys.
	 * Structure per key:
	 *   - 'default'  => mixed|null
	 *   - 'sanitize' => callable|null
	 *   - 'validate' => callable|null (returns true or throws/returns false)
	 *
	 * @var array<string, array{default:mixed|null, sanitize?:callable|null, validate?:callable|null}>
	 */
	private array $schema = array();

	/**
	 * Internal: origin op for persistence gating when _save_all_options() is called
	 * from another operation (e.g., 'set_option'). Null means default 'save_all'.
	 *
	 * @var string|null
	 */
	private ?string $__persist_origin = null;

	/**
	 * Immutable write policy used to gate persistence before filters.
	 * Initialized lazily to a RestrictedDefaultWritePolicy.
	 *
	 * @var WritePolicyInterface|null
	 */
	private ?WritePolicyInterface $write_policy = null;

	/**
     * Creates a new RegisterOptions instance.
     *
     * Initializes options by loading them from the database under `$main_wp_option_name`.
     * No implicit writes occur in the constructor.
     *
     * @internal
     * @param string      $main_wp_option_name The primary key for this instance's grouped settings.
     * @param bool        $main_option_autoload  Whether the entire group of options should be autoloaded by WordPress (if supported by storage). Defaults to true.
     * @param Logger|null $logger Optional Logger for dependency injection; when provided, it is bound before the first read.
     * @return mixed
     */
	protected function __construct(
        string $main_wp_option_name,
        bool $main_option_autoload = true,
        ?Logger $logger = null
    ) {
		$this->main_wp_option_name  = $main_wp_option_name;
		$this->main_option_autoload = $main_option_autoload;

		// Bind provided logger first (DI)
		if ($logger instanceof Logger) {
			$this->logger = $logger;
		}

		// Load all existing options from the single database entry (via storage adapter).
		$this->options = $this->_read_main_option();

		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: Initialized with main option '{$this->main_wp_option_name}'. Loaded " . count($this->options) . ' existing sub-options.');
		}
	}

	/**
	 * Named factory: Site scope instance.
	 *
	 * @param string      $option_name        Main option key
	 * @param bool        $autoload_on_create Whether to autoload on first create (site scope supports autoload)
	 * @param Logger|null $logger             Optional logger to bind before first read
	 * @return static
	 */
	public static function site(string $option_name, bool $autoload_on_create = true, ?Logger $logger = null): static {
		$instance                  = new static($option_name, $autoload_on_create, $logger);
		$instance->storage_context = StorageContext::forSite();
		// Ensure storage is rebuilt for this scope and payload is read from correct storage
		$instance->storage = null;
		$instance->options = $instance->_read_main_option();
		return $instance;
	}

	/**
	 * Named factory: Network scope instance.
	 *
	 * Network options do not support autoload semantics; flag is ignored at storage.
	 *
	 * @param string      $option_name Main option key
	 * @param Logger|null $logger      Optional logger to bind before first read
	 * @return static
	 */
	public static function network(string $option_name, ?Logger $logger = null): static {
		$instance                  = new static($option_name, false, $logger);
		$instance->storage_context = StorageContext::forNetwork();
		$instance->storage         = null;
		$instance->options         = $instance->_read_main_option();
		return $instance;
	}

	/**
	 * Named factory: Blog scope instance.
	 *
	 * Autoload on create is only meaningful when targeting the current blog. The underlying
	 * Blog storage adapter currently ignores the autoload flag; we still accept it for
	 * API parity and potential future support.
	 *
	 * @param string     $option_name         Main option key
	 * @param int        $blog_id             Target blog/site ID
	 * @param bool|null  $autoload_on_create  Autoload preference for current blog; null to leave default
	 * @param Logger|null $logger             Optional logger to bind before first read
	 * @return static
	 */
	public static function blog(string $option_name, int $blog_id, ?bool $autoload_on_create = null, ?Logger $logger = null): static {
		// Decide autoload preference (constructor requires bool). For non-current blog, force false.
		$current_blog = (int) (new class {
			use WPWrappersTrait;
			public function id() {
				return $this->_do_get_current_blog_id();
			}
		})->id();
		$effective_autoload = ($autoload_on_create === true || $autoload_on_create === false)
			? (bool) $autoload_on_create
			: false;
		if ($blog_id !== $current_blog) {
			$effective_autoload = false;
		}
		$instance                  = new static($option_name, $effective_autoload, $logger);
		$instance->storage_context = StorageContext::forBlog($blog_id);
		$instance->storage         = null;
		$instance->options         = $instance->_read_main_option();
		return $instance;
	}

	/**
	 * Named factory: User scope instance.
	 *
	 * User storage does not support autoload. By default, we use user meta storage.
	 * To opt into user option (per-site or global), callers can later specify args via Config
	 * or future fluent methods; for now, we default to meta with optional global option retained in args.
	 *
	 * @param string      $option_name Main option key
	 * @param int         $user_id     Target user ID
	 * @param bool        $global      When using user option storage, whether to use user_settings (network-wide)
	 * @param Logger|null $logger      Optional logger to bind before first read
	 * @return static
	 */
	public static function user(string $option_name, int $user_id, bool $global = false, ?Logger $logger = null): static {
		$instance                  = new static($option_name, false, $logger);
		$instance->storage_context = StorageContext::forUser($user_id, 'meta', $global);
		$instance->storage         = null;
		$instance->options         = $instance->_read_main_option();
		return $instance;
	}

	/**
	 * Factory: create a RegisterOptions instance using the option name derived from Config.
	 * Uses late static binding (new static) so subclasses receive instances when calling ::from_config().
	 *
	 * Simplified factory for construction-time concerns only. Use fluent methods for configuration:
	 * - .with_logger() for logger binding
	 * - .with_schema() for schema registration
	 * - .with_policy() for write policy
	 * - .with_defaults() for initial values
	 *
	 * Typed-first signature. StorageContext is the single source of truth for scope.
     *
     * @param ConfigInterface             $config   Initialized config instance.
     * @param StorageContext|null         $context  When null, defaults to site scope.
     * @param bool                        $autoload Autoload preference for site/blog storages on first create.
     * @return static
     */
	public static function from_config(\Ran\PluginLib\Config\ConfigInterface $config, ?StorageContext $context = null, bool $autoload = true): static {
		$optionName = $config->get_options_key();
		if ($optionName === '') {
			throw new \InvalidArgumentException('Missing or invalid options key from Config');
		}
		$instance = new static($optionName, $autoload, $config->get_logger());
		// Initialize typed context
		$ctx                       = $context ?? StorageContext::forSite();
		$instance->storage_context = $ctx;
		// Derive legacy storage_args for current write-gate compatibility (will be removed later)
		// Removed
		// Ensure storage is rebuilt for this scope and payload is read from correct storage
		$instance->storage = null;
		$instance->options = $instance->_read_main_option();
		return $instance;
	}

	/**
	 * Fluent setter: Set initial default values (in-memory only).
	 *
	 * This bypasses write gates and does NOT persist. Values are sanitized/validated
	 * if schema exists. Use add_options()->flush() to persist later if desired.
	 *
	 * @param array $defaults Default values to set
	 * @return static
	 */
	public function with_defaults(array $defaults): static {
		foreach ($defaults as $key => $value) {
			$k                 = $this->_do_sanitize_key((string) $key);
			$v                 = $this->_sanitize_and_validate_option($k, $value);
			$this->options[$k] = $v;
		}
		return $this;
	}

	/**
	 * Fluent setter: Configure write policy.
	 *
	 * @param WritePolicyInterface $policy Write policy instance
	 * @return static
	 */
	public function with_policy(WritePolicyInterface $policy): static {
		$this->write_policy = $policy;
		return $this;
	}

	/**
	 * Fluent setter: Configure logger instance.
	 *
	 * @param Logger $logger Logger instance
	 * @return static
	 */
	public function with_logger(Logger $logger): static {
		$this->logger = $logger;
		return $this;
	}

	/**
	 * Fluent alias of register_schema(); returns $this for chaining.
	 *
	 * Schema key principles: no implicit writes (unless $flush is true),
	 * separation of concerns (schema can be pre-wired via Config), and
	 * Config as source for main option name and autoload policy.
	 *
	 * @param array $schema  Schema map: ['key' => ['default' => mixed|callable(ConfigInterface|null): mixed, 'sanitize' => callable|null, 'validate' => callable|null]]
	 * @param bool  $seed_defaults If true, set missing option values from 'default' (after sanitize/validate)
	 * @param bool  $flush If true, persist after seeding (single write)
	 * @return self
	 */
	public function with_schema(array $schema, bool $seed_defaults = false, bool $flush = false): self {
		$this->register_schema($schema, $seed_defaults, $flush);
		return $this;
	}

	/**
	 * Register/extend schema post-construction (for lazy registration or migrations).
	 *
	 * - Merges provided rules into the existing schema (per-key shallow override)
	 * - Optionally seeds defaults for keys missing a value
	 * - Optionally flushes once after seeding
	 *
	 * @param array $schema  Schema map: ['key' => ['default' => mixed|callable(ConfigInterface|null): mixed, 'sanitize' => callable|null, 'validate' => callable|null]]
	 * @param bool  $seed_defaults If true, set missing option values from 'default' (after sanitize/validate)
	 * @param bool  $flush If true, persist after seeding (single write)
	 * @return bool When flush=false: whether any values were seeded; when flush=true: whether the save succeeded
	 */
	public function register_schema(array $schema, bool $seed_defaults = false, bool $flush = false): bool {
		if (empty($schema)) {
			return false;
		}

		$normalized = $this->_normalize_schema_keys($schema);

		// Merge schema shallowly per provided fields (by design)
		foreach ($normalized as $key => $rules) {
			if (!isset($this->schema[$key])) {
				$this->schema[$key] = $rules;
			} else {
				$existing           = $this->schema[$key];
				$this->schema[$key] = array(
				    'default'  => array_key_exists('default', $rules)  ? $rules['default']  : ($existing['default'] ?? null),
				    'sanitize' => array_key_exists('sanitize', $rules) ? $rules['sanitize'] : ($existing['sanitize'] ?? null),
				    'validate' => array_key_exists('validate', $rules) ? $rules['validate'] : ($existing['validate'] ?? null),
				);
			}
		}

		$changed = false;
		if ($seed_defaults) {
			// Note: Seeding defaults is an in-memory operation; do not gate with allow_persist.

			// Build a temporary map to avoid partial in-memory state on failure
			if ($seed_defaults) {
				$toSeed = array();
				try {
					foreach ($normalized as $key => $rules) {
						$has_value = isset($this->options[$key]);
						if (!$has_value && array_key_exists('default', $rules)) {
							$resolved     = $this->_resolve_default_value($rules['default']);
							$resolved     = $this->_sanitize_and_validate_option($key, $resolved);
							$toSeed[$key] = $resolved;
						}
					}
				} catch (\Throwable $e) {
					// Log and abort seeding without mutating in-memory state
					$this->_get_logger()->error(
						'RegisterOptions: register_schema seed_defaults failed; aborting seeding.',
						array(
							'main_option' => $this->main_wp_option_name,
							'error'       => $e->getMessage(),
						)
					);
					$toSeed        = array();
					$seed_defaults = false;
				}

				// Apply staged seeds atomically
				if ($seed_defaults && !empty($toSeed)) {
					foreach ($toSeed as $k => $entry) {
						$this->options[$k] = $entry;
					}
					$changed = true;
				}
			}
		}

		if ($flush && $changed) {
			return $this->_save_all_options();
		}
		return $changed;
	}

	/**
	 * Retrieves a specific option's value from the main options array.
	 *
     * @param string $option_name The name of the sub-option to retrieve. Key is sanitized via sanitize_key().
	 * @param mixed  $default     Optional. Default value to return if the sub-option does not exist.
	 * @return mixed The value of the sub-option, or the default value if not found.
	 */
	public function get_option(string $option_name, mixed $default = false): mixed {
		$option_name_clean = $this->_do_sanitize_key($option_name);
		$value             = $this->options[$option_name_clean] ?? $default;

		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$log_value = is_scalar($value) ? (string) $value : (is_array($value) ? 'Array' : 'Object');
			if (strlen($log_value) > 100) {
				$log_value = substr($log_value, 0, 97) . '...';
			}
			$found_status = isset($this->options[$option_name_clean]) ? 'Found' : 'Not found, using default';
			$this->_get_logger()->debug("RegisterOptions: Getting option '{$option_name_clean}' from '{$this->main_wp_option_name}'. Status: {$found_status}. Value: {$log_value}");
		}
		// @codeCoverageIgnoreEnd
		return $value;
	}

	/**
	 * Returns the entire array of options currently held by this instance.
	 *
	 * @return array<string, mixed> The array of all sub-options.
	 */
	public function get_options(): array {
		return $this->options;
	}

	/**
	 * Sets or updates a specific option's value within the main options array and saves any added options to the DB.
	 *
	 * @param string     $option_name The name of the sub-option to set. Key is sanitized via sanitize_key().
	 * @param mixed      $value       The value for the sub-option.
	 * @return bool True if any added options were successfully saved, false otherwise.
	 *
	 * Note:
	 * - No-op guard uses strict (===) comparison for the value.
	 * - Arrays must match exactly (keys/order/values) to be considered unchanged.
	 * - Objects must be the same instance to avoid a write; identical state in different instances will trigger a save.
	 */
	public function set_option(string $option_name, mixed $value): bool {
		$option_name_clean = $this->_do_sanitize_key($option_name);
		$value             = $this->_sanitize_and_validate_option($option_name_clean, $value);

		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: Setting option '{$option_name_clean}' in '{$this->main_wp_option_name}'.");
		}
		// @codeCoverageIgnoreEnd

		// Early gate (global and scope when known) before any storage interaction
		// Removed

		// Avoid DB churn: if nothing changed, short-circuit
		if (isset($this->options[$option_name_clean])) {
			$existing = $this->options[$option_name_clean];
			if ($existing === $value) {
				return true; // No-op change
			}
		}

		// Write gate just before mutating in-memory state
		$ctx = $this->_get_storage_context();
		$wc  = WriteContext::for_set_option(
			$this->main_wp_option_name,
			$ctx->scope->value,
			$ctx->blog_id,
			$ctx->user_id,
			$ctx->user_storage ?? 'meta',
			(bool) $ctx->user_global,
			$option_name_clean
		);
		$__pre_mut_allowed = $this->_apply_write_gate('set_option', $wc);
		if (!$__pre_mut_allowed) {
			return false; // veto: protect in-memory state
		}

		// Stage mutation and rollback if persistence is vetoed/fails to avoid in-memory drift
		$__prev_options                    = $this->options;
		$this->options[$option_name_clean] = $value;
		// Defensive: re-check write gate just prior to persistence in case policies changed
		$__pre_persist_allowed = $this->_apply_write_gate('set_option', $wc);
		if (!$__pre_persist_allowed) {
			$this->options = $__prev_options; // rollback staged change
			return false;
		}
		// Persist with origin-aware gating so a set_option veto blocks its persistence
		$this->__persist_origin = 'set_option';
		$__ok                   = $this->_save_all_options();
		$this->__persist_origin = null;
		if (!$__ok) {
			$this->options = $__prev_options; // rollback on veto/failure
		}
		return $__ok;
	}

	/**
	 * Add a single option to the in-memory store (fluent). Call flush() to persist.
	 *
	 * @param string $option_name The name of the sub-option to add.
	 * @param mixed $value The value for the sub-option.
	 * @return self
	 */
	public function add_option(string $option_name, mixed $value): self {
		$key   = $this->_do_sanitize_key($option_name);
		$value = $this->_sanitize_and_validate_option($key, $value);

		// No-op guard
		if (isset($this->options[$key])) {
			$existing = $this->options[$key];
			if ($existing === $value) {
				return $this;
			}
		}

		// Gate before mutating memory (after no-op guard)
		$ctx = $this->_get_storage_context();
		$wc  = WriteContext::for_add_option(
			$this->main_wp_option_name,
			$ctx->scope->value,
			$ctx->blog_id,
			$ctx->user_id,
			(string)($ctx->user_storage ?? 'meta'),
			(bool)($ctx->user_global ?? false),
			$key
		);
		if (!$this->_apply_write_gate('add_option', $wc)) {
			return $this; // veto: no mutation
		}

		$this->options[$key] = $value;
		return $this;
	}

	/**
	 * Batch add multiple options to the in-memory store (fluent). Call flush() to persist.
	 *
	 * @param array<string, mixed> $keyToValue Map of option name => value
	 * @return self
	 */
	public function add_options(array $keyToValue): self {
		$changed = false;

		// Gate batch addition before mutating memory
		$keys = array_map(static fn($k) => (string) $k, array_keys($keyToValue));
		$ctx  = $this->_get_storage_context();
		$wc2  = WriteContext::for_add_options($this->main_wp_option_name, $ctx->scope->value, $ctx->blog_id, $ctx->user_id, $ctx->user_storage ?? 'meta', (bool) $ctx->user_global, $keys);
		if (!$this->_apply_write_gate('add_options', $wc2)) {
			return $this; // veto: no mutation
		}

		foreach ($keyToValue as $option_name => $value) {
			$key   = $this->_do_sanitize_key((string) $option_name);
			$value = $this->_sanitize_and_validate_option($key, $value);

			if (isset($this->options[$key])) {
				$existing = $this->options[$key];
				if ($existing === $value) {
					continue; // no change
				}
			}

			$this->options[$key] = $value;
			$changed             = true;
		}

		// Return self for fluent chaining (flush separately)
		return $this;
	}

	/**
	 * Determine if an option exists (by normalized key) in the in-memory store.
	 *
	 * @param string $option_name The name of the sub-option to check.
	 * @return bool True if the option exists, false otherwise.
	 */
	public function has_option(string $option_name): bool {
		$key = $this->_do_sanitize_key($option_name);
		return array_key_exists($key, $this->options);
	}

	/**
	 * Delete an option by name and persist changes. Returns true if the key existed and was removed.
	 */
	public function delete_option(string $option_name): bool {
		$key = $this->_do_sanitize_key($option_name);
		if (!array_key_exists($key, $this->options)) {
			return false;
		}
		// Gate delete before mutating
		$ctx = $this->_get_storage_context();
		$wc  = WriteContext::for_delete_option(
			$this->main_wp_option_name,
			$ctx->scope->value,
			$ctx->blog_id,
			$ctx->user_id,
			(string)($ctx->user_storage ?? 'meta'),
			(bool)($ctx->user_global ?? false),
			$key
		);
		if (!$this->_apply_write_gate('delete_option', $wc)) {
			return false; // veto: no mutation
		}

		unset($this->options[$key]);
		return $this->_save_all_options();
	}

	/**
	 * Refreshes the local options cache by reloading them from the database.
	 *
	 * @return void
	 */
	public function refresh_options(): void {
		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: Refreshing options from database for '{$this->main_wp_option_name}'.");
		}
		// @codeCoverageIgnoreEnd
		$this->options = $this->_read_main_option();
	}

	/**
	 * Clear all sub-options in this group and persist the empty set.
	 */
	public function clear(): bool {
		// Gate clear before mutating
		$ctx = $this->_get_storage_context();
		$wc  = WriteContext::for_clear(
			$this->main_wp_option_name,
			$ctx->scope->value,
			$ctx->blog_id,
			$ctx->user_id,
			(string)($ctx->user_storage ?? 'meta'),
			(bool)($ctx->user_global ?? false)
		);
		if (!$this->_apply_write_gate('clear', $wc)) {
			return false; // veto
		}

		$this->options = array();
		return $this->_save_all_options();
	}

	/**
	 * Persist current in-memory options to the database.
     * Explicit persistence point: complements the "No implicit writes" principle.
	 *
	 * @param bool $merge_from_db When true, reads current DB value and performs a
	 *                          shallow, top-level merge before saving:
	 *                          - Existing DB keys are preserved
	 *                          - In-memory keys overwrite on collision
	 *                          This reduces lost updates for disjoint keys during
	 *                          installers/migrations. Nested values are replaced
	 *                          as a whole; for complex merges, callers should
	 *                          read–modify–write and then flush(false).
	 *                          See header notes for details.
	 *
	 * @return bool Whether the save succeeded.
	 */
	public function flush(bool $merge_from_db = false): bool {
		return $this->_save_all_options($merge_from_db);
	}

	/**
	 * Seed the main option row with provided defaults if it does not already exist (idempotent).
	 *
	 * - No write occurs if the main option row already exists.
	 * - Defaults are normalized and passed through schema sanitize/validate if present.
	 * - Autoload follows this instance's `$main_option_autoload` flag and is applied by the storage
	 *   adapter only when the current scope supports autoload for the main option row.
	 *
	 * @param array<string, mixed> $defaults Map of sub-option => value
	 * @return self
	 */
	public function seed_if_missing(array $defaults): self {
		// Distinguish truly missing from other falsy values via sentinel
		$sentinel = new \stdClass();
		$existing = $this->_do_get_option($this->main_wp_option_name, $sentinel);
		if ($existing !== $sentinel) {
			// Already present; do not modify DB or in-memory state
			// @codeCoverageIgnoreStart
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug("RegisterOptions: seed_if_missing no-op; option '{$this->main_wp_option_name}' already exists.");
			}
			// @codeCoverageIgnoreEnd
			return $this;
		}

		// Normalize defaults and apply schema rules
		$normalized = $this->_normalize_defaults($defaults);

		// Gate seeding before writing or mutating
		$ctx = $this->_get_storage_context();
		$wc  = WriteContext::for_seed_if_missing(
			$this->main_wp_option_name,
			$ctx->scope->value,
			$ctx->blog_id,
			$ctx->user_id,
			(string)($ctx->user_storage ?? 'meta'),
			(bool)($ctx->user_global ?? false),
			array_keys($normalized)
		);
		if (!$this->_apply_write_gate('seed_if_missing', $wc)) {
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: seed_if_missing vetoed by write gate');
			}
			return $this; // veto: do not write or mutate
		}

		// Persist atomically; add_option is a no-op if row is concurrently created
		$autoload = $this->main_option_autoload ? 'yes' : 'no';
		$this->_do_add_option($this->main_wp_option_name, $normalized, '', $autoload);

		// Sync in-memory cache
		$this->options = $normalized;

		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: seed_if_missing created '{$this->main_wp_option_name}' with " . count($normalized) . ' defaults.');
		}
		// @codeCoverageIgnoreEnd

		return $this;
	}

	/**
	 * Apply a migration function to the stored option value, if present.
	 *
	 * Callable signature: function (mixed $current, RegisterOptions $self): mixed
	 * Behavior:
	 * - Reads current stored value using a sentinel to detect true absence
	 * - If missing, no-op and returns $this
	 * - Invokes $migration($current, $this) without try/catch (exceptions propagate)
	 * - Strict change detection (!==). If changed, normalizes and updates the stored value
	 * - Preserves autoload by invoking core set_option() without autoload parameter
	 * - Synchronizes in-memory cache when a write occurs
	 */
	public function migrate(callable $migration): self {
		// Detect missing row
		$sentinel = new \stdClass();
		$current  = $this->_do_get_option($this->main_wp_option_name, $sentinel);
		if ($current === $sentinel) {
			// No-op when option row is absent
			// @codeCoverageIgnoreStart
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug("RegisterOptions: migrate no-op; option '{$this->main_wp_option_name}' missing.");
			}
			// @codeCoverageIgnoreEnd
			return $this;
		}

		// Compute new value (may throw; do not catch)
		$new = $migration($current, $this);

		// Strict change detection
		if ($new === $current) {
			return $this;
		}

		// Normalize to internal structure
		$normalized = array();
		if (is_array($new)) {
			foreach ($new as $key => $value) {
				$nk              = $this->_do_sanitize_key((string) $key);
				$normalized[$nk] = $this->_sanitize_and_validate_option($nk, $value);
			}
		} else {
			// If migration returns a scalar/object, wrap under a reserved key 'value'
			$nk              = $this->_do_sanitize_key('value');
			$normalized[$nk] = $this->_sanitize_and_validate_option($nk, $new);
		}

		// Gate migration write before updating DB / mutating memory
		$ctx = $this->_get_storage_context();
		$wc  = WriteContext::for_migrate(
			$this->main_wp_option_name,
			$ctx->scope->value,
			$ctx->blog_id,
			$ctx->user_id,
			(string)($ctx->user_storage ?? 'meta'),
			(bool)($ctx->user_global ?? false),
			array_keys($normalized)
		);
		if (!$this->_apply_write_gate('migrate', $wc)) {
			return $this; // veto: do not write/mutate
		}

		// Preserve autoload: call core set_option with two parameters
		$this->_do_update_option($this->main_wp_option_name, $normalized);

		// Sync in-memory cache
		$this->options = $normalized;

		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: migrate updated '{$this->main_wp_option_name}' with migrated data.");
		}
		// @codeCoverageIgnoreEnd

		return $this;
	}

	/**
	 * Whether the underlying storage supports autoload semantics.
	 *
	 * This is a passthrough to the scope-aware storage adapter to allow
	 * examples and callers to check autoload capability without reaching
	 * into internals.
	 */
	public function supports_autoload(): bool {
		return $this->_get_storage()->supports_autoload();
	}

	/**
	 * Returns the logger instance. Initializes a default logger if none is provided.
	 *
	 * @return Logger The logger instance.
	 */
	protected function _get_logger(): Logger {
		// @codeCoverageIgnoreStart
		if (null === $this->logger) {
			// No config provided; create a lightweight default logger
			$logger_from_config = new Logger(array());
			if (null === $logger_from_config) {
				// This case should ideally be prevented by Config::get_logger() throwing an exception if it cannot provide a logger.
				throw new \LogicException(static::class . ': Failed to retrieve a valid logger instance from Config. Config::get_logger() returned null.');
			}
			$this->logger = $logger_from_config;
		}
		// @codeCoverageIgnoreEnd
		return $this->logger;
	}

	/**
	 * Returns the composed storage adapter. Defaults to current site scope.
	 * Memoized per instance. No public API changes.
	 *
	 * @return OptionStorageInterface The storage adapter.
	 */
	protected function _get_storage(): OptionStorageInterface {
		if ($this->storage instanceof OptionStorageInterface) {
			$st = $this->storage;
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _get_storage resolved (cached)', array('scope' => $st->scope()->value));
			}
			return $st;
		}
		// Create storage via internal factory to reduce indirection
		$this->storage = $this->_make_storage();
		$st            = $this->storage;
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _get_storage resolved (new)', array('scope' => $st->scope()->value));
		}
		return $st;
	}

	/**
	 * Instantiate the scope-aware storage adapter from current scope/args.
	 *
	 * Rules:
	 * - site: SiteOptionStorage (supports autoload on create)
	 * - network: NetworkOptionStorage (no autoload)
	 * - blog: requires integer blog_id → BlogOptionStorage(blog_id)
	 * - user: requires integer user_id; user_storage 'meta' (default) or 'option'
	 *         when 'option', honor user_global (bool) flag
	 */
	private function _make_storage(): OptionStorageInterface {
		$ctx = $this->_get_storage_context();
		switch ($ctx->scope) {
			case OptionScope::Network:
				return new NetworkOptionStorage();
			case OptionScope::Blog:
				return new BlogOptionStorage((int) $ctx->blog_id);
			case OptionScope::User:
				if ($ctx->user_storage === 'option') {
					return new UserOptionStorage((int) $ctx->user_id, (bool) $ctx->user_global);
				}
				return new UserMetaStorage((int) $ctx->user_id);
			default:
				return new SiteOptionStorage();
		}
	}

	/**
	 * Resolve typed storage context (memoized). Defaults to Site.
	 */
	private function _get_storage_context(): StorageContext {
		if ($this->storage_context instanceof StorageContext) {
			return $this->storage_context;
		}
		$this->storage_context = StorageContext::forSite();
		return $this->storage_context;
	}

	/**
	 * Apply write-gating filters with rich context. Returns true when allowed.
	 *
	 * Filters applied (in order):
	 * - ran/plugin_lib/options/allow_persist
	 * - ran/plugin_lib/options/allow_persist/scope/{scope}
	 *
	 * @param string $op  Operation name (e.g., 'save_all', 'set_option', 'add_options')
	 * @param WriteContext $wc
	 * @return bool
	 */
	protected function _apply_write_gate(string $op, WriteContext $wc): bool {
		// Derive array ctx for filters/logging from WriteContext
		$ctx = array(
		    'op'           => $op,
		    'main_option'  => $wc->main_option(),
		    'scope'        => $wc->scope(),
		    'blog_id'      => $wc->blogId(),
		    'user_id'      => $wc->userId(),
		    'user_storage' => $wc->user_storage() ?? 'meta',
		    'user_global'  => (bool) $wc->user_global(),
		);
		if ($wc->key() !== null) {
			$ctx['key'] = $wc->key();
		}
		if ($wc->keys() !== null) {
			$ctx['keys'] = $wc->keys();
		}
		if ($wc->options() !== null) {
			$ctx['options'] = $wc->options();
		}
		if ($wc->merge_from_db()) {
			$ctx['merge_from_db'] = true;
		}

		// First, consult immutable, non-filterable write policy.
		if (!($this->write_policy instanceof WritePolicyInterface)) {
			$this->write_policy = new RestrictedDefaultWritePolicy();
		}
		$policyAllowed = $this->write_policy->allow($op, $wc);
		// Debug: trace policy decision
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug(
				'RegisterOptions: _apply_write_gate policy decision',
				array(
					'op'          => $op,
					'main_option' => $ctx['main_option'] ?? '',
					'scope'       => isset($ctx['scope']) ? (string) $ctx['scope'] : '',
					'policy'      => 'RestrictedDefaultWritePolicy',
					'allowed'     => (bool) $policyAllowed,
				)
			);
		}
		if ($policyAllowed === false) {
			$this->_get_logger()->notice(
				'RegisterOptions: Write vetoed by immutable policy.',
				array(
					'op'          => $op,
					'main_option' => $ctx['main_option'] ?? '',
					'scope'       => isset($ctx['scope']) ? (string) $ctx['scope'] : '',
				)
			);
			return false;
		}

		$allowed = true;
		// Debug: before general filter
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug(
				'RegisterOptions: _apply_write_gate applying general allow_persist filter',
				array(
					'hook' => 'ran/plugin_lib/options/allow_persist',
					'op'   => $op,
				)
			);
		}
		$allowed = (bool) $this->_do_apply_filter('ran/plugin_lib/options/allow_persist', $allowed, $ctx);
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug(
				'RegisterOptions: _apply_write_gate general filter result',
				array(
					'hook'    => 'ran/plugin_lib/options/allow_persist',
					'allowed' => $allowed,
				)
			);
		}

		$scope = isset($ctx['scope']) ? (string) $ctx['scope'] : '';
		if ($scope !== '') {
			$hook = 'ran/plugin_lib/options/allow_persist/scope/' . $scope;
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug(
					'RegisterOptions: _apply_write_gate applying scoped allow_persist filter',
					array(
						'hook'  => $hook,
						'op'    => $op,
						'scope' => $scope,
					)
				);
			}
			$allowed = (bool) $this->_do_apply_filter($hook, $allowed, $ctx);
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug(
					'RegisterOptions: _apply_write_gate scoped filter result',
					array(
						'hook'    => $hook,
						'allowed' => $allowed,
					)
				);
			}
		}

		if ($allowed === false) {
			$this->_get_logger()->notice(
				'RegisterOptions: Write vetoed by allow_persist filter.',
				array(
					'op'          => $op,
					'main_option' => $ctx['main_option'] ?? '',
					'scope'       => $scope,
				)
			);
		}
		// Debug: final decision
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug(
				'RegisterOptions: _apply_write_gate final decision',
				array(
					'op'          => $op,
					'main_option' => $ctx['main_option'] ?? '',
					'scope'       => $scope,
					'allowed'     => $allowed,
				)
			);
		}
		return $allowed;
	}

	/**
	 * Read the main option payload through the storage adapter with safe fallbacks.
	 *
	 * @return array<string, mixed>
	 */
	protected function _read_main_option(): array {
		$raw = $this->_get_storage()->read($this->main_wp_option_name);
		if (!is_array($raw)) {
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _read_main_option completed', array('count' => 0));
			}
			return array();
		}
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _read_main_option completed', array('count' => count($raw)));
		}
		return $raw;
	}

	/**
	 * Saves all currently held options to the database under the main option name.
	 *
	 * @param bool $merge_from_db Optional. If true, applies a shallow, top-level
	 *                          merge with the current DB value (keeps DB keys, overwrites with in-memory on collision)
	 *                          and does not perform deep/nested merges.
	 * @return bool True if the option was successfully updated or added, false otherwise.
	 */
	protected function _save_all_options(bool $merge_from_db = false): bool {
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug(
				'RegisterOptions: _save_all_options starting...',
				array(
					'origin'        => $this->__persist_origin ?? 'save_all',
					'merge_from_db' => $merge_from_db,
				)
			);
		}


		$to_save = $this->options;
		if ($merge_from_db) {
			// Load DB snapshot and merge top-level keys (no deep merge)
			$dbCurrent = $this->_get_storage()->read($this->main_wp_option_name);
			if (!is_array($dbCurrent)) {
				if ($this->_get_logger()->is_active()) {
					$this->_get_logger()->debug(
						'RegisterOptions: _save_all_options merge_from_db snapshot not array; normalizing to empty array',
						array('snapshot_type' => gettype($dbCurrent))
					);
				}

				$dbCurrent = array();
			}
			// Shallow top-level merge: keep DB keys, overwrite with in-memory on collision
			foreach ($this->options as $k => $value) {
				$dbCurrent[$k] = $value;
			}
			$to_save = $dbCurrent;
		}

		// Apply final gate before persistence with full context
		// Build WriteContext directly from typed StorageContext
		$ctx = $this->_get_storage_context();
		$wc  = WriteContext::for_save_all(
			$this->main_wp_option_name,
			$ctx->scope->value,
			$ctx->blog_id,
			$ctx->user_id,
			(string) ($ctx->user_storage ?? 'meta'),
			(bool) ($ctx->user_global ?? false),
			$to_save,
			$merge_from_db
		);
		$allowed = $this->_apply_write_gate($this->__persist_origin ?? 'save_all', $wc);
		if (!$allowed) {
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _save_all_options vetoed by policy.');
			}

			return false;
		}

		// Honor initial autoload preference only on creation.
		// Determine existence via WordPress get_option sentinel (legacy-compatible, testable via WP_Mock).
		$__sentinel     = new \stdClass();
		$__raw_existing = $this->_do_get_option($this->main_wp_option_name, $__sentinel);
		$__exists       = ($__raw_existing !== $__sentinel);
		if ($__exists) {
			// Existing row: use update() without autoload
			$result = $this->_get_storage()->update($this->main_wp_option_name, $to_save);
		} else {
			// Missing row: prefer add() with autoload; if it fails, fall back to update()
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug(
					'RegisterOptions: storage->add() selected',
					array('autoload' => (bool) $this->main_option_autoload)
				);
			}
			$result = $this->_get_storage()->add($this->main_wp_option_name, $to_save, $this->main_option_autoload);
			if (!$result) {
				if ($this->_get_logger()->is_active()) {
					$this->_get_logger()->debug('RegisterOptions: storage->add() returned false; falling back to storage->update().');
				}
				$result = $this->_get_storage()->update($this->main_wp_option_name, $to_save);
			}
		}

		// Mirror what we just saved to keep local cache consistent
		$this->options = $to_save;

		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug(
				'RegisterOptions: storage->update() completed.',
				array('result' => (bool) $result)
			);
		}

		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _save_all_options completed', array('result' => (bool) $result));
		}
		return $result;
	}

	/**
	 * Applies schema-based sanitization and validation to the value of a given option key.
	 * If no schema exists for the key, returns the value unchanged.
	 *
	 * @param string $normalized_key
	 * @param mixed  $value
	 * @return mixed
	 * @throws \InvalidArgumentException on failed validation
	 */
	protected function _sanitize_and_validate_option(string $normalized_key, mixed $value): mixed {
		if (!isset($this->schema[$normalized_key])) {
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _sanitize_and_validate_option no-schema', array('key' => $normalized_key));
			}
			return $value;
		}

		$rules = $this->schema[$normalized_key];

		if (isset($rules['sanitize']) && \is_callable($rules['sanitize'])) {
			$value = ($rules['sanitize'])($value);
		}
		if (isset($rules['validate']) && \is_callable($rules['validate'])) {
			$validator = $rules['validate'];
			$valid     = $validator($value);
			if ($valid !== true) {
				$valStr       = $this->_stringify_value_for_error($value);
				$validatorStr = $this->_describe_callable($validator);
				throw new \InvalidArgumentException(
					static::class . ": Validation failed for option '{$normalized_key}' with value {$valStr} using validator {$validatorStr}."
				);
			}
		}

		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _sanitize_and_validate_option completed', array('key' => $normalized_key));
		}
		return $value;
	}

	/**
	 * Resolves a default value which may be a raw value or a callable.
	 * If callable, it will be invoked with the current ConfigInterface|null and should return a value.
	 *
	 * @param mixed $default
	 * @return mixed
	 */
	protected function _resolve_default_value(mixed $default): mixed {
		if (\is_callable($default)) {
			// Contract: callable defaults accept ConfigInterface|null; we now pass null.
			$val = $default(null);
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _resolve_default_value resolved callable');
			}
			return $val;
		}
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _resolve_default_value returned literal');
		}
		return $default;
	}

	/**
	 * Normalize defaults and apply schema rules.
	 *
	 * @param array $defaults Raw defaults array
	 * @return array Normalized defaults with sanitized keys and validated values
	 */
	protected function _normalize_defaults(array $defaults): array {
		$normalized = array();
		foreach ($defaults as $key => $value) {
			$nk              = $this->_do_sanitize_key((string) $key);
			$normalized[$nk] = $this->_sanitize_and_validate_option($nk, $value);
		}
		return $normalized;
	}

	/**
	 * Normalize external schema map keys to internal normalized option keys.
	 *
	 * Note: 'default' is included only when explicitly provided by caller to
	 * avoid seeding with null unintentionally.
	 *
	 * @param array $schema
	 * @return array<string, array{default?:mixed|null, sanitize?:callable|null, validate?:callable|null}>
	 */
	protected function _normalize_schema_keys(array $schema): array {
		$normalized = array();
		foreach ($schema as $key => $rules) {
			$nKey  = $this->_do_sanitize_key((string) $key);
			$entry = array(
				'sanitize' => $rules['sanitize'] ?? null,
				'validate' => $rules['validate'] ?? null,
			);
			if (\is_array($rules) && array_key_exists('default', $rules)) {
				$entry['default'] = $rules['default'];
			}
			$normalized[$nKey] = $entry;
		}
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _normalize_schema_keys completed', array('count' => count($normalized)));
		}
		return $normalized;
	}

	/**
	 * Create a short, safe string representation of a value for error messages.
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function _stringify_value_for_error(mixed $value): string {
		if (is_scalar($value) || $value === null) {
			$s = var_export($value, true);
		} elseif (is_array($value)) {
			$s = 'Array(' . count($value) . ')';
		} else {
			$s = 'Object(' . get_class($value) . ')';
		}
		if (strlen($s) > 120) {
			$s = substr($s, 0, 117) . '...';
		}
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _stringify_value_for_error completed');
		}
		return $s;
	}

	/**
	 * Describe a callable for diagnostics.
	 *
	 * @param callable $callable
	 * @return string
	 */
	protected function _describe_callable(mixed $callable): string {
		if (is_string($callable)) {
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (string)');
			}
			return $callable;
		}
		if (is_array($callable) && isset($callable[0], $callable[1])) {
			$class = is_object($callable[0]) ? get_class($callable[0]) : (string) $callable[0];
			$desc  = $class . '::' . (string) $callable[1];
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (array)');
			}
			return $desc;
		}
		if ($callable instanceof \Closure) {
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (closure)');
			}
			return 'Closure';
		}
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (other)');
		}
		return 'callable';
	}
}
