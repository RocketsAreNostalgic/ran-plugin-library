<?php
/**
 * Fluent media picker component definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\MediaPicker;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase {
	/** @var array<string,string> */
	private array $data             = array();
	private ?string $name           = null;
	private ?string $input_id       = null;
	private ?string $button_id      = null;
	private ?string $remove_id      = null;
	private ?string $value          = null;
	private ?string $description_id = null;
	private ?string $select_label   = null;
	private ?string $replace_label  = null;
	private ?string $remove_label   = null;
	private ?string $preview_html   = null;
	private bool $multiple          = false;
	private ?bool $has_selection    = null;
	/** @var bool|callable */
	private mixed $required = false;

	public function __construct(string $id, string $label) {
		parent::__construct($id, $label);
	}

	// attributes() and attribute() methods inherited from ComponentBuilderBase

	public function data(array $data): static {
		foreach ($data as $key => $value) {
			$this->data[(string) $key] = (string) $value;
		}
		return $this;
	}

	public function name(?string $name): static {
		$this->name = $name;
		return $this;
	}

	public function input_id(?string $id): static {
		$this->input_id = $id;
		return $this;
	}

	public function button_id(?string $id): static {
		$this->button_id = $id;
		return $this;
	}

	public function remove_id(?string $id): static {
		$this->remove_id = $id;
		return $this;
	}

	public function value(?string $value): static {
		$this->value = $value;
		return $this;
	}

	public function required(bool|callable $required = true): static {
		$this->required = $required;
		return $this;
	}

	// description() method inherited from ComponentBuilderBase

	public function description_id(?string $description_id): static {
		$this->description_id = $description_id;
		return $this;
	}

	public function select_label(?string $label): static {
		$this->select_label = $label;
		return $this;
	}

	public function replace_label(?string $label): static {
		$this->replace_label = $label;
		return $this;
	}

	public function remove_label(?string $label): static {
		$this->remove_label = $label;
		return $this;
	}

	public function preview_html(?string $html): static {
		$this->preview_html = $html;
		return $this;
	}

	public function multiple(bool $multiple = true): static {
		$this->multiple = $multiple;
		return $this;
	}

	public function has_selection(?bool $has_selection): static {
		$this->has_selection = $has_selection;
		return $this;
	}


	protected function _build_component_context(): array {
		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add data array if not empty
		$this->_add_if_not_empty($context, 'data', $this->data);

		// Add all optional properties using base class helpers
		$this->_add_if_not_empty($context, 'name', $this->name);
		$this->_add_if_not_empty($context, 'id', $this->input_id);
		$this->_add_if_not_empty($context, 'button_id', $this->button_id);
		$this->_add_if_not_empty($context, 'remove_id', $this->remove_id);
		$this->_add_if_not_empty($context, 'value', $this->value);
		$this->_add_if_not_empty($context, 'description_id', $this->description_id);
		$this->_add_if_not_empty($context, 'select_label', $this->select_label);
		$this->_add_if_not_empty($context, 'replace_label', $this->replace_label);
		$this->_add_if_not_empty($context, 'remove_label', $this->remove_label);
		$this->_add_if_not_empty($context, 'preview_html', $this->preview_html);
		if (is_callable($this->required)) {
			$context['required'] = $this->required;
		} else {
			$this->_add_if_true($context, 'required', (bool) $this->required);
		}

		// Add boolean properties
		$this->_add_if_true($context, 'multiple', $this->multiple);

		// Add nullable boolean
		if ($this->has_selection !== null) {
			$context['has_selection'] = $this->has_selection;
		}

		return $context;
	}

	protected function _get_component(): string {
		return 'components.media-picker';
	}
}
