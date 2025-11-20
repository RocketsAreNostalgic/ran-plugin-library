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
use Ran\PluginLib\Forms\Component\ComponentRenderResult;

/**
 * FormsServiceSession: per-render context for component dispatching and asset collection.
 */
class FormsServiceSession {
	use WPWrappersTrait;

	private ComponentManifest $manifest;
	private FormsAssets $assets;
	private FormsTemplateOverrideResolver $template_resolver;
	private Logger $logger;
	/** @var array<string,callable> */
	private array $root_template_callbacks = array();

	public function __construct(
		ComponentManifest $manifest,
		FormsAssets $assets,
		FormsTemplateOverrideResolver $template_resolver,
		Logger $logger,
		array $form_defaults = array()
	) {
		$this->manifest          = $manifest;
		$this->assets            = $assets;
		$this->template_resolver = $template_resolver;
		$this->logger            = $logger;

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
		try {
			$template_key = $this->template_resolver->resolve_template($element_type, $context);
			if (isset($element_config['root_override']) && is_string($element_config['root_override']) && $element_config['root_override'] !== '') {
				$template_key = $element_config['root_override'];
			}

			// Step 2: Pass resolved template key to ComponentManifest for rendering
			$render_context = array_merge($element_config, $context);
			$result         = $this->manifest->render($template_key, $render_context);

			// Step 3: Extract and store assets from ComponentRenderResult
			$field_id = isset($context['field_id']) && is_string($context['field_id']) ? $context['field_id'] : null;
			$this->ingest_component_result($result, sprintf('render_element:%s', $element_type), $field_id);

			return $result->markup;
		} catch (\Throwable $e) {
			$this->logger->warning('FormsServiceSession: Template render failed; returning fallback markup', array(
				'element_type'      => $element_type,
				'context_keys'      => array_keys($context),
				'element_config'    => array_keys($element_config),
				'exception_class'   => get_class($e),
				'exception_code'    => $e->getCode(),
				'exception_message' => $e->getMessage(),
			));

			$fallback_markup = '';
			if (isset($element_config['component_html']) && is_string($element_config['component_html'])) {
				$fallback_markup = $element_config['component_html'];
			} elseif (isset($element_config['content']) && is_string($element_config['content'])) {
				$fallback_markup = $element_config['content'];
			}

			$warning_id   = isset($context['field_id']) ? (string) $context['field_id'] : $element_type;
			$warning_text = sprintf(
				'Template failure while rendering "%s". Check logs for details.',
				$template_key ?? $element_type
			);

			return $fallback_markup . sprintf(
				"\n<!-- kepler-template-fallback: %s -->\n<div class=\"kepler-template-warning screen-reader-text\" aria-live=\"polite\">%s</div>\n",
				esc_html($warning_id),
				esc_html($warning_text)
			);
		}
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
		$result   = $this->manifest->render($component, $context);
		$field_id = isset($context['field_id']) && is_string($context['field_id'])
			? $context['field_id']
			: (isset($context['_field_id']) && is_string($context['_field_id']) ? $context['_field_id'] : null);
		$this->ingest_component_result($result, sprintf('render_component:%s', $component), $field_id);
		return $result->markup;
	}

