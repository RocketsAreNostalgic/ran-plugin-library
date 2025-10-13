<?php
/**
 * ComponentLoader: lightweight PHP template resolver for shared form components.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use SplFileInfo;
use FilesystemIterator;
use UnexpectedValueException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class ComponentLoader {
	/** @var array<string,string> */
	private array $map = array();
	private string $baseDir;

	/**
	 * Constructor.
	 *
	 * @param string $baseDir
	 * @param array<string,string> $map
	 */
	public function __construct(string $baseDir, array $map = array()) {
		$this->baseDir = rtrim($baseDir, '/');
		$this->map     = array_merge($this->_default_map(), $this->_normalize_map($map));
	}

	/**
	 * Register a template alias to its relative path.
	 * Useful for adding custom templates or overriding default ones.
	 *
	 * @param string $name
	 * @param string $relativePath
	 */
	public function register(string $name, string $relativePath): self {
		$this->map[$name] = $this->_normalize_path($relativePath);
		return $this;
	}

	/**
	 * Render the named template with the provided context.
	 *
	 * @param string $name
	 * @param array<string,mixed> $context
	 */
	public function render(string $name, array $context = array()): string {
		$path = $this->_resolve_path($name);
		if (!\file_exists($path)) {
			throw new \LogicException(sprintf('Template "%s" not found at "%s".', $name, $path));
		}

		$execution = $this->_require_template($path, $context);
		if ($execution['output'] !== '') {
			throw new \LogicException(sprintf('Template "%s" produced direct output; templates must return strings.', $name));
		}
		if (!is_string($execution['result'])) {
			throw new \LogicException(sprintf('Template "%s" must return a string, got %s.', $name, gettype($execution['result'])));
		}
		return $execution['result'];
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
			throw new \LogicException(sprintf('Template "%s" not found at "%s".', $name, $path));
		}

		$execution = $this->_require_template($path, $context);
		if ($execution['output'] !== '') {
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
		if ($last === 'view') {
			array_pop($segments);
		}

		if (empty($segments)) {
			return null;
		}

		$normalizedSegments = array_map(array($this, '_normalize_alias_segment'), $segments);

		return implode('.', $normalizedSegments);
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
}
