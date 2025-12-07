<?php
/**
 * Fluent select field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Select;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	protected ?string $name           = null;
	protected ?string $element_id     = null;
	protected ?string $description_id = null;
	protected ?string $value          = null;
	protected ?string $default        = null;
	protected bool $disabled          = false;
	protected bool $required          = false;
	/** @var array<int,array<string,mixed>> */
	protected array $options = array();

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

	public function element_id(?string $id): static {
		if ($id === null) {
			$this->element_id = null;
			unset($this->attributes['id']);
			return $this;
		}
		$trimmed                = trim($id);
		$this->element_id       = $trimmed;
		$this->attributes['id'] = $trimmed;
		return $this;
	}

	// description() method inherited from ComponentBuilderBase

	public function description_id(?string $description_id): static {
		$this->description_id = $description_id;
		return $this;
	}

	public function value(?string $value): static {
		$this->value = $value;
		return $this;
	}

	public function default(?string $default): static {
		$this->default = $default;
		return $this;
	}

	public function disabled(bool $disabled = true): static {
		$this->disabled = $disabled;
		return $this;
	}

	public function required(bool $required = true): static {
		$this->required = $required;
		return $this;
	}

	public function attribute(string $key, string $value): static {
		parent::attribute($key, $value);
		if ($key === 'name') {
			$this->name = trim($value);
		}
		if ($key === 'id') {
			$this->element_id = trim($value);
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

		// Add required properties
		$context['options'] = $this->normalizeOptions();

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'name', $this->name);
		$this->_add_if_not_empty($context, 'id', $this->element_id);
		$this->_add_if_not_empty($context, 'description_id', $this->description_id);
		$this->_add_if_not_empty($context, 'value', $this->value);
		$this->_add_if_not_empty($context, 'default', $this->default);
		$this->_add_if_true($context, 'disabled', $this->disabled);
		$this->_add_if_true($context, 'required', $this->required);

		return $context;
	}

	protected function _get_component(): string {
		return 'select';
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
