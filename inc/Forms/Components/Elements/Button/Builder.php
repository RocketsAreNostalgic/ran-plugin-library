<?php
/**
 * Fluent button component definition.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Components\Elements\Button;

use Ran\PluginLib\Forms\CallableRegistry;
use Ran\PluginLib\Forms\Component\Build\CallableKeysProviderInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class Builder extends ComponentBuilderBase implements CallableKeysProviderInterface {
	private string $type       = 'button';
	private mixed $disabled    = false;
	private string $variant    = 'primary';
	private ?string $icon_html = null;

	public function __construct(string $id, string $label) {
		parent::__construct($id, $label);
	}

	public static function register_callable_keys(CallableRegistry $registry): void {
		$registry->register_bool_key('disabled');
		$registry->register_value_key('default');
		$registry->register_value_key('options');
		$registry->register_string_key('style');
		$registry->register_nested_rule('options.*.disabled', 'bool');
	}

	/**
	 * Sets the button type.
	 *
	 * @param string $type
	 */
	public function type(string $type): static {
		$this->type = $type;
		return $this;
	}

	/**
	 * Disables the button.
	 *
	 * @param bool|callable $disabled
	 */
	public function disabled(bool|callable $disabled = true): static {
		$this->disabled = $disabled;
		return $this;
	}

	/**
	 * Sets the button variant.
	 *
	 * @param string $variant
	 */
	public function variant(string $variant): static {
		$this->variant = strtolower($variant);
		return $this;
	}

	/**
	 * Sets the button icon HTML.
	 *
	 * @param string|null $html
	 */
	public function icon_html(?string $html): static {
		$this->icon_html = $html;
		return $this;
	}

	/**
	 * Gets the button type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Gets the button variant.
	 *
	 * @return string
	 */
	public function get_variant(): string {
		return $this->variant;
	}

	/**
	 * Gets the button icon HTML.
	 *
	 * @return string|null
	 */
	public function get_icon_html(): ?string {
		return $this->icon_html;
	}

	protected function _build_component_context(): array {
		// Start with base context (attributes, description)
		$context = $this->_build_base_context();

		// Add button-specific properties
		$context['label'] = $this->label;

		// Add optional properties using base class helpers
		$this->_add_if_not_empty($context, 'type', $this->type !== 'button' ? $this->type : null);
		if (is_callable($this->disabled)) {
			$context['disabled'] = $this->disabled;
		} else {
			$this->_add_if_true($context, 'disabled', (bool) $this->disabled);
		}
		$this->_add_if_not_empty($context, 'variant', $this->variant !== 'primary' ? $this->variant : null);
		$this->_add_if_not_empty($context, 'icon_html', $this->icon_html);

		return $context;
	}

	protected function _get_component(): string {
		return 'components.button';
	}
}
