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
use Ran\PluginLib\Forms\Component\Sanitize\SanitizerInterface;
use Ran\PluginLib\Forms\Component\Normalize\NormalizeInterface;
use Ran\PluginLib\Forms\Component\Normalize\ComponentNormalizationContext;
use Ran\PluginLib\Forms\Component\Cache\ComponentCacheService;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;

class ComponentManifest {
	use WPWrappersTrait;
	/** @var array<string, callable(array<string,mixed>):ComponentRenderResult|array{result:ComponentRenderResult,warnings?:array<int,string>}> */
	private array $components = array();
	/** @var array<int,string> */
	private array $warnings = array();
	/** @var array<string,array{normalizer:?string,builder:?string,validator:?string,sanitizer:?string,defaults?:array{sanitize?:array<int,string>,validate?:array<int,string>,context?:array{component_type:string,repeatable:bool}}}> */
	private array $componentMetadata = array();
	private ComponentNormalizationContext $helpers;
	/** @var ComponentCacheService Shared caching service */
	private ComponentCacheService $cacheService;

	/**
	 * Memoized validator factory map.
	 *
	 * @var array<string, callable():ValidatorInterface>|null
	 */
	private ?array $validatorFactoriesCache = null;

	/**
	 * Memoized sanitizer factory map.
	 *
	 * @var array<string, callable():SanitizerInterface>|null
	 */
	private ?array $sanitizerFactoriesCache = null;

	/**
	 * Cached validator instances keyed by component alias.
	 *
	 * @var array<string, ValidatorInterface>
	 */
	private array $validatorInstances = array();

	/**
	 * Cached sanitizer instances keyed by component alias.
	 *
	 * @var array<string, SanitizerInterface>
	 */
	private array $sanitizerInstances = array();

