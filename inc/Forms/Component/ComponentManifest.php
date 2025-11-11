<?php
/**
 * ComponentManifest: unified registry and _discovery for form components.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Ran\PluginLib\Forms\Component\Normalize\NormalizeInterface;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;

class ComponentManifest {
	use WPWrappersTrait;
	/** @var array<string, callable(array<string,mixed>):ComponentRenderResult|array{result:ComponentRenderResult,warnings?:array<int,string>}> */
	private array $components = array();
	/** @var array<int,string> */
	private array $warnings = array();
	/** @var array<string,array{normalizer:?string,builder:?string,validator:?string,defaults?:array{sanitize?:array<int,string>,validate?:array<int,string>,context?:array{submits_data:bool,component_type:string,repeatable:bool}}}> */
	private array $componentMetadata = array();
	private ComponentNormalizationContext $helpers;
	/** @var int Cache TTL in seconds */
	private int $cacheTTL;
	/** @var bool Whether caching is enabled */
	private bool $cachingEnabled;

	public function __construct(private ComponentLoader $views, private Logger $logger) {
		$this->logger         = $logger;
		$this->helpers        = new ComponentNormalizationContext($this->logger);
		$this->cachingEnabled = $this->_should_use_cache();
		$this->cacheTTL       = $this->_get_cache_ttl();

		$this->_discover();
		$this->_register_defaults();
	}

	/**
	 * Registers a component factory.
	 *
	 * @param string $alias
	 * @param callable $factory
	 */
	public function register(string $alias, callable $factory): void {
		$this->components[$alias] = $factory;
	}

	/**
	 * Checks if a component is registered.
	 *
	 * @param string $alias
	 * @return bool
	 */
	public function has(string $alias): bool {
		return isset($this->components[$alias]);
	}

	/**
	 * Renders a component.
	 *
	 * @param string $alias
	 * @param array<string,mixed> $context
	 * @return ComponentRenderResult
	 */
	public function render(string $alias, array $context = array()): ComponentRenderResult {
		if (!isset($this->components[$alias])) {
			// Check if the alias has invalid format
			if (!$this->_is_valid_template_key($alias)) {
				$this->logger->warning("Attempted to render template with invalid key format: '$alias'");
				throw new \InvalidArgumentException(
					sprintf('Template "%s" was not registered because it has an invalid key format. ' .
					       'Template keys must contain only letters, numbers, dots, hyphens, and underscores.', $alias)
				);
			}

			$this->logger->warning(sprintf('Unknown form component "%s".', $alias), array('context' => $context));
			throw new \InvalidArgumentException(sprintf('Unknown form component "%s".', $alias));
		}

		$factory = $this->components[$alias];
		$outcome = $factory($context);

		$localWarnings = array();
		if ($outcome instanceof ComponentRenderResult) {
			$result = $outcome;
		} elseif (is_array($outcome) && isset($outcome['result'])) {
			$result        = $outcome['result'];
			$localWarnings = isset($outcome['warnings']) && is_array($outcome['warnings']) ? $outcome['warnings'] : array();
			if (!$result instanceof ComponentRenderResult) {
				$this->logger->warning(sprintf('Component "%s" factory must return ComponentRenderResult.', $alias), array('context' => $context));
				throw new \UnexpectedValueException(sprintf('Component "%s" must return ComponentRenderResult.', $alias));
			}
		} else {
			$this->logger->warning(sprintf('Component "%s" factory must return ComponentRenderResult.', $alias), array('context' => $context));
			throw new \UnexpectedValueException(sprintf('Component "%s" factory must return ComponentRenderResult.', $alias));
		}

		$helperWarnings = $this->helpers->take_warnings();
		if (!empty($helperWarnings)) {
			$localWarnings = array_merge($localWarnings, $helperWarnings);
		}
		if (!empty($localWarnings)) {
			$this->warnings = array_merge($this->warnings, $localWarnings);
		}

		return $result;
	}

	/**
	 * Returns any warnings generated during rendering.
	 *
	 * @return array<int,string>
	 */
	public function take_warnings(): array {
		$warnings       = $this->warnings;
		$this->warnings = array();
		return $warnings;
	}

	/**
	 * Creates normalizers for all registered components.
	 *
	 * @return array<string,NormalizeInterface>
	 */
	public function create_normalizers(): array {
		$instances = array();
		if (empty($this->componentMetadata)) {
			return $instances;
		}

		foreach ($this->componentMetadata as $alias => $meta) {
			if (!is_array($meta)) {
				$this->logger->warning('ComponentManifest: Skipping cached metadata (invalid format)', array(
					'alias'         => $alias,
					'metadata_type' => gettype($meta),
				));
				continue;
			}
			if (!array_key_exists('normalizer', $meta)) {
				$this->logger->warning('ComponentManifest: Skipping cached metadata (missing normalizer key)', array(
					'alias' => $alias,
					'keys'  => array_keys($meta),
				));
				continue;
			}
			$normalizer = $meta['normalizer'];
			if ($normalizer === null) {
				continue;
			}
			$instances[$alias] = new $normalizer($this->views);
		}

		return $instances;
	}

	/**
	 * Creates builder factories for all registered components.
	 *
	 * @return array<string,callable(string,string):ComponentBuilderDefinitionInterface>
	 */
	public function builder_factories(): array {
		$factories = array();
		foreach ($this->componentMetadata as $alias => $meta) {
			if (!is_array($meta)) {
				$this->logger->warning('ComponentManifest: Skipping cached metadata (invalid format)', array(
					'alias'         => $alias,
					'metadata_type' => gettype($meta),
				));
				continue;
			}
			if (!array_key_exists('builder', $meta)) {
				$this->logger->warning('ComponentManifest: Skipping cached metadata (missing builder key)', array(
					'alias' => $alias,
					'keys'  => array_keys($meta),
				));
				continue;
			}

			$builder = $meta['builder'];
			if ($builder === null) {
				continue;
			}
			$factories[$alias] = function (string $id, string $label, mixed ...$args) use ($builder): ComponentBuilderDefinitionInterface {
				return new $builder($id, $label, ...$args);
			};
		}

		return $factories;
	}

	/**
	 * Returns a map of validator factories for each component.
	 *
	 * @return array<string,callable():ValidatorInterface>
	 */
	public function validator_factories(): array {
		$factories = array();
		if (empty($this->componentMetadata)) {
			return $factories;
		}

		foreach ($this->componentMetadata as $alias => $meta) {
			if (!is_array($meta)) {
				$this->logger->warning('ComponentManifest: Skipping cached metadata (invalid format)', array(
					'alias'         => $alias,
					'metadata_type' => gettype($meta),
				));
				continue;
			}
			if (!array_key_exists('validator', $meta)) {
				$this->logger->warning('ComponentManifest: Skipping cached metadata (missing validator key)', array(
					'alias' => $alias,
					'keys'  => array_keys($meta),
				));
				continue;
			}
			$validator = $meta['validator'];
			if ($validator === null) {
				continue;
			}
			$factories[$alias] = function () use ($validator): ValidatorInterface {
				$instance = new $validator($this->logger);
				if (!$instance instanceof ValidatorInterface) {
					$this->logger->warning(sprintf('Validator for "%s" must implement %s.', $validator, ValidatorInterface::class), array('validator' => $validator));
					throw new \UnexpectedValueException(sprintf('Validator for "%s" must implement %s.', $validator, ValidatorInterface::class));
				}
				return $instance;
			};
		}

		return $factories;
	}

	/**
	 * Pre-populates the component cache by calling existing discovery.
	 * V2: Simple caching method that leverages existing infrastructure.
	 */
	public function warm_cache(): void {
		$this->_discover();
	}

	/**
	 * Clears component cache with optional targeted clearing.
	 *
	 * @param string|null $alias Optional component alias for targeted clearing
	 */
	public function clear_cache(string $alias = ''): void {
		if ($alias !== '') {
			// Clear specific component
			$cache_key = $this->_generate_component_cache_key($alias);
			$this->_do_delete_transient($cache_key);
			unset($this->componentMetadata[$alias]);
			$this->_remove_tracked_transient($cache_key);
			$this->logger->debug('ComponentManifest: CLEARED cache for component', array(
				'alias'     => $alias,
				'cache_key' => $cache_key
			));
		} else {
			// Clear all component caches
			$component_count         = count($this->componentMetadata);
			$this->componentMetadata = array();
			$this->_clear_all_component_transients();
			$this->logger->debug('ComponentManifest: CLEARED all component caches', array(
				'cleared_count' => $component_count
			));
		}
	}

	/**
	 * Checks if a component is eligible for auto-schema generation.
	 * V2: Simple method for auto-schema generation compatibility.
	 *
	 * @param string $alias
	 * @return bool
	 */
	public function is_component_schema_eligible(string $alias): bool {
		if (!$this->has($alias)) {
			return false;
		}
		$result = $this->render($alias, array());
		return $result->submits_data;
	}

	/**
	 * Discovers all available components.
	 */
	private function _discover(): void {
		foreach ($this->views->aliases() as $alias => $_path) {
			$this->_register_alias($alias);
		}
	}

	/**
	 * Registers a component alias.
	 *
	 * @param string $alias
	 */
	private function _register_alias(string $alias): void {
		// Skip cache entirely in development or when disabled
		if (!$this->cachingEnabled) {
			$this->_discover_component_metadata($alias);
			return;
		}

		// Try cache first (staging/production only)
		$cache_key   = $this->_generate_component_cache_key($alias);
		$cached_meta = $this->_do_get_transient($cache_key);

		if ($cached_meta !== false) {
			$this->componentMetadata[$alias] = $cached_meta;
			$this->logger->debug('ComponentManifest: Cache HIT for component', array(
				'alias'     => $alias,
				'cache_key' => $cache_key
			));
			return;
		}

		// Cache miss - discover and cache
		$this->logger->debug('ComponentManifest: Cache MISS for component', array(
			'alias'     => $alias,
			'cache_key' => $cache_key
		));

		$this->_discover_component_metadata($alias);
		if (isset($this->componentMetadata[$alias])) {
			$this->_do_set_transient($cache_key, $this->componentMetadata[$alias], $this->cacheTTL);
			$this->_track_transient($cache_key);
			$this->logger->debug('ComponentManifest: Cached component metadata', array(
				'alias'     => $alias,
				'cache_key' => $cache_key,
				'ttl'       => $this->cacheTTL
			));
		}
	}

	/**
	 * Extract discovery logic for reuse.
	 *
	 * @param string $alias
	 */
	private function _discover_component_metadata(string $alias): void {
		$meta = array(
			'normalizer' => null,
			'builder'    => null,
			'validator'  => null,
		);

		$class = $this->views->resolve_normalizer_class($alias);
		if ($class !== null && is_subclass_of($class, NormalizeInterface::class)) {
			$meta['normalizer'] = $class;
		}

		$builder = $this->views->resolve_builder_class($alias);
		if ($builder !== null && is_subclass_of($builder, ComponentBuilderDefinitionInterface::class)) {
			$meta['builder'] = $builder;
		}

		$validator = $this->views->resolve_validator_class($alias);
		if ($validator !== null && is_subclass_of($validator, ValidatorInterface::class)) {
			$meta['validator'] = $validator;
		}

		$defaults         = $this->_derive_component_defaults($alias, $meta);
		$meta['defaults'] = $defaults;

		$sources = array();
		if (!empty($defaults['sanitize'] ?? array())) {
			$sources[] = 'sanitize';
		}
		if (!empty($defaults['validate'] ?? array())) {
			$sources[] = 'validate';
		}

		if (!empty($sources)) {
			$this->logger->debug('ComponentManifest: defaults discovered for component', array(
				'alias'   => $alias,
				'sources' => $sources,
			));
		} else {
			$this->logger->debug('ComponentManifest: defaults missing for component', array(
				'alias' => $alias,
			));
		}

		$this->componentMetadata[$alias] = $meta;
	}

	/**
	 * Registers default factories for all components.
	 */
	private function _register_defaults(): void {
		$normalizers = $this->create_normalizers();

		foreach ($this->views->aliases() as $alias => $_path) {
			if (isset($normalizers[$alias])) {
				$normalizer = $normalizers[$alias];
				$this->register($alias, function (array $context) use ($normalizer, $alias): array {
					$normalized = $normalizer->render($context, $this->helpers, $alias);
					$payload    = $normalized['payload'];
					$result     = $this->_create_result_from_payload($payload);
					return array(
						'result'      => $result,
						'warnings'    => $normalized['warnings'] ?? array(),
					    'result'   => $result,
					    'warnings' => $normalized['warnings'] ?? array(),
					);
				});
				continue;
			}

			$this->register($alias, function (array $context) use ($alias): array {
				$result = $this->_render_raw_component($alias, $context);
				return array(
				    'result'   => $result,
				    'warnings' => array(),
				);
			});
		}
	}

	/**
	 * Retrieve defaults for a specific component alias.
	 *
	 * @param string $alias
	 * @return array<string,mixed>
	 */
	public function get_defaults_for(string $alias): array {
		$meta = $this->componentMetadata[$alias] ?? null;
		if (!is_array($meta)) {
			return array();
		}

		$defaults = $meta['defaults'] ?? array();
		return is_array($defaults) ? $defaults : array();
	}

	/**
	 * Retrieve the full defaults catalogue keyed by alias.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function default_catalogue(): array {
		$catalogue = array();
		foreach ($this->componentMetadata as $alias => $meta) {
			$defaults          = $meta['defaults'] ?? array();
			$catalogue[$alias] = is_array($defaults) ? $defaults : array();
		}
		return $catalogue;
	}

	/**
	 * Collect defaults exposed by discovered component classes.
	 *
	 * @param string      $alias
	 * @param string|null $normalizer
	 * @param string|null $builder
	 * @param string|null $validator
	 * @return array<string,mixed>
	 */
	private function _derive_component_defaults(string $alias, array $meta): array {
		$defaults = array();
		if (!empty($meta['normalizer'])) {
			$defaults['sanitize'] = array($meta['normalizer']);
		}
		if (!empty($meta['validator'])) {
			$defaults['validate'] = array($meta['validator']);
		}

		$defaults['context'] = $this->_derive_component_context($alias, $meta);

		return $defaults;
	}

	/**
	 * Derive baseline context metadata for a component.
	 *
	 * @param string                     $alias
	 * @param array<string,null|string> $meta
	 * @return array{submits_data:bool,component_type:string,repeatable:bool}
	 */
	private function _derive_component_context(string $alias, array $meta): array {
		// Baseline assumptions; refined values can be introduced in later tasks.
		return array(
			'submits_data'   => false,
			'component_type' => 'input',
			'repeatable'     => false,
		);
	}

	/**
	 * Creates a ComponentRenderResult from a payload.
	 *
	 * @param array<string,mixed> $payload
	 * @return ComponentRenderResult
	 */
	private function _create_result_from_payload(array $payload): ComponentRenderResult {
		return new ComponentRenderResult(
			(string) ($payload['markup'] ?? ''),
			$payload['script'] ?? null,
			$payload['style']  ?? null,
			(bool) ($payload['requires_media'] ?? false),
			(bool) ($payload['repeatable'] ?? false),
			$payload['context_schema'] ?? array(),
			(bool) ($payload['submits_data'] ?? false),        // V2
			(string) ($payload['component_type'] ?? 'input') // V2
		);
	}

	/**
	 * Renders a component without normalizing it.
	 * V2: Enhanced to handle direct ComponentRenderResult returns.
	 *
	 * @param array<string,mixed> $context
	 * @return ComponentRenderResult
	 */
	private function _render_raw_component(string $alias, array $context): ComponentRenderResult {
		$result = $this->views->render_payload($alias, $context);

		// V2: Direct ComponentRenderResult return (preferred)
		if ($result instanceof ComponentRenderResult) {
			return $result;
		}

		// Backward compatibility: convert array to ComponentRenderResult
		if (is_array($result) && isset($result['markup'])) {
			return $this->_create_result_from_payload($result);
		}

		// Fallback for string returns
		$markup = is_string($result) ? $result : $this->views->render($alias, $context);
		return new ComponentRenderResult(
			markup: (string) $markup,
			submits_data: false,        // V2: Default for raw components
			component_type: 'input' // V2: Default for raw components
		);
	}

	/**
	 * Get the ComponentLoader instance for template registration.
	 *
	 * @return ComponentLoader
	 */
	public function get_component_loader(): ComponentLoader {
		return $this->views;
	}

	/**
	 * Determine if caching should be used.
	 *
	 * @return bool
	 */
	private function _should_use_cache(): bool {
		// Explicit disable via constant
		if (\defined('KEPLER_COMPONENT_CACHE_DISABLED') && (bool) \constant('KEPLER_COMPONENT_CACHE_DISABLED')) {
			return false;
		}

		// Disable in development mode (WP_DEBUG) for immediate feedback
		if (\defined('WP_DEBUG') && \WP_DEBUG) {
			return false;
		}

		return true;
	}

	/**
	 * Get cache TTL with environment-based defaults.
	 *
	 * @return int TTL in seconds
	 */
	private function _get_cache_ttl(): int {
		// Allow override via constant
		if (\defined('KEPLER_COMPONENT_CACHE_TTL')) {
			return \max(300, (int) \constant('KEPLER_COMPONENT_CACHE_TTL')); // Minimum 5 minutes
		}

		// Environment-based defaults
		$environment = $this->_do_wp_get_environment_type();
		switch ($environment) {
			case 'development':
				return 300; // 5 minutes
			case 'staging':
				return 1800; // 30 minutes
			default:
				return 3600; // 1 hour (production)
		}
	}

	/**
	 * Generate optimized cache key for component metadata with collision avoidance.
	 *
	 * @param string $alias Component alias
	 * @return string Optimized cache key
	 */
	private function _generate_component_cache_key(string $alias): string {
		// Sanitize alias and add prefix to avoid collisions
		$sanitized_alias = preg_replace('/[^a-zA-Z0-9._-]/', '_', $alias);
		return "kepler_comp_meta_{$sanitized_alias}";
	}

	/**
	 * Track transient for cleanup purposes.
	 *
	 * @param string $transient_key
	 */
	private function _track_transient(string $transient_key): void {
		$active_transients = $this->_do_get_option('component_cache_transients', array());

		// Ensure we have an array (handle case where _do_get_option returns null)
		if (!is_array($active_transients)) {
			$active_transients = array();
		}

		if (!\in_array($transient_key, $active_transients, true)) {
			$active_transients[] = $transient_key;
			$this->_do_update_option('component_cache_transients', $active_transients, false);
		}
	}

	/**
	 * Remove tracked transient from cleanup list.
	 *
	 * @param string $transient_key
	 */
	private function _remove_tracked_transient(string $transient_key): void {
		$active_transients = $this->_do_get_option('component_cache_transients', array());
		$key               = \array_search($transient_key, $active_transients, true);
		if ($key !== false) {
			unset($active_transients[$key]);
			$this->_do_update_option('component_cache_transients', \array_values($active_transients), false);
		}
	}

	/**
	 * Clear all component transients using tracked transient list.
	 */
	private function _clear_all_component_transients(): void {
		$active_transients = $this->_do_get_option('component_cache_transients', array());
		$cleared_count     = 0;

		foreach ($active_transients as $transient_key) {
			if ($this->_do_delete_transient($transient_key)) {
				$cleared_count++;
			}
		}

		$this->_do_delete_option('component_cache_transients');
		$this->logger->debug('ComponentManifest: Cleared component transients', array('count' => $cleared_count));
	}
	/**
	 * Validate template key format
	 *
	 * @param string $key The template key to validate
	 * @return bool True if valid, false otherwise
	 */
	private function _is_valid_template_key(string $key): bool {
		return !empty($key) && preg_match('/^[a-zA-Z0-9._-]+$/', $key);
	}
}
