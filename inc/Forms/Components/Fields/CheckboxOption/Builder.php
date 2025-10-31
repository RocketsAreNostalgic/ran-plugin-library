<?php
/**
 * Fluent checkbox option definition for checkbox-group components.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxOption;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	private string $name;
	private string $value        = 'on';
	private bool $defaultChecked = false;
	private bool $disabled       = false;

	public function __construct(string $id, string $label, string $name) {
		parent::__construct($id, $label);
		$this->setName($name);
		$this->attributes['value'] = $this->value;
	}

	// description() method inherited from ComponentBuilderBase

	public function value(string $value): self {
		$this->value               = $value;
		$this->attributes['value'] = $this->value;
		return $this;
	}

	public function defaultChecked(bool $checked = true): self {
		$this->defaultChecked = $checked;
		return $this;
	}

	public function disabled(bool $disabled = true): self {
		$this->disabled = $disabled;
		return $this;
	}

	public function attribute(string $key, string $value): self {
		parent::attribute($key, $value);
		if ($key === 'name') {
			$this->setName($value);
		}
		if ($key === 'value') {
			$this->value = (string) $value;
			parent::attribute('value', $this->value);
		}
		return $this;
	}


	protected function _build_component_context(): array {
		if ($this->name === '') {
			throw new \InvalidArgumentException(sprintf('CheckboxOption "%s" requires a name before rendering.', $this->id));
		}

		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Override attributes to include required fields
		$context['attributes']['name']  = $this->name;
		$context['attributes']['value'] = $this->value;
		if ($this->disabled) {
			$context['attributes']['disabled'] = 'disabled';
		}

		// Add required properties
		$context['label'] = $this->label;
		$context['name']  = $this->name;
		$context['value'] = $this->value;

		// Add optional properties using base class helpers
		$this->_add_if_true($context, 'default_checked', $this->defaultChecked);
		$this->_add_if_true($context, 'disabled', $this->disabled);

		return $context;
	}

	protected function _get_component(): string {
		return 'checkbox-option';
	}

	private function setName(string $name): void {
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new \InvalidArgumentException('CheckboxOption requires a non-empty name.');
		}
		$this->name = $trimmed;
		parent::attribute('name', $this->name);
	}
}
