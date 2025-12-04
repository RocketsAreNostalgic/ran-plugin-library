<?php
/**
 * Fluent single checkbox field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Checkbox;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	private ?string $text           = null;
	private ?string $name           = null;
	private string $checkedValue    = 'on';
	private ?string $uncheckedValue = 'off';
	private bool $defaultChecked    = false;

	public function __construct(string $id, string $label) {
		parent::__construct($id, $label);
	}

	// description() method inherited from ComponentBuilderBase

	public function text(?string $text): static {
		$this->text = $text;
		return $this;
	}

	public function name(string $name): static {
		$this->name               = trim($name);
		$this->attributes['name'] = $this->name;
		return $this;
	}

	public function values(string $checkedValue, ?string $uncheckedValue = null): static {
		$this->checkedValue   = $checkedValue;
		$this->uncheckedValue = $uncheckedValue ?? 'off';
		return $this;
	}

	public function defaultChecked(bool $checked = true): static {
		$this->defaultChecked = $checked;
		return $this;
	}

	public function attribute(string $key, string $value): static {
		parent::attribute($key, $value);
		if ($key === 'name') {
			$this->name = trim($value);
		}
		return $this;
	}

	protected function _build_component_context(): array {
		// Use field ID as default name if not explicitly set (consistent with other input builders)
		$name = $this->name ?? $this->id;
		if ($name === '') {
			throw new \InvalidArgumentException(sprintf('CheckboxField "%s" requires a name before rendering.', $this->id));
		}

		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties
		$context['checked_value'] = $this->checkedValue;
		$context['name']          = $name;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'label_text', $this->text);
		$this->_add_if_not_empty($context, 'unchecked_value', $this->uncheckedValue);
		$this->_add_if_true($context, 'default_checked', $this->defaultChecked);

		return $context;
	}

	protected function _get_component(): string {
		return 'checkbox';
	}
}
