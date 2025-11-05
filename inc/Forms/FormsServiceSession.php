<?php
/**
 * FormsServiceSession: per-render context for component dispatching and asset collection.
 *
 * @package Ran\PluginLib\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Util\WPWrappersTrait;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Component\ComponentManifest;

/**
 * FormsServiceSession: per-render context for component dispatching and asset collection.
 */
class FormsServiceSession {
	use WPWrappersTrait;

	private ComponentManifest $manifest;
	private FormsAssets $assets;
	private FormsTemplateOverrideResolver $template_resolver;
	/** @var array<string,callable> */
	private array $root_template_callbacks = array();

	public function __construct(ComponentManifest $manifest, FormsAssets $assets, Logger $logger, array $form_defaults = array()) {
		$this->manifest          = $manifest;
		$this->assets            = $assets;
		$this->template_resolver = new FormsTemplateOverrideResolver($logger);

		// Set form-wide defaults if provided
		if (!empty($form_defaults)) {
			$this->template_resolver->set_form_defaults($form_defaults);
		}
	}

	/**
	 * Render an element using the complete pipeline: template resolution → component rendering → asset collection
	 *
	 * @internal
	 *
	 * @param string $element_type The template type (e.g., 'field-wrapper', 'section-wrapper')
	 * @param array<string,mixed> $element_config Element configuration
	 * @param array<string,mixed> $context Resolution context containing field_id, section_id, etc.
	 * @return string Rendered HTML markup
	 */
	public function render_element(string $element_type, array $element_config = array(), array $context = array()): string {
		if ($element_type === 'root-wrapper') {
			$root_id = isset($context['root_id']) ? (string) $context['root_id'] : ($context['page_slug'] ?? ($context['id_slug'] ?? ''));
			if ($root_id !== '' && isset($this->root_template_callbacks[$root_id])) {
				$callback = $this->root_template_callbacks[$root_id];
				return $this->execute_root_callback($callback, array_merge($element_config, $context));
			}
		}

		// Step 1: Resolve template key via FormsTemplateOverrideResolver
		$template_key = $this->template_resolver->resolve_template($element_type, $context);
		if (isset($element_config['root_override']) && is_string($element_config['root_override']) && $element_config['root_override'] !== '') {
			$template_key = $element_config['root_override'];
		}

		// Step 2: Pass resolved template key to ComponentManifest for rendering
		$render_context = array_merge($element_config, $context);
		$result         = $this->manifest->render($template_key, $render_context);

		// Step 3: Extract and store assets from ComponentRenderResult
		$this->assets->ingest($result);

		return $result->markup;
	}

	/**
	 * Execute a root-level callback while capturing its output.
	 *
	 * @param callable $callback
	 * @param array<string,mixed> $payload
	 * @return string
	 */
	private function execute_root_callback(callable $callback, array $payload): string {
		ob_start();
		$callback($payload);
		$output = (string) ob_get_clean();
		return $output;
	}

	/**
	 * Render a component-backed field with field-specific context.
	 *
	 * @internal
	 *
	 * @param string               $component
	 * @param array<string,mixed>  $context
	 * @return string Rendered HTML markup
	 */
	public function render_component(string $component, array $context = array()): string {
		$result = $this->manifest->render($component, $context);
		$this->assets->ingest($result);
		return $result->markup;
	}

	/**
	 * Render a component-backed field with field-specific context.
	 *
	 * @internal
	 *
	 * @param string               $component
	 * @param string               $field_id
	 * @param string               $label
	 * @param array<string,mixed>  $context
	 * @param array<string,mixed>  $values
	 * @return string Rendered HTML markup
	 */
	public function render_field_component(string $component, string $field_id, string $label, array $context, array $values): string {
		$context['_field_id'] = $field_id;
		$context['_label']    = $label;
		$context['_values']   = $values;

		return $this->render_component($component, $context);
	}

	/**
	 * Enqueue registered assets for the current context.
	 *
	 * @internal
	 */
	public function enqueue_assets(): void {
		if (!$this->assets->has_assets()) {
			return;
		}

		foreach ($this->assets->styles() as $definition) {
			$src     = $definition->src;
			$deps    = $definition->deps;
			$version = $definition->version;
			$this->_do_wp_register_style($definition->handle, is_string($src) || $src === false ? $src : '', $deps, $version ?: false);
			if ($definition->hook === null) {
				$this->_do_wp_enqueue_style($definition->handle);
			}
		}

		foreach ($this->assets->scripts() as $definition) {
			$src      = $definition->src;
			$deps     = $definition->deps;
			$version  = $definition->version;
			$inFooter = $definition->data['in_footer'] ?? true;
			$this->_do_wp_register_script($definition->handle, is_string($src) || $src === false ? $src : '', $deps, $version ?: false, (bool) $inFooter);
			if (!empty($definition->data['localize']) && is_array($definition->data['localize'])) {
				foreach ($definition->data['localize'] as $objectName => $l10n) {
					$this->_do_wp_localize_script($definition->handle, (string) $objectName, $l10n);
				}
			}
			if ($definition->hook === null) {
				$this->_do_wp_enqueue_script($definition->handle);
			}
		}

		if ($this->assets->requires_media()) {
			$this->_do_wp_enqueue_media();
		}
	}

