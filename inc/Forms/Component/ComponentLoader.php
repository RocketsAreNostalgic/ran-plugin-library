<?php
/**
 * ComponentLoader: lightweight PHP template resolver for shared form components.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use UnexpectedValueException;
use SplFileInfo;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Component\Cache\ComponentCacheService;
use Ran\PluginLib\Config\ConfigInterface;
use FilesystemIterator;
use DirectoryIterator;

/** @package Ran\PluginLib\Forms\Component */
class ComponentLoader {
	use WPWrappersTrait;

	/**
	 * Component alias map.
	 *
	 * - Built-in components: string (relative path from baseDir)
	 * - External components: array{path:string, namespace:string} (absolute path + PSR-4 namespace)
	 *
	 * @var array<string, string|array{path:string, namespace:string}>
	 */
	private array $map = array();
	private string $baseDir;

	/** @var ComponentCacheService Shared caching service */
	private ComponentCacheService $cacheService;

	/** @var Logger|null Logger instance for cache operations */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param string $baseDir
	 * @param Logger $logger Logger for cache operations
	 * @param array<string,string> $map
	 * @param ComponentCacheService|null $cacheService Optional shared cache service
	 */
	public function __construct(string $baseDir, Logger $logger, array $map = array(), ?ComponentCacheService $cacheService = null) {
		$this->baseDir = rtrim($baseDir, '/');

		$discovered = $this->_default_map();
		$overrides  = $this->_normalize_map($map);

		$this->map          = array_merge($discovered, $overrides);
		$this->logger       = $logger;
		$this->cacheService = $cacheService ?? new ComponentCacheService($logger);
	}

	/**
	 * Register a template alias to its relative path.
	 * Useful for adding custom templates or overriding default ones.
	 *
	 * @param string $name
	 * @param string $relativePath
	 */
	public function register(string $name, string $relativePath): self {
		if (!$this->_is_valid_template_key($name)) {
			$this->logger?->warning("Skipping invalid template key: '$name'");
			return $this;
		}

		$this->map[$name] = $this->_normalize_path($relativePath);
		return $this;
	}

	/**
	 * Register multiple components from an array of registration options.
	 *
	 * @param array $registrations Array of component registration options
	 * @param ConfigInterface $config Configuration instance
	 * @return self
	 */
	public function register_components_batch(array $registrations, ConfigInterface $config): self {
		foreach ($registrations as $options) {
			if (!is_array($options)) {
				continue;
			}
			$this->register_components($options, $config);
		}
		return $this;
	}

	/**
	 * Register a template with an absolute path.
	 *
	 * Unlike register() which treats paths as relative to the base directory,
	 * this method stores the path as-is for direct file access.
	 *
	 * @param string $name    Template alias
	 * @param string $absPath Absolute path to the template file
	 * @return self
	 */
	public function register_absolute(string $name, string $absPath): self {
		if (!$this->_is_valid_template_key($name)) {
			$this->logger?->warning("Skipping invalid template key: '$name'");
			return $this;
		}

		$this->map[$name] = array('path' => $absPath);
		return $this;
	}

	/**
	 * Register an external component with Config-based path and namespace resolution.
	 *
	 * @param string $name Component name (e.g., 'color-picker')
	 * @param array{path: string, prefix?: string} $options Component options:
	 *        - path: Relative path from plugin root to component directory
	 *        - prefix: Optional alias prefix (e.g., 'my-plugin')
	 * @param ConfigInterface $config Plugin configuration for path and namespace resolution
	 * @return self
	 */
	public function register_component(string $name, array $options, ConfigInterface $config): self {
		if (!$this->_is_valid_template_key($name)) {
			$this->logger?->warning("Skipping invalid template key: '$name'");
			return $this;
		}

		// Determine prefix and final alias
		$prefix = $options['prefix'] ?? null;
		$alias  = $prefix ? $prefix . '.' . $name : $name;

		if (!isset($options['path'])) {
			$this->logger?->warning("Component '$name' config missing 'path' key");
			return $this;
		}

		$this->_register_external_component($alias, $options['path'], $config);
		return $this;
	}