	/**
	 * Constructor.
	 *
	 * @param ComponentLoader $views Component loader instance
	 * @param Logger $logger Logger for operations
	 * @param ComponentCacheService|null $cacheService Optional shared cache service
	 */
	public function __construct(private ComponentLoader $views, private Logger $logger, ?ComponentCacheService $cacheService = null) {
		$this->logger       = $logger;
		$this->helpers      = new ComponentNormalizationContext($this->logger);
		$this->cacheService = $cacheService ?? $views->get_cache_service();

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
	 * Registers a builder factory for a component.
	 *
	 * This allows tests and external code to register builder factories
	 * for components that don't have a Builder.php file.
	 *
	 * @param string $alias The component alias.
	 * @param string|callable $builder The fully-qualified builder class name, or a factory callable.
	 */
	public function register_builder(string $alias, string|callable $builder): void {
		if (!isset($this->componentMetadata[$alias])) {
			$this->componentMetadata[$alias] = array(
				'normalizer' => null,
				'builder'    => null,
				'validator'  => null,
				'sanitizer'  => null,
			);
		}
		$this->componentMetadata[$alias]['builder'] = $builder;
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

			// Check if the alias was registered on the ComponentLoader after manifest construction
			// (e.g., by UserSettings or AdminSettings registering their context-specific templates)
			$loaderAliases = $this->views->aliases();
			if (isset($loaderAliases[$alias])) {
				// Dynamically register the factory for this alias
				$this->_register_alias_factory($alias);
			} else {
				$this->logger->warning(sprintf('Unknown form component "%s".', $alias), array('context' => $context));
				throw new \InvalidArgumentException(sprintf('Unknown form component "%s".', $alias));
			}
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
	 * Render a component and return just the HTML markup string.
	 *
	 * Convenience method for debugging, testing, and cases where only
	 * the markup is needed (e.g., AJAX responses, email templates).
	 *
	 * @param string $alias Component alias (e.g., 'fields.select')
	 * @param array<string,mixed> $context Component context
	 * @return string Rendered HTML markup
	 */
	public function render_to_string(string $alias, array $context = array()): string {
		return $this->render($alias, $context)->markup;
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

			// Support both class names and factory callables
			if (is_callable($builder)) {
				$factories[$alias] = $builder;
			} else {
				$factories[$alias] = function (string $id, string $label, mixed ...$args) use ($builder): ComponentBuilderDefinitionInterface {
					return new $builder($id, $label, ...$args);
				};
			}
		}

		return $factories;
	}

	/**
	 * Returns a map of validator factories for each component.
	 *
	 * Uses two-level caching:
	 * - Level 1: Memoizes the factory map (built once per manifest instance)
	 * - Level 2: Each factory caches its instance (one instance per component alias)
	 *
	 * @return array<string,callable():ValidatorInterface>
	 */
	public function validator_factories(): array {
		// Level 1: Return memoized factory map if available
		if ($this->validatorFactoriesCache !== null) {
			return $this->validatorFactoriesCache;
		}

		$factories = array();
		if (empty($this->componentMetadata)) {
			$this->validatorFactoriesCache = $factories;
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
			// Level 2: Factory closure uses instance cache
			$factories[$alias] = function () use ($validator, $alias): ValidatorInterface {
				if (isset($this->validatorInstances[$alias])) {
					return $this->validatorInstances[$alias];
				}
				$this->validatorInstances[$alias] = new $validator($this->logger);
				return $this->validatorInstances[$alias];
			};
		}

		$this->validatorFactoriesCache = $factories;
		return $factories;
	}

	/**
	 * Returns a map of sanitizer factories for each component.
	 *
	 * Uses two-level caching:
	 * - Level 1: Memoizes the factory map (built once per manifest instance)
	 * - Level 2: Each factory caches its instance (one instance per component alias)
	 *
	 * @return array<string,callable():SanitizerInterface>
	 */
	public function sanitizer_factories(): array {
		// Level 1: Return memoized factory map if available
		if ($this->sanitizerFactoriesCache !== null) {
			return $this->sanitizerFactoriesCache;
		}

		$factories = array();
		if (empty($this->componentMetadata)) {
			$this->sanitizerFactoriesCache = $factories;
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
			// Sanitizers are optional – skip silently if key missing or null
			if (!array_key_exists('sanitizer', $meta)) {
				continue;
			}
			$sanitizer = $meta['sanitizer'];
			if ($sanitizer === null) {
				continue;
			}
			// Level 2: Factory closure uses instance cache
			$factories[$alias] = function () use ($sanitizer, $alias): SanitizerInterface {
				if (isset($this->sanitizerInstances[$alias])) {
					return $this->sanitizerInstances[$alias];
				}
				$this->sanitizerInstances[$alias] = new $sanitizer($this->logger);
				return $this->sanitizerInstances[$alias];
			};
		}

		$this->sanitizerFactoriesCache = $factories;
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
			$cache_key = $this->cacheService->generate_component_key($alias);
			$this->cacheService->delete($cache_key, ComponentCacheService::PREFIX_COMPONENT);
			unset($this->componentMetadata[$alias]);
		} else {
			// Clear all component caches
			$this->componentMetadata = array();
			$this->cacheService->clear_all(ComponentCacheService::PREFIX_COMPONENT);
		}
	}

	/**
	 * Get the cache service instance.
	 *
	 * @return ComponentCacheService
	 */
	public function get_cache_service(): ComponentCacheService {
		return $this->cacheService;
	}

	/**
	 * Clear cached validator/sanitizer instances and factory maps.
	 *
	 * @internal For testing only. Validators and sanitizers are stateless,
	 * so instance caching is safe for production use.
	 */
	public function __clear_instance_cache(): void {
		$this->validatorInstances      = array();
		$this->sanitizerInstances      = array();
		$this->validatorFactoriesCache = null;
		$this->sanitizerFactoriesCache = null;
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
		return $result->submits_data();
	}

