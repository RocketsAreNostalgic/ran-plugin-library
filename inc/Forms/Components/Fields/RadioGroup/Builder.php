<?php
/**
 * Fluent radio group field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	protected ?string $legend  = null;
	protected ?string $name    = null;
	protected ?string $default = null;
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

	public function default(string $value): static {
		$this->default = $value;
		return $this;
	}

	// attribute() method inherited from ComponentBuilderBase

	/**
	 * @param array<string,string> $attributes
	 * @param array<string,string> $labelAttributes
	 */
	public function option(string $value, string $label, ?string $description = null, array $attributes = array(), array $labelAttributes = array(), bool $disabled = false): static {
		$this->options[] = array(
		    'value'            => $value,
		    'label'            => $label,
		    'description'      => $description,
		    'attributes'       => array_map('strval', $attributes),
		    'label_attributes' => array_map('strval', $labelAttributes),
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
			if ($this->default !== null && $this->default === $option['value']) {
				$optionContext['checked'] = true;
			}
			$options[] = $optionContext;
		}

		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties
		$context['name']    = $name;
		$context['legend']  = $this->legend ?? $this->label;
		$context['options'] = $options;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'default', $this->default);

		return $context;
	}

	protected function _get_component(): string {
		return 'radio-group';
	}
}
