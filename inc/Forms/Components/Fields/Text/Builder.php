<?php
/**
 * Fluent text input field builder.
 *
 * Provides a specialized builder for single-line text inputs with
 * text-specific attributes like autocomplete, pattern, and length constraints.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Text;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase;

final class Builder extends ComponentBuilderTextBase {
	protected ?int $size = null;

	/**
	 * Sets the visible width of the input in characters.
	 *
	 * @param int|null $size
	 * @return static
	 */
	public function size(?int $size): static {
		$this->size = $size;
		return $this;
	}

	/**
	 * Get the size value.
	 *
	 * @return int|null
	 */
	public function get_size(): ?int {
		return $this->size;
	}

	/**
	 * Build the component context.
	 *
	 * Forces input_type to 'text' and includes text-specific attributes.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_component_context(): array {
		$context = $this->_build_text_context();

		// Force input type to text
		$context['input_type'] = 'text';

		// Add size if set
		if ($this->size !== null) {
			$context['size'] = $this->size;
		}

		return $context;
	}

	/**
	 * Get the component identifier.
	 *
	 * @return string
	 */
	protected function _get_component(): string {
		return 'text';
	}
}