	/**
	 * Ingest ComponentRenderResult assets with centralized logging and error handling.
	 *
	 * @param ComponentRenderResult $result
	 * @param string                $source
	 * @param string|null           $field_id
	 * @return void
	 */
	public function ingest_component_result(ComponentRenderResult $result, string $source, ?string $field_id = null): void {
		try {
			$this->assets->ingest($result);

			if ($result->has_assets()) {
				$this->logger->debug('FormsServiceSession: Assets ingested successfully', array(
					'source'         => $source,
					'field_id'       => $field_id,
					'has_script'     => $result->has_script(),
					'has_style'      => $result->has_style(),
					'requires_media' => $result->requires_media,
					'script_handle'  => $result->has_script() ? $result->script->handle : null,
					'style_handle'   => $result->has_style() ? $result->style->handle : null,
				));
			}
		} catch (\Throwable $e) {
			$this->logger->warning('FormsServiceSession: Asset ingestion failed; continuing without assets', array(
				'source'          => $source,
				'field_id'        => $field_id,
				'error'           => $e->getMessage(),
				'exception_class' => get_class($e),
			));
		}
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
	 * Expose manifest defaults catalogue for read-only inspection.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function manifest_defaults(): array {
		return $this->manifest->default_catalogue();
	}

	/**
	 * Merge a schema definition with manifest defaults for the given component alias.
	 *
	 * @param string                 $alias  Component alias registered in the manifest
	 * @param array<string,mixed>    $schema Integrator supplied schema entry
	 * @return array<string,mixed>
	 */
	public function merge_schema_with_defaults(string $alias, array $schema): array {
		$defaults = $this->manifest->get_defaults_for($alias);
		if ($defaults === array()) {
			$this->logger->debug('forms.schema.merge.no_defaults', array(
				'alias' => $alias,
			));
			return $schema;
		}

		$merged = $this->_merge_schema_with_defaults($schema, $defaults);
		if ($this->logger->is_active()) {
			$defaultBuckets = $this->_coerce_bucketed_lists($defaults, true);
			$schemaBuckets  = $this->_coerce_bucketed_lists($schema, false);
			$mergedBuckets  = $this->_coerce_bucketed_lists($merged, false);
			$this->logger->debug('forms.schema.component_counts', array(
				'alias'                            => $alias,
				'default_validate_component_count' => count($defaultBuckets['validate']['component']),
				'schema_validate_component_count'  => count($schemaBuckets['validate']['component']),
				'merged_validate_component_count'  => count($mergedBuckets['validate']['component']),
			));
		}

		if ($merged['validate']['component'] === array() && $merged['validate']['schema'] === array()) {
			$this->logger->error('forms.schema.merge.no_validators', array(
				'alias'    => $alias,
				'schema'   => $schema,
				'defaults' => $defaults,
			));
			throw new \InvalidArgumentException("FormsServiceSession: merge_schema_with_defaults requires at least one validator for component '{$alias}'.");
		}

		$this->_log_schema_merge($alias, $defaults, $schema, $merged);

		return $merged;
	}

	/**
	 * Internal merge implementation that keeps manifest defaults immutable.
	 *
	 * @param array<string,mixed> $schema
	 * @param array<string,mixed> $defaults
	 * @return array<string,mixed>
	 */
	private function _merge_schema_with_defaults(array $schema, array $defaults): array {
		$merged = $schema;

		$schemaBuckets  = $this->_coerce_bucketed_lists($schema, false);
		$defaultBuckets = $this->_coerce_bucketed_lists($defaults, true);

		$merged['sanitize'] = array(
			'component' => array_merge($defaultBuckets['sanitize']['component'], $schemaBuckets['sanitize']['component']),
			'schema'    => $schemaBuckets['sanitize']['schema'] !== array()
				? $schemaBuckets['sanitize']['schema']
				: $defaultBuckets['sanitize']['schema'],
		);

		$merged['validate'] = array(
			'component' => array_merge($defaultBuckets['validate']['component'], $schemaBuckets['validate']['component']),
			'schema'    => $schemaBuckets['validate']['schema'] !== array()
				? $schemaBuckets['validate']['schema']
				: $defaultBuckets['validate']['schema'],
		);

		$defaultContext = isset($defaults['context']) && is_array($defaults['context']) ? $defaults['context'] : array();
		$schemaContext  = isset($schema['context'])   && is_array($schema['context']) ? $schema['context'] : array();
		if ($defaultContext !== array() || $schemaContext !== array()) {
			$merged['context'] = $defaultContext === array()
				? $schemaContext
				: array_merge($defaultContext, $schemaContext);
		}

		return $merged;
	}

	/**
	 * Normalize callable entries to arrays for merge operations.
	 *
	 * @param mixed $value
	 * @return array<int,callable>|null
	 */
	private function _coerce_bucketed_lists(array $source, bool $flatAsComponent): array {
		$blank = array(
			'component' => array(),
			'schema'    => array(),
		);

		$sanitize = $blank;
		$validate = $blank;

		if (isset($source['sanitize'])) {
			$sanitizeField = $source['sanitize'];
			if (is_array($sanitizeField) && array_key_exists('component', $sanitizeField)) {
				$sanitize['component'] = $this->_coerce_callable_array($sanitizeField['component'] ?? null);
				$sanitize['schema']    = $this->_coerce_callable_array($sanitizeField['schema'] ?? null);
			} else {
				$bucketKey            = $flatAsComponent ? 'component' : 'schema';
				$sanitize[$bucketKey] = $this->_coerce_callable_array($sanitizeField);
			}
		}

		if (isset($source['validate'])) {
			$validateField = $source['validate'];
			if (is_array($validateField) && array_key_exists('component', $validateField)) {
				$validate['component'] = $this->_coerce_callable_array($validateField['component'] ?? null);
				$validate['schema']    = $this->_coerce_callable_array($validateField['schema'] ?? null);
			} else {
				$bucketKey            = $flatAsComponent ? 'component' : 'schema';
				$validate[$bucketKey] = $this->_coerce_callable_array($validateField);
			}
		}

		return array(
			'sanitize' => $sanitize,
			'validate' => $validate,
		);
	}

	/**
	 * @param mixed $value
	 * @return array<int,callable>
	 */
	private function _coerce_callable_array(mixed $value): array {
		if ($value === null) {
			return array();
		}
		if (is_callable($value)) {
			return array($value);
		}
		if (is_array($value)) {
			$callables = array();
			foreach ($value as $maybeCallable) {
				if (is_callable($maybeCallable)) {
					$callables[] = $maybeCallable;
				}
			}
			return $callables;
		}
		return array();
	}

	/**
	 * Emit debug information summarizing the merge outcome.
	 *
	 * @param string               $alias
	 * @param array<string,mixed>  $defaults
	 * @param array<string,mixed>  $schema
	 * @param array<string,mixed>  $merged
	 * @return void
	 */
	private function _log_schema_merge(string $alias, array $defaults, array $schema, array $merged): void {
		$defaultContext = isset($defaults['context']) && is_array($defaults['context']) ? $defaults['context'] : array();
		$schemaContext  = isset($schema['context'])   && is_array($schema['context']) ? $schema['context'] : array();
		$mergedContext  = isset($merged['context'])   && is_array($merged['context']) ? $merged['context'] : array();

		$summary = static function (array $source): array {
			return array(
				'component' => isset($source['component']) && is_array($source['component']) ? count($source['component']) : 0,
				'schema'    => isset($source['schema'])    && is_array($source['schema'])    ? count($source['schema'])    : 0,
			);
		};

		$defaultsBuckets = $this->_coerce_bucketed_lists($defaults, true);
		$schemaBuckets   = $this->_coerce_bucketed_lists($schema, false);
		$mergedBuckets   = $this->_coerce_bucketed_lists($merged, false);

		$this->logger->debug('forms.schema.merge', array(
			'alias'                   => $alias,
			'default_sanitize_counts' => $summary($defaultsBuckets['sanitize']),
			'default_validate_counts' => $summary($defaultsBuckets['validate']),
			'schema_sanitize_counts'  => $summary($schemaBuckets['sanitize']),
			'schema_validate_counts'  => $summary($schemaBuckets['validate']),
			'merged_sanitize_counts'  => $summary($mergedBuckets['sanitize']),
			'merged_validate_counts'  => $summary($mergedBuckets['validate']),
			'manifest_context_keys'   => array_keys($defaultContext),
			'schema_context_keys'     => array_keys($schemaContext),
			'merged_context_keys'     => array_keys($mergedContext),
		));
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
