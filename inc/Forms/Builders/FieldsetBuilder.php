<?php
/**
 * FieldsetBuilder: Fluent builder for semantic fieldset groups within a section.
 *
 * @template TRoot of BuilderRootInterface
 * @template TSection of SectionBuilder<TRoot>
 * @extends SectionFieldContainerBuilder<TRoot, TSection>
 * @implements FieldsetBuilderInterface<TRoot, TSection>
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use InvalidArgumentException;

class FieldsetBuilder extends SectionFieldContainerBuilder implements FieldsetBuilderInterface {
	private string $form   = '';
	private string $name   = '';
	private bool $disabled = false;

	public function __construct(
		SectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading = '',
		string|callable|null $description_cb = null,
		?callable $updateFn = null,
		array $args = array()
	) {
		if ($updateFn === null) {
			throw new \InvalidArgumentException('updateFn is required');
		}
		$this->form     = isset($args['form']) ? (string) $args['form'] : '';
		$this->name     = isset($args['name']) ? (string) $args['name'] : '';
		$this->disabled = (bool) ($args['disabled'] ?? false);

		if (array_key_exists('style', $args)) {
			$styleArg      = $args['style'];
			$args['style'] = $styleArg === '' ? '' : $this->_resolve_style_arg($styleArg);
		}

		parent::__construct(
			$sectionBuilder,
			$container_id,
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$updateFn,
			$args
		);

		// Set fieldset-specific templates
		$this->template('fieldset-wrapper');
		// Fields inside fieldsets use a div-based wrapper (no <tr>)
		// Use the actual component path directly to avoid resolution issues
		$this->default_field_template = 'layout.field.field-wrapper';
	}

	/**
	 * Set the visual style for this fieldset.
	 * Overrides parent to add validation.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 *
	 * @return static
	 */
	public function style(string|callable $style): static {
		return parent::style($style === '' ? '' : $this->_resolve_style_arg($style));
	}

	/**
	 * Set the form attribute for this fieldset.
	 * Associates the fieldset with a form element by its ID.
	 *
	 * @param string $form_id The ID of the form element to associate with.
	 *
	 * @return static
	 */
	public function form(string $form_id): static {
		$this->_update_meta('form', $form_id);
		return $this;
	}

	/**
	 * Set the name attribute for this fieldset.
	 *
	 * @param string $name The name for the fieldset.
	 *
	 * @return static
	 */
	public function name(string $name): static {
		$this->_update_meta('name', $name);
		return $this;
	}

	/**
	 * Set the disabled state for this fieldset.
	 * When disabled, all form controls within the fieldset are disabled.
	 *
	 * @param bool $disabled Whether the fieldset is disabled.
	 *
	 * @return static
	 */
	public function disabled(bool $disabled = true): static {
		$this->_update_meta('disabled', $disabled);
		return $this;
	}

	/**
	 * Add a field with a component builder to this fieldset.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<FieldsetBuilder> The proxy instance with correct return type for end_field().
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		$proxy = parent::field($field_id, $label, $component, $args);
		if (!$proxy instanceof GenericFieldBuilder) {
			throw new \RuntimeException('Unexpected proxy type from parent::field()');
		}
		return $proxy;
	}

	/**
	 * Define a fieldset within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param string|callable|null $description_cb The fieldset description (string or callback).
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return FieldsetBuilderInterface The fieldset builder instance.
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): FieldsetBuilderInterface {
		return $this->section()->fieldset($fieldset_id, $heading, $description_cb, $args ?? array());
	}

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * @return TSection
	 */
	public function end_fieldset(): mixed {
		return $this->section();
	}

	/**
	 * Apply a meta update to this fieldset.
	 *
	 * @param string $key The key to update.
	 * @param mixed $value The value to update.
	 *
	 * @return void
	 */
	protected function _apply_meta_update(string $key, mixed $value): void {
		switch ($key) {
			case 'form':
				$this->form = (string) $value;
				break;
			case 'name':
				$this->name = (string) $value;
				break;
			case 'disabled':
				$this->disabled = (bool) $value;
				break;
			default:
				parent::_apply_meta_update($key, $value);
				return;
		}

		parent::_apply_meta_update($key, $value);
	}

	/**
	 * Build the update payload for this fieldset.
	 *
	 * @param string $key The key to update.
	 * @param mixed $value The value to update.
	 *
	 * @return array The update payload.
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
		$payload                           = parent::_build_update_payload($key, $value);
		$payload['group_data']['type']     = 'fieldset'; // Override parent's 'group' type
		$payload['group_data']['form']     = $this->form;
		$payload['group_data']['name']     = $this->name;
		$payload['group_data']['disabled'] = $this->disabled;
		// Style is already included from parent

		return $payload;
	}

	/**
	 * Normalize the style for this fieldset.
	 *
	 * @param mixed $style The style to normalize.
	 *
	 * @return string The normalized style.
	 *
	 * @throws InvalidArgumentException If the style is not a string.
	 */
	protected function _normalize_style(mixed $style): string {
		if (is_callable($style)) {
			$style = $style();
		}
		if (!is_string($style)) {
			throw new InvalidArgumentException('Fieldset style must be a string.');
		}

		return trim($style);
	}

	/**
	 * Factory method to create a ComponentBuilderProxy.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return GenericFieldBuilder<FieldsetBuilder> The proxy instance.
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): GenericFieldBuilder {
		return new GenericFieldBuilder(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component_alias,
			$this->group_id,
			$field_template,
			$component_context
		);
	}
}