	/**
	 * Register multiple external components from a directory.
	 *
	 * Scans the specified directory for subdirectories containing View.php files
	 * and registers each as an external component.
	 *
	 * @param array{path: string, prefix?: string} $options Batch options:
	 *        - path: Relative path from plugin root to components directory
	 *        - prefix: Optional alias prefix for all discovered components
	 * @param ConfigInterface $config Plugin configuration for path and namespace resolution
	 * @return self
	 */
	public function register_components(array $options, ConfigInterface $config): self {
		if (!isset($options['path'])) {
			$this->logger?->warning("register_components() requires 'path' option");
			return $this;
		}

		$basePath = rtrim($config->get_config()['PATH'] ?? '', '/');
		$fullPath = $basePath . '/' . ltrim($options['path'], '/');
		$prefix   = $options['prefix'] ?? null;

		if (!is_dir($fullPath)) {
			$this->logger?->warning("Components directory does not exist: $fullPath");
			return $this;
		}

		$iterator = new DirectoryIterator($fullPath);
		foreach ($iterator as $item) {
			if (!$item->isDir() || $item->isDot()) {
				continue;
			}

			$componentDir = $item->getPathname();
			if (!is_file($componentDir . '/View.php')) {
				continue;
			}

			$folderName   = $item->getFilename();
			$aliasSegment = $this->_normalize_to_kebab($folderName);
			$alias        = $prefix ? $prefix . '.' . $aliasSegment : $aliasSegment;

			$relativePath = $options['path'] . '/' . $folderName;
			$this->_register_external_component($alias, $relativePath, $config);
		}

		return $this;
	}

	/**
	 * Check if an alias refers to an external component.
	 *
	 * @param string $alias Component alias
	 * @return bool True if external (array entry), false if built-in (string entry)
	 */
	public function is_external(string $alias): bool {
		return isset($this->map[$alias]) && is_array($this->map[$alias]);
	}

	/**
	 * Get the base directory for template resolution.
	 *
	 * @return string
	 */
	public function get_base_directory(): string {
		return $this->baseDir;
	}

	/**
	 * Render the named template with the provided context.
	 *
	 * @param string $name
	 * @param array<string,mixed> $context
	 */
	public function render(string $name, array $context = array()): ComponentRenderResult {
		// Skip cache entirely in development or when disabled
		if (!$this->cacheService->is_enabled()) {
			return $this->_render_template_directly($name, $context);
		}

		// Generate optimized cache key with collision avoidance
		$cache_key     = $this->cacheService->generate_template_key($name, $context);
		$cached_output = $this->cacheService->get($cache_key);

		if ($cached_output !== false) {
			return $this->_coerce_to_result($cached_output, $name, $context);
		}

		// Cache miss - render and cache
		$output = $this->_render_template_directly($name, $context);
		$this->cacheService->set($cache_key, $output, ComponentCacheService::PREFIX_TEMPLATE);

		return $output;
	}

	/**
	 * Execute a template and retrieve its raw return value.
	 *
	 * @param string $name
	 * @param array<string,mixed> $context
	 * @return mixed
	 */
	public function render_payload(string $name, array $context = array()): mixed {
		$path = $this->resolve_template_path($name);
		if (!\file_exists($path)) {
			$this->logger?->warning('ComponentLoader: Template not found', array(
				'template' => $name,
				'path'     => $path
			));
			throw new \LogicException(sprintf('Template "%s" not found at "%s".', $name, $path));
		}

		$execution = $this->_require_template($path, $context);
		if ($execution['output'] !== '') {
			$this->logger?->warning('ComponentLoader: Template produced direct output', array(
				'template' => $name,
				'path'     => $path
			));
			throw new \LogicException(sprintf('Template "%s" produced direct output; templates must return values.', $name));
		}

		return $execution['result'];
	}

	/**
	 * Retrieve the registered alias map.
	 *
	 * @return array<string, string|array{path:string, namespace:string}> Alias => path info.
	 */
	public function aliases(): array {
		return $this->map;
	}

