<?php

/**
 * Base class for input-like form component builders.
 * Provides common functionality for text inputs, textareas, and other input elements.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

use Ran\PluginLib\Forms\CallableRegistry;

abstract class ComponentBuilderInputBase extends ComponentBuilderBase implements InputComponentBuilderInterface, CallableKeysProviderInterface {
	protected ?string $placeholder = null;
	/** @var string|callable|null */
	protected mixed $default = null;
	/** @var bool|callable */
	protected mixed $disabled = false;
	/** @var bool|callable */
	protected mixed $required = false;
	protected bool $autofocus = false;
	protected ?string $name   = null;
	/** @var string|callable */
	protected mixed $style = '';
	/** @var array<string,mixed> */
	private array $base_metadata;

	public function __construct(string $id, string $label, array $base_metadata = array()) {
		parent::__construct($id, $label);
		$this->base_metadata = $base_metadata;
		if (isset($base_metadata['order'])) {
			$this->order((int) $base_metadata['order']);
		}
	}

	public static function register_callable_keys(CallableRegistry $registry): void {
		$registry->register_bool_key('disabled');
		$registry->register_bool_key('required');
		$registry->register_value_key('default');
		$registry->register_string_key('style');
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
			return $this;
		}

		if (is_callable($style)) {
			$this->style = $style;
			return $this;
		}

		$this->style = trim($style);
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
		if (is_callable($this->default)) {
			$context['default'] = $this->default;
		} else {
			$this->_add_if_not_empty($context, 'default', $this->default);
		}
		$this->_add_if_not_empty($context, 'name', $this->name ?? $this->id);
		if (is_callable($this->style)) {
			$context['style'] = $this->style;
		} else {
			$this->_add_if_not_empty($context, 'style', trim((string) $this->style));
		}

		// Add boolean flags (resolve callables)
		if (is_callable($this->disabled)) {
			$context['disabled'] = $this->disabled;
		} else {
			$this->_add_if_true($context, 'disabled', (bool) $this->disabled);
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
	 * Get the name that will be used for the input.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name ?? $this->id;
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
	 * Check if the input has autofocus.
	 *
	 * @return bool
	 */
	public function has_autofocus(): bool {
		return $this->autofocus;
	}
}
