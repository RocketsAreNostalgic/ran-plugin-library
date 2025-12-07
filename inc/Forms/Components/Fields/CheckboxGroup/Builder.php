<?php
/**
 * Fluent checkbox group field definition (multiple checkboxes).
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxGroup;

use Ran\PluginLib\Forms\Components\Fields\CheckboxOption\Builder as CheckboxOptionBuilder;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	private ?string $legend = null;
	/** @var array<int,CheckboxOptionBuilder|array<string,mixed>> */
	private array $options = array();
	/** @var array<int,string> */
	private array $defaults     = array();
	private int $__option_index = 0;

	public function __construct(string $id, string $label) {
		parent::__construct($id, $label);
	}

	public function legend(?string $legend): static {
		$this->legend = $legend;
		return $this;
	}

	// description() method inherited from ComponentBuilderBase

	public function defaults(array $values): static {
		$this->defaults = array_map('strval', $values);
		return $this;
	}

	// attribute() method inherited from ComponentBuilderBase

	public function checkbox(string $value, string $label, ?string $description = null, array $attributes = array(), bool $default_checked = false): static {
		$optionId = $this->id . '__option_' . (++$this->__option_index);
		$name     = $this->resolveName($attributes);
		$builder  = new CheckboxOptionBuilder($optionId, $label, $name);
		$builder->value($value);
		if ($description !== null) {
			$builder->description($description);
		}
		$builder->default_checked($default_checked);
		foreach ($attributes as $key => $attrValue) {
			$builder->attribute((string) $key, (string) $attrValue);
		}
		if (!$default_checked && !empty($attributes['checked'])) {
			$builder->default_checked(true);
		}
		if (!empty($attributes['disabled'])) {
			$builder->disabled(true);
		}
		$this->options[] = $builder;
		return $this;
	}

	public function option(CheckboxOptionBuilder $option): static {
		$this->__option_index++;
		$this->options[] = $option;
		return $this;
	}


	protected function _build_component_context(): array {
		$contextOptions = array();
		foreach ($this->options as $option) {
			if ($option instanceof CheckboxOptionBuilder) {
				$contextOptions[] = $option->to_array()['component_context'];
				continue;
			}
			$contextOptions[] = $option;
		}

		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties
		$context['legend']  = $this->legend ?? $this->label;
		$context['options'] = $contextOptions;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'defaults', $this->defaults);

		return $context;
	}

	protected function _get_component(): string {
		return 'checkbox-group';
	}

	private function resolveName(array $attributes): string {
		if (isset($attributes['name']) && trim((string) $attributes['name']) !== '') {
			return (string) $attributes['name'];
		}
		return $this->id . '[]';
	}
}
