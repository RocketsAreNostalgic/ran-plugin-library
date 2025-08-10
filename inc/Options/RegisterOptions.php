<?php
/**
 * WordPress Options Registration and Management.
 *
 * This class manages plugin options by storing them as a single array
 * under a specified main option name in the WordPress options table.
 * This approach enhances encapsulation and keeps the wp_options table cleaner.
 *
 * @package  RanPluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\Options;

use Ran\PluginLib\Config\ConfigAbstract;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\WPWrappersTrait;

/**
 * Manages plugin options by storing them as a single array in the wp_options table.
 *
 * This class provides methods for registering, retrieving, and updating plugin settings,
 * all grouped under one main WordPress option. This improves organization and reduces
 * the number of individual rows added to the wp_options table.
 *
 * Important semantics and recommendations:
 * - Autoload flag: WordPress applies the autoload value primarily on option creation.
 *   Changing $main_option_autoload later will not flip the stored autoload flag for an
 *   existing row. Use set_main_autoload(true|false) to force a flip (delete + add).
 * - Schema merges are shallow: register_schema() performs a per-key shallow merge of
 *   rules, and default seeding replaces the entire value when seeding a missing key.
 *   For nested structures that require deep/conditional merging, perform an explicit
 *   read–modify–write using this sequence:
 *     1) Read current value: `$current = $options->get_option('my_key', array());`
 *     2) Merge with your patch (caller-defined):
 *        - Simple deep merge: `$merged = array_replace_recursive($current, $patch);`
 *        - Or custom logic for precise add/remove/transform semantics
 *     3) Write back: `$options->set_option('my_key', $merged);`
 *     4) Persist once (batch-friendly): `$options->flush(false);`
 *   Prefer flat keys where possible, and for disjoint top-level keys use
 *   `$options->add_options([...])` then `$options->flush(true)` to reduce churn.
 */
class RegisterOptions {
	use WPWrappersTrait;

	/**
	 * The in-memory store for all plugin options.
	 * Structure: ['option_key' => ['value' => mixed, 'autoload_hint' => bool|null]]
	 *
	 * @var array<string, array{"value": mixed, "autoload_hint": bool|null}>
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
	 * Optional injected config instance to reduce coupling to concrete Config.
	 */
	private ?ConfigInterface $config = null;

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
     * Creates a new RegisterOptions instance.
	 *
	 * Initializes options by loading them from the database under `$main_wp_option_name`.
	 * If initial `$default_options` are provided, they are merged with existing options
	 * and the entire set is saved back to the database.
	 *
	 * @param string $main_wp_option_name The primary key in wp_options where all settings for this instance are stored.
	 * @param array<string, array{"value": mixed, "autoload_hint": bool|null}|mixed> $initial_options Optional. An array of default options to set if not already present or to merge.
	 *                                   Each key is the option name. Value can be the direct value or an array ['value' => mixed, 'autoload_hint' => bool].
     * @param bool $main_option_autoload  Whether the entire group of options should be autoloaded by WordPress. Defaults to true.
     * @param Logger|null $logger         Optional logger to use. If null, a logger is resolved from Config.
     * @param ConfigInterface|null $config Optional config used to resolve logger and metadata (favored over concrete Config).
     * @param array $schema               Optional schema map: ['key' => ['default' => mixed|callable(ConfigInterface|null): mixed, 'sanitize' => callable|null, 'validate' => callable|null]].
     *                                    - default: value or callable(ConfigInterface|null): mixed used when the option is missing
     *                                    - sanitize: callable(value): mixed
     *                                    - validate: callable(value): true|throws/false
	 */
	public function __construct(
        string $main_wp_option_name,
        array $initial_options = array(),
        bool $main_option_autoload = true,
        ?Logger $logger = null,
        ?ConfigInterface $config = null,
        array $schema = array()
    ) {
		$this->main_wp_option_name  = $main_wp_option_name;
		$this->main_option_autoload = $main_option_autoload;
		$this->logger               = $logger ?? $this->logger;
		$this->config               = $config ?? $this->config;

		// Load all existing options from the single database entry.
		$this->options = $this->_do_get_option($this->main_wp_option_name, array());
		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: Initialized with main option '{$this->main_wp_option_name}'. Loaded " . count($this->options) . ' existing sub-options.');
		}
		// @codeCoverageIgnoreEnd

