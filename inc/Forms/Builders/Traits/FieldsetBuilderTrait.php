<?php
/**
 * FieldsetBuilderTrait: Fieldset-specific logic for fluent builders.
 *
 * This trait provides the fieldset-specific implementation on top of
 * SectionFieldContainerTrait. It handles fieldset HTML attributes (form, name, disabled)
 * and fieldset-specific template defaults.
 *
 * @package Ran\PluginLib\Forms\Builders\Traits
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits;

use Ran\PluginLib\Forms\Builders\FieldsetBuilderInterface;
use InvalidArgumentException;

/**
 * Fieldset-specific builder logic.
 *
 * Classes using this trait must:
 * 1. Also use SectionFieldContainerTrait
 * 2. Call `_init_fieldset()` after `_init_container()` in their constructor
 * 3. Implement abstract methods for context-specific behavior
 */
trait FieldsetBuilderTrait {
	private string $form   = '';
	private string $name   = '';
	private bool $disabled = false;

	/**
	 * Initialize fieldset-specific properties.
	 *
	 * Call this after `_init_container()` in the constructor.
	 *
	 * @param array<string,mixed> $args Optional arguments (form, name, disabled).
	 */
	protected function _init_fieldset(array $args = array()): void {
		$this->form     = isset($args['form']) ? (string) $args['form'] : '';
		$this->name     = isset($args['name']) ? (string) $args['name'] : '';
		$this->disabled = (bool) ($args['disabled'] ?? false);

		// Fields inside fieldsets use a div-based wrapper (no <tr>)
		$this->default_field_template = 'layout.field.field-wrapper';

		// Re-emit metadata now that fieldset properties are set
		// This ensures the payload includes form, name, disabled
		$this->_emit_container_metadata();

		// Set fieldset-specific template (emits template_override)
		$this->template('fieldset-wrapper');
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
	 * Apply a fieldset-specific meta update.
	 *
	 * Call this from the class's _apply_meta_update method for fieldset keys.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 *
	 * @return bool True if the key was handled, false otherwise.
	 */
	protected function _apply_fieldset_meta_update(string $key, mixed $value): bool {
		switch ($key) {
			case 'form':
				$this->form = (string) $value;
				return true;
			case 'name':
				$this->name = (string) $value;
				return true;
			case 'disabled':
				$this->disabled = (bool) $value;
				return true;
		}
		return false;
	}

	/**
	 * Add fieldset-specific data to the payload.
	 *
	 * Call this to extend the container payload with fieldset attributes.
	 *
	 * @param array<string,mixed> $payload The base payload from _build_container_payload().
	 *
	 * @return array<string,mixed> The extended payload.
	 */
	protected function _extend_fieldset_payload(array $payload): array {
		$payload['group_data']['type']     = 'fieldset';
		$payload['group_data']['form']     = $this->form;
		$payload['group_data']['name']     = $this->name;
		$payload['group_data']['disabled'] = $this->disabled;

		return $payload;
	}

	/**
	 * Get the container type for fieldsets.
	 *
	 * @return string Always 'fieldset'.
	 */
	protected function _get_fieldset_container_type(): string {
		return 'fieldset';
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
	protected function _normalize_fieldset_style(mixed $style): string {
		if (is_callable($style)) {
			$style = $style();
		}
		if (!is_string($style)) {
			throw new InvalidArgumentException('Fieldset style must be a string.');
		}

		return trim($style);
	}
}
