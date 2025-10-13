<?php
/**
 * Base class for input-like form component builders.
 * Provides common functionality for text inputs, textareas, and other input elements.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

abstract class BuilderInputBase extends BuilderBase {
	protected ?string $placeholder = null;
	protected ?string $default     = null;
	protected bool $disabled       = false;
	protected bool $readonly       = false;
	protected bool $required       = false;
	protected bool $autofocus      = false;
	protected ?string $name        = null;

	/**
	 * Sets placeholder text for the input element.
	 *
	 * @param string|null $placeholder
	 * @return static
	 */
	public function placeholder(?string $placeholder): static {
		$this->placeholder = $placeholder;
		return $this;
	}

	/**
	 * Sets a default value for the input element.
	 *
	 * @param string|null $value
	 * @return static
	 */
	public function default(?string $value): static {
		$this->default = $value;
		return $this;
	}

	/**
	 * Sets the name attribute for the input element.
	 * If not set, the component ID will be used as the name.
	 *
	 * @param string|null $name
	 * @return static
	 */
	public function name(?string $name): static {
		$this->name = $name;
		return $this;
	}

	/**
	 * Marks the input as disabled.
	 *
	 * @param bool $disabled
	 * @return static
	 */
	public function disabled(bool $disabled = true): static {
		$this->disabled = $disabled;
		return $this;
	}

	/**
	 * Marks the input as readonly.
	 *
	 * @param bool $readonly
	 * @return static
	 */
	public function readonly(bool $readonly = true): static {
		$this->readonly = $readonly;
		return $this;
	}

	/**
	 * Marks the input as required.
	 *
	 * @param bool $required
	 * @return static
	 */
	public function required(bool $required = true): static {
		$this->required = $required;
		return $this;
	}

	/**
	 * Sets autofocus on the input element.
	 *
	 * @param bool $autofocus
	 * @return static
	 */
	public function autofocus(bool $autofocus = true): static {
		$this->autofocus = $autofocus;
		return $this;
	}

	/**
	 * Build input-specific context that extends base context.
	 * Child classes should call this and extend the returned array.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_input_context(): array {
		$context = $this->_build_base_context();

		// Add input-specific properties
		$this->_add_if_not_empty($context, 'placeholder', $this->placeholder);
		$this->_add_if_not_empty($context, 'default', $this->default);
		$this->_add_if_not_empty($context, 'name', $this->name ?? $this->id);

		// Add boolean flags
		$this->_add_if_true($context, 'disabled', $this->disabled);
		$this->_add_if_true($context, 'readonly', $this->readonly);
		$this->_add_if_true($context, 'required', $this->required);
		$this->_add_if_true($context, 'autofocus', $this->autofocus);

		return $context;
	}

	/**
	 * Get the name that will be used for the input.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name ?? $this->id;
	}

	/**
	 * Get the default value.
	 *
	 * @return string|null
	 */
	public function get_default(): ?string {
		return $this->default;
	}

	/**
	 * Get the placeholder text.
	 *
	 * @return string|null
	 */
	public function get_placeholder(): ?string {
		return $this->placeholder;
	}

	/**
	 * Check if the input is required.
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return $this->required;
	}

	/**
	 * Check if the input is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return $this->disabled;
	}

	/**
	 * Check if the input is readonly.
	 *
	 * @return bool
	 */
	public function is_readonly(): bool {
		return $this->readonly;
	}

	/**
	 * Check if the input has autofocus.
	 *
	 * @return bool
	 */
	public function has_autofocus(): bool {
		return $this->autofocus;
	}
}
