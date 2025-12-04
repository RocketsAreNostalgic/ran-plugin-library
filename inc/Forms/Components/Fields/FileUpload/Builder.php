<?php
/**
 * Fluent file upload component definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
final class Builder extends ComponentBuilderBase {
	private string $name;
	private bool $multiple         = false;
	private bool $required         = false;
	private ?string $descriptionId = null;
	private ?string $inputId       = null;
	/** @var string|array<int,string>|null */
	private $accept = null;
	/** @var array<int,string> */
	private array $existing_files = array();

	public function __construct(string $id, string $label, string $name) {
		parent::__construct($id, $label);
		$this->name = $name;
	}

	public function name(string $name): static {
		$this->name = $name;
		return $this;
	}

	// attributes() and attribute() methods inherited from ComponentBuilderBase

	/**
	 * Allow multiple files to be uploaded.
	 *
	 * @param  bool $multiple
	 *
	 * @return self
	 */
	public function multiple(bool $multiple = true): static {
		$this->multiple = $multiple;
		return $this;
	}

	/**
	 * Sets whether the input is required.
	 *
	 * @param  bool $required
	 *
	 * @return self
	 */
	public function required(bool $required = true): static {
		$this->required = $required;
		return $this;
	}

	/**
	 * Sets the accepted file types.
	 *
	 * @param string|array<int,string>|null $accept
	 */
	public function accept($accept): static {
		$this->accept = $accept;
		return $this;
	}

	/**
	 * @param string|array<int,string>|null $accept
	 */
	// description() method inherited from ComponentBuilderBase

	public function descriptionId(?string $descriptionId): static {
		$this->descriptionId = $descriptionId;
		return $this;
	}

	public function inputId(?string $id): static {
		$this->inputId = $id;
		return $this;
	}

	/**
	 * @param array<int,string> $files
	 */
	public function existing_files(array $files): static {
		$this->existing_files = array_map('strval', $files);
		return $this;
	}


	protected function _build_component_context(): array {
		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add required properties
		$context['name'] = $this->name;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'id', $this->inputId);
		$this->_add_if_not_empty($context, 'description_id', $this->descriptionId);
		$this->_add_if_not_empty($context, 'accept', $this->accept);
		$this->_add_if_not_empty($context, 'existing_files', $this->existing_files);
		$this->_add_if_true($context, 'multiple', $this->multiple);
		$this->_add_if_true($context, 'required', $this->required);

		return $context;
	}

	protected function _get_component(): string {
		return 'components.file-upload';
	}
}
