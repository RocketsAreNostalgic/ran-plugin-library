<?php
/**
 * MenuRegistryPageBuilder: Fluent builder for pages in AdminMenuRegistry.
 *
 * @package Ran\PluginLib\Settings
 * @since   0.3.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\Builders\DeferredBuilderTrait;

/**
 * Fluent builder for pages in AdminMenuRegistry.
 *
 * Collects page metadata (heading, menu_title, capability) and the
 * on_render callback without requiring expensive dependencies.
 */
class MenuRegistryPageBuilder {
	use DeferredBuilderTrait;

	private MenuRegistryGroupBuilder $group;
	private string $page_slug;
	private Logger $logger;

	/**
	 * @var array{heading: string, menu_title: string, capability: ?string, style?: string|callable}
	 */
	private array $meta = array(
		'heading'    => '',
		'menu_title' => '',
		'capability' => null, // Inherit from group if null
	);

	/**
	 * @var callable|null
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
	 * @param MenuRegistryGroupBuilder $group The parent group builder.
	 * @param string $page_slug The page slug (will be prefixed).
	 * @param Logger $logger Logger instance.
	 */
	public function __construct(MenuRegistryGroupBuilder $group, string $page_slug, Logger $logger) {
		$this->group     = $group;
		$this->page_slug = $page_slug;
		$this->logger    = $logger;
		$this->_initDeferred($logger);
	}

	/**
	 * Set the heading (page title) for this page.
	 *
	 * @param string $heading The heading text.
	 * @return self
	 */
	public function heading(string $heading): self {
		$this->meta['heading']    = $heading;
		$this->meta['menu_title'] = $this->meta['menu_title'] ?: $heading;
		return $this;
	}

	/**
	 * Set the menu label (sidebar text) for this page.
	 *
	 * @param string $label The menu label text.
	 * @return self
	 */
	public function menu_label(string $label): self {
		$this->meta['menu_title'] = $label;
		return $this;
	}

	/**
	 * Set the capability required to access this page.
	 *
	 * @param string $capability The capability (e.g., 'manage_options').
	 * @return self
	 */
	public function capability(string $capability): self {
		$this->meta['capability'] = $capability;
		return $this;
	}

	/**
	 * Set a custom CSS class/style identifier for this page.
	 *
	 * This value is passed through to the AdminSettingsPageBuilder
	 * when the page is rendered.
	 *
	 * @param string $style Style identifier.
	 * @return self
	 */
	public function style(string|callable $style): self {
		$this->meta['style'] = $style;
		return $this;
	}

	/**
	 * Set the validation/sanitization schema for this page's fields.
	 *
	 * The schema is registered before on_render() or deferred calls are executed.
	 * This allows schema to work with both manual on_render() and deferred syntax.
	 *
	 * Accepts either:
	 * - An array: Schema array keyed by field name
	 * - A callable: Function that returns the schema array (lazy evaluation)
	 *
	 * @param array|callable $schema Schema array or callable returning schema array.
	 * @return self
	 */
	public function schema(array|callable $schema): self {
		$this->schema = $schema;
		return $this;
	}

	public function register_components(array $options): self {
		$path = isset($options['path']) ? trim((string) $options['path']) : '';
		if ($path === '') {
			return $this;
		}
		if (!isset($this->meta['register_components']) || !is_array($this->meta['register_components'])) {
			$this->meta['register_components'] = array();
		}
		$this->meta['register_components'][] = $options;
		return $this;
	}

	/**
	 * Set a before callback for wrapping content.
	 *
	 * Context-aware: If called before section(), sets page-level metadata.
	 * If called after section()/field(), records for deferred replay.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return self
	 */
	public function before(?callable $callback): self {
		if ($this->deferred->hasCalls()) {
			// We're in deferred mode (after section/field calls)
			$this->deferred->record('before', func_get_args());
		} else {
			// Page-level metadata
			$this->meta['before'] = $callback;
		}
		return $this;
	}

	/**
	 * Set an after callback for wrapping content.
	 *
	 * Context-aware: If called before section(), sets page-level metadata.
	 * If called after section()/field(), records for deferred replay.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return self
	 */
	public function after(?callable $callback): self {
		if ($this->deferred->hasCalls()) {
			// We're in deferred mode (after section/field calls)
			$this->deferred->record('after', func_get_args());
		} else {
			// Page-level metadata
			$this->meta['after'] = $callback;
		}
		return $this;
	}

	// =========================================================================
	// Render Callback
	// =========================================================================

	/**
	 * Set the render callback for this page.
	 *
	 * The callback receives an AdminSettings instance and should define
	 * sections and fields. It is ONLY called when the user visits this page.
	 *
	 * @param callable $callback Receives AdminSettings instance.
	 * @return self
	 */
	public function on_render(callable $callback): self {
		$this->render_callback = $callback;
		return $this;
	}

	/**
	 * Finalize this page and return to the group builder.
	 *
	 * @return MenuRegistryGroupBuilder
	 * @throws \LogicException If no on_render callback was provided.
	 */
	public function end_page(): MenuRegistryGroupBuilder {
		// If deferred calls exist but no on_render, create one that replays them
		if ($this->render_callback === null && $this->hasDeferred()) {
			$this->render_callback = $this->_createDeferredRenderCallback();
		}

		if ($this->render_callback === null) {
			throw new \LogicException("Page '{$this->page_slug}' must have an on_render() callback or deferred section/field definitions.");
		}

		// Wrap the render callback to register schema first (if provided)
		$final_callback = $this->_wrapCallbackWithSchema($this->render_callback);

		$this->group->_commit_page($this->page_slug, $this->meta, $final_callback);
		return $this->group;
	}

	/**
	 * Wrap a render callback to register schema before execution.
	 *
	 * @param callable $callback The original render callback.
	 * @return callable The wrapped callback.
	 */
	private function _wrapCallbackWithSchema(callable $callback): callable {
		if ($this->schema === null) {
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

	/**
	 * Shortcut: finalize this page and the menu group.
	 *
	 * @return AdminMenuRegistry
	 * @throws \LogicException If no on_render callback was provided.
	 */
	public function end_menu(): AdminMenuRegistry {
		return $this->end_page()->end_menu();
	}
}
