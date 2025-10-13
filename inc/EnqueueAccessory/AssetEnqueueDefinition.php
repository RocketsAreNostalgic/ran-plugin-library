<?php
/**
 * Shared asset enqueue definition value object.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Validate;

/**
 * Base immutable value object for enqueue asset definitions.
 */
abstract readonly class AssetEnqueueDefinition {
	/**
	 * @param string $handle Unique asset handle.
	 * @param string|array|false $src Asset source URL, environment map, or false for register-only assets.
	 * @param array<int, string> $deps Dependency handles.
	 * @param string|false|null $version Asset version.
	 * @param callable|null $condition Optional condition callback.
	 * @param array<string, mixed> $attributes HTML attributes to apply.
	 * @param array<string, mixed> $data Data passed to WordPress helpers.
	 * @param string|null $hook Deferred hook name.
	 * @param int $priority Hook priority.
	 * @param bool $replace Whether to replace an existing asset with the same handle.
	 * @param bool $cache_bust Whether cache busting should be applied.
	 */
	protected function __construct(
        public string $handle,
        public string|array|false $src,
        public array $deps,
        public string|false|null $version,
        /** @var callable|null */
        public mixed $condition,
		/** @var array<string, mixed> */
        public array $attributes,
		/** @var array<string, mixed> */
        public array $data,
        /** @var array<int, array<string, mixed>> */
        public array $inline,
        public ?string $hook,
        public int $priority,
        public bool $replace,
        public bool $cache_bust
    ) {
		if (!Validate::string()->min_length(1, $this->handle)) {
			throw new \InvalidArgumentException('Asset handle must be a non-empty string.');
		}

		$srcValidator = Validate::compose()->union(
			static fn(mixed $value): bool => $value === false,
			Validate::basic()->is_string(),
			Validate::collection()->list_of(Validate::basic()->is_string())
		);
		if (!$srcValidator($this->src)) {
			throw new \InvalidArgumentException("Asset '{$this->handle}' must define 'src' as string, array of strings, or false.");
		}

		if (!Validate::collection()->list_of(Validate::basic()->is_string())($this->deps)) {
			throw new \InvalidArgumentException("Asset '{$this->handle}' dependencies must be an array of strings.");
		}

		if (!Validate::compose()->nullable(Validate::basic()->is_callable())($this->condition)) {
			throw new \InvalidArgumentException("Asset '{$this->handle}' condition must be a callable or null.");
		}

		if (!Validate::collection()->list_of(Validate::basic()->is_array())($this->inline)) {
			throw new \InvalidArgumentException("Asset '{$this->handle}' inline entries must be arrays.");
		}
	}

	/**
	 * Create a definition from a raw array.
	 *
	 * @param array<string, mixed> $definition
	 * @return static
	 */
	abstract public static function from_array(array $definition): static;

	/**
	 * Convert to the array structure expected by the enqueue traits.
	 *
	 * @return array<string, mixed>
	 */
	final public function to_array(): array {
		return array_merge($this->export_base_fields(), $this->get_specific_fields());
	}

	/**
	 * Export base fields for merging with subclass-specific data.
	 *
	 * @return array<string, mixed>
	 */
	final protected function export_base_fields(): array {
		return array(
		    'handle'     => $this->handle,
		    'src'        => $this->src,
		    'deps'       => $this->deps,
		    'version'    => $this->version,
		    'condition'  => $this->condition,
		    'attributes' => $this->attributes,
		    'data'       => $this->data,
		    'inline'     => $this->inline,
		    'hook'       => $this->hook,
		    'priority'   => $this->priority,
		    'replace'    => $this->replace,
		    'cache_bust' => $this->cache_bust,
		);
	}

	/**
	 * Fields specific to the concrete asset definition.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_specific_fields(): array {
		return array();
	}

	/**
	 * Normalize base fields from the input array.
	 * @param array<string, mixed> $definition
	 * @return array{string, string|array|false, array<int, string>, string|false|null, callable|null, array<string, mixed>, array<string, mixed>, array<int, array<string, mixed>>, string|null, int, bool, bool}
	 */
	final protected static function parse_base_fields(array $definition): array {
		$handle = $definition['handle'] ?? null;
		if (!Validate::string()->min_length(1, $handle)) {
			throw new \InvalidArgumentException("Asset definition must include a non-empty 'handle'.");
		}
		$handle = (string) $handle;

		if (!array_key_exists('src', $definition)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must include a 'src' key (string, array, or false).");
		}

		$src          = $definition['src'];
		$srcValidator = Validate::compose()->union(
			static fn(mixed $value): bool => $value === false,
			Validate::basic()->is_string(),
			Validate::collection()->list_of(Validate::basic()->is_string())
		);
		if (!$srcValidator($src)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must provide 'src' as string, array of strings, or false.");
		}
		if (is_array($src)) {
			$src = array_map(static fn(string $value): string => $value, $src);
		}

		$deps = $definition['deps'] ?? array();
		if (!Validate::collection()->list_of(Validate::basic()->is_string())($deps)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must provide 'deps' as an array of strings.");
		}
		/** @var array<int, string> $deps */
		$deps = array_values($deps);

		$version          = $definition['version'] ?? false;
		$versionValidator = Validate::compose()->union(
			Validate::basic()->is_string(),
			static fn(mixed $value): bool => $value === false,
			static fn(mixed $value): bool => $value === null
		);
		if (!$versionValidator($version)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must provide 'version' as string, false, or null.");
		}
		if (is_string($version)) {
			$version = (string) $version;
		}
		$condition = $definition['condition'] ?? null;
		if (!Validate::compose()->nullable(Validate::basic()->is_callable())($condition)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must provide 'condition' as a callable or null.");
		}

		$attributes = $definition['attributes'] ?? array();
		if (!Validate::basic()->is_array($attributes)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must provide 'attributes' as an array.");
		}

		$data = $definition['data'] ?? array();
		if (!Validate::basic()->is_array($data)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must provide 'data' as an array.");
		}

		$inline = self::normalize_inline_definitions($definition['inline'] ?? array(), $handle);

		$hook = $definition['hook'] ?? null;
		if (!Validate::compose()->nullable(Validate::basic()->is_string())($hook)) {
			throw new \InvalidArgumentException("Asset definition for '{$handle}' must provide 'hook' as a string or null.");
		}
		if (is_string($hook)) {
			$hook = (string) $hook;
		}

		$priority_raw = $definition['priority'] ?? 10;
		if (!is_int($priority_raw)) {
			$priority_raw = (int) $priority_raw;
		}
		$priority   = max(0, $priority_raw);
		$replace    = (bool) ($definition['replace'] ?? false);
		$cache_bust = (bool) ($definition['cache_bust'] ?? false);

		return array(
		    $handle,
		    $src,
		    $deps,
		    $version,
		    $condition,
		    $attributes,
		    $data,
		    $inline,
		    $hook,
		    $priority,
		    $replace,
		    $cache_bust,
		);
	}

	/**
	 * Normalize inline definitions to a consistent array-of-arrays structure.
	 *
	 * @param mixed $inline
	 * @param string $handle
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_inline_definitions(mixed $inline, string $handle): array {
		if ($inline === null || $inline === array()) {
			return array();
		}

		// Allow single string/callable inline definitions for backward compatibility.
		if (!is_array($inline)) {
			return array(array('content' => $inline));
		}

		$normalized = array();

		if (!array_is_list($inline)) {
			$inline = array($inline);
		}

		foreach ($inline as $entry) {
			if (!is_array($entry)) {
				if (is_string($entry) || is_callable($entry)) {
					$normalized[] = array('content' => $entry);
					continue;
				}

				throw new \InvalidArgumentException("Inline definition for asset '{$handle}' must be an array, string, or callable.");
			}

			$normalized[] = $entry;
		}

		return array_values($normalized);
	}
}