		// Normalize and set schema
		if (!empty($schema)) {
			$this->schema = $this->normalize_schema_keys($schema);
		}

		// Track whether defaults or initial options cause persistence
		$options_changed = false;

		// Seed defaults from schema for any missing values
		if (!empty($this->schema)) {
			foreach ($this->schema as $normalized_key => $rules) {
				$has_value = isset($this->options[$normalized_key]) && array_key_exists('value', $this->options[$normalized_key]);
				if (!$has_value && array_key_exists('default', $rules)) {
					$resolved_default               = $this->_resolve_default_value($rules['default'] ?? null);
					$resolved_default               = $this->_sanitize_and_validate_option($normalized_key, $resolved_default);
					$this->options[$normalized_key] = array(
					    'value'         => $resolved_default,
					    'autoload_hint' => $this->options[$normalized_key]['autoload_hint'] ?? null,
					);
					$options_changed = true;
				}
			}
		}

		// If initial/default options are provided, merge and save them.
		if (!empty($initial_options)) {
			$options_changed = $options_changed || false;
			foreach ($initial_options as $option_name => $definition) {
				$option_name_clean    = self::sanitize_key((string) $option_name);
				$current_value_exists = isset($this->options[$option_name_clean]);

				$value_to_set = is_array($definition) && isset($definition['value']) ? $definition['value'] : $definition;
				// Apply schema sanitization/validation if defined
				$value_to_set  = $this->_sanitize_and_validate_option($option_name_clean, $value_to_set);
				$autoload_hint = is_array($definition) && isset($definition['autoload_hint']) ? $definition['autoload_hint'] : ($this->options[$option_name_clean]['autoload_hint'] ?? null);

				// Set if new, or if existing value is different (for complex types, this is a simple check).
				if (!$current_value_exists || ($current_value_exists && $this->options[$option_name_clean]['value'] !== $value_to_set)) {
					$this->options[$option_name_clean] = array('value' => $value_to_set, 'autoload_hint' => $autoload_hint);
					$options_changed                   = true;
					// @codeCoverageIgnoreStart
					if ($this->_get_logger()->is_active()) {
						$this->_get_logger()->debug("RegisterOptions: Initial option '{$option_name_clean}' set/updated.");
					}
					// @codeCoverageIgnoreEnd
				}
			}

			if ($options_changed) {
				$this->_save_all_options();
				// @codeCoverageIgnoreStart
				if ($this->_get_logger()->is_active()) {
					$this->_get_logger()->debug("RegisterOptions: Options (schema defaults and/or initial) processed and saved to '{$this->main_wp_option_name}'.");
				}
				// @codeCoverageIgnoreEnd
			}
		}
		// Persist defaults even when no initial options provided
		if ($options_changed && empty($initial_options)) {
			$this->_save_all_options();
			// @codeCoverageIgnoreStart
			if ($this->_get_logger()->is_active()) {
				$this->_get_logger()->debug("RegisterOptions: Schema defaults seeded and saved to '{$this->main_wp_option_name}'.");
			}
			// @codeCoverageIgnoreEnd
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
		$option_name_clean = self::sanitize_key($option_name);
		$value             = $this->options[$option_name_clean]['value'] ?? $default;

		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$log_value = is_scalar($value) ? (string) $value : (is_array($value) ? 'Array' : 'Object');
			if (strlen($log_value) > 100) {
				$log_value = substr($log_value, 0, 97) . '...';
			}
			$found_status = isset($this->options[$option_name_clean]['value']) ? 'Found' : 'Not found, using default';
			$this->_get_logger()->debug("RegisterOptions: Getting option '{$option_name_clean}' from '{$this->main_wp_option_name}'. Status: {$found_status}. Value: {$log_value}");
		}
		// @codeCoverageIgnoreEnd
		return $value;
	}

	/**
	 * Returns the entire array of options currently held by this instance.
	 *
	 * @return array<string, array{"value": mixed, "autoload_hint": bool|null}> The array of all sub-options.
	 */
	public function get_options(): array {
		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: Getting all options from '{$this->main_wp_option_name}'. Count: " . count($this->options));
		}
		// @codeCoverageIgnoreEnd
		return $this->options;
	}

	/**
	 * Convenience: returns values-only view of options.
	 * Does not include metadata like autoload_hint.
	 *
	 * @return array<string, mixed>
	 */
	public function get_values(): array {
		$values = array();
		foreach ($this->options as $key => $entry) {
			if (is_array($entry) && array_key_exists('value', $entry)) {
				$values[$key] = $entry['value'];
			}
		}
		return $values;
	}

	/**
	 * Sets or updates a specific option's value within the main options array and saves any added options to the DB.
	 *
     * @param string     $option_name The name of the sub-option to set. Key is sanitized via sanitize_key().
	 * @param mixed      $value       The value for the sub-option.
	 * @param bool|null  $autoload_hint Optional. A hint for whether this specific sub-option might have been intended for autoloading (for metadata purposes only).
	 * @return bool True if any added options were successfully saved, false otherwise.
     *
     * Note:
     * - No-op guard uses strict (===) comparison for both value and autoload_hint.
     * - Arrays must match exactly (keys/order/values) to be considered unchanged.
     * - Objects must be the same instance to avoid a write; identical state in different instances will trigger a save.
	 */
	public function set_option(string $option_name, mixed $value, ?bool $autoload_hint = null): bool {
		$option_name_clean = self::sanitize_key($option_name);
		$value             = $this->_sanitize_and_validate_option($option_name_clean, $value);

		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: Setting option '{$option_name_clean}' in '{$this->main_wp_option_name}'.");
		}
		// @codeCoverageIgnoreEnd

		$new_autoload_hint = $autoload_hint ?? ($this->options[$option_name_clean]['autoload_hint'] ?? null);

		// Avoid DB churn: if nothing changed, short-circuit
		if (isset($this->options[$option_name_clean])) {
			$existing      = $this->options[$option_name_clean];
			$existing_hint = $existing['autoload_hint'] ?? null;
			if (($existing['value'] ?? null) === $value && $existing_hint === $new_autoload_hint) {
				return true; // No-op change
			}
		}

		$this->options[$option_name_clean] = array(
		    'value'         => $value,
		    'autoload_hint' => $new_autoload_hint,
		);
		return $this->_save_all_options();
	}

	/**
	 * Batch add multiple options to the in-memory store (fluent). Call flush() to persist.
	 *
	 * @param array<string, mixed|array{value:mixed, autoload_hint?:bool|null}> $keyToValue Map of option name => value or ['value'=>..., 'autoload_hint'=>...]
	 * @return self
	 */
	public function add_options(array $keyToValue): self {
		$changed = false;

		foreach ($keyToValue as $option_name => $definition) {
			$key = self::sanitize_key((string) $option_name);

			$value    = is_array($definition) && array_key_exists('value', $definition) ? $definition['value'] : $definition;
			$value    = $this->_sanitize_and_validate_option($key, $value);
			$new_hint = is_array($definition) && array_key_exists('autoload_hint', $definition)
			    ? $definition['autoload_hint']
			    : ($this->options[$key]['autoload_hint'] ?? null);

			if (isset($this->options[$key])) {
				$existing      = $this->options[$key];
				$existing_hint = $existing['autoload_hint'] ?? null;
				if (($existing['value'] ?? null) === $value && $existing_hint === $new_hint) {
					continue; // no change
				}
			}

			$this->options[$key] = array(
			    'value'         => $value,
			    'autoload_hint' => $new_hint,
			);
			$changed = true;
		}

		// Return self for fluent chaining (flush separately)
		return $this;
	}

	/**
	 * Add a single option to the in-memory store (fluent). Call flush() to persist.
	 *
	 * @param string $option_name The name of the sub-option to add.
	 * @param mixed $value The value for the sub-option.
	 * @param bool|null $autoload_hint Optional. A hint for whether this specific sub-option might have been intended for autoloading (for metadata purposes only).
	 * @return self
	 */
	public function add_option(string $option_name, mixed $value, ?bool $autoload_hint = null): self {
		$key   = self::sanitize_key($option_name);
		$value = $this->_sanitize_and_validate_option($key, $value);

		// No-op guard
		if (isset($this->options[$key])) {
			$existing      = $this->options[$key];
			$existing_hint = $existing['autoload_hint'] ?? null;
			if (($existing['value'] ?? null) === $value && $existing_hint === ($autoload_hint ?? $existing_hint)) {
				return $this;
			}
		}

		$this->options[$key] = array(
		    'value'         => $value,
		    'autoload_hint' => $autoload_hint ?? ($this->options[$key]['autoload_hint'] ?? null),
		);
		return $this;
	}

	/**
	 * Persist current in-memory options to the database.
	 *
	 * @param bool $mergeFromDb When true, reads current DB value and performs a
	 *                          shallow, top-level merge before saving:
	 *                          - Existing DB keys are preserved
	 *                          - In-memory keys overwrite on collision
	 *                          This reduces lost updates for disjoint keys during
	 *                          installers/migrations. Nested values are replaced
	 *                          as a whole; for complex merges, callers should
	 *                          read–modify–write and then flush(false).
	 *                          See header notes for details.
	 * @return bool Whether the save succeeded.
	 */
	public function flush(bool $mergeFromDb = false): bool {
		return $this->_save_all_options($mergeFromDb);
	}

	/**
	 * Register/extend schema post-construction (for lazy registration or migrations).
	 *
	 * - Merges provided rules into the existing schema (per-key shallow override)
	 * - Optionally seeds defaults for keys missing a value
	 * - Optionally flushes once after seeding
	 *
	 * @param array $schema  Schema map: ['key' => ['default' => mixed|callable(ConfigInterface|null): mixed, 'sanitize' => callable|null, 'validate' => callable|null]]
	 * @param bool  $seedDefaults If true, set missing option values from 'default' (after sanitize/validate)
	 * @param bool  $flush If true, persist after seeding (single write)
	 * @return bool When flush=false: whether any values were seeded; when flush=true: whether the save succeeded
	 */
	public function register_schema(array $schema, bool $seedDefaults = false, bool $flush = false): bool {
		if (empty($schema)) {
			return false;
		}

		$normalized = $this->normalize_schema_keys($schema);

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
		if ($seedDefaults) {
			foreach ($normalized as $key => $rules) {
				$has_value = isset($this->options[$key]) && array_key_exists('value', $this->options[$key]);
				if (!$has_value && array_key_exists('default', $rules)) {
					$resolved            = $this->_resolve_default_value($rules['default']);
					$resolved            = $this->_sanitize_and_validate_option($key, $resolved);
					$this->options[$key] = array(
					    'value'         => $resolved,
					    'autoload_hint' => $this->options[$key]['autoload_hint'] ?? null,
					);
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
	 * Fluent alias of register_schema(); returns $this for chaining.
	 */
	public function with_schema(array $schema, bool $seedDefaults = false, bool $flush = false): self {
		$this->register_schema($schema, $seedDefaults, $flush);
		return $this;
	}

	/**
	 * Updates a specific option's value. Alias for set.
	 *
	 * @param string     $option_name The name of the sub-option to update.
	 * @param mixed      $value       The new value for the sub-option.
	 * @param bool|null  $autoload_hint Optional. Autoload hint for the sub-option.
	 * @return bool True if options were saved successfully, false otherwise.
	 */
	public function update_option(string $option_name, mixed $value, ?bool $autoload_hint = null): bool {
		return $this->set_option($option_name, $value, $autoload_hint);
	}

	/**
	 * Determine if an option exists (by normalized key) in the in-memory store.
	 */
	public function has_option(string $option_name): bool {
		$key = self::sanitize_key($option_name);
		return array_key_exists($key, $this->options) && array_key_exists('value', $this->options[$key]);
	}

	/**
	 * Refreshes the local options cache by reloading them from the database.
	 */
	public function refresh_options(): void {
		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug("RegisterOptions: Refreshing options from database for '{$this->main_wp_option_name}'.");
		}
		// @codeCoverageIgnoreEnd
		$this->options = $this->_do_get_option($this->main_wp_option_name, array());
	}

	/**
	 * Delete an option by name and persist changes. Returns true if the key existed and was removed.
	 */
	public function delete_option(string $option_name): bool {
		$key = self::sanitize_key($option_name);
		if (!array_key_exists($key, $this->options)) {
			return false;
		}
		unset($this->options[$key]);
		return $this->_save_all_options();
	}

	/**
	 * Clear all sub-options in this group and persist the empty set.
	 */
	public function clear(): bool {
		$this->options = array();
		return $this->_save_all_options();
	}


	/**
	 * Get the autoload preset value for an option if one has been set in the schema.
	 * Utility for debugging, introspection, migration, etc.
	 *
	 * @param string $key
	 * @return bool|null
	 */
	public function get_autoload_hint(string $key): ?bool {
		$k = self::sanitize_key($key);
		return $this->options[$k]['autoload_hint'] ?? null;
	}

	/**
	 * Set the autoload behavior for the main option group explicitly.
	 *
	 * WordPress only guarantees autoload is applied on creation.
	 * This method forces the desired autoload by deleting and re-adding the option row.
	 *
	 * @param bool $autoload
	 * @return bool
	 */
	public function set_main_autoload(bool $autoload): bool {
		$current = $this->_do_get_option($this->main_wp_option_name, array());
		$this->_do_delete_option($this->main_wp_option_name);
		$result = $this->_do_add_option(
			$this->main_wp_option_name,
			$current,
			'',
			$autoload ? 'yes' : 'no'
		);

		// Keep in-memory state consistent with the change
		$this->main_option_autoload = $autoload;
		// We just wrote $current, so safely mirror it locally.
		$this->options = $current;
		// Alternatively, call $this->refresh_options() if a fresh DB read is preferred.

		return $result;
	}

	/**
	 * Sanitize sub-option keys using WordPress's sanitize_key() when available.
	 * Provides a safe fallback if WP is not loaded.
	 *
	 * @param string $key Raw option key
	 * @return string Normalized option key
	 */
	public static function sanitize_key(string $key): string {
		if (\function_exists('sanitize_key')) {
			// @codeCoverageIgnoreStart
			return \sanitize_key($key);
			// @codeCoverageIgnoreEnd
		}
		$key = strtolower($key);
		// Match WP semantics: allow a-z, 0-9, underscore and hyphen; strip everything else
		$key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? $key;
		return trim($key, '_');
	}

	/**
	 * Factory: create a RegisterOptions instance using the option name derived from Config.
	 *
	 * Uses the library's convention of storing the primary option name in
	 * `$config->get_plugin_config()['RANPluginOption']`.
	 *
	 * @param ConfigInterface $config        Initialized config instance.
	 * @param array           $initial       Optional initial options (same structure as constructor).
	 * @param bool            $autoload      Whether the grouped option should autoload.
	 * @param Logger|null     $logger        Optional logger instance (fallbacks to Config when null).
	 *
	 * @return self
	 */
	public static function from_config(
        ConfigInterface $config,
        array $initial = array(),
        bool $autoload = true,
        ?Logger $logger = null,
        array $schema = array()
    ): self {
		$plugin_config = $config->get_plugin_config();
		$main_option   = $plugin_config['RANPluginOption'] ?? '';

		if ($main_option === '' || !is_string($main_option)) {
			throw new \InvalidArgumentException(static::class . ': Missing or invalid RANPluginOption in Config::get_plugin_config().');
		}

		return new self($main_option, $initial, $autoload, $logger, $config, $schema);
	}

	/**
	 * Retrieves the logger instance.
	 *
	 * @return Logger The logger instance.
	 */
	protected function _get_logger(): Logger {
		// @codeCoverageIgnoreStart
		if (null === $this->logger) {
			if ($this->config instanceof ConfigInterface) {
				$logger_from_config = $this->config->get_logger();
			} else {
				// Fallback to abstract base if available
				$config_instance    = ConfigAbstract::get_instance();
				$logger_from_config = $config_instance->get_logger();
			}

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
	 * Saves all currently held options to the database under the main option name.
	 *
	 * @param bool $mergeFromDb Optional. If true, applies a shallow, top-level
	 *                          merge with the current DB value (DB + in-memory),
	 *                          where in-memory values win on collisions. This is
	 *                          intended to reduce lost updates for disjoint keys
	 *                          and does not perform deep/nested merges.
	 * @return bool True if the option was successfully updated or added, false otherwise.
	 */
	private function _save_all_options(bool $mergeFromDb = false): bool {
		// @codeCoverageIgnoreStart
		if ($this->_get_logger()->is_active()) {
			$this->_get_logger()->debug(
				"RegisterOptions: Saving all options to '{$this->main_wp_option_name}'. Total sub-options: "
				. count($this->options) . '. Autoload: ' . ($this->main_option_autoload ? 'true' : 'false')
				. ($mergeFromDb ? ' (mergeFromDb)' : '')
			);
		}
		// @codeCoverageIgnoreEnd

		$to_save = $this->options;

		if ($mergeFromDb) {
			$dbCurrent = $this->_do_get_option($this->main_wp_option_name, array());
			if (!is_array($dbCurrent)) {
				$dbCurrent = array();
			}
			// Shallow top-level merge: keep DB keys, overwrite with in-memory on collision
			foreach ($this->options as $k => $entry) {
				$dbCurrent[$k] = $entry;
			}
			$to_save = $dbCurrent;
		}

		$result = $this->_do_update_option($this->main_wp_option_name, $to_save, $this->main_option_autoload);
		// Mirror what we just saved to keep local cache consistent
		$this->options = $to_save;
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
	private function _sanitize_and_validate_option(string $normalized_key, mixed $value): mixed {
		if (!isset($this->schema[$normalized_key])) {
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

		return $value;
	}

	/**
	 * Resolves a default value which may be a raw value or a callable.
	 * If callable, it will be invoked with the current ConfigInterface|null and should return a value.
	 *
	 * @param mixed $default
	 * @return mixed
	 */
	private function _resolve_default_value(mixed $default): mixed {
		if (\is_callable($default)) {
			return $default($this->config);
		}
		return $default;
	}

	/**
	 * Create a short, safe string representation of a value for error messages.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function _stringify_value_for_error(mixed $value): string {
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
		return $s;
	}

	/**
	 * Describe a callable for diagnostics.
	 *
	 * @param callable $callable
	 * @return string
	 */
	private function _describe_callable(mixed $callable): string {
		if (is_string($callable)) {
			return $callable;
		}
		if (is_array($callable) && isset($callable[0], $callable[1])) {
			$class = is_object($callable[0]) ? get_class($callable[0]) : (string) $callable[0];
			return $class . '::' . (string) $callable[1];
		}
		if ($callable instanceof \Closure) {
			return 'Closure';
		}
		return 'callable';
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
	private function normalize_schema_keys(array $schema): array {
		$normalized = array();
		foreach ($schema as $key => $rules) {
			$nKey  = self::sanitize_key((string) $key);
			$entry = array(
			    'sanitize' => $rules['sanitize'] ?? null,
			    'validate' => $rules['validate'] ?? null,
			);
			if (\is_array($rules) && array_key_exists('default', $rules)) {
				$entry['default'] = $rules['default'];
			}
			$normalized[$nKey] = $entry;
		}
		return $normalized;
	}
}
