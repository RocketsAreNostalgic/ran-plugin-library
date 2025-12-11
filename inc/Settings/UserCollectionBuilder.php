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

/**
 * Lightweight builder for user settings collection metadata.
 *
 * Collects collection configuration (heading, order, etc.) and an on_render
 * callback. Does NOT create expensive dependencies - that happens when
 * the collection is actually rendered on a profile page.
 */
class UserCollectionBuilder {
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
	 * Constructor.
	 *
	 * @param UserSettingsRegistry $registry Parent registry.
	 * @param string               $slug     Collection slug.
	 */
	public function __construct(UserSettingsRegistry $registry, string $slug) {
		$this->registry = $registry;
		$this->slug     = $slug;
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
	 * Set the collection description.
	 *
	 * @param string $description Collection description.
	 * @return static
	 */
	public function description(string $description): static {
		$this->meta['description'] = $description;
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
	 * @param string $style Style identifier.
	 * @return static
	 */
	public function style(string $style): static {
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
	 * Set a before callback for wrapping content.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return static
	 */
	public function before(callable $callback): static {
		$this->meta['before'] = $callback;
		return $this;
	}

	/**
	 * Set an after callback for wrapping content.
	 *
	 * @param callable $callback Callback returning HTML string.
	 * @return static
	 */
	public function after(callable $callback): static {
		$this->meta['after'] = $callback;
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
		$this->registry->_store_collection($this->slug, $this->meta, $this->render_callback);
		return $this->registry;
	}
}
