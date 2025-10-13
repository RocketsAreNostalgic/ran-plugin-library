<?php
/**
 * Fluent media picker component definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\MediaPicker;

use Ran\PluginLib\Forms\Component\Build\BuilderBase;

final class Builder extends BuilderBase {
	/** @var array<string,string> */
	private array $data            = array();
	private ?string $name          = null;
	private ?string $inputId       = null;
	private ?string $buttonId      = null;
	private ?string $removeId      = null;
	private ?string $value         = null;
	private ?string $descriptionId = null;
	private ?string $selectLabel   = null;
	private ?string $replaceLabel  = null;
	private ?string $removeLabel   = null;
	private ?string $previewHtml   = null;
	private bool $multiple         = false;
	private ?bool $hasSelection    = null;

	public function __construct(string $id, string $label) {
		parent::__construct($id, $label);
	}

	// attributes() and attribute() methods inherited from BuilderBase

	public function data(array $data): self {
		foreach ($data as $key => $value) {
			$this->data[(string) $key] = (string) $value;
		}
		return $this;
	}

	public function name(?string $name): self {
		$this->name = $name;
		return $this;
	}

	public function inputId(?string $id): self {
		$this->inputId = $id;
		return $this;
	}

	public function buttonId(?string $id): self {
		$this->buttonId = $id;
		return $this;
	}

	public function removeId(?string $id): self {
		$this->removeId = $id;
		return $this;
	}

	public function value(?string $value): self {
		$this->value = $value;
		return $this;
	}

	// description() method inherited from BuilderBase

	public function descriptionId(?string $descriptionId): self {
		$this->descriptionId = $descriptionId;
		return $this;
	}

	public function selectLabel(?string $label): self {
		$this->selectLabel = $label;
		return $this;
	}

	public function replaceLabel(?string $label): self {
		$this->replaceLabel = $label;
		return $this;
	}

	public function removeLabel(?string $label): self {
		$this->removeLabel = $label;
		return $this;
	}

	public function previewHtml(?string $html): self {
		$this->previewHtml = $html;
		return $this;
	}

	public function multiple(bool $multiple = true): self {
		$this->multiple = $multiple;
		return $this;
	}

	public function hasSelection(?bool $hasSelection): self {
		$this->hasSelection = $hasSelection;
		return $this;
	}


	protected function _build_component_context(): array {
		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add data array if not empty
		$this->_add_if_not_empty($context, 'data', $this->data);

		// Add all optional properties using base class helpers
		$this->_add_if_not_empty($context, 'name', $this->name);
		$this->_add_if_not_empty($context, 'id', $this->inputId);
		$this->_add_if_not_empty($context, 'button_id', $this->buttonId);
		$this->_add_if_not_empty($context, 'remove_id', $this->removeId);
		$this->_add_if_not_empty($context, 'value', $this->value);
		$this->_add_if_not_empty($context, 'description_id', $this->descriptionId);
		$this->_add_if_not_empty($context, 'select_label', $this->selectLabel);
		$this->_add_if_not_empty($context, 'replace_label', $this->replaceLabel);
		$this->_add_if_not_empty($context, 'remove_label', $this->removeLabel);
		$this->_add_if_not_empty($context, 'preview_html', $this->previewHtml);

		// Add boolean properties
		$this->_add_if_true($context, 'multiple', $this->multiple);

		// Add nullable boolean
		if ($this->hasSelection !== null) {
			$context['has_selection'] = $this->hasSelection;
		}

		return $context;
	}

	protected function _get_component(): string {
		return 'components.media-picker';
	}
}
