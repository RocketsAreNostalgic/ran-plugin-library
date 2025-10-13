<?php
/**
 * Script asset definition value object.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Validate;

/**
 * Immutable definition representing a script to enqueue.
 */
final readonly class ScriptDefinition extends AssetEnqueueDefinition {
	/**
	 * @param string $handle
	 * @param string|array|false $src
	 * @param array<int, string> $deps
	 * @param string|false|null $version
	 * @param callable|null $condition
	 * @param array<string, mixed> $attributes
	 * @param array<string, mixed> $data
	 * @param string|null $hook
	 * @param int $priority
	 * @param bool $replace
	 * @param bool $cache_bust
	 * @param bool $in_footer
	 * @param array<string, mixed> $localize
	 */
	private function __construct(
        string $handle,
        string|array|false $src,
        array $deps,
        string|false|null $version,
        mixed $condition,
        array $attributes,
        array $data,
        array $inline,
        ?string $hook,
        int $priority,
        bool $replace,
        bool $cache_bust,
        public bool $in_footer,
        public array $localize
    ) {
		parent::__construct(
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
			$cache_bust
		);

		$localizeValidator = Validate::collection()->shape(array(
		    'object_name' => Validate::basic()->is_string(),
		    'data'        => Validate::basic()->is_array(),
		));

		if (!empty($this->localize) && !$localizeValidator($this->localize)) {
			throw new \InvalidArgumentException("Asset '{$this->handle}' localize must include 'object_name' (string) and 'data' (array).");
		}
	}

	/**
	 * Build a script definition from array input.
	 *
	 * @param array<string, mixed> $definition
	 * @return self
	 */
	public static function from_array(array $definition): static {
		[
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
		] = self::parse_base_fields($definition);

		$in_footer = (bool) ($definition['in_footer'] ?? false);

		$localize = $definition['localize'] ?? array();
		if ($localize !== array() && !is_array($localize)) {
			throw new \InvalidArgumentException("Asset '{$handle}' localize configuration must be an array.");
		}

		return new self(
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
			$in_footer,
			$localize
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function get_specific_fields(): array {
		return array(
		    'in_footer' => $this->in_footer,
		    'localize'  => $this->localize,
		);
	}
}
