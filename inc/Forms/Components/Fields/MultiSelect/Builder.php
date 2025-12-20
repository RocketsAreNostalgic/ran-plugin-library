<?php
/**
 * Fluent multi-select field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MultiSelect;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Component\Build\CallableKeysProviderInterface;
use Ran\PluginLib\Forms\CallableRegistry;

final class Builder extends ComponentBuilderBase implements CallableKeysProviderInterface {
	private ?string $name           = null;
	private ?string $element_id     = null;
	private ?string $description_id = null;
	/** @var array<int,string> */
	private array $values = array();
	/** @var array<int,string>|callable */
	private mixed $default_values = array();
	/** @var bool|callable */
	private mixed $disabled = false;
	/** @var bool|callable */
	private mixed $required = false;
	/** @var array<int,array<string,mixed>>|callable */
	private mixed $options = array();

	public function __construct(string $id, string $label) {
		parent::__construct($id, $label);
	}

	public static function register_callable_keys(CallableRegistry $registry): void {
		$registry->register_bool_key('disabled');
		$registry->register_bool_key('required');
		$registry->register_value_key('default');
		$registry->register_value_key('options');
		$registry->register_nested_rule('options.*.disabled', 'bool');
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

	public function values(array $values): static {
		$this->values = array_map('strval', $values);
		return $this;
	}

	/**
	 * Set default values.
	 *
	 * @param array|callable $values Array of values or callable that returns array.
	 */
	public function default_values(array|callable $values): static {
		$this->default_values = $values;
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
	 * Set options for the multi-select field.
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

		// Add multiple attribute
		$context['attributes']['multiple'] = 'multiple';

		// Add required properties (resolve callable if needed)
		$context['options'] = $this->options;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'name', $this->name);
		$this->_add_if_not_empty($context, 'id', $this->element_id);
		$this->_add_if_not_empty($context, 'description_id', $this->description_id);
		$this->_add_if_not_empty($context, 'values', $this->values);
		if (is_callable($this->default_values)) {
			$context['default'] = $this->default_values;
		} else {
			$resolved_defaults = array_map('strval', $this->default_values);
			$this->_add_if_not_empty($context, 'default', $resolved_defaults);
		}
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

		return $context;
	}

	protected function _get_component(): string {
		return 'fields.multi-select';
	}
}
