<?php
/**
 * Fluent file upload component definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase;

final class Builder extends ComponentBuilderInputBase {
	private bool $multiple = false;
	/** @var string|array<int,string>|null */
	private $accept = null;
	/** @var array<int,string> */
	private array $existing_files = array();

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
	 * Sets the accepted file types.
	 *
	 * @param string|array<int,string>|null $accept
	 */
	public function accept($accept): static {
		$this->accept = $accept;
		return $this;
	}

	/**
	 * @param array<int,string> $files
	 */
	public function existing_files(array $files): static {
		$this->existing_files = array_map('strval', $files);
		return $this;
	}

	/**
	 * Build the component context.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_component_context(): array {
		// Start with input context (includes name, disabled, required, etc.)
		$context = $this->_build_input_context();

		// Add file-upload-specific properties
		$this->_add_if_not_empty($context, 'accept', $this->accept);
		$this->_add_if_not_empty($context, 'existing_files', $this->existing_files);
		$this->_add_if_true($context, 'multiple', $this->multiple);

		return $context;
	}

	/**
	 * Get the component name.
	 *
	 * @return string
	 */
	protected function _get_component(): string {
		return 'file-upload';
	}
}
