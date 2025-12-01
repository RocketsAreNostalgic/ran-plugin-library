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
		?callable $description_cb = null,
		?callable $updateFn = null,
		array $args = array()
	) {
		if ($updateFn === null) {
			throw new \InvalidArgumentException('updateFn is required');
		}
		$this->form     = isset($args['form']) ? (string) $args['form'] : '';
		$this->name     = isset($args['name']) ? (string) $args['name'] : '';
		$this->disabled = (bool) ($args['disabled'] ?? false);

		if (isset($args['style']) && $args['style'] !== '') {
			$args['style'] = $this->_normalize_style($args['style']);
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
		// Resolved via form_defaults to 'layout.field.field-wrapper' (the base template)
		$this->default_field_template = 'fieldset-field-wrapper';
	}

	/**
	 * Set the visual style for this fieldset.
	 * Overrides parent to add validation.
	 *
	 * @param string $style The style identifier (e.g., 'bordered', 'plain').
	 *
	 * @return static
	 */
	public function style(string $style): static {
		return parent::style($this->_normalize_style($style));
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
	 * Commit buffered data and return to the section builder.
	 *
	 * @return SectionBuilderInterface
	 */
	public function end_fieldset(): SectionBuilderInterface {
		return $this->section();
	}

	/**
	 * Not valid in fieldset context - throws exception.
	 *
	 * @return SectionBuilderInterface
	 * @throws \RuntimeException Always throws - use end_fieldset() instead.
	 */
	public function end_group(): SectionBuilderInterface {
		throw new \RuntimeException('Cannot call end_group() from fieldset context. Use end_fieldset() instead.');
	}

	/**
	 * Define a fieldset within this section.
	 *
	 * @param string $fieldset_id The fieldset ID.
	 * @param string $heading The fieldset heading.
	 * @param callable|null $description_cb The fieldset description callback.
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return FieldsetBuilderInterface The fieldset builder instance.
	 */
	public function fieldset(string $fieldset_id, string $heading = '', ?callable $description_cb = null, ?array $args = null): FieldsetBuilderInterface {
		return $this->section()->fieldset($fieldset_id, $heading, $description_cb, $args ?? array());
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
		if (!is_string($style)) {
			throw new InvalidArgumentException('Fieldset style must be a string.');
		}

		return trim($style);
	}
}
