<?php
/**
 * WordPress Options Registration and Management.
 *
 * This class manages Plugin/Theme options by storing them as a single array.
 * It uses a storage adapter to handle scope-aware persistence.
 *
 * @package  RanPluginLib\Options
 * @author   Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license  GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link     https://github.com/RocketsAreNostalgic
 * @since    0.1.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options;

use Closure;
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
use Ran\PluginLib\Forms\Validation\ValidatorPipelineService;
use Ran\PluginLib\Options\Policy\WritePolicyInterface;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Options\WriteContext;
use Ran\PluginLib\Options\Policy\RestrictedDefaultWritePolicy;
use Ran\PluginLib\Settings\Settings;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Validation\Helpers;

/**
 * Manages grouped settings via a scope-aware storage adapter (site, network, blog, or user).
 * Settings are grouped under one main option key, yielding a single stored row per scope context.
 *
 * This class provides methods for registering, retrieving, and updating settings grouped under
 * one main option key. Grouping improves organization and reduces the number of discrete rows,
 * while the actual storage location is selected by the current scope and adapter.
 *
 * Collision safety for the same key across scopes:
 * - Using the same main option key in different helpers (e.g., `site($key)`, `network($key)`,
 *   `blog($key)`, `user($key, ...)`) does NOT collide. Each scope maps to a distinct storage backend:
 *   - Site scope → `wp_options` (per site) via `get_option()/update_option()`
 *   - Network scope → `wp_sitemeta` (network-wide) via `get_site_option()/update_site_option()`
 *   - Blog scope → `wp_{blog_id}_options` (isolated per blog) via `get_blog_option()/update_option()`
 *   - User scope → user-specific storage (typically user meta via `get_user_meta()/update_user_meta()`,
 *     or user option variants depending on adapter configuration)
 *   Therefore, the same option name is stored in separate locations per scope and cannot overwrite
 *   or collide with another scope's value.
 *
 * Important semantics and recommendations:
 * - Schema merges are shallow: register_schema() performs a per-key shallow merge of rules, and default
 *   seeding replaces the entire value when seeding a missing key. For nested structures that require
 *   deep/conditional merging, perform an explicit read–modify–write using this sequence:
 *     1) Read current value: `$current = $options->get_option('my_key', array());`
 *     2) Merge with your patch (caller-defined):
 *        - Simple deep merge: `$merged = array_replace_recursive($current, $patch);`
 *        - Or custom logic for precise add/remove/transform semantics
 *     3) Write back: `$options->stage_option('my_key', $merged)->commit_merge();`
 *     4) Persist once (batch-friendly): `$options->commit_replace();`
 *   Prefer flat keys where possible, and for disjoint top-level keys use
 *   `$options->stage_options([...])` then `$options->commit_merge()` to reduce churn.
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
 * - Persistence is explicit (set/update/add + `commit_merge()`/`commit_replace()`)
 * - `commit_merge()` performs a top-level shallow merge with DB to reduce lost updates for disjoint keys.
 *   Nested structures are replaced wholesale; for deep merges, use read–modify–write pattern, then `commit_replace()`.
 */
class RegisterOptions {
	use WPWrappersTrait;

	private const BUCKET_COMPONENT = ValidatorPipelineService::BUCKET_COMPONENT;
	private const BUCKET_SCHEMA    = ValidatorPipelineService::BUCKET_SCHEMA;
	/**
	 * @var string[]
	 */
	private const BUCKET_ORDER = ValidatorPipelineService::BUCKET_ORDER;

	/**
	 * Shared validator pipeline helper.
	 */
	private ?ValidatorPipelineService $validator_pipeline = null;

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
	 * Typed storage context.
	 * When null, defaults to Site on first storage access.
	 */
	private ?StorageContext $storage_context = null;

	/**
	 * Form message handler for validation warnings and notices.
	 *
	 * @var FormMessageHandler
	 */
	private FormMessageHandler $message_handler;

	/**
	 * Option schema map for sanitization, validation, and defaults.
	 * Keys are normalized option keys.
	 * Structure per key:
	 *   - 'default'  => mixed|null
	 *   - 'sanitize' => array{component: array<callable>, schema: array<callable>}
	 *   - 'validate' => array{component: array<callable>, schema: array<callable>}
	 *
	 * @var array<string, array{default?:mixed|null, sanitize:array{component:array<callable>,schema:array<callable>}, validate:array{component:array<callable>,schema:array<callable>}}>
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
	 * @param    string                       $main_wp_option_name   The primary key for this instance's grouped settings.
	 * @param    StorageContext|null          $storage_context       Storage context for scope-aware persistence. Defaults to site scope if null.
	 * @param    bool                         $main_option_autoload  Whether the entire group of options should be autoloaded by WordPress (if supported by storage). Defaults to true.
	 * @param    Logger|null                  $logger                Optional Logger for dependency injection; when provided, it is bound before the first read.
	 */
	public function __construct(
	        string $main_wp_option_name,
	        ?StorageContext $storage_context = null,
	        bool $main_option_autoload = true,
	        ?Logger $logger = null
	    ) {
		// Bind provided logger first
		if ($logger instanceof Logger) {
			$this->logger = $logger;
		}

		// Initialize message handler
		$this->message_handler = new FormMessageHandler($this->logger);

		// Validate required parameters
		if (empty($main_wp_option_name)) {
			$this->_get_logger()->error('RegisterOptions: main_wp_option_name cannot be empty');
			throw new \InvalidArgumentException('RegisterOptions: main_wp_option_name cannot be empty');
		}

		$this->main_wp_option_name  = $main_wp_option_name;
		$this->main_option_autoload = $main_option_autoload;

		// Initialize typed context (defaults to site scope)
		$this->storage_context = $storage_context ?? StorageContext::forSite();

        // Ensure storage is built for this scope
		$this->storage = null; // Force rebuild

		// Load options from correct storage
		$this->options = $this->_read_main_option();

		$this->_get_logger()->debug("RegisterOptions: Initialized with main option '{$this->main_wp_option_name}'. Loaded " . count($this->options) . ' existing sub-options.');
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
		return new static($option_name, StorageContext::forSite(), $autoload_on_create, $logger);
	}

	/**
	 * Named factory: Network scope instance.
	 *
	 * Network options do not support autoload semantics; flag is ignored at storage.
	 *
	 * @param  string      $option_name Main option key
	 * @param  Logger|null $logger      Optional logger to bind before first read
	 * @return static
	 */
	public static function network(string $option_name, ?Logger $logger = null): static {
		return new static($option_name, StorageContext::forNetwork(), false, $logger);
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
		return new static($option_name, StorageContext::forBlog($blog_id), $effective_autoload, $logger);
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
		return new static($option_name, StorageContext::forUser($user_id, 'meta', $global), false, $logger);
	}

