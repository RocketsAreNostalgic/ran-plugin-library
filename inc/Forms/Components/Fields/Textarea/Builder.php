<?php
/**
 * Fluent textarea field definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Fields\Textarea;

use Ran\PluginLib\Forms\Component\Build\BuilderTextBase;

final class Builder extends BuilderTextBase {
	private ?int $rows = null;
	private ?int $cols = null;

	/**
	 * Sets the number of visible text lines for the textarea.
	 *
	 * @param int|null $rows
	 * @return self
	 */
	public function rows(?int $rows): self {
		$this->rows = $rows;
		return $this;
	}

	/**
	 * Sets the visible width of the textarea.
	 *
	 * @param int|null $cols
	 * @return self
	 */
	public function cols(?int $cols): self {
		$this->cols = $cols;
		return $this;
	}

	protected function _build_component_context(): array {
		// Start with text context (includes input and base context)
		$context = $this->_build_text_context();

		// Add textarea-specific properties
		if ($this->rows !== null) {
			$context['rows'] = $this->rows;
		}
		if ($this->cols !== null) {
			$context['cols'] = $this->cols;
		}

		return $context;
	}

	protected function _get_component(): string {
		return 'textarea';
	}

	/**
	 * Get the number of rows.
	 *
	 * @return int|null
	 */
	public function get_rows(): ?int {
		return $this->rows;
	}

	/**
	 * Get the number of columns.
	 *
	 * @return int|null
	 */
	public function get_cols(): ?int {
		return $this->cols;
	}
}
