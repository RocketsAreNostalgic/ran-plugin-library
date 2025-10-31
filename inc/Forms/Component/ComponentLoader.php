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
use FilesystemIterator;

class ComponentLoader {
	use WPWrappersTrait;

	/** @var array<string,string> */
	private array $map = array();
	private string $baseDir;

	/** @var int Cache TTL in seconds */
	private int $cacheTTL;

	/** @var bool Whether caching is enabled */
	private bool $cachingEnabled;

	/** @var Logger|null Logger instance for cache operations */
	private ?Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param string $baseDir
	 * @param array<string,string> $map
	 * @param Logger|null $logger Optional logger for cache operations
	 */
	public function __construct(string $baseDir, array $map = array(), ?Logger $logger = null) {
		$this->baseDir = rtrim($baseDir, '/');

		$discovered = $this->_default_map();
		$legacy     = $this->_legacy_aliases();
		$overrides  = $this->_normalize_map($map);

		$this->map            = array_merge($discovered, $legacy, $overrides);
		$this->cachingEnabled = $this->_should_use_cache();
		$this->cacheTTL       = $this->_get_cache_ttl();
		$this->logger         = $logger;
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
		if (!$this->cachingEnabled) {
			return $this->_render_template_directly($name, $context);
		}

		// Generate optimized cache key with collision avoidance
		$cache_key     = $this->_generate_template_cache_key($name, $context);
		$cached_output = $this->_do_get_transient($cache_key);

		if ($cached_output !== false) {
			$this->logger?->debug('ComponentLoader: Cache HIT for template', array(
				'template'  => $name,
				'cache_key' => $cache_key
			));
			return $this->_coerce_to_result($cached_output, $name, $context);
		}

		// Cache miss - render and cache
		$this->logger?->debug('ComponentLoader: Cache MISS for template', array(
			'template'  => $name,
			'cache_key' => $cache_key
		));

		$output = $this->_render_template_directly($name, $context);
		$this->_do_set_transient($cache_key, $output, $this->cacheTTL);

		// Track transient for cleanup
		$this->_track_template_transient($cache_key);

		$this->logger?->info('ComponentLoader: Cached template', array(
			'template'  => $name,
			'cache_key' => $cache_key,
			'ttl'       => $this->cacheTTL
		));

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
		$path = $this->_resolve_path($name);
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
	 * @return array<string,string> Alias => relative template path.
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
	 * Resolve the path to a template by its alias.
	 *
	 * @param string $name
	 * @return string
	 */
	private function _resolve_path(string $name): string {
		if (!isset($this->map[$name])) {
			$this->logger?->warning('ComponentLoader: No template mapping registered', array(
				'template' => $name
			));
			throw new \LogicException(sprintf('No template mapping registered for "%s".', $name));
		}

		return $this->baseDir . '/' . $this->map[$name];
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

	private function _resolve_component_class(string $alias, string $suffix): ?string {
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
	 * Provide backward-compatible aliases for legacy template names.
	 *
	 * @return array<string,string>
	 */
	private function _legacy_aliases(): array {
		$aliases = array(
			'root-wrapper'     => 'layout/container/root-wrapper.php',
			'section-wrapper'  => 'layout/zone/section-wrapper.php',
			'group-wrapper'    => 'layout/zone/group-wrapper.php',
			'field-wrapper'    => 'layout/field/field-wrapper.php',
			'fieldset-wrapper' => 'layout/field/fieldset-wrapper.php',
		);

		$resolved = array();
		foreach ($aliases as $alias => $relativePath) {
			if (!$this->_is_valid_template_key($alias)) {
				continue;
			}

			if ($this->_template_exists($relativePath)) {
				$resolved[$alias] = $relativePath;
			}
		}

		return $resolved;
	}

	private function _template_exists(string $relativePath): bool {
		$path = $this->baseDir . '/' . $this->_normalize_path($relativePath);
		return is_file($path);
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
			return new ComponentRenderResult(
				(string) ($payload['markup'] ?? ''),
				$payload['script'] ?? null,
				$payload['style']  ?? null,
				(bool) ($payload['requires_media'] ?? false),
				(bool) ($payload['repeatable'] ?? false),
				$payload['context_schema'] ?? array(),
				(bool) ($payload['submits_data'] ?? false),
				(string) ($payload['component_type'] ?? 'form_field')
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
	 * Get cache TTL in seconds.
	 *
	 * @return int
	 */
	private function _get_cache_ttl(): int {
		// Allow override via constant
		if (\defined('KEPLER_COMPONENT_CACHE_TTL')) {
			return \max(300, (int) \constant('KEPLER_COMPONENT_CACHE_TTL')); // Minimum 5 minutes
		}

		// Default to 1 hour
		return 3600;
	}

	/**
	 * Generate optimized cache key with collision avoidance.
	 *
	 * @param string $name Template name
	 * @param array<string,mixed> $context Template context
	 * @return string Optimized cache key
	 */
	private function _generate_template_cache_key(string $name, array $context): string {
		// Filter out non-serializable data (Closures, resources, etc.)
		$serializable_context = $this->_filter_serializable_context($context);
		$context_hash         = empty($serializable_context) ? 'empty' : hash('crc32b', serialize($serializable_context));
		$sanitized_name       = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);

		// Prefix with kepler_tpl_ to avoid collisions with other plugins
		return "kepler_tpl_{$sanitized_name}_{$context_hash}";
	}

	/**
	 * Filter context array to remove non-serializable values.
	 *
	 * @param array<string,mixed> $context Template context
	 * @return array<string,mixed> Filtered context with only serializable values
	 */
	private function _filter_serializable_context(array $context): array {
		$filtered = array();
		foreach ($context as $key => $value) {
			// Skip Closures and other callables
			if (is_callable($value) || ($value instanceof \Closure)) {
				continue;
			}

			// Skip resources
			if (is_resource($value)) {
				continue;
			}

			// Recursively filter nested arrays
			if (is_array($value)) {
				$filtered[$key] = $this->_filter_serializable_context($value);
			} else {
				$filtered[$key] = $value;
			}
		}
		return $filtered;
	}

	/**
	 * Track template transient for cleanup.
	 *
	 * @param string $transient_key
	 */
	private function _track_template_transient(string $transient_key): void {
		$active_transients = $this->_do_get_option('template_cache_transients', array());

		// Ensure we have an array (handle case where _do_get_option returns null)
		if (!is_array($active_transients)) {
			$active_transients = array();
		}

		if (!in_array($transient_key, $active_transients, true)) {
			$active_transients[] = $transient_key;
			$this->_do_update_option('template_cache_transients', $active_transients, false);
		}
	}

	/**
	 * Clear template cache.
	 *
	 * @param string|null $name Optional template name to clear specific template cache
	 */
	public function clear_template_cache(?string $name = null): void {
		if ($name) {
			// Clear specific template (all contexts)
			$this->logger?->debug('ComponentLoader: CLEARING cache for template', array('template' => $name));
			$this->_clear_template_transients_by_name($name);
		} else {
			// Clear all template caches
			$this->logger?->debug('ComponentLoader: CLEARING all template caches');
			$this->_clear_all_template_transients();
		}
	}

	/**
	 * Clear template transients by name pattern.
	 *
	 * @param string $name
	 */
	private function _clear_template_transients_by_name(string $name): void {
		// Clear all transients that match this template name
		$active_transients = $this->_do_get_option('template_cache_transients', array());
		$sanitized_name    = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
		$prefix            = "kepler_tpl_{$sanitized_name}_";
		$cleared_count     = 0;

		foreach ($active_transients as $key => $transient_key) {
			if (strpos($transient_key, $prefix) === 0) {
				$this->_do_delete_transient($transient_key);
				unset($active_transients[$key]);
				$cleared_count++;
			}
		}

		$this->_do_update_option('template_cache_transients', array_values($active_transients), false);

		$this->logger?->info('ComponentLoader: Cleared cache entries for template', array(
			'template'      => $name,
			'cleared_count' => $cleared_count
		));
	}

	/**
	 * Clear all template transients.
	 */
	private function _clear_all_template_transients(): void {
		$active_transients = $this->_do_get_option('template_cache_transients', array());
		$cleared_count     = 0;

		foreach ($active_transients as $transient_key) {
			$this->_do_delete_transient($transient_key);
			$cleared_count++;
		}

		$this->_do_delete_option('template_cache_transients');

		$this->logger?->info('ComponentLoader: Cleared all template cache entries', array(
			'cleared_count' => $cleared_count
		));
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
