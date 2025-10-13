<?php
/**
 * Static factory for fluent field definitions.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentCatalog;

final class Builders {
	/** @var array<string,callable(string,string):BuilderDefinitionInterface>|null */
	private static ?array $factories = null;

	private function __construct() {
	}

	public static function make(string $alias, string $id, string $label): BuilderDefinitionInterface {
		$factories = self::factories();
		if (!isset($factories[$alias])) {
			throw new \InvalidArgumentException(sprintf('Unknown builder alias "%s".', $alias));
		}

		return $factories[$alias]($id, $label);
	}

	public static function __callStatic(string $name, array $arguments): BuilderDefinitionInterface {
		if (count($arguments) !== 2) {
			throw new \InvalidArgumentException(sprintf('Builder "%s" expects exactly two arguments: id and label.', $name));
		}

		[$id, $label] = $arguments;
		if (!is_string($id) || !is_string($label)) {
			throw new \InvalidArgumentException(sprintf('Builder "%s" requires string id and label.', $name));
		}

		$factories = self::factories();
		$alias     = self::select_alias($name, $factories);
		if (!isset($factories[$alias])) {
			throw new \BadMethodCallException(sprintf('Unknown builder method "%s".', $name));
		}

		return $factories[$alias]($id, $label);
	}

	private static function factories(): array {
		if (self::$factories !== null) {
			return self::$factories;
		}

		$loader          = self::ComponentLoader();
		$catalog         = new ComponentCatalog($loader);
		self::$factories = $catalog->builder_factories();

		return self::$factories;
	}

	/**
	 * @param array<string,callable(string,string):BuilderDefinitionInterface> $factories
	 */
	private static function select_alias(string $method, array $factories): string {
		$normalized = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $method));
		if (isset($factories[$normalized])) {
			return $normalized;
		}

		$prefixed = 'fields.' . $normalized;
		if (isset($factories[$prefixed])) {
			return $prefixed;
		}

		return $normalized;
	}
}
