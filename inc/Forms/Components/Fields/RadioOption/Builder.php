<?php
/**
 * Fluent radio option definition for radio-group components.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioOption;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	private string $name;
	private string $value;
	private bool $checked  = false;
	private bool $disabled = false;
	/** @var array<string,string> */
	private array $labelAttributes = array();

	public function __construct(string $id, string $label, string $name, string $value) {
		parent::__construct($id, $label);
		$this->setName($name);
		$this->value = $value;
	}

	// description() method inherited from ComponentBuilderBase

	public function checked(bool $checked = true): self {
		$this->checked = $checked;
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
		}
		return $this;
	}

	public function labelAttribute(string $key, string $value): self {
		$this->labelAttributes[$key] = (string) $value;
		return $this;
	}

	protected function _build_component_context(): array {
		if ($this->name === '') {
			throw new \InvalidArgumentException(sprintf('RadioOption "%s" requires a non-empty name.', $this->id));
		}

		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Override attributes to include required fields
		$context['attributes']['name']  = $this->name;
		$context['attributes']['value'] = $this->value;

		if ($this->checked) {
			$context['attributes']['checked'] = 'checked';
		}
		if ($this->disabled) {
			$context['attributes']['disabled'] = 'disabled';
		}

		// Add required properties
		$context['label']            = $this->label;
		$context['label_attributes'] = $this->labelAttributes;

		// Add optional properties using base class helpers
		$this->_add_if_true($context, 'checked', $this->checked);
		$this->_add_if_true($context, 'disabled', $this->disabled);

		return $context;
	}

	protected function _get_component(): string {
		return 'fields.radio-option';
	}

	private function setName(string $name): void {
		$trimmed = trim($name);
		if ($trimmed === '') {
			throw new \InvalidArgumentException('RadioOption requires a non-empty name.');
		}
		$this->name = $trimmed;
	}
}
