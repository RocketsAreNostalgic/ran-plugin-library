<?php
/**
 * Generic builder for injecting arbitrary HTML content into forms.
 *
 * Use this builder when you need to insert custom HTML markup that doesn't
 * require form validation - such as dividers, informational blocks, custom
 * layouts, or decorative elements.
 *
 * This builder includes safeguards to prevent misuse:
 * - Form input elements (input, textarea, select) are blocked - use proper
 *   field components with validation instead.
 * - Script/style tags are blocked - use WordPress enqueue functions instead.
 * - Security-sensitive tags (iframe, object, embed) are blocked.
 *
 * @package Ran\PluginLib\Forms\Component\Build
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Component\Build;

use Ran\PluginLib\Forms\Component\ComponentManifest;
use InvalidArgumentException;

class GenericComponentBuilder extends ComponentBuilderBase {
	/**
	 * Form input elements that must use proper component builders with validation.
	 *
	 * @var array<string,string>
	 */
	private const FORM_INPUT_TAGS = array(
		'input'    => 'Use an existing component (e.g., fields.input, fields.checkbox) or create a custom component with proper validation.',
		'textarea' => 'Use the fields.textarea component or create a custom component with proper validation.',
		'select'   => 'Use the fields.select component or create a custom component with proper validation.',
		'button'   => 'Use the components.button or submit-button component.',
		'form'     => 'Forms are managed by AdminSettings/UserSettings. Do not inject raw form tags.',
	);

	/**
	 * Asset/script tags that should use WordPress enqueue system.
	 *
	 * @var array<string,string>
	 */
	private const ASSET_TAGS = array(
		'script' => 'Use wp_enqueue_script() or the ScriptDefinition in ComponentRenderResult to properly enqueue JavaScript.',
		'style'  => 'Use wp_enqueue_style() or the StyleDefinition in ComponentRenderResult to properly enqueue CSS.',
		'link'   => 'Use wp_enqueue_style() for stylesheets or wp_enqueue_script() for preload hints.',
	);

	/**
	 * Security-sensitive tags that should never be injected.
	 *
	 * @var array<string,string>
	 */
	private const SECURITY_TAGS = array(
		'iframe' => 'Iframes pose security risks. If you need to embed external content, create a dedicated component with proper sandboxing.',
		'object' => 'Object embeds are deprecated and pose security risks. Use modern alternatives.',
		'embed'  => 'Embed tags pose security risks. Use WordPress oEmbed or a dedicated component.',
	);

	private string $componentAlias;

	/** @var array<string,mixed> */
	private array $context = array();

	/**
	 * Create a builder for the given component alias.
	 *
	 * @param string $id The field ID.
	 * @param string $label The field label.
	 * @param string $componentAlias The component alias to use.
	 */
	public function __construct(string $id, string $label, string $componentAlias = 'generic.html') {
		parent::__construct($id, $label);
		$this->componentAlias = $componentAlias;
	}

	/**
	 * Create a factory closure for a specific component alias.
	 *
	 * Usage:
	 *   $manifest->register_builder('fields.smoke-input', GenericComponentBuilder::factory('fields.smoke-input'));
	 *
	 * @param string $componentAlias The component alias.
	 *
	 * @return callable(string,string):GenericComponentBuilder
	 */
	public static function factory(string $componentAlias): callable {
		return fn(string $id, string $label) => new self($id, $label, $componentAlias);
	}

	/**
	 * Register a generic builder for a component on a manifest.
	 *
	 * This is a convenience method that registers both the render factory
	 * and the builder factory for a component.
	 *
	 * @param ComponentManifest $manifest The manifest to register on.
	 * @param string $alias The component alias.
	 * @param callable|null $renderFactory Optional render factory. If null, a simple passthrough is used.
	 */
	public static function register_on(ComponentManifest $manifest, string $alias, ?callable $renderFactory = null): void {
		$manifest->register_builder($alias, self::factory($alias));

		if ($renderFactory !== null) {
			$manifest->register($alias, $renderFactory);
		}
	}

	/**
	 * Set arbitrary context values.
	 *
	 * @param string $key The context key.
	 * @param mixed $value The context value.
	 *
	 * @return static
	 */
	public function context(string $key, mixed $value): static {
		$this->context[$key] = $value;
		return $this;
	}

	/**
	 * Set multiple context values at once.
	 *
	 * @param array<string,mixed> $context The context values.
	 *
	 * @return static
	 */
	public function with_context(array $context): static {
		$this->context = array_merge($this->context, $context);
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	protected function _get_component(): string {
		return $this->componentAlias;
	}

	/**
	 * @inheritDoc
	 */
	protected function _build_component_context(): array {
		$context = array_merge($this->_build_base_context(), $this->context);

		// Scan context for forbidden HTML before returning
		$this->scan_for_forbidden_html($context);

		return $context;
	}

	/**
	 * Scan context values for forbidden HTML tags.
	 *
	 * @param array<string,mixed> $context The context to scan.
	 *
	 * @throws InvalidArgumentException If forbidden HTML is detected.
	 */
	private function scan_for_forbidden_html(array $context): void {
		$this->scan_value_recursive($context, '');
	}

	/**
	 * Recursively scan a value for forbidden HTML tags.
	 *
	 * @param mixed $value The value to scan.
	 * @param string $path The current path for error messages.
	 *
	 * @throws InvalidArgumentException If forbidden HTML is detected.
	 */
	private function scan_value_recursive(mixed $value, string $path): void {
		if (is_string($value) && $value !== '') {
			$this->check_string_for_forbidden_tags($value, $path);
		} elseif (is_array($value)) {
			foreach ($value as $key => $item) {
				$childPath = $path !== '' ? "{$path}.{$key}" : (string) $key;
				$this->scan_value_recursive($item, $childPath);
			}
		}
	}

	/**
	 * Check a string for forbidden HTML tags.
	 *
	 * @param string $value The string to check.
	 * @param string $path The context path for error messages.
	 *
	 * @throws InvalidArgumentException If forbidden HTML is detected.
	 */
	private function check_string_for_forbidden_tags(string $value, string $path): void {
		$allForbiddenTags = array_merge(
			self::FORM_INPUT_TAGS,
			self::ASSET_TAGS,
			self::SECURITY_TAGS
		);

		foreach ($allForbiddenTags as $tag => $guidance) {
			// Match opening tags like <input, <input>, <input type="text">, < input (with space)
			if (preg_match('/<\s*' . preg_quote($tag, '/') . '[\s>\/]/i', $value)) {
				$location = $path !== '' ? " (found in context key '{$path}')" : '';

				throw new InvalidArgumentException(
					"Forbidden HTML tag <{$tag}> detected{$location}.\n\n" .
					"Guidance: {$guidance}"
				);
			}
		}
	}
}
