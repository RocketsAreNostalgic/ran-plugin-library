<?php
/**
 * FormServiceSession: per-render context for component dispatching and asset collection.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Forms\Component\ComponentDispatcher;

class FormServiceSession {
	private ComponentDispatcher $dispatcher;
	private FormAssets $assets;

	public function __construct(ComponentDispatcher $dispatcher, FormAssets $assets) {
		$this->dispatcher = $dispatcher;
		$this->assets     = $assets;
	}

	public function render_component(string $component, array $context = array()): string {
		return $this->dispatcher->render($component, $context);
	}

	public function dispatcher(): ComponentDispatcher {
		return $this->dispatcher;
	}

	public function assets(): FormAssets {
		return $this->assets;
	}
}
