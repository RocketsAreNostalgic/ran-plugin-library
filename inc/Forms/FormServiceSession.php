<?php
/**
 * FormServiceSession: per-render context for component dispatching and asset collection.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Forms\Component\ComponentManifest;

class FormServiceSession {
	private ComponentManifest $manifest;
	private FormAssets $assets;

	public function __construct(ComponentManifest $manifest, FormAssets $assets) {
		$this->manifest = $manifest;
		$this->assets   = $assets;
	}

	public function render_component(string $component, array $context = array()): string {
		$result = $this->manifest->render($component, $context);
		$this->assets->ingest($result);
		return $result->markup;
	}

	/**
	 * Render a component-backed field with field-specific context.
	 *
	 * @param string               $component
	 * @param string               $field_id
	 * @param string               $label
	 * @param array<string,mixed>  $context
	 * @param array<string,mixed>  $values
	 */
	public function render_field_component(string $component, string $field_id, string $label, array $context, array $values): string {
		$context['_field_id'] = $field_id;
		$context['_label']    = $label;
		$context['_values']   = $values;

		return $this->render_component($component, $context);
	}

	/**
	 * Enqueue registered assets for the current context.
	 */
	public function enqueue_assets(): void {
		if (!$this->assets->has_assets()) {
			return;
		}

		foreach ($this->assets->styles() as $definition) {
			$src     = $definition->src;
			$deps    = $definition->deps;
			$version = $definition->version;
			wp_register_style($definition->handle, is_string($src) || $src === false ? $src : '', $deps, $version ?: false);
			if ($definition->hook === null) {
				wp_enqueue_style($definition->handle);
			}
		}

		foreach ($this->assets->scripts() as $definition) {
			$src      = $definition->src;
			$deps     = $definition->deps;
			$version  = $definition->version;
			$inFooter = $definition->data['in_footer'] ?? true;
			wp_register_script($definition->handle, is_string($src) || $src === false ? $src : '', $deps, $version ?: false, (bool) $inFooter);
			if (!empty($definition->data['localize']) && is_array($definition->data['localize'])) {
				foreach ($definition->data['localize'] as $objectName => $l10n) {
					wp_localize_script($definition->handle, (string) $objectName, $l10n);
				}
			}
			if ($definition->hook === null) {
				wp_enqueue_script($definition->handle);
			}
		}

		if ($this->assets->requires_media()) {
			wp_enqueue_media();
		}
	}

	public function manifest(): ComponentManifest {
		return $this->manifest;
	}

	public function assets(): FormAssets {
		return $this->assets;
	}
}
