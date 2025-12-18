<?php
/**
 * User Collection Builder
 *
 * Lightweight builder for user settings collection metadata.
 * Used by UserSettingsRegistry to define collections with on_render callbacks.
 *
 * @package Ran\PluginLib\Settings
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\DeferredBuilderTrait;

/**
 * Lightweight builder for user settings collection metadata.
 *
 * Collects collection configuration (heading, order, etc.) and an on_render
 * callback. Does NOT create expensive dependencies - that happens when
 * the collection is actually rendered on a profile page.
 */
class UserCollectionBuilder {
	use DeferredBuilderTrait;

	/**
	 * @var UserSettingsRegistry Parent registry.
	 */
	private UserSettingsRegistry $registry;

	/**
	 * @var string Collection slug.
	 */
	private string $slug;

	/**
	 * @var array Collection metadata.
	 */
	private array $meta = array();

	/**
	 * @var callable|null Render callback.
	 */
	private $render_callback = null;

	/**
	 * Schema for field validation/sanitization.
	 *
	 * @var array|callable|null
	 */
	private mixed $schema = null;

	/**
	 * Constructor.
	 *
	 * @param UserSettingsRegistry $registry Parent registry.
	 * @param string               $slug     Collection slug.
	 */
	public function __construct(UserSettingsRegistry $registry, string $slug) {
		$this->registry = $registry;
		$this->slug     = $slug;
		$this->_initDeferred($registry->get_logger());
	}

	/**
	 * Set the collection heading.
	 *
	 * @param string $heading Collection heading.
	 * @return static
	 */
	public function heading(string $heading): static {
		$this->meta['heading'] = $heading;
		return $this;
	}

	/**
	 * Set the collection description (collection-level, not deferred).
	 *
	 * Note: This overrides the trait's description() to set collection metadata.
	 * For field-level descriptions, call after section()/field().
	 *
	 * @param string $description Collection description.
	 * @return static
	 */
	public function description(string $description): static {
		if ($this->hasDeferred()) {
			// We're in deferred mode - record for field/section
			$this->deferred->record('description', func_get_args());
		} else {
			// Collection-level metadata
			$this->meta['description'] = $description;
		}
		return $this;
	}

	/**
	 * Set the display order (priority for WordPress hooks).
	 *
	 * @param int $order Display order (lower = earlier).
	 * @return static
	 */
	public function order(int $order): static {
		$this->meta['order'] = $order;
		return $this;
	}

	/**
	 * Set a custom CSS class/style identifier.
	 *
	 * @param string|callable $style Style identifier.
	 * @return static
	 */
	public function style(string|callable $style): static {
		$this->meta['style'] = $style;
		return $this;
	}

	/**
	 * Set the capability required to view/edit this collection.
	 *
	 * @param string $capability WordPress capability.
	 * @return static
	 */
	public function capability(string $capability): static {
		$this->meta['capability'] = $capability;
		return $this;
	}

	/**
	 * Set the validation/sanitization schema for this collection's fields.
	 *
	 * The schema is registered before on_render() or deferred calls are executed.
	 * This allows schema to work with both manual on_render() and deferred syntax.
	 *
	 * Accepts either:
	 * - An array: Schema array keyed by field name
	 * - A callable: Function that returns the schema array (lazy evaluation)
	 *
	 * @param array|callable $schema Schema array or callable returning schema array.
	 * @return static
	 */
	public function schema(array|callable $schema): static {
		$this->schema = $schema;
		return $this;
	}

	/**
	 * Set a before callback for wrapping content.
	 *
	 * Context-aware: If called before section(), sets collection-level metadata.
	 * If called after section()/field(), records for deferred replay.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return static
	 */
	public function before(callable $callback): static {
		if ($this->hasDeferred()) {
			$this->deferred->record('before', func_get_args());
		} else {
			$this->meta['before'] = $callback;
		}
		return $this;
	}

	/**
	 * Set an after callback for wrapping content.
	 *
	 * Context-aware: If called before section(), sets collection-level metadata.
	 * If called after section()/field(), records for deferred replay.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return static
	 */
	public function after(callable $callback): static {
		if ($this->hasDeferred()) {
			$this->deferred->record('after', func_get_args());
		} else {
			$this->meta['after'] = $callback;
		}
		return $this;
	}

	/**
	 * Set the render callback for defining sections and fields.
	 *
	 * The callback receives a UserSettingsCollectionBuilder (the real builder
	 * from UserSettings) when the collection is rendered on a profile page.
	 *
	 * @param callable $callback Receives UserSettingsCollectionBuilder.
	 * @return static
	 */
	public function on_render(callable $callback): static {
		$this->render_callback = $callback;
		return $this;
	}

	/**
	 * Finalize and store the collection definition.
	 *
	 * @return UserSettingsRegistry
	 */
	public function end_collection(): UserSettingsRegistry {
		// If deferred calls exist but no on_render, create one that replays them
		if ($this->render_callback === null && $this->hasDeferred()) {
			$this->render_callback = $this->_createDeferredRenderCallback();
		}

		// Wrap the render callback to register schema first (if provided)
		$final_callback = $this->_wrapCallbackWithSchema($this->render_callback);

		$this->registry->_store_collection($this->slug, $this->meta, $final_callback);
		return $this->registry;
	}

	/**
	 * Wrap a render callback to register schema before execution.
	 *
	 * @param callable|null $callback The original render callback.
	 * @return callable|null The wrapped callback.
	 */
	private function _wrapCallbackWithSchema(?callable $callback): ?callable {
		if ($this->schema === null || $callback === null) {
			return $callback;
		}

		$schema = $this->schema;
		return function ($builder) use ($callback, $schema) {
			// Resolve schema if callable
			$resolved_schema = is_callable($schema) ? $schema() : $schema;
			// Register schema before running the callback
			$builder->get_options()->register_schema($resolved_schema);
			// Then run the original callback
			$callback($builder);
		};
	}
}
