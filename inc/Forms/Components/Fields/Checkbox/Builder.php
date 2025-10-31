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

	public function text(?string $text): self {
		$this->text = $text;
		return $this;
	}

	public function name(string $name): self {
		$this->name               = trim($name);
		$this->attributes['name'] = $this->name;
		return $this;
	}

	public function values(string $checkedValue, ?string $uncheckedValue = null): self {
		$this->checkedValue   = $checkedValue;
		$this->uncheckedValue = $uncheckedValue ?? 'off';
		return $this;
	}

	public function defaultChecked(bool $checked = true): self {
		$this->defaultChecked = $checked;
		return $this;
	}

	public function attribute(string $key, string $value): self {
		parent::attribute($key, $value);
		if ($key === 'name') {
			$this->name = trim($value);
		}
		return $this;
	}

	protected function _build_component_context(): array {
		if ($this->name === null || $this->name === '') {
			throw new \InvalidArgumentException(sprintf('CheckboxField "%s" requires a name before rendering.', $this->id));
		}

		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties
		$context['checked_value'] = $this->checkedValue;
		$context['name']          = $this->name;

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
