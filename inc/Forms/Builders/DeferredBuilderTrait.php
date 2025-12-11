<?php
/**
 * DeferredBuilderTrait - Provides deferred builder methods for registry builders.
 *
 * This trait adds section(), field(), group(), fieldset() and other fluent
 * builder methods that record calls for later replay on a target builder.
 * Used by lightweight registry builders (MenuRegistryPageBuilder, UserCollectionBuilder)
 * to provide a single-callback DX while maintaining lazy loading.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\DeferredCallRecorder;
use Ran\PluginLib\Util\Logger;

/**
 * Provides deferred builder methods for registry builders.
 *
 * Classes using this trait must:
 * 1. Call _initDeferred() in their constructor
 * 2. Implement _getDeferredRecorder(): DeferredCallRecorder
 * 3. Implement _hasDeferredCalls(): bool
 * 4. Handle before()/after() context-awareness if needed
 */
trait DeferredBuilderTrait {
	/**
	 * Deferred call recorder instance.
	 *
	 * @var DeferredCallRecorder
	 */
	private DeferredCallRecorder $deferred;

	/**
	 * Initialize the deferred call recorder.
	 *
	 * Call this in the constructor of the class using this trait.
	 *
	 * @param Logger|null $logger Optional logger for debugging.
	 * @return void
	 */
	protected function _initDeferred(?Logger $logger = null): void {
		$this->deferred = new DeferredCallRecorder($logger);
	}

	/**
	 * Get the deferred call recorder.
	 *
	 * @return DeferredCallRecorder
	 */
	public function getDeferred(): DeferredCallRecorder {
		return $this->deferred;
	}

	/**
	 * Check if deferred calls have been recorded.
	 *
	 * @return bool
	 */
	public function hasDeferred(): bool {
		return $this->deferred->hasCalls();
	}

	// =========================================================================
	// Deferred Builder Methods - Record calls for replay at render time
	// =========================================================================

	/**
	 * Define a section (deferred until render).
	 *
	 * @param string                    $section_id     Section identifier.
	 * @param string                    $title          Section title/heading.
	 * @param string|callable|null      $description_cb Optional description or callback.
	 * @param array                     $args           Optional additional arguments.
	 * @return static
	 */
	public function section(string $section_id, string $title = '', string|callable|null $description_cb = null, array $args = array()): static {
		$this->deferred->record('section', func_get_args());
		return $this;
	}

	/**
	 * End the current section (deferred).
	 *
	 * @return static
	 */
	public function end_section(): static {
		$this->deferred->record('end_section', array());
		return $this;
	}

	/**
	 * Define a group (deferred until render).
	 *
	 * @param string                    $group_id       Group identifier.
	 * @param string                    $heading        Group heading.
	 * @param string|callable|null      $description_cb Optional description or callback.
	 * @param array|null                $args           Optional additional arguments.
	 * @return static
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): static {
		$this->deferred->record('group', func_get_args());
		return $this;
	}

	/**
	 * End the current group (deferred).
	 *
	 * @return static
	 */
	public function end_group(): static {
		$this->deferred->record('end_group', array());
		return $this;
	}

	/**
	 * Define a fieldset (deferred until render).
	 *
	 * @param string                    $fieldset_id    Fieldset identifier.
	 * @param string                    $heading        Fieldset heading/legend.
	 * @param string|callable|null      $description_cb Optional description or callback.
	 * @param array|null                $args           Optional additional arguments.
	 * @return static
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): static {
		$this->deferred->record('fieldset', func_get_args());
		return $this;
	}

	/**
	 * End the current fieldset (deferred).
	 *
	 * @return static
	 */
	public function end_fieldset(): static {
		$this->deferred->record('end_fieldset', array());
		return $this;
	}

	/**
	 * Define a field (deferred until render).
	 *
	 * @param string      $field_id  Field identifier/key.
	 * @param string|null $label     Field label.
	 * @param string|null $component Component alias (e.g., 'fields.input', 'fields.select').
	 * @param array       $args      Additional arguments (context, order, field_template, etc.).
	 * @return static
	 */
	public function field(string $field_id, ?string $label = null, ?string $component = null, array $args = array()): static {
		$this->deferred->record('field', func_get_args());
		return $this;
	}

	/**
	 * End the current field (deferred).
	 *
	 * @return static
	 */
	public function end_field(): static {
		$this->deferred->record('end_field', array());
		return $this;
	}

	/**
	 * Set description on current element (deferred).
	 *
	 * @param string $description Description text.
	 * @return static
	 */
	public function description(string $description): static {
		$this->deferred->record('description', func_get_args());
		return $this;
	}

	/**
	 * Set placeholder on current field (deferred).
	 *
	 * @param string $placeholder Placeholder text.
	 * @return static
	 */
	public function placeholder(string $placeholder): static {
		$this->deferred->record('placeholder', func_get_args());
		return $this;
	}

	/**
	 * Set default value on current field (deferred).
	 *
	 * @param mixed $value Default value.
	 * @return static
	 */
	public function default(mixed $value): static {
		$this->deferred->record('default', func_get_args());
		return $this;
	}

	/**
	 * Set options on current field (deferred).
	 *
	 * @param array $options Options array.
	 * @return static
	 */
	public function options(array $options): static {
		$this->deferred->record('options', func_get_args());
		return $this;
	}

	/**
	 * Add validation rule (deferred).
	 *
	 * @param string $rule  Rule name.
	 * @param mixed  $value Rule value.
	 * @return static
	 */
	public function validate(string $rule, mixed $value = true): static {
		$this->deferred->record('validate', func_get_args());
		return $this;
	}

	/**
	 * Set attribute on current element (deferred).
	 *
	 * @param string $name  Attribute name.
	 * @param mixed  $value Attribute value.
	 * @return static
	 */
	public function attr(string $name, mixed $value): static {
		$this->deferred->record('attr', func_get_args());
		return $this;
	}

	/**
	 * Set before wrapper callback (deferred).
	 *
	 * Note: This records to deferred. For page/collection-level before(),
	 * the using class should override this method to handle context.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return static
	 */
	public function before(callable $callback): static {
		$this->deferred->record('before', func_get_args());
		return $this;
	}

	/**
	 * Set after wrapper callback (deferred).
	 *
	 * Note: This records to deferred. For page/collection-level after(),
	 * the using class should override this method to handle context.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return static
	 */
	public function after(callable $callback): static {
		$this->deferred->record('after', func_get_args());
		return $this;
	}

	/**
	 * Create an on_render callback that replays deferred calls.
	 *
	 * Use this in end_page()/end_collection() when no manual on_render was provided.
	 *
	 * @return callable The replay callback.
	 */
	protected function _createDeferredRenderCallback(): callable {
		$deferred = $this->deferred;
		return function ($builder) use ($deferred) {
			$deferred->replay($builder);
		};
	}

	/**
	 * Magic method to record unknown method calls for deferred replay.
	 *
	 * This allows custom component builder methods (e.g., custom_option(),
	 * special_config()) to be recorded and replayed without explicitly
	 * defining them in this trait.
	 *
	 * @param string $name Method name.
	 * @param array<int,mixed> $arguments Method arguments.
	 * @return static
	 */
	public function __call(string $name, array $arguments): static {
		$this->deferred->record($name, $arguments);
		return $this;
	}
}