	/**
	 * Get the ComponentManifest instance for direct access
	 *
	 * @internal
	 *
	 * @return ComponentManifest
	 */
	public function manifest(): ComponentManifest {
		return $this->manifest;
	}

	/**
	 * Get the FormsAssets instance for direct access
	 *
	 * @internal
	 *
	 * @return FormsAssets
	 */
	public function assets(): FormsAssets {
		return $this->assets;
	}

	/**
	 * Get the FormsTemplateOverrideResolver instance for direct access
	 *
	 * @internal
	 *
	 * @return FormsTemplateOverrideResolver
	 */
	public function template_resolver(): FormsTemplateOverrideResolver {
		return $this->template_resolver;
	}

	// Two-tier template override configuration methods

	/**
	 * Set form-wide defaults (Tier 1)
	 *
	 * @internal
	 *
	 * @param array<string, string> $defaults Template type => template key mappings
	 * @return void
	 */
	public function set_form_defaults(array $defaults): void {
		$this->template_resolver->set_form_defaults($defaults);
	}

	/**
	 * Register a root-level template callback override.
	 *
	 * @param string   $root_id
	 * @param callable $callback
	 * @return void
	 */
	public function set_root_template_callback(string $root_id, ?callable $callback): void {
		if ($callback === null) {
			unset($this->root_template_callbacks[$root_id]);
			return;
		}

		$this->root_template_callbacks[$root_id] = $callback;
	}

	/**
	 * Retrieve root-level callback if registered.
	 *
	 * @param string $root_id
	 * @return callable|null
	 */
	public function get_root_template_callback(string $root_id): ?callable {
		return $this->root_template_callbacks[$root_id] ?? null;
	}

	/**
	 * Clear all overrides for a root element.
	 *
	 * @param string $root_id
	 * @return void
	 */
	public function clear_root_template_override(string $root_id): void {
		unset($this->root_template_callbacks[$root_id]);
		$this->template_resolver->set_root_template_overrides($root_id, array());
	}

	/**
	 * Override specific form-wide defaults (Tier 1)
	 *
	 * @internal
	 *
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function override_form_defaults(array $overrides): void {
		$this->template_resolver->override_form_defaults($overrides);
	}

	/**
	 * Get form-wide defaults (Tier 1)
	 *
	 * @internal
	 *
	 * @return array<string, string> Template type => template key mappings
	 */
	public function get_form_defaults(): array {
		return $this->template_resolver->get_form_defaults();
	}

	/**
	 * Set submit controls wrapper override for a specific zone on a root element.
	 *
	 * @internal
	 *
	 * @param string $root_id The page/root identifier.
	 * @param string $zone_id The submit controls zone identifier.
	 * @param string $template_key The template key to use.
	 * @return void
	 */
	public function set_submit_controls_override(string $root_id, string $zone_id, string $template_key): void {
		$this->template_resolver->set_submit_controls_override($root_id, $zone_id, $template_key);
	}

	/**
	 * Set individual element override (Tier 2)
	 *
	 * @internal Use fluent methods eg ->template('my.registered-override')->set_field_template_overrides()
	 *
	 * @param string $element_type The element type (field, section, group, root)
	 * @param string $element_id The element ID
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function set_individual_element_override(string $element_type, string $element_id, array $overrides): void {
		switch ($element_type) {
			case 'field':
				$this->template_resolver->set_field_template_overrides($element_id, $overrides);
				break;
			case 'section':
				$this->template_resolver->set_section_template_overrides($element_id, $overrides);
				break;
			case 'group':
				$this->template_resolver->set_group_template_overrides($element_id, $overrides);
				break;
			case 'root':
				$this->template_resolver->set_root_template_overrides($element_id, $overrides);
				break;
			default:
				throw new \InvalidArgumentException("Invalid element type: '$element_type'. Must be one of: field, section, group, root");
		}
	}

	/**
	 * Get individual element overrides (Tier 2)
	 *
	 * @internal
	 *
	 * @param string $element_type The element type (field, section, group, root)
	 * @param string $element_id The element ID
	 * @return array<string, string> Template type => template key mappings
	 */
	public function get_individual_element_overrides(string $element_type, string $element_id): array {
		switch ($element_type) {
			case 'field':
				return $this->template_resolver->get_field_template_overrides($element_id);
			case 'section':
				return $this->template_resolver->get_section_template_overrides($element_id);
			case 'group':
				return $this->template_resolver->get_group_template_overrides($element_id);
			case 'root':
				return $this->template_resolver->get_root_template_overrides($element_id);
			default:
				throw new \InvalidArgumentException("Invalid element type: '$element_type'. Must be one of: field, section, group, root");
		}
	}

	/**
	 * Resolve template using the two-tier system
	 *
	 * @internal
	 *
	 * @param string $template_type The template type
	 * @param array<string, mixed> $context Resolution context
	 * @return string The resolved template key
	 */
	public function resolve_template(string $template_type, array $context = array()): string {
		return $this->template_resolver->resolve_template($template_type, $context);
	}
}
