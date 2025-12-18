<?php
/**
 * Base class for text-based input component builders.
 * Provides common functionality for text inputs, textareas, and other text-based elements.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

abstract class ComponentBuilderTextBase extends ComponentBuilderInputBase implements ReadonlyComponentBuilderInterface {
	/** @var bool|callable */
	protected mixed $readonly = false;

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

	protected ?string $autocomplete   = null;
	protected ?string $autocapitalize = null;
	protected ?bool $spellcheck       = null;
	protected ?int $minlength         = null;
	protected ?int $maxlength         = null;
	protected ?string $pattern        = null;

	protected function _build_input_context(): array {
		$context = parent::_build_input_context();
		if (is_callable($this->readonly)) {
			$context['readonly'] = $this->readonly;
		} else {
			$this->_add_if_true($context, 'readonly', (bool) $this->readonly);
		}
		return $context;
	}

	/**
	 * Sets the autocomplete attribute for the input.
	 * Common values: 'on', 'off', 'name', 'email', 'username', etc.
	 *
	 * @param string|null $autocomplete
	 * @return static
	 */
	public function autocomplete(?string $autocomplete): static {
		$this->autocomplete = $autocomplete;
		return $this;
	}

	/**
	 * Sets the autocapitalize attribute for the input.
	 * Values: 'none', 'sentences', 'words', 'characters'
	 *
	 * @param string|null $autocapitalize
	 * @return static
	 */
	public function autocapitalize(?string $autocapitalize): static {
		$this->autocapitalize = $autocapitalize;
		return $this;
	}

	/**
	 * Sets whether spellcheck is enabled for the input.
	 *
	 * @param bool|null $spellcheck
	 * @return static
	 */
	public function spellcheck(?bool $spellcheck): static {
		$this->spellcheck = $spellcheck;
		return $this;
	}

	/**
	 * Sets the minimum length for the input value.
	 *
	 * @param int|null $minlength
	 * @return static
	 */
	public function minlength(?int $minlength): static {
		$this->minlength = $minlength;
		return $this;
	}

	/**
	 * Sets the maximum length for the input value.
	 *
	 * @param int|null $maxlength
	 * @return static
	 */
	public function maxlength(?int $maxlength): static {
		$this->maxlength = $maxlength;
		return $this;
	}

	/**
	 * Sets a regex pattern for input validation.
	 *
	 * @param string|null $pattern
	 * @return static
	 */
	public function pattern(?string $pattern): static {
		$this->pattern = $pattern;
		return $this;
	}

	/**
	 * Build text-specific context that extends input context.
	 * Child classes should call this and extend the returned array.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_text_context(): array {
		$context = $this->_build_input_context();

		// Add text-specific properties
		$this->_add_if_not_empty($context, 'autocomplete', $this->autocomplete);
		$this->_add_if_not_empty($context, 'autocapitalize', $this->autocapitalize);
		$this->_add_if_not_empty($context, 'pattern', $this->pattern);

		// Add numeric properties
		if ($this->minlength !== null) {
			$context['minlength'] = $this->minlength;
		}
		if ($this->maxlength !== null) {
			$context['maxlength'] = $this->maxlength;
		}

		// Add spellcheck (can be explicitly true or false)
		if ($this->spellcheck !== null) {
			$context['spellcheck'] = $this->spellcheck;
		}

		return $context;
	}

	/**
	 * Get the autocomplete value.
	 *
	 * @return string|null
	 */
	public function get_autocomplete(): ?string {
		return $this->autocomplete;
	}

	/**
	 * Get the autocapitalize value.
	 *
	 * @return string|null
	 */
	public function get_autocapitalize(): ?string {
		return $this->autocapitalize;
	}

	/**
	 * Get the spellcheck setting.
	 *
	 * @return bool|null
	 */
	public function get_spellcheck(): ?bool {
		return $this->spellcheck;
	}

	/**
	 * Get the minimum length.
	 *
	 * @return int|null
	 */
	public function get_minlength(): ?int {
		return $this->minlength;
	}

	/**
	 * Get the maximum length.
	 *
	 * @return int|null
	 */
	public function get_maxlength(): ?int {
		return $this->maxlength;
	}

	/**
	 * Get the pattern.
	 *
	 * @return string|null
	 */
	public function get_pattern(): ?string {
		return $this->pattern;
	}
}