	/**
	 * Resolve the class name for a component by its alias.
	 *
	 * @param string $alias
	 * @param string $suffix
	 * @return ?string
	 */
	public function resolve_normalizer_class(string $alias): ?string {
		return $this->_resolve_component_class($alias, 'Normalizer');
	}

	/**
	 * Resolve the class name for a builder by its alias.
	 *
	 * @param string $alias
	 * @return ?string
	 */
	public function resolve_builder_class(string $alias): ?string {
		return $this->_resolve_component_class($alias, 'Builder');
	}

	/**
	 * Resolve the class name for a validator by its alias.
	 */
	public function resolve_validator_class(string $alias): ?string {
		return $this->_resolve_component_class($alias, 'Validator');
	}

	/**
	 * Resolve the class name for a sanitizer by its alias.
	 *
	 * @param string $name
	 *
	 * @return string|null The class name for the sanitizer, or null if not found
	 */
	public function resolve_sanitizer_class(string $alias): ?string {
		return $this->_resolve_component_class($alias, 'Sanitizer');
	}

	/**
	 * Resolve the class name for an assets definition companion by its alias.
	 *
	 * @param string $alias
	 * @return ?string
	 */
	public function resolve_assets_class(string $alias): ?string {
		return $this->_resolve_component_class($alias, 'Assets');
	}

	/**
	 * Resolve the directory path for a component by its alias.
	 *
	 * Returns the directory containing the component's View.php and companion files.
	 *
	 * @param string $alias Component alias
	 * @return string|null Absolute path to the component directory, or null if not found
	 */
	public function resolve_component_directory(string $alias): ?string {
		if (!isset($this->map[$alias])) {
			return null;
		}

		$entry = $this->map[$alias];

		// External component: array with 'path' key (absolute path to View.php)
		if (is_array($entry)) {
			return dirname($entry['path']);
		}

		// Built-in component: string (relative path from baseDir)
		return dirname($this->baseDir . '/' . $entry);
	}

	/**
	 * Resolve the path to a template by its alias.
	 *
	 * @param string $name
	 * @return string Absolute path to the template file.
	 */
	public function resolve_template_path(string $name): string {
		if (!isset($this->map[$name])) {
			$this->logger?->warning('ComponentLoader: No template mapping registered', array(
				'template' => $name
			));
			throw new \LogicException(sprintf('No template mapping registered for "%s".', $name));
		}

		$entry = $this->map[$name];

		// External component: array with 'path' key (absolute path)
		if (is_array($entry)) {
			return $entry['path'];
		}

		// Built-in component: string (relative path from baseDir)
		return $this->baseDir . '/' . $entry;
	}

