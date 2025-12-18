<?php
/**
 * Fluent radio group field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	protected ?string $legend = null;
	protected ?string $name   = null;
	/** @var string|callable|null */
	protected mixed $default = null;
	/** @var array<int,array{value:string,label:string,description:?string,attributes:array<string,string>,disabled:bool,label_attributes:array<string,string>}> */
	protected array $options = array();

	public function legend(?string $legend): static {
		$this->legend = $legend;
		return $this;
	}

	// description() method inherited from ComponentBuilderBase

	public function name(?string $name): static {
		$this->name = $name !== null ? trim($name) : null;
		return $this;
	}

	public function default(string|callable|null $value): static {
		$this->default = $value;
		return $this;
	}

	// attribute() method inherited from ComponentBuilderBase

	/**
	 * @param array<string,string> $attributes
	 * @param array<string,string> $label_attributes
	 */
	public function option(string $value, string $label, ?string $description = null, array $attributes = array(), array $label_attributes = array(), bool $disabled = false): static {
		$this->options[] = array(
		    'value'            => $value,
		    'label'            => $label,
		    'description'      => $description,
		    'attributes'       => array_map('strval', $attributes),
		    'label_attributes' => array_map('strval', $label_attributes),
		    'disabled'         => $disabled,
		);
		return $this;
	}

	protected function _build_component_context(): array {
		// Use field ID as default name if not explicitly set (consistent with other input builders)
		$name = $this->name ?? $this->id;
		if ($name === '') {
			throw new \InvalidArgumentException(sprintf('RadioGroup "%s" requires a name before rendering.', $this->id));
		}

		$options = array();
		foreach ($this->options as $option) {
			$optionContext         = $option;
			$optionContext['name'] = $name;
			$options[]             = $optionContext;
		}

		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties
		$context['name']    = $name;
		$context['legend']  = $this->legend ?? $this->label;
		$context['options'] = $options;

		// Add optional properties using base class helpers
		if (is_callable($this->default)) {
			$context['default'] = $this->default;
		} else {
			$this->_add_if_not_empty($context, 'default', $this->default);
		}

		return $context;
	}

	protected function _get_component(): string {
		return 'radio-group';
	}
}
