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
	/** @var string|callable|null */
	protected mixed $default = null;
	/** @var bool|callable */
	protected mixed $disabled = false;
	/** @var bool|callable */
	protected mixed $required = false;
	/** @var array<int,array<string,mixed>>|callable */
	protected mixed $options = array();

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

	/**
	 * Set the default value.
	 *
	 * @param string|callable|null $default String value or callable that returns string.
	 */
	public function default(string|callable|null $default): static {
		$this->default = $default;
		return $this;
	}

	/**
	 * Set the disabled state.
	 *
	 * @param bool|callable $disabled Boolean or callable that returns bool.
	 */
	public function disabled(bool|callable $disabled = true): static {
		$this->disabled = $disabled;
		return $this;
	}

	/**
	 * Set the required state.
	 *
	 * @param bool|callable $required Boolean or callable that returns bool.
	 */
	public function required(bool|callable $required = true): static {
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

	/**
	 * Set options for the select field.
	 *
	 * @param array|callable $options Array of options or callable that returns array.
	 */
	public function options(array|callable $options): static {
		$this->options = $options;
		return $this;
	}

	protected function _build_component_context(): array {
		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties (resolve callable if needed)
		$context['options'] = $this->_resolve_options();

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'name', $this->name);
		$this->_add_if_not_empty($context, 'id', $this->element_id);
		$this->_add_if_not_empty($context, 'description_id', $this->description_id);
		$this->_add_if_not_empty($context, 'value', $this->value);
		$this->_add_if_not_empty($context, 'default', $this->_resolve_callable($this->default));
		$this->_add_if_true($context, 'disabled', $this->_resolve_bool_callable($this->disabled));
		$this->_add_if_true($context, 'required', $this->_resolve_bool_callable($this->required));

		return $context;
	}

	protected function _get_component(): string {
		return 'select';
	}

	/**
	 * Resolve options, handling callable if set.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function _resolve_options(): array {
		$options = is_callable($this->options) ? ($this->options)() : $this->options;
		return $this->_normalize_options($options);
	}

	/**
	 * Normalize options array to consistent format.
	 *
	 * @param array $options Raw options array.
	 * @return array<int,array<string,mixed>>
	 */
	private function _normalize_options(array $options): array {
		$normalized = array();
		foreach ($options as $option) {
			if (!is_array($option)) {
				continue;
			}
			$item = array(
			    'value'      => isset($option['value']) ? (string) $option['value'] : '',
			    'label'      => isset($option['label']) ? (string) $option['label'] : (isset($option['value']) ? (string) $option['value'] : ''),
			    'attributes' => isset($option['attributes']) && is_array($option['attributes']) ? array_map('strval', $option['attributes']) : array(),
			);
			if (isset($option['group']) && $option['group'] !== null && $option['group'] !== '') {
				$item['group'] = (string) $option['group'];
			}
			if (!empty($option['selected'])) {
				$item['selected'] = true;
			}
			if (!empty($option['disabled'])) {
				$item['disabled'] = true;
			}
			$normalized[] = $item;
		}
		return $normalized;
	}

	/**
	 * Resolve a bool|callable to a bool value.
	 */
	private function _resolve_bool_callable(mixed $value): bool {
		if (is_callable($value)) {
			return (bool) $value();
		}
		return (bool) $value;
	}

	/**
	 * Resolve a callable to its value, or return the value as-is.
	 */
	private function _resolve_callable(mixed $value): mixed {
		if (is_callable($value)) {
			return $value();
		}
		return $value;
	}
}
