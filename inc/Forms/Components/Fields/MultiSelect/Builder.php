<?php
/**
 * Fluent multi-select field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MultiSelect;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	private ?string $name          = null;
	private ?string $elementId     = null;
	private ?string $descriptionId = null;
	/** @var array<int,string> */
	private array $values = array();
	/** @var array<int,string> */
	private array $defaultValues = array();
	private bool $disabled       = false;
	/** @var array<int,array<string,mixed>> */
	private array $options = array();

	public function __construct(string $id, string $label) {
		parent::__construct($id, $label);
	}

	public function name(?string $name): static {
		if ($name === null) {
			$this->name = null;
			unset($this->attributes['name']);
			return $this;
		}
		$trimmed                  = trim($name);
		$this->name               = $trimmed;
		$this->attributes['name'] = $trimmed;
		return $this;
	}

	public function elementId(?string $id): static {
		if ($id === null) {
			$this->elementId = null;
			unset($this->attributes['id']);
			return $this;
		}
		$trimmed                = trim($id);
		$this->elementId        = $trimmed;
		$this->attributes['id'] = $trimmed;
		return $this;
	}

	// description() method inherited from ComponentBuilderBase

	public function descriptionId(?string $descriptionId): static {
		$this->descriptionId = $descriptionId;
		return $this;
	}

	public function values(array $values): static {
		$this->values = array_map('strval', $values);
		return $this;
	}

	public function defaultValues(array $values): static {
		$this->defaultValues = array_map('strval', $values);
		return $this;
	}

	public function disabled(bool $disabled = true): static {
		$this->disabled = $disabled;
		return $this;
	}

	public function attribute(string $key, string $value): static {
		parent::attribute($key, $value);
		if ($key === 'name') {
			$this->name = trim($value);
		}
		if ($key === 'id') {
			$this->elementId = trim($value);
		}
		return $this;
	}

	public function option(
		string $value,
		string $label,
		?string $group = null,
		array $attributes = array(),
		bool $selected = false,
		bool $disabled = false
	): static {
		$this->options[] = array(
		    'value'      => $value,
		    'label'      => $label,
		    'group'      => $group,
		    'attributes' => array_map('strval', $attributes),
		    'selected'   => $selected,
		    'disabled'   => $disabled,
		);
		return $this;
	}

	public function options(array $options): static {
		$this->options = array();
		foreach ($options as $option) {
			if (!is_array($option)) {
				continue;
			}
			$this->options[] = array(
			    'value'      => isset($option['value']) ? (string) $option['value'] : '',
			    'label'      => isset($option['label']) ? (string) $option['label'] : (isset($option['value']) ? (string) $option['value'] : ''),
			    'group'      => isset($option['group']) ? (string) $option['group'] : null,
			    'attributes' => isset($option['attributes']) && is_array($option['attributes']) ? array_map('strval', $option['attributes']) : array(),
			    'selected'   => !empty($option['selected']),
			    'disabled'   => !empty($option['disabled']),
			);
		}
		return $this;
	}

	protected function _build_component_context(): array {
		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add multiple attribute
		$context['attributes']['multiple'] = 'multiple';

		// Add required properties
		$context['options'] = $this->normalizeOptions();

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'name', $this->name);
		$this->_add_if_not_empty($context, 'id', $this->elementId);
		$this->_add_if_not_empty($context, 'description_id', $this->descriptionId);
		$this->_add_if_not_empty($context, 'values', $this->values);
		$this->_add_if_not_empty($context, 'default', $this->defaultValues);
		$this->_add_if_true($context, 'disabled', $this->disabled);

		return $context;
	}

	protected function _get_component(): string {
		return 'fields.multi-select';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function normalizeOptions(): array {
		$normalized = array();
		foreach ($this->options as $option) {
			$item = array(
			    'value'      => $option['value'],
			    'label'      => $option['label'],
			    'attributes' => $option['attributes'],
		);
			if ($option['group'] !== null && $option['group'] !== '') {
				$item['group'] = (string) $option['group'];
			}
			if ($option['selected']) {
				$item['selected'] = true;
			}
			if ($option['disabled']) {
				$item['disabled'] = true;
			}
			$normalized[] = $item;
		}
		return $normalized;
	}
}
