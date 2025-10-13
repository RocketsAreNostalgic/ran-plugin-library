<?php
/**
 * ComponentRenderingTrait: shared component rendering helpers for settings contexts.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\FormServiceSession;

trait ComponentRenderingTrait {
	protected FormServiceSession $form_session;

	/**
	 * Render a component-backed field.
	 *
	 * @param string               $component
	 * @param string               $field_id
	 * @param string               $label
	 * @param array<string,mixed>  $context
	 * @param array<string,mixed>  $values
	 */
	protected function _render_field_component(string $component, string $field_id, string $label, array $context, array $values): string {
		$context['_field_id'] = $field_id;
		$context['_label']    = $label;
		$context['_values']   = $values;
		$context              = $this->_augment_component_context($context, $values);

		return $this->form_session->render_component($component, $context);
	}

	/**
	 * @param array<string,mixed> $context
	 * @param array<string,mixed> $values
	 * @return array<string,mixed>
	 */
	protected function _augment_component_context(array $context, array $values): array {
		return $context;
	}

	/**
	 * Enqueue registered assets for the current context.
	 */
	protected function _enqueue_component_assets(): void {
		$assets = $this->form_session->assets();
		if (!$assets->has_assets()) {
			return;
		}

		foreach ($assets->styles() as $definition) {
			$src     = $definition->src;
			$deps    = $definition->deps;
			$version = $definition->version;
			wp_register_style($definition->handle, is_string($src) || $src === false ? $src : '', $deps, $version ?: false);
			if ($definition->hook === null) {
				wp_enqueue_style($definition->handle);
			}
		}

		foreach ($assets->scripts() as $definition) {
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

		if ($assets->requires_media()) {
			wp_enqueue_media();
		}
	}
}
