<?php
/**
 * ComponentDispatcher: Executes form component callables and aggregates results.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component;

use Ran\PluginLib\Forms\FormAssets;
use Ran\PluginLib\Forms\Component\ComponentManifest;

final class ComponentDispatcher {
	private ComponentManifest $registry;
	private FormAssets $assets;

	public function __construct(ComponentManifest $registry, ?FormAssets $assets = null) {
		$this->registry = $registry;
		$this->assets   = $assets ?? new FormAssets();
	}

	/**
	 * Render a component by identifier.
	 *
	 * @param string $component
	 * @param array<string, mixed> $context
	 */
	public function render(string $component, array $context = array()): string {
		/** @var ComponentRenderResult $result */
		$result = $this->registry->render($component, $context);
		$this->assets->ingest($result);
		return $result->markup;
	}

	public function assets(): FormAssets {
		return $this->assets;
	}
}
