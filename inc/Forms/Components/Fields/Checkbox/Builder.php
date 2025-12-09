<?php
/**
 * Fluent single checkbox field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Checkbox;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase;

final class Builder extends ComponentBuilderInputBase {
	private ?string $text           = null;
	private string $checkedValue    = 'on';
	private ?string $uncheckedValue = 'off';
	private bool $default_checked   = false;

	// description(), disabled(), required(), name() methods inherited from ComponentBuilderInputBase

	public function text(?string $text): static {
		$this->text = $text;
		return $this;
	}

	public function values(string $checkedValue, ?string $uncheckedValue = null): static {
		$this->checkedValue   = $checkedValue;
		$this->uncheckedValue = $uncheckedValue ?? 'off';
		return $this;
	}

	public function default_checked(bool $checked = true): static {
		$this->default_checked = $checked;
		return $this;
	}

	protected function _build_component_context(): array {
		// Start with input context (includes name, disabled, required, etc.)
		$context = $this->_build_input_context();

		// Add checkbox-specific properties
		$context['checked_value'] = $this->checkedValue;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'label', $this->text);
		$this->_add_if_not_empty($context, 'unchecked_value', $this->uncheckedValue);
		$this->_add_if_true($context, 'default_checked', $this->default_checked);

		return $context;
	}

	protected function _get_component(): string {
		return 'checkbox';
	}
}