	/**
	 * Discover templates and build the default alias map.
	 *
	 * Recursively scans `$baseDir` for `*.php` files, strips the extension, and
	 * turns directory separators into dotted aliases (e.g. `fields/checkbox.php`
	 * becomes `fields.checkbox`).
	 *
	 * @return array<string,string> Alias => relative template path.
	 */
	private function _default_map(): array {
		$map = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($this->baseDir, FilesystemIterator::SKIP_DOTS)
			);
		} catch (UnexpectedValueException $e) {
			return $map;
		}
		/** @var SplFileInfo $file */
		foreach ($iterator as $file) {
			if (!$file->isFile() || $file->getExtension() !== 'php') {
				continue;
			}

			$basename = $file->getBasename('.php');

			// Skip companion classes (Builder, Normalizer, Sanitizer, Validator, Assets)
			// These are resolved programmatically via resolve_*_class() methods
			// Only View.php files or standalone templates should create aliases
			if (in_array($basename, array('Builder', 'Normalizer', 'Sanitizer', 'Validator', 'Assets'), true)) {
				continue;
			}

			$relative = substr($file->getPathname(), strlen($this->baseDir));
			$relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
			if ($relative === '') {
				continue;
			}

			$alias = $this->_alias_from_relativePath($relative);
			if ($alias === null) {
				continue;
			}

			$map[$alias] = $relative;
		}

		ksort($map);
		return $map;
	}

	/**
	 * Convert a relative path to a template alias.
	 *
	 * @param string $relative
	 * @return ?string
	 */
	private function _alias_from_relativePath(string $relative): ?string {
		$withoutExtension = substr($relative, 0, -4);
		if ($withoutExtension === '') {
			return null;
		}

		$segments = explode('/', $withoutExtension);
		if (empty($segments)) {
			return null;
		}

		$last = end($segments);
		if (strtolower((string) $last) === 'view') {
			array_pop($segments);
		}

		if (empty($segments)) {
			return null;
		}

		$normalizedSegments = array_map(array($this, '_normalize_alias_segment'), $segments);
		$alias              = implode('.', $normalizedSegments);

		// Validate the generated alias - this should never fail for our internal templates
		if (!$this->_is_valid_template_key($alias)) {
			throw new \UnexpectedValueException(
				"Internal error: Generated invalid template alias '$alias' from file '$relative'. " .
				'This indicates a bug in the alias generation logic.'
			);
		}

		return $alias;
	}

	/**
	 * Normalize a segment of an alias.
	 *
	 * @param string $segment
	 * @return string
	 */
	private function _normalize_alias_segment(string $segment): string {
		$segment = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $segment);
		$segment = str_replace(array('_', '\\'), '-', $segment ?? '');
		$segment = strtolower($segment);
		return $segment;
	}

	/**
	 * Resolve the fully qualified class name for a component companion class.
	 *
	 * For external components (array entries), uses the stored namespace.
	 * For built-in components (string entries), derives namespace from alias.
	 *
	 * @param string $alias Component alias (e.g., 'input.text' or 'my-plugin.color-picker')
	 * @param string $suffix Class suffix (e.g., 'Validator', 'Sanitizer', 'Normalizer', 'Builder')
	 * @return string|null Fully qualified class name, or null if class doesn't exist.
	 */
	private function _resolve_component_class(string $alias, string $suffix): ?string {
		$entry = $this->map[$alias] ?? null;

		// External component: array with 'namespace' key
		if (is_array($entry) && isset($entry['namespace'])) {
			$className = $entry['namespace'] . '\\' . $suffix;
			return class_exists($className) ? $className : null;
		}

		// Built-in component: derive namespace from alias
		$segments = explode('.', $alias);
		if (empty($segments)) {
			return null;
		}

		$namespaceSegments = array('Ran', 'PluginLib', 'Forms', 'Components');
		foreach ($segments as $segment) {
			$namespaceSegments[] = $this->_segment_to_namespace($segment);
		}
		$namespace = implode('\\', $namespaceSegments);

		if (!class_exists($namespace . '\\' . $suffix)) {
			return null;
		}

		return $namespace . '\\' . $suffix;
	}

	private function _segment_to_namespace(string $segment): string {
		$segment = str_replace(array('-', '_'), ' ', $segment);
		$segment = ucwords($segment);
		return str_replace(' ', '', $segment);
	}

	/**
	 * Register an external component with resolved path and namespace.
	 *
	 * @param string $alias Final component alias (e.g., 'my-plugin.color-picker')
	 * @param string $relativePath Relative path from plugin root to component directory
	 * @param ConfigInterface $config Plugin configuration
	 */
	private function _register_external_component(string $alias, string $relativePath, ConfigInterface $config): void {
		$basePath = rtrim($config->get_config()['PATH'] ?? '', '/');
		$fullPath = $basePath . '/' . ltrim($relativePath, '/');

		if (!is_dir($fullPath)) {
			$this->logger?->warning("Component '$alias' directory does not exist: $fullPath");
			return;
		}

		$viewPath = $fullPath . '/View.php';
		if (!is_file($viewPath)) {
			$this->logger?->warning("Component '$alias' missing required View.php in: $fullPath");
			return;
		}

		// Derive namespace from Config + relative path
		$rootNamespace      = $config->get_namespace();
		$pathSegments       = array_filter(explode('/', $relativePath));
		$namespaceSegments  = array_map(array($this, '_segment_to_namespace'), $pathSegments);
		$componentNamespace = $rootNamespace . '\\' . implode('\\', $namespaceSegments);

		// Store as array with path and namespace
		$this->map[$alias] = array(
			'path'      => $viewPath,
			'namespace' => $componentNamespace,
		);
	}

	/**
	 * Normalize any naming convention to kebab-case for alias generation.
	 *
	 * Handles PascalCase, camelCase, snake_case, and kebab-case inputs.
	 *
	 * @param string $input Directory name in any convention
	 * @return string kebab-case string (e.g., 'color-picker')
	 */
	private function _normalize_to_kebab(string $input): string {
		// First handle PascalCase/camelCase: insert hyphen before uppercase letters
		$result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $input);
		// Then replace underscores with hyphens (snake_case)
		$result = preg_replace('/_/', '-', $result ?? $input);
		// Lowercase everything
		return strtolower($result ?? $input);
	}

	/**
	 * Normalize all paths in the map by removing leading slashes.
	 *
	 * @param array<string,string> $map
	 * @return array<string,string>
	 */
	private function _normalize_map(array $map): array {
		$normalized = array();
		foreach ($map as $key => $value) {
			$normalized[$key] = $this->_normalize_path($value);
		}
		return $normalized;
	}

	/**
	 * Normalize a relative path by removing leading slashes.
	 *
	 * @param string $relativePath
	 * @return string
	 */
	private function _normalize_path(string $relativePath): string {
		return ltrim($relativePath, '/');
	}

	/**
	 * Require a template and capture its output.
	 *
	 * @param string $path
	 * @param array<string,mixed> $context
	 * @return array{result:mixed, output:string}
	 */
	private function _require_template(string $path, array $context): array {
		$context = $context;
		ob_start();
		$result = require $path;
		$output = ob_get_clean();
		return array('result' => $result, 'output' => $output ?? '');
	}

	/**
	 * Extract rendering logic for reuse (without caching).
	 *
	 * @param string $name
	 * @param array<string,mixed> $context
	 * @return ComponentRenderResult
	 */
	private function _render_template_directly(string $name, array $context): ComponentRenderResult {
		$payload = $this->render_payload($name, $context);
		return $this->_coerce_to_result($payload, $name, $context);
	}

	/**
	 * Normalize arbitrary template payloads into ComponentRenderResult.
	 *
	 * @param mixed $payload
	 * @param string $alias
	 * @param array<string,mixed> $context
	 */
	private function _coerce_to_result(mixed $payload, string $alias, array $context): ComponentRenderResult {
		if ($payload instanceof ComponentRenderResult) {
			return $payload;
		}

		if (is_array($payload) && isset($payload['markup'])) {
			if (
				array_key_exists('script', $payload)
				|| array_key_exists('style', $payload)
				|| array_key_exists('requires_media', $payload)
				|| array_key_exists('repeatable', $payload)
			) {
				throw new \LogicException(sprintf(
					'Template "%s" must not return legacy asset keys (script/style/requires_media/repeatable). Declare assets via Assets.php / ComponentManifest instead.',
					$alias
				));
			}

			return new ComponentRenderResult(
				(string) ($payload['markup'] ?? ''),
				$payload['context_schema'] ?? array()
			);
		}

		if (is_string($payload)) {
			return new ComponentRenderResult($payload);
		}

		$this->logger?->warning('ComponentLoader: Unexpected template payload', array(
			'template' => $alias,
			'payload'  => gettype($payload),
			'context'  => array_keys($context)
		));

		throw new \LogicException(sprintf('Template "%s" must return a ComponentRenderResult, array with markup, or string.', $alias));
	}

	/**
	 * Clear template cache.
	 *
	 * @param string|null $name Optional template name to clear specific template cache
	 */
	public function clear_template_cache(?string $name = null): void {
		if ($name) {
			$this->cacheService->clear_by_name($name, ComponentCacheService::PREFIX_TEMPLATE);
		} else {
			$this->cacheService->clear_all(ComponentCacheService::PREFIX_TEMPLATE);
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
	 * Validate template key format
	 *
	 * @param string $key The template key to validate
	 * @return bool True if valid, false otherwise
	 */
	private function _is_valid_template_key(string $key): bool {
		return !empty($key) && preg_match('/^[a-zA-Z0-9._-]+$/', $key);
	}
}
