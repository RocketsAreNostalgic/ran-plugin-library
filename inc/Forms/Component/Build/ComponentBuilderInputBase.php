<?php

/**
 * Base class for input-like form component builders.
 * Provides common functionality for text inputs, textareas, and other input elements.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

abstract class ComponentBuilderInputBase extends ComponentBuilderBase {
	protected ?string $placeholder = null;
	/** @var string|callable|null */
	protected mixed $default = null;
	/** @var bool|callable */
	protected mixed $disabled = false;
	/** @var bool|callable */
	protected mixed $readonly = false;
	/** @var bool|callable */
	protected mixed $required = false;
	protected bool $autofocus = false;
	protected ?string $name   = null;
	protected string $style   = '';
	/** @var array<string,mixed> */
	private array $base_metadata;

	public function __construct(string $id, string $label, array $base_metadata = array()) {
		parent::__construct($id, $label);
		$this->base_metadata = $base_metadata;
		if (isset($base_metadata['order'])) {
			$this->order((int) $base_metadata['order']);
		}
	}

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
	 * @param string|callable|null $value A string value or callable that returns a string.
	 * @return static
	 */
	public function default(string|callable|null $value): static {
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
	 * @param bool|callable $disabled Boolean or callable that returns bool.
	 * @return static
	 */
	public function disabled(bool|callable $disabled = true): static {
		$this->disabled = $disabled;
		return $this;
	}

	/**
	 * Marks the input as readonly.
	 *
	 * @param bool|callable $readonly Boolean or callable that returns bool.
	 * @return static
	 */
	public function readonly(bool|callable $readonly = true): static {
		$this->readonly = $readonly;
		return $this;
	}

	/**
	 * Marks the input as required.
	 *
	 * @param bool|callable $required Boolean or callable that returns bool.
	 * @return static
	 */
	public function required(bool|callable $required = true): static {
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
	 * Sets the visual style for this field.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 * @return static
	 */
	public function style(string|callable $style): static {
		if ($style === '') {
			$this->style = '';
		} elseif (is_callable($style)) {
			$resolved = $style();
			if (!is_string($resolved)) {
				throw new \InvalidArgumentException('Field style callback must return a string.');
			}
			$this->style = trim($resolved);
		} else {
			$this->style = trim($style);
		}
		return $this;
	}

	/**
	 * Get the style css classes for this field.
	 *
	 * @return string
	 */
	public function get_style(): string {
		return $this->style;
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
		if (is_callable($this->default)) {
			$context['default'] = $this->default;
		} else {
			$this->_add_if_not_empty($context, 'default', $this->default);
		}
		$this->_add_if_not_empty($context, 'name', $this->name ?? $this->id);

		// Add boolean flags (resolve callables)
		if (is_callable($this->disabled)) {
			$context['disabled'] = $this->disabled;
		} else {
			$this->_add_if_true($context, 'disabled', (bool) $this->disabled);
		}
		if (is_callable($this->readonly)) {
			$context['readonly'] = $this->readonly;
		} else {
			$this->_add_if_true($context, 'readonly', (bool) $this->readonly);
		}
		if (is_callable($this->required)) {
			$context['required'] = $this->required;
		} else {
			$this->_add_if_true($context, 'required', (bool) $this->required);
		}
		$this->_add_if_true($context, 'autofocus', $this->autofocus);

		return $context;
	}

	/**
	 * Resolve a bool|callable to a bool value.
	 *
	 * @param bool|callable $value
	 * @return bool
	 */
	protected function _resolve_bool_callable(mixed $value): bool {
		if (is_callable($value)) {
			return (bool) $value();
		}
		return (bool) $value;
	}

	/**
	 * Resolve a callable to its value, or return the value as-is.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	protected function _resolve_callable(mixed $value): mixed {
		if (is_callable($value)) {
			return $value();
		}
		return $value;
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
	 * Get the default value (resolves callable if set).
	 *
	 * @return string|null
	 */
	public function get_default(): ?string {
		$resolved = $this->_resolve_callable($this->default);
		return $resolved !== null ? (string) $resolved : null;
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
	 * Check if the input is required (resolves callable if set).
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return $this->_resolve_bool_callable($this->required);
	}

	/**
	 * Check if the input is disabled (resolves callable if set).
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return $this->_resolve_bool_callable($this->disabled);
	}

	/**
	 * Check if the input is readonly (resolves callable if set).
	 *
	 * @return bool
	 */
	public function is_readonly(): bool {
		return $this->_resolve_bool_callable($this->readonly);
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
