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
use FilesystemIterator;

class ComponentLoader {
	use WPWrappersTrait;

	/** @var array<string,string> */
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
	 * Resolve the class name for a sanitizer by its alias.
	 */
	public function resolve_sanitizer_class(string $alias): ?string {
		return $this->_resolve_component_class($alias, 'Sanitizer');
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
				(string) ($payload['component_type'] ?? 'input')
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
