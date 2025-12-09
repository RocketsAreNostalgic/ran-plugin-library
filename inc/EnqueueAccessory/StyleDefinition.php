<?php
/**
 * Style asset definition value object.
 *
 * @package Ran\PluginLib\EnqueueAccessory
 */

declare(strict_types=1);

namespace Ran\PluginLib\EnqueueAccessory;

use Ran\PluginLib\Util\Validate;

/**
 * Immutable definition representing a stylesheet to enqueue.
 */
final readonly class StyleDefinition extends AssetEnqueueDefinition {
	/**
	 * @param string $handle
	 * @param string|array|false $src
	 * @param array<int, string> $deps
	 * @param string|false|null $version
	 * @param callable|null $condition
	 * @param array<string, mixed> $attributes
	 * @param array<string, mixed> $data
	 * @param array<int, array<string, mixed>> $inline
	 * @param string|null $hook
	 * @param int $priority
	 * @param bool $replace
	 * @param bool $cache_bust
	 * @param string $media
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
        public string $media
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

		if (!Validate::string()->min_length(1, $this->media)) {
			throw new \InvalidArgumentException("Asset '{$this->handle}' media must be a non-empty string.");
		}
	}

	/**
	 * Build a style definition from array input.
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

		$media = (string) ($definition['media'] ?? 'all');

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
			$media
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function get_specific_fields(): array {
		return array(
		    'media' => $this->media,
		);
	}
}