	/**
	 * Clone this RegisterOptions configuration onto a different storage context.
	 *
	 * Preserves main option name, logger, schema, and write policy. The new
	 * instance reloads options for the requested context so callers always work
	 * with fresh data for that scope.
	 *
	 * @param StorageContext $context The new storage context.
	 * @return static A new instance with the specified context.
	 */
	public function with_context(StorageContext $context): static {
		$autoload = $this->main_option_autoload;
		if ($context->scope === OptionScope::User) {
			$autoload = false; // user storage does not support autoload semantics
		}

		$new = new static(
			$this->main_wp_option_name,
			$context,
			$autoload,
			$this->logger
		);

		// Share the validator pipeline with the cloned instance
		if ($this->validator_pipeline instanceof ValidatorPipelineService) {
			$new->_set_validator_pipeline($this->validator_pipeline);
		}

		if ($this->write_policy instanceof WritePolicyInterface) {
			$new->with_policy($this->write_policy);
		}

		if (!empty($this->schema)) {
			$new->_register_internal_schema($this->schema);
		}

		return $new;
	}

	/**
	 * Instantiate a scope-aware Settings facade for this RegisterOptions instance.
	 */
	public function settings(?Logger $logger = null): FormsInterface {
		return new Settings($this, $logger);
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
	 * Retrieve captured messages from the most recent staging pass.
	 * Returns structured data with both warnings and notices.
	 *
	 * @return array<string, array{warnings: array<int, string>, notices: array<int, string>}>
	 */
	public function take_messages(): array {
		$this->_ensure_message_handler();
		$messages = $this->message_handler->get_all_messages();
		$this->message_handler->clear();
		return $messages;
	}

	/**
	 * Retrieve captured warning messages from the most recent staging pass.
	 *
	 * @deprecated Use take_messages() instead for structured data with both warnings and notices
	 * @return array<string, array<int, string>>
	 */
	public function take_warnings(): array {
		$this->_ensure_message_handler();
		$warnings     = array();
		$all_messages = $this->message_handler->get_all_messages();
		foreach ($all_messages as $field => $messages) {
			if (!empty($messages['warnings'])) {
				$warnings[$field] = $messages['warnings'];
			}
		}
		$this->message_handler->clear();
		return $warnings;
	}

	/**
	 * Retrieve captured notice messages from the most recent staging pass.
	 *
	 * @deprecated Use take_messages() instead for structured data with both warnings and notices
	 * @return array<string, array<int, string>>
	 */
	public function take_notices(): array {
		$this->_ensure_message_handler();
		$notices      = array();
		$all_messages = $this->message_handler->get_all_messages();
		foreach ($all_messages as $field => $messages) {
			if (!empty($messages['notices'])) {
				$notices[$field] = $messages['notices'];
			}
		}
		$this->message_handler->clear();
		return $notices;
	}


	/**
	 * Fluent alias of register_schema(); returns $this for chaining.
	 *
	 * Schema key principles: no implicit writes (unless $flush is true),
	 * separation of concerns (schema can be pre-wired via Config), and
	 * Config as source for main option name and autoload policy.
	 *
	 * @param array $schema  Schema map: ['key' => ['default' => mixed|callable(ConfigInterface|null): mixed,
	 *   											'sanitize' => callable|null,
	 *   											'validate' => callable|null]]
	 * @return static
	 */
	public function with_schema(array $schema): static {
		$this->register_schema($schema);
		return $this;
	}

	/**
	 * Register/extend schema post-construction (for lazy registration or migrations).
	 *
	 * - Always seeds defaults for missing keys into the in-memory store (sanitize+validate applied)
	 * - Always normalizes existing in-memory values for keys covered by the schema (sanitize+validate)
	 * - NEVER persists implicitly; callers should use commit_merge()/commit_replace()
	 *
	 * @param array $schema Schema map: ['key' => ['default' => mixed|callable(ConfigInterface|null): mixed, 'sanitize' => callable|null, 'validate' => callable|null]]
	 * @return bool Whether the in-memory store changed as a result of seeding/normalization
	 */
	public function register_schema(array $schema): bool {
		if (empty($schema)) {
			$this->_get_logger()->error('RegisterOptions: register_schema() called with empty schema');
			return false;
		}

		$normalized            = $this->_normalize_schema_keys($schema);
		$oldSchema             = $this->schema;
		$newOptions            = $this->options;
		$changed               = false;
		$seedKeysApplied       = array();
		$normalizedKeysApplied = array();
		$defaultKeysSubmitted  = array();
		$defaultUnchangedKeys  = array();
		$defaultChangedKeys    = array();
		$defaultMissingOptions = array();

		try {
			$this->_register_internal_schema($normalized);

			foreach ($normalized as $key => $rules) {
				if (!array_key_exists('default', $rules)) {
					continue;
				}
				$defaultKeysSubmitted[] = $key;
				$hasExistingDefault     = isset($oldSchema[$key]) && array_key_exists('default', $oldSchema[$key]);
				if ($hasExistingDefault && Helpers::canonicalStructuresMatch($oldSchema[$key]['default'], $rules['default'])) {
					$defaultUnchangedKeys[] = $key;
				} else {
					$defaultChangedKeys[] = $key;
				}
				if (!array_key_exists($key, $newOptions)) {
					$defaultMissingOptions[] = $key;
				}
			}

			$toSeed   = array();
			$seedKeys = array();
			foreach ($normalized as $key => $rules) {
				if (!isset($newOptions[$key]) && array_key_exists('default', $rules)) {
					$resolved     = $this->_resolve_default_value($rules['default']);
					$resolved     = $this->_sanitize_and_validate_option($key, $resolved);
					$toSeed[$key] = $resolved;
					$seedKeys[]   = $key;
				}
			}

			if (!empty($toSeed)) {
				$ctx    = $this->_get_storage_context();
				$wcSeed = WriteContext::for_stage_options(
					$this->main_wp_option_name,
					$ctx->scope->value,
					$ctx->blog_id,
					$ctx->user_id,
					$ctx->user_storage ?? 'meta',
					(bool) $ctx->user_global,
					$seedKeys
				);
				if ($this->_apply_write_gate('register_schema', $wcSeed)) {
					foreach ($toSeed as $k => $entry) {
						$newOptions[$k]    = $entry;
						$seedKeysApplied[] = $k;
					}
					$changed = $changed || !empty($seedKeysApplied);
				}
			}

			$normalizedChanges = array();
			$normalizedKeys    = array();
			foreach ($newOptions as $k => $v) {
				if (isset($normalized[$k])) {
					$normalizedValue = $this->_sanitize_and_validate_option($k, $v);
					if ($normalizedValue !== $v) {
						$normalizedChanges[$k] = $normalizedValue;
						$normalizedKeys[]      = $k;
					}
				}
			}
			if (!empty($normalizedChanges)) {
				$ctx    = $this->_get_storage_context();
				$wcNorm = WriteContext::for_stage_options(
					$this->main_wp_option_name,
					$ctx->scope->value,
					$ctx->blog_id,
					$ctx->user_id,
					$ctx->user_storage ?? 'meta',
					(bool) $ctx->user_global,
					$normalizedKeys
				);
				if ($this->_apply_write_gate('register_schema', $wcNorm)) {
					foreach ($normalizedChanges as $k => $nv) {
						$newOptions[$k]          = $nv;
						$normalizedKeysApplied[] = $k;
					}
					$changed = $changed || !empty($normalizedKeysApplied);
				}
			}

			$this->options = $newOptions;
			// Note: defaults currently always flow through sanitize/validate even when unchanged.
			// If telemetry shows costly defaults, consider memoising canonical default signatures here
			// so unchanged keys can skip the resolution path without breaking seed semantics.
			$this->_register_internal_schema(
				array(),
				array(),
				array(),
				array(
					'submitted'  => $defaultKeysSubmitted,
					'unchanged'  => $defaultUnchangedKeys,
					'changed'    => $defaultChangedKeys,
					'missing'    => $defaultMissingOptions,
					'seeded'     => $seedKeysApplied,
					'normalized' => $normalizedKeysApplied,
				)
			);

			return $changed;
		} catch (\Throwable $e) {
			$this->schema = $oldSchema;
			$this->_get_logger()->error('RegisterOptions: register_schema failed', array(
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));
			throw $e;
		}

		if ($defaultsTelemetry !== null) {
			$this->_log_schema_defaults_summary($defaultsTelemetry);
		}
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
	 * Add a single option to the in-memory store (fluent).
	 * Call {@see commit_merge()} or {@see commit_replace()} to persist.
	 *
	 * @param  string $option_name The name of the sub-option to add.
	 * @param  mixed  $value       The value for the sub-option.
	 * @return self
	 */
	public function stage_option(string $option_name, mixed $value): self {
		$this->_ensure_message_handler();
		$key = $this->_do_sanitize_key($option_name);
		$this->message_handler->remove_messages(array($key));

		// Perform sanitization and validation
		$sanitizedValue = $this->_sanitize_and_validate_option($key, $value);

		// If validation failed (warnings were recorded), don't stage the value
		if ($this->message_handler->has_validation_failures()) {
			$this->_get_logger()->debug('RegisterOptions: stage_option validation failed', array(
				'key'           => $key,
				'warning_count' => $this->message_handler->get_warning_count()
			));
			return $this; // Don't stage invalid values
		}

		// No-op guard
		if (isset($this->options[$key])) {
			$existing = $this->options[$key];
			if ($existing === $sanitizedValue) {
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

		$this->options[$key] = $sanitizedValue;
		return $this;
	}

	/**
	 * Batch add multiple options to the in-memory store (fluent).
	 * Call {@see commit_merge()} or {@see commit_replace()} to persist.
	 *
	 * @param  array<string, mixed> $keyToValue Map of option name => value
	 * @return self
	 */
	public function stage_options(array $keyToValue): self {
		$this->_ensure_message_handler();
		$normalizedKeys = array();
		foreach (array_keys($keyToValue) as $candidateKey) {
			$normalizedKeys[] = $this->_do_sanitize_key((string) $candidateKey);
		}
		if (!empty($normalizedKeys)) {
			$this->message_handler->remove_messages($normalizedKeys);
		}
		$changed     = false;
		$changedKeys = array();

		// Gate batch addition before mutating memory
		$keys = $normalizedKeys;
		$ctx  = $this->_get_storage_context();

		// Only create WriteContext and apply gate if we have keys to process
		if (!empty($keys)) {
			$wc2 = WriteContext::for_stage_options($this->main_wp_option_name, $ctx->scope->value, $ctx->blog_id, $ctx->user_id, $ctx->user_storage ?? 'meta', (bool) $ctx->user_global, $keys);
			if (!$this->_apply_write_gate('stage_options', $wc2)) {
				return $this; // veto: no mutation
			}
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
			$changedKeys[]       = $key;
		}

		// Operation-level summary for staging
		if ($this->_get_logger()->is_active()) {
			$count = count($changedKeys);
			$brief = ($count <= 10) ? $changedKeys : array_slice($changedKeys, 0, 10);
			$this->_get_logger()->debug(
				'RegisterOptions: stage_options summary',
				array(
					'changed' => $count,
					'keys'    => $brief
				)
			);
		}

		// Return self for fluent chaining (commit_merge or commit_replace separately)
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
	 * Commit staged changes with a top-level (shallow) merge against the current DB row.
	 *
	 * - Preserves existing DB keys, overwriting collisions with in-memory values
	 * - Does not deep-merge nested arrays; for nested merges, perform a read–modify–write
	 *   for the specific key and then use {@see stage_option()} or {@see commit_replace()}
	 *
	 * @return bool Whether the save succeeded.
	 */
	public function commit_merge(): bool {
		$this->_ensure_message_handler();
		// Check if there are any validation warnings (not notices) from staging
		if ($this->message_handler->has_validation_failures()) {
			$this->_get_logger()->info('RegisterOptions: commit_merge aborted due to validation failures', array(
				'warning_count' => $this->message_handler->get_warning_count(),
				'messages'      => $this->message_handler->get_all_messages()
			));
			return false;
		}

		return $this->_save_all_options(true);
	}

	/**
	 * Commit staged changes by replacing the entire stored row (no merge).
	 *
	 * Use when you have staged a complete, authoritative in-memory payload.
	 *
	 * @return bool Whether the save succeeded.
	 */
	public function commit_replace(): bool {
		return $this->_save_all_options(false);
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
		$sentinel = new \stdClass(); // Allows us to differentiate between a missing option and an option set to nullish (e.g. false, 0, '', null)
		$existing = $this->_do_get_option($this->main_wp_option_name, $sentinel);
		if ($existing !== $sentinel) {
			// Already present; do not modify DB or in-memory state
			// @codeCoverageIgnoreStart
			$this->_get_logger()->debug("RegisterOptions: seed_if_missing no-op; option '{$this->main_wp_option_name}' already exists.");
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
			$this->_get_logger()->debug('RegisterOptions: seed_if_missing vetoed by write gate');
			return $this; // veto: do not write or mutate
		}

		// Persist atomically; add_option is a no-op if row is concurrently created
		$autoload = $this->main_option_autoload ? 'yes' : 'no';
		$this->_do_add_option($this->main_wp_option_name, $normalized, '', $autoload);

		// Sync in-memory cache
		$this->options = $normalized;

		// @codeCoverageIgnoreStart
		$this->_get_logger()->debug("RegisterOptions: seed_if_missing created '{$this->main_wp_option_name}' with " . count($normalized) . ' defaults.');
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
	 * - Preserves autoload by invoking core stage_option() without autoload parameter
	 * - Synchronizes in-memory cache when a write occurs
	 */
	public function migrate(callable $migration): self {
		// Detect missing row
		$sentinel = new \stdClass();
		$current  = $this->_do_get_option($this->main_wp_option_name, $sentinel);
		if ($current === $sentinel) {
			// No-op when option row is absent
			// @codeCoverageIgnoreStart
			$this->_get_logger()->debug("RegisterOptions: migrate no-op; option '{$this->main_wp_option_name}' missing.");
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

		// Preserve autoload: call core stage_option with two parameters, autoload is preserved, do not mutate
		$this->_do_update_option($this->main_wp_option_name, $normalized);

		// Sync in-memory cache
		$this->options = $normalized;

		// @codeCoverageIgnoreStart
		$this->_get_logger()->debug("RegisterOptions: migrate updated '{$this->main_wp_option_name}' with migrated data.");
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
	 * Public accessor: return the main grouped option name for this instance.
	 *
	 * @return string The main grouped option name.
	 */
	public function get_main_option_name(): string {
		return $this->main_wp_option_name;
	}

	/**
	 * Public accessor: return the typed StorageContext for this instance.
	 *
	 * Defaults to Site when not explicitly set (same behavior as internals).
	 *
	 * @return StorageContext The storage context.
	 */
	public function get_storage_context(): StorageContext {
		return $this->_get_storage_context();
	}

	/**
	 * Public accessor: return the schema for this instance.
	 *
	 * @return array The schema.
	 */
	public function get_schema(): array {
		return $this->_create_flat_schema_view();
	}

	/**
	 * Internal accessor that returns the bucketed schema map.
	 *
	 * @internal
	 *
	 * @return array<string,array{sanitize:array{component:array<callable>,schema:array<callable>}, validate:array{component:array<callable>,schema:array<callable>}, default?:mixed}>
	 */
	public function _get_schema_internal(): array {
		return $this->schema;
	}

	/**
	 * Normalize an option key using internal sanitization rules.
	 */
	public function normalize_schema_key(string $key): string {
		return $this->_do_sanitize_key($key);
	}

	/**
	 * Check if schema exists for the provided key (after normalization).
	 */
	public function has_schema_key(string $key): bool {
		$normalized = $this->_do_sanitize_key($key);
		return isset($this->schema[$normalized]);
	}

	/**
	 * Merge bucketed schema entries into the internal schema map.
	 *
	 * @internal Consumers should prefer register_schema(); this helper accepts bucketed structures
	 *           and is used by auto-schema backfill logic.
	 *
	 * @param array<string,array{sanitize:array{component:array<callable>,schema:array<callable>}, validate:array{component:array<callable>,schema:array<callable>}, default?:mixed}> $schema
	 * @param array<string,array<string,mixed>> $metadata Optional meta flags (e.g. ['requires_validator' => true]).
	 * @param array<string,array<int,callable>> $queuedValidators Component validators queued prior to schema merge.
 	 * @return void
 	 */
	public function _register_internal_schema(array $schema, array $metadata = array(), array $queuedValidators = array(), ?array $defaultsTelemetry = null): void {
		if (empty($schema)) {
			if ($defaultsTelemetry !== null) {
				$this->_log_schema_defaults_summary($defaultsTelemetry);
			}
			return;
		}

		foreach ($schema as $key => $entry) {
			if (!\is_array($entry)) {
				throw new \InvalidArgumentException('RegisterOptions: _register_internal_schema expects bucketed schema arrays.');
			}

			$normalized_key    = $this->_do_sanitize_key((string) $key);
			$entryForCoercion  = $entry;
			$hadExisting       = isset($this->schema[$normalized_key]);
			$requiresValidator = false;
			if (isset($metadata[$normalized_key]) && is_array($metadata[$normalized_key])) {
				$requiresValidator = (bool) ($metadata[$normalized_key]['requires_validator'] ?? false);
			}

			$queuedCount = isset($queuedValidators[$normalized_key]) && is_array($queuedValidators[$normalized_key])
				? count($queuedValidators[$normalized_key])
				: 0;
			$metadataKeys = isset($metadata[$normalized_key]) && is_array($metadata[$normalized_key])
				? array_keys($metadata[$normalized_key])
				: array();
			$this->_get_logger()->debug(
				'RegisterOptions: _register_internal_schema processing entry',
				array(
					'key'                  => $normalized_key,
					'had_existing'         => $hadExisting,
					'queued_validator_cnt' => $queuedCount,
					'metadata_flags'       => $metadataKeys,
				)
			);

			$incoming = $this->_coerce_schema_entry($entryForCoercion, $normalized_key);

			if (!isset($this->schema[$normalized_key])) {
				$this->schema[$normalized_key] = $incoming;
			} else {
				// Call 2: Structure check – existing entry should already be coerced from prior registration.
				// Skip redundant coercion if already in canonical bucket form.
				$existing = $this->_is_canonical_bucket_structure($this->schema[$normalized_key])
					? $this->schema[$normalized_key]
					: $this->_coerce_schema_entry($this->schema[$normalized_key], $normalized_key);

				$this->schema[$normalized_key] = array(
					'default'  => array_key_exists('default', $incoming) ? $incoming['default'] : ($existing['default'] ?? null),
					'sanitize' => $this->_merge_bucketed_callables($existing['sanitize'], $incoming['sanitize']),
					'validate' => $this->_merge_bucketed_callables($existing['validate'], $incoming['validate']),
				);
			}

			if (!empty($queuedValidators[$normalized_key])) {
				// NOTE: Call 3 (pre-merge coercion) removed – merge result from _merge_bucketed_callables()
				// is already in canonical bucket form, so coercion here was redundant.
				$componentBucket = &$this->schema[$normalized_key]['validate'][self::BUCKET_COMPONENT];
				$componentBucket = array_merge($queuedValidators[$normalized_key], $componentBucket);
				$this->_get_logger()->debug(
					'RegisterOptions: _register_internal_schema queued validators merged',
					array(
						'key'          => $normalized_key,
						'queued_count' => count($queuedValidators[$normalized_key]),
					)
				);
			}

			if ($requiresValidator) {
				$this->_assert_internal_validator_presence($normalized_key, $this->schema[$normalized_key]);
			}

			// Only coerce if queued validators were injected (they need normalization).
			// Without queued validators, the merge result is already in canonical bucket form.
			if (!empty($queuedValidators[$normalized_key])) {
				$this->schema[$normalized_key] = $this->_coerce_schema_entry($this->schema[$normalized_key], $normalized_key);
			}
			$finalEntry             = $this->schema[$normalized_key];
			$sanitizeComponentCount = count($finalEntry['sanitize'][self::BUCKET_COMPONENT] ?? array());
			$sanitizeSchemaCount    = count($finalEntry['sanitize'][self::BUCKET_SCHEMA] ?? array());
			$validateComponentCount = count($finalEntry['validate'][self::BUCKET_COMPONENT] ?? array());
			$validateSchemaCount    = count($finalEntry['validate'][self::BUCKET_SCHEMA] ?? array());
			$this->_get_logger()->debug(
				'RegisterOptions: _register_internal_schema merged entry',
				array(
					'key'                        => $normalized_key,
					'had_existing'               => $hadExisting,
					'requires_validator'         => $requiresValidator,
					'sanitize_component_count'   => $sanitizeComponentCount,
					'sanitize_schema_count'      => $sanitizeSchemaCount,
					'validate_component_count'   => $validateComponentCount,
					'validate_schema_count'      => $validateSchemaCount,
					'sanitize_component_summary' => $this->_summarize_callable_bucket($finalEntry['sanitize'][self::BUCKET_COMPONENT]),
					'sanitize_schema_summary'    => $this->_summarize_callable_bucket($finalEntry['sanitize'][self::BUCKET_SCHEMA]),
					'validate_component_summary' => $this->_summarize_callable_bucket($finalEntry['validate'][self::BUCKET_COMPONENT]),
					'validate_schema_summary'    => $this->_summarize_callable_bucket($finalEntry['validate'][self::BUCKET_SCHEMA]),
					'default_present'            => array_key_exists('default', $finalEntry),
				)
			);
		}
	}

	/**
	 * Public accessor: return the write policy for this instance.
	 *
	 * @return WritePolicyInterface The write policy.
	 */
	public function get_write_policy(): WritePolicyInterface {
		if (!($this->write_policy instanceof WritePolicyInterface)) {
			$this->write_policy = new RestrictedDefaultWritePolicy();
		}
		return $this->write_policy;
	}

	/**
	 * Emit defaults telemetry once schema/default processing completes.
	 *
	 * @param array<string,array<int,string>>|array<string,array<string>>|array<string,array> $telemetry
	 *     Expected keys: submitted, unchanged, changed, missing, seeded, normalized.
	 *
	 * @return void
	 */
	private function _log_schema_defaults_summary(array $telemetry): void {
		$logger = $this->_get_logger();
		if (!method_exists($logger, 'is_active') || !$logger->is_active()) {
			return;
		}

		$submittedKeys  = isset($telemetry['submitted'])  && is_array($telemetry['submitted']) ? $telemetry['submitted'] : array();
		$unchangedKeys  = isset($telemetry['unchanged'])  && is_array($telemetry['unchanged']) ? $telemetry['unchanged'] : array();
		$changedKeys    = isset($telemetry['changed'])    && is_array($telemetry['changed']) ? $telemetry['changed'] : array();
		$missingKeys    = isset($telemetry['missing'])    && is_array($telemetry['missing']) ? $telemetry['missing'] : array();
		$seedKeys       = isset($telemetry['seeded'])     && is_array($telemetry['seeded']) ? $telemetry['seeded'] : array();
		$normalizedKeys = isset($telemetry['normalized']) && is_array($telemetry['normalized']) ? $telemetry['normalized'] : array();

		$brief = static function (array $keys): array {
			return count($keys) <= 10 ? $keys : array_slice($keys, 0, 10);
		};

		if (!empty($submittedKeys)) {
			$logger->info(
				'RegisterOptions: register_schema defaults',
				array(
					'submitted_count'       => count($submittedKeys),
					'submitted_keys'        => $brief($submittedKeys),
					'unchanged_count'       => count($unchangedKeys),
					'unchanged_keys'        => $brief($unchangedKeys),
					'changed_count'         => count($changedKeys),
					'changed_keys'          => $brief($changedKeys),
					'missing_options_count' => count($missingKeys),
					'missing_option_keys'   => $brief($missingKeys),
				)
			);
		}

		$logger->info(
			'RegisterOptions: register_schema summary',
			array(
				'seeded'          => count($seedKeys),
				'normalized'      => count($normalizedKeys),
				'seed_keys'       => $brief($seedKeys),
				'normalized_keys' => $brief($normalizedKeys),
			)
		);
	}

	/**
	 * Public accessor: return the bound Logger instance (creates default if missing).
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		return $this->_get_logger();
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
			$constructed_logger = new Logger(array());
			if (null === $constructed_logger) {
				throw new \LogicException(static::class . ': Failed to retrieve a valid logger instance.');
			}
			$this->logger = $constructed_logger;
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
			$this->_get_logger()->debug('RegisterOptions: _get_storage resolved (cached)', array('scope' => $st->scope()->value));
			return $st;
		}
		// Create storage via internal factory to reduce indirection
		$this->storage = $this->_make_storage();
		$st            = $this->storage;
		$this->_get_logger()->debug('RegisterOptions: _get_storage resolved (new)', array('scope' => $st->scope()->value));
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
		* Context enrichment notes:
		* - For single-key operations (e.g., 'add_option', 'set_option', 'delete_option'), the context includes 'key'.
		* - For batch operations (e.g., 'stage_options'), the context includes 'keys' (array of strings).
		* - For 'save_all', the context includes 'options' (full map) and 'merge_from_db' flag.
		* - stage_options is atomic by default: policy callbacks should inspect 'keys' and veto the batch if any key is disallowed.
		*
		* @param string $op  Operation name (e.g., 'save_all', 'set_option', 'stage_options')
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
		// Enrich context with op-specific identifiers for policy decisions
		if (method_exists($wc, 'key') && null !== $wc->key()) {
			$ctx['key'] = (string) $wc->key();
		}
		if (method_exists($wc, 'keys') && is_array($wc->keys())) {
			$ctx['keys'] = array_values(array_map('strval', $wc->keys()));
		}
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
		$this->_get_logger()->debug(
			'RegisterOptions: _apply_write_gate applying general allow_persist filter',
			array(
			'hook'    => 'ran/plugin_lib/options/allow_persist',
			'op'      => $op,
			'context' => $ctx,
			)
		);
		$allowed = (bool) $this->_do_apply_filter('ran/plugin_lib/options/allow_persist', $allowed, $ctx);
		$this->_get_logger()->debug(
			'RegisterOptions: _apply_write_gate general filter result',
			array(
			'hook'    => 'ran/plugin_lib/options/allow_persist',
			'allowed' => $allowed,
			'context' => $ctx,
			)
		);

		$scope = isset($ctx['scope']) ? (string) $ctx['scope'] : '';
		if ($scope !== '') {
			$hook = 'ran/plugin_lib/options/allow_persist/scope/' . $scope;
			$this->_get_logger()->debug(
				'RegisterOptions: _apply_write_gate applying scoped allow_persist filter',
				array(
				'hook'    => $hook,
				'op'      => $op,
				'scope'   => $scope,
				'context' => $ctx,
				)
			);
			$allowed = (bool) $this->_do_apply_filter($hook, $allowed, $ctx);
			$this->_get_logger()->debug(
				'RegisterOptions: _apply_write_gate scoped filter result',
				array(
					'hook'    => $hook,
					'allowed' => $allowed,
					'context' => $ctx,
					)
			);
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
		$this->_get_logger()->debug(
			'RegisterOptions: _apply_write_gate final decision',
			array(
			'op'          => $op,
			'main_option' => $ctx['main_option'] ?? '',
			'scope'       => $scope,
			'allowed'     => $allowed,
			)
		);
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
			$this->_get_logger()->debug('RegisterOptions: _read_main_option completed', array('count' => 0));
			return array();
		}
		$this->_get_logger()->debug('RegisterOptions: _read_main_option completed', array('count' => count($raw)));
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
		$this->_get_logger()->debug(
			'RegisterOptions: _save_all_options starting...',
			array(
			'origin'        => $this->__persist_origin ?? 'save_all',
			'merge_from_db' => $merge_from_db,
				)
		);
		$to_save             = $this->options;
		$__exists_from_merge = null; // null = unknown, true = confirmed from merge read

		if ($merge_from_db) {
			// Load DB snapshot and merge top-level keys (no deep merge)
			$dbCurrent = $this->_get_storage()->read($this->main_wp_option_name);

			// Optimization: non-empty array proves existence, skip sentinel check later
			if (is_array($dbCurrent) && !empty($dbCurrent)) {
				$__exists_from_merge = true;
				$this->_get_logger()->debug(
					'RegisterOptions: _save_all_options merge read returned non-empty array; existence confirmed',
					array('key_count' => count($dbCurrent))
				);
			} elseif (!is_array($dbCurrent)) {
				$this->_get_logger()->debug(
					'RegisterOptions: _save_all_options merge_from_db snapshot not array; normalizing to empty array',
					array('snapshot_type' => gettype($dbCurrent))
				);
				$dbCurrent = array();
			}
			// Note: empty array case leaves $__exists_from_merge as null (ambiguous)

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
			$this->_get_logger()->debug('RegisterOptions: _save_all_options vetoed by policy.');
			$this->_get_logger()->debug('RegisterOptions: _save_all_options completed', array('result' => false));
			return false;
		}

		// Honor initial autoload preference only on creation.
		// Determine existence: skip sentinel check if merge read already confirmed existence.
		if ($__exists_from_merge === true) {
			$__exists   = true;
			$__sentinel = null; // Not needed, but defined for consistency in retry verification
			$this->_get_logger()->debug('RegisterOptions: _save_all_options existence confirmed from merge read; skipping sentinel check');
		} else {
			// Sentinel pattern: differentiate missing option from nullish stored value (false, 0, '', null)
			$__sentinel     = new \stdClass();
			$__raw_existing = $this->_do_get_option($this->main_wp_option_name, $__sentinel);
			$__exists       = ($__raw_existing !== $__sentinel);
		}
		if ($__exists) {
			// Existing row: use update() without autoload
			$result = $this->_get_storage()->update($this->main_wp_option_name, $to_save);
			if (!$result) {
				// Retry once for transient conditions, then verify DB state
				$this->_get_logger()->debug('RegisterOptions: storage->update() returned false; retrying once.');
				$result = $this->_get_storage()->update($this->main_wp_option_name, $to_save);
				if (!$result) {
					// Create sentinel for verification if we skipped the initial sentinel check
					$__verify_sentinel = $__sentinel ?? new \stdClass();
					$__verify          = $this->_do_get_option($this->main_wp_option_name, $__verify_sentinel);
					if ($__verify !== $__verify_sentinel && Helpers::canonicalStructuresMatch($__verify, $to_save)) {
						$this->_get_logger()->warning('RegisterOptions: storage->update() returned false but DB matches desired state; treating as success.');
						$result = true;
					} else {
						$this->_get_logger()->warning('RegisterOptions: storage->update() failed and DB does not match desired state.');
					}
				}
			}
		} else {
			// Missing row: prefer add() with autoload; if it fails, fall back to update()
			$this->_get_logger()->debug(
				'RegisterOptions: storage->add() selected, as option did not exist in DB',
				array('autoload' => (bool) $this->main_option_autoload)
			);
			$result = $this->_get_storage()->add($this->main_wp_option_name, $to_save, $this->main_option_autoload);
			if (!$result) {
				$this->_get_logger()->debug('RegisterOptions: storage->add() returned false; falling back to storage->update().');
				$result = $this->_get_storage()->update($this->main_wp_option_name, $to_save);
				if (!$result) {
					// Retry once for transient conditions before verifying DB state
					$this->_get_logger()->debug('RegisterOptions: storage->update() returned false; retrying once.');
					$result = $this->_get_storage()->update($this->main_wp_option_name, $to_save);
					if (!$result) {
						$__verify = $this->_do_get_option($this->main_wp_option_name, $__sentinel);
						if ($__verify !== $__sentinel && Helpers::canonicalStructuresMatch($__verify, $to_save)) {
							$this->_get_logger()->warning('RegisterOptions: storage->update() also failed but DB matches desired state; treating as success.');
							$result = true;
						} else {
							$this->_get_logger()->warning('RegisterOptions: storage->update() also failed and DB does not match desired state.');
						}
					}
				}
			}
		}

		// Mirror what we just saved to keep local cache consistent on success.
		if ($result) {
			$this->options = $to_save;
		}

		$this->_get_logger()->debug('RegisterOptions: _save_all_options completed', array('result' => (bool) $result));
		return $result;
	}

	/**
	 * Apply schema-based sanitization and validation to the value of a given option key.
	 * If no schema exists for the key, returns the value unchanged.
	 *
	 * @param  string $normalized_key
	 * @param  mixed  $value
	 * @return mixed
	 * @throws \InvalidArgumentException on failed validation
	 */
	protected function _sanitize_and_validate_option(string $normalized_key, mixed $value): mixed {
		if (!isset($this->schema[$normalized_key])) {
			$this->_get_logger()->warning('RegisterOptions: _sanitize_and_validate_option no schema', array('key' => $normalized_key));
			throw new \InvalidArgumentException(static::class . ": No schema defined for option '{$normalized_key}'.");
		}

		$rules                         = $this->_coerce_schema_entry($this->schema[$normalized_key], $normalized_key);
		$this->schema[$normalized_key] = $rules;

		$value = $this->_get_validator_pipeline()->sanitize_and_validate(
			$normalized_key,
			$value,
			$rules,
			'RegisterOptions',
			$this->_get_logger(),
			function (callable $callable): string {
				return $this->_describe_callable($callable);
			},
			function (mixed $subject): string {
				return $this->_stringify_value_for_error($subject);
			},
			function (string $key, string $message): void {
				$this->_record_message($key, $message, 'notice');
			},
			function (string $key, string $message): void {
				$this->_record_message($key, $message, 'warning');
			}
		);

		return $value;
	}

	/**
	 * Resolves a default value which may be a raw value or a callable.
	 * If callable, it will be invoked with the current ConfigInterface|null and should return a value.
	 *
	 * @param  mixed $default
	 * @return mixed
	 */
	protected function _resolve_default_value(mixed $default): mixed {
		if (\is_callable($default)) {
			// Contract: callable defaults accept ConfigInterface|null; we now pass null.
			$val = $default(null);
			$this->_get_logger()->debug('RegisterOptions: _resolve_default_value resolved callable');
			return $val;
		}
		$this->_get_logger()->debug('RegisterOptions: _resolve_default_value returned literal');
		return $default;
	}

	/**
	 * Normalize defaults and apply schema rules.
	 *
	 * @param  array $defaults Raw defaults array
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
	 * Record a normalized message for the supplied option key with type classification.
	 *
	 * @param  string $normalized_key
	 * @param  string $message
	 * @param  string $type Either 'warning' or 'notice'
	 * @return void
	 */
	private function _record_message(string $normalized_key, string $message, string $type = 'warning'): void {
		$this->_ensure_message_handler();
		$this->message_handler->add_message($normalized_key, $message, $type);
	}

	/**
	 * Return a fresh bucket map for sanitize/validate arrays.
	 *
	 * @return array{component:array<int,callable>,schema:array<int,callable>}
	 */
	private function _blank_bucket_map(): array {
		return $this->_get_validator_pipeline()->create_bucket_map();
	}

	/**
	 * Normalize callable field from schema definitions.
	 *
	 * @param callable|array<callable>|null $callables
	 * @param string                        $field
	 * @param string                        $option_key
	 * @return array<callable>
	 */
	private function _normalize_callable_field($callables, string $field, string $option_key): array {
		return $this->_get_validator_pipeline()->normalize_callable_field($callables, $field, $option_key, 'RegisterOptions');
	}

	/**
	 * Produce a lightweight summary for a bucket of callables so logs avoid retaining raw closures.
	 *
	 * @param array<int, callable> $bucket
	 * @return array{count:int, descriptors:array<int,string>}
	 */
	private function _summarize_callable_bucket(array $bucket): array {
		$count       = count($bucket);
		$limit       = 5;
		$descriptors = array();
		$index       = 0;
		foreach ($bucket as $callable) {
			if ($index >= $limit) {
				break;
			}
			$descriptors[] = $this->_describe_callable($callable);
			$index++;
		}

		return array(
			'count'       => $count,
			'descriptors' => $descriptors,
		);
	}

	/**
	 * Ensure the schema entry adheres to the bucket structure.
	 *
	 * @param array $entry
	 * @param string $option_key
	 * @return array{sanitize:array{component:array, schema:array}, validate:array{component:array, schema:array}, default?:mixed}
	 */
	private function _coerce_schema_entry(array $entry, string $option_key): array {
		return $this->_get_validator_pipeline()->normalize_schema_entry($entry, $option_key, 'RegisterOptions', $this->_get_logger());
	}

	/**
	 * Merge two bucket maps while preserving per-bucket ordering.
	 *
	 * @param array{component:array<callable>,schema:array<callable>} $existing
	 * @param array{component:array<callable>,schema:array<callable>} $incoming
	 * @return array{component:array<callable>,schema:array<callable>}
	 */
	private function _merge_bucketed_callables(array $existing, array $incoming): array {
		return $this->_get_validator_pipeline()->merge_bucketed_callables($existing, $incoming);
	}

	/**
	 * Determine whether the supplied array looks like a bucket map.
	 */
	private function _is_bucket_map(array $candidate): bool {
		return $this->_get_validator_pipeline()->is_bucket_map($candidate);
	}

	/**
	 * Check if schema entry is already in canonical bucket structure.
	 *
	 * This verifies the COMPLETE structure (all 4 arrays present), not just
	 * partial bucket form. Used to skip redundant coercion when an entry
	 * has already been normalized.
	 *
	 * @param array $entry Schema entry to check.
	 * @return bool True if already in canonical normalized form.
	 */
	private function _is_canonical_bucket_structure(array $entry): bool {
		return isset($entry['sanitize']['component'])
			&& isset($entry['sanitize']['schema'])
			&& isset($entry['validate']['component'])
			&& isset($entry['validate']['schema'])
			&& is_array($entry['sanitize']['component'])
			&& is_array($entry['sanitize']['schema'])
			&& is_array($entry['validate']['component'])
			&& is_array($entry['validate']['schema']);
	}

	private function _get_validator_pipeline(): ValidatorPipelineService {
		if (!($this->validator_pipeline instanceof ValidatorPipelineService)) {
			$this->validator_pipeline = new ValidatorPipelineService();
		}
		return $this->validator_pipeline;
	}

	/**
	 * Expose the validator pipeline instance (shared when injected).
	 */
	public function get_validator_pipeline(): ValidatorPipelineService {
		return $this->_get_validator_pipeline();
	}

	/**
	 * Set the validator pipeline instance.
	 *
	 * @internal Used by with_context() to share pipeline with cloned instances.
	 *           Not part of the public API.
	 *
	 * @param ValidatorPipelineService $pipeline The pipeline instance to use.
	 * @return void
	 */
	public function _set_validator_pipeline(ValidatorPipelineService $pipeline): void {
		$this->validator_pipeline = $pipeline;
	}

	/**
	 * Record a normalized warning message for the supplied option key.
	 *
	 * @deprecated Use _record_message() with type 'warning' instead
	 * @param  string $normalized_key
	 * @param  string $message
	 * @return void
	 */
	private function _record_warning(string $normalized_key, string $message): void {
		$this->_record_message($normalized_key, $message, 'warning');
	}

	/**
	 * Ensure the message handler is initialized.
	 *
	 * @return void
	 */
	private function _ensure_message_handler(): void {
		if (!isset($this->message_handler)) {
			$this->message_handler = new FormMessageHandler($this->logger);
		}
	}

	/**
	 * Normalize external schema map keys to internal normalized option keys.
	 *
	 * Note: 'default' is included only when explicitly provided by caller to
	 * avoid seeding with null unintentionally.
	 *
	 * @param array $schema
	 * @return array<string, array{
	 * 						default?:mixed|null, 	 // Literal default value or callable returning a value
	 * 						sanitize?:callable|null, // Callable accepting a value and returning a sanitized value
	 * 						validate?:callable|null  // Callable accepting a value and returning a boolean
	 * 					}>
	 */
	protected function _normalize_schema_keys(array $schema): array {
		$normalized = array();
		foreach ($schema as $key => $ruleSet) {
			if (!\is_array($ruleSet)) {
				throw new \InvalidArgumentException('RegisterOptions: register_schema expects each rule definition to be an array.');
			}

			$nKey  = $this->_do_sanitize_key((string) $key);
			$entry = array(
				'sanitize' => $this->_blank_bucket_map(),
				'validate' => $this->_blank_bucket_map(),
			);

			if (array_key_exists('sanitize', $ruleSet)) {
				$sanitizeField = $ruleSet['sanitize'];
				if (\is_array($sanitizeField) && $this->_is_bucket_map($sanitizeField)) {
					$this->_get_logger()->warning('RegisterOptions: register_schema disallows component sanitize buckets', array('key' => $nKey));
					throw new \InvalidArgumentException("RegisterOptions: Schema for key '{$nKey}' must not provide bucketed sanitize entries.");
				}
				$entry['sanitize'][self::BUCKET_SCHEMA] = $this->_normalize_callable_field($sanitizeField, 'sanitize', $nKey);
			}

			if (array_key_exists('validate', $ruleSet)) {
				$validateField = $ruleSet['validate'];
				if (\is_array($validateField) && $this->_is_bucket_map($validateField)) {
					$this->_get_logger()->warning('RegisterOptions: register_schema disallows component validate buckets', array('key' => $nKey));
					throw new \InvalidArgumentException("RegisterOptions: Schema for key '{$nKey}' must not provide bucketed validate entries.");
				}
				$entry['validate'][self::BUCKET_SCHEMA] = $this->_normalize_callable_field($validateField, 'validate', $nKey);
			}

			if (array_key_exists('default', $ruleSet)) {
				$entry['default'] = $ruleSet['default'];
			}

			$normalized[$nKey] = $entry;
		}
		$this->_get_logger()->debug('RegisterOptions: _normalize_schema_keys completed', array('count' => count($normalized)));
		return $normalized;
	}

	/**
	 * Ensure required validator presence for auto-generated schema entries.
	 */
	private function _assert_internal_validator_presence(string $normalized_key, array $entry): void {
		$componentValidators = $entry['validate'][self::BUCKET_COMPONENT] ?? array();
		$schemaValidators    = $entry['validate'][self::BUCKET_SCHEMA]    ?? array();
		if (!empty($componentValidators) || !empty($schemaValidators)) {
			return;
		}

		$this->_get_logger()->error('RegisterOptions: Validator required but missing for option', array('key' => $normalized_key));
		throw new \UnexpectedValueException(sprintf('RegisterOptions: Option "%s" requires at least one validator.', $normalized_key));
	}

	/**
	 * Create a flattened schema view with buckets merged for external consumers.
	 */
	private function _create_flat_schema_view(): array {
		$flat = array();
		foreach ($this->schema as $key => $entry) {
			$coerced        = $this->_coerce_schema_entry($entry, (string) $key);
			$schemaSanitize = $coerced['sanitize'][self::BUCKET_SCHEMA] ?? array();
			$schemaValidate = $coerced['validate'][self::BUCKET_SCHEMA] ?? array();

			$flattened = array(
				'sanitize' => $this->_prepare_callables_for_export($schemaSanitize),
				'validate' => $this->_prepare_callables_for_export($schemaValidate),
			);
			if (array_key_exists('default', $coerced)) {
				$flattened['default'] = $coerced['default'];
			}

			$flat[$key] = $flattened;
		}

		return $flat;
	}

	/**
	 * Prepare callable array for developer export.
	 *
	 * @param  array<int,mixed> $callables
	 * @return array<int,mixed>
	 */
	private function _prepare_callables_for_export(array $callables): array {
		return array_values(array_map(function ($callable) {
			return $this->_export_schema_callable($callable);
		}, $callables));
	}

	/**
	 * Convert a callable into an export-safe representation.
	 *
	 * @param  mixed $callable
	 * @return mixed
	 */
	private function _export_schema_callable(mixed $callable): mixed {
		if (\is_string($callable)) {
			return $callable;
		}

		if (\is_array($callable) && isset($callable[0], $callable[1])) {
			if (\is_string($callable[0])) {
				return $callable[0] . '::' . (string) $callable[1];
			}
			if (\is_object($callable[0])) {
				return $this->_export_placeholder_for_callable($callable);
			}
		}

		if ($callable instanceof \Closure) {
			return $this->_export_placeholder_for_callable($callable);
		}

		if (\is_object($callable) && method_exists($callable, '__invoke')) {
			return $this->_export_placeholder_for_callable($callable);
		}

		return $callable;
	}

	/**
	 * Create a descriptive placeholder string for non-portable callables and log guidance.
	 *
	 * @param  mixed $callable
	 * @return string
	 */
	private function _export_placeholder_for_callable(mixed $callable): string {
		$description = $this->_describe_callable($callable);
		$note        = ' (NOTE: Consider using a named function or Class::method for portability.)';
		$placeholder = ValidatorPipelineService::CLOSURE_PLACEHOLDER_PREFIX . $description . $note;
		$this->_get_logger()->info('RegisterOptions: Exported closure placeholder for schema callable, consider using a named function or Class::method for portability.', array('callable' => $description));
		return $placeholder;
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
		$this->_get_logger()->debug('RegisterOptions: _stringify_value_for_error completed');
		return $s;
	}

	/**
	 * Describe a callable for diagnostics. Provides richer context for closures and invokable objects.
	 *
	 * @param mixed $callable
	 * @return string
	 */
	protected function _describe_callable(mixed $callable): string {
		if (is_string($callable)) {
			$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (string)');
			return $callable;
		}
		if (is_array($callable) && isset($callable[0], $callable[1])) {
			$class = is_object($callable[0]) ? get_class($callable[0]) : (string) $callable[0];
			$desc  = $class . '::' . (string) $callable[1];
			$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (array)');
			return $desc;
		}
		if ($callable instanceof \Closure) {
			$desc = 'Closure';
			if (function_exists('spl_object_id')) {
				$desc = 'Closure#' . spl_object_id($callable);
			} elseif (function_exists('spl_object_hash')) {
				$desc = 'Closure#' . spl_object_hash($callable);
			}
			$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (closure)');
			return $desc;
		}
		if (is_object($callable) && method_exists($callable, '__invoke')) {
			// Enhanced diagnostics: render as Class::__invoke
			$desc = get_class($callable) . '::__invoke';
			$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (invokable object)');
			return $desc;
		}
		$this->_get_logger()->debug('RegisterOptions: _describe_callable completed (other)');
		return 'callable';
	}
}
