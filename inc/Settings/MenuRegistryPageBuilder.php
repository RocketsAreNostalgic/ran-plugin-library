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

/**
 * Fluent builder for pages in AdminMenuRegistry.
 *
 * Collects page metadata (heading, menu_title, capability) and the
 * on_render callback without requiring expensive dependencies.
 */
class MenuRegistryPageBuilder {
	private MenuRegistryGroupBuilder $group;
	private string $page_slug;
	private Logger $logger;

	/**
	 * @var array{heading: string, menu_title: string, capability: ?string}
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
	 * Constructor.
	 *
	 * @param MenuRegistryGroupBuilder $group The parent group builder.
	 * @param string $page_slug The page slug (will be prefixed).
	 * @param Logger $logger Logger instance.
	 */
	public function __construct(MenuRegistryGroupBuilder $group, string $page_slug, Logger $logger) {
		$this->group     = $group;
		$this->page_slug = AdminMenuRegistry::prefix_slug($page_slug);
		$this->logger    = $logger;
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
		if ($this->render_callback === null) {
			throw new \LogicException("Page '{$this->page_slug}' must have an on_render() callback.");
		}
		$this->group->_commit_page($this->page_slug, $this->meta, $this->render_callback);
		return $this->group;
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
