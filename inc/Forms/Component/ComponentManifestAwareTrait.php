<?php
/**
 * ComponentManifestAwareTrait: shared component registration helpers for settings classes.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\Forms\Component\ComponentManifest;

trait ComponentManifestAwareTrait {
	public function register_component(string $alias, callable $factory): void {
		$this->component_manifest()->register($alias, $factory);
	}

	/**
	 * @param array<string, callable> $factories
	 */
	public function register_components(array $factories): void {
		foreach ($factories as $alias => $factory) {
			if (!is_string($alias) || $alias === '') {
				throw new \InvalidArgumentException('Component alias must be a non-empty string.');
			}
			if (!is_callable($factory)) {
				throw new \InvalidArgumentException(sprintf('Component factory for "%s" must be callable.', $alias));
			}
			$this->component_manifest()->register($alias, $factory);
		}
	}

	protected function component_manifest(): ComponentManifest {
		return $this->components;
	}
}
