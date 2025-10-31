<?php
/**
 * Fluent text input field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Input;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase;

final class Builder extends ComponentBuilderTextBase {
	private string $type = 'text';

	/**
	 * Sets the type of the input element.
	 * Supported input types include: text, number, range, email, url, password, date, datetime-local, time, file, tel, search, checkbox, radio, select, range, color, and hidden.
	 *
	 * Many types have custom components with default validation and normalization support, so it is recommended to use them where possible.
	 *
	 * @param string $type
	 * @return self
	 */
	public function type(string $type): self {
		$this->type = $type;
		return $this;
	}

	protected function _build_component_context(): array {
		// Start with text context (includes input and base context)
		$context = $this->_build_text_context();

		// Add input-specific properties
		$context['input_type'] = $this->type;

		return $context;
	}

	protected function _get_component(): string {
		return 'text';
	}

	/**
	 * Get the input type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}
}