	/**
	 * Discover and register metadata for a single alias.
	 *
	 * Used to wire up externally registered components after initial discovery.
	 * This method discovers metadata, registers a factory, and makes the component
	 * available for rendering.
	 *
	 * @param string $alias Component alias to discover (e.g., 'my-plugin.color-picker')
	 */
	public function discover_alias(string $alias): void {
		// Discover and cache metadata
		$this->_register_alias($alias);

		// Register a factory for the component if not already registered
		if (!$this->has($alias)) {
			$this->_register_alias_factory($alias);
		}
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
		if (!$this->cacheService->is_enabled()) {
			$this->_discover_component_metadata($alias);
			return;
		}

		// Try cache first (staging/production only)
		$cache_key   = $this->cacheService->generate_component_key($alias);
		$cached_meta = $this->cacheService->get($cache_key);

		if ($cached_meta !== false) {
			$this->componentMetadata[$alias] = $cached_meta;
			return;
		}

		// Cache miss - discover and cache
		$this->_discover_component_metadata($alias);
		if (isset($this->componentMetadata[$alias])) {
			$this->cacheService->set($cache_key, $this->componentMetadata[$alias], ComponentCacheService::PREFIX_COMPONENT);
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
			'sanitizer'  => null,
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

		$sanitizer = $this->views->resolve_sanitizer_class($alias);
		if ($sanitizer !== null && is_subclass_of($sanitizer, SanitizerInterface::class)) {
			$meta['sanitizer'] = $sanitizer;
		}

		$defaults         = $this->_derive_component_defaults($alias, $meta);
		$meta['defaults'] = $defaults;

		// Check for validator/sanitizer presence in metadata (not defaults)
		// since sanitizers/validators are now injected via factory -> queue -> merge path
		$sources = array();
		if (!empty($meta['sanitizer'])) {
			$sources[] = 'sanitizer';
		}
		if (!empty($meta['validator'])) {
			$sources[] = 'validator';
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
		foreach ($this->views->aliases() as $alias => $_path) {
			$this->_register_alias_factory($alias);
		}
	}

	/**
	 * Register a factory for a single alias.
	 *
	 * @param string $alias Component alias
	 */
	private function _register_alias_factory(string $alias): void {
		// Check if this alias has a normalizer
		$meta = $this->componentMetadata[$alias] ?? null;
		if (is_array($meta) && !empty($meta['normalizer'])) {
			$normalizerClass = $meta['normalizer'];
			$normalizer      = new $normalizerClass($this->views);
			$this->register($alias, function (array $context) use ($normalizer, $alias): array {
				$normalized = $normalizer->render($context, $this->helpers, $alias);
				$payload    = $normalized['payload'];
				// Handle both ComponentRenderResult and array payloads
				if ($payload instanceof ComponentRenderResult) {
					$result = $payload;
				} else {
					$result = $this->_create_result_from_payload($payload);
				}
				return array(
					'result'   => $result,
					'warnings' => $normalized['warnings'] ?? array(),
				);
			});
			return;
		}

		// No normalizer - use raw rendering
		$this->register($alias, function (array $context) use ($alias): array {
			$result = $this->_render_raw_component($alias, $context);
			return array(
				'result'   => $result,
				'warnings' => array(),
			);
		});
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
	 * Derive component defaults from metadata.
	 *
	 * Note: Sanitizers and validators are injected via the factory → queue → merge
	 * path in FormsBaseTrait (_inject_component_validators, _inject_component_sanitizers),
	 * not stored in defaults. Only context is needed here.
	 *
	 * @param string              $alias Component alias.
	 * @param array<string,mixed> $meta  Component metadata.
	 * @return array{context: array{repeatable: bool}}
	 */
	private function _derive_component_defaults(string $alias, array $meta): array {
		return array(
			'context' => $this->_derive_component_context($alias, $meta),
		);
	}

	/**
	 * Derive baseline context metadata for a component.
	 *
	 * @param string                     $alias
	 * @param array<string,null|string> $meta
	 * @return array{component_type:string,repeatable:bool}
	 */
	private function _derive_component_context(string $alias, array $meta): array {
		// Don't assume component_type - let the View declare it explicitly.
		// This prevents non-input components from being incorrectly flagged as requiring validators.
		return array(
			'repeatable' => false,
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
	 * Validate template key format
	 *
	 * @param string $key The template key to validate
	 * @return bool True if valid, false otherwise
	 */
	private function _is_valid_template_key(string $key): bool {
		return !empty($key) && preg_match('/^[a-zA-Z0-9._-]+$/', $key);
	}
}
