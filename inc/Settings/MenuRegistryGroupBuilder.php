<?php
/**
 * MenuRegistryGroupBuilder: Fluent builder for menu groups in AdminMenuRegistry.
 *
 * @package Ran\PluginLib\Settings
 * @since   0.3.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Util\Logger;

/**
 * Fluent builder for menu groups in AdminMenuRegistry.
 *
 * Collects menu group metadata (heading, icon, position, capability)
 * and pages without requiring expensive dependencies.
 */
class MenuRegistryGroupBuilder {
	private AdminMenuRegistry $registry;
	private string $group_slug;
	private Logger $logger;

	/**
	 * @var array{heading: string, menu_title: string, capability: string, parent: ?string, icon: ?string, position: ?int}
	 */
	private array $meta = array(
		'heading'    => '',
		'menu_title' => '',
		'capability' => 'manage_options',
		'parent'     => null,
		'icon'       => null,
		'position'   => null,
	);

	/**
	 * @var array<string, array>
	 */
	private array $pages = array();

	/**
	 * Constructor.
	 *
	 * @param AdminMenuRegistry $registry The parent registry.
	 * @param string $group_slug The menu group slug.
	 * @param Logger $logger Logger instance.
	 */
	public function __construct(AdminMenuRegistry $registry, string $group_slug, Logger $logger) {
		$this->registry   = $registry;
		$this->group_slug = $group_slug;
		$this->logger     = $logger;
	}

	/**
	 * Set the heading (page title) for this menu group.
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
	 * Set the menu label (sidebar text) for this menu group.
	 *
	 * @param string $label The menu label text.
	 * @return self
	 */
	public function menu_label(string $label): self {
		$this->meta['menu_title'] = $label;
		return $this;
	}

	/**
	 * Set the capability required to access this menu group.
	 *
	 * @param string $capability The capability (e.g., 'manage_options').
	 * @return self
	 */
	public function capability(string $capability): self {
		$this->meta['capability'] = $capability;
		return $this;
	}

	/**
	 * Set the parent menu for this group.
	 *
	 * @param string|null $parent The parent menu slug (e.g., 'options-general.php').
	 * @return self
	 */
	public function parent(?string $parent): self {
		$this->meta['parent'] = $parent;
		return $this;
	}

	/**
	 * Set the icon for this menu group.
	 *
	 * @param string|null $icon The dashicon class or URL.
	 * @return self
	 */
	public function icon(?string $icon): self {
		$this->meta['icon'] = $icon;
		return $this;
	}

	/**
	 * Set the position for this menu group.
	 *
	 * @param int|null $position The menu position.
	 * @return self
	 */
	public function position(?int $position): self {
		$this->meta['position'] = $position;
		return $this;
	}

	/**
	 * Add a page to this menu group.
	 *
	 * @param string $page_slug The page slug (will be prefixed with kplr-).
	 * @return MenuRegistryPageBuilder
	 */
	public function page(string $page_slug): MenuRegistryPageBuilder {
		return new MenuRegistryPageBuilder($this, $page_slug, $this->logger);
	}

	/**
	 * Register a page with this group.
	 *
	 * @internal Called by MenuRegistryPageBuilder.
	 *
	 * @param string $page_slug The prefixed page slug.
	 * @param array $meta The page metadata.
	 * @param callable $render_callback The render callback.
	 * @return void
	 */
	public function _commit_page(string $page_slug, array $meta, callable $render_callback): void {
		$this->pages[$page_slug] = $meta;
		$this->registry->_store_render_callback($page_slug, $render_callback);
	}

	/**
	 * Finalize this menu group and return to the registry.
	 *
	 * @return AdminMenuRegistry
	 */
	public function end_menu(): AdminMenuRegistry {
		$this->registry->_commit_group($this->group_slug, $this->meta, $this->pages);
		return $this->registry;
	}

	/**
	 * Get the parent registry.
	 *
	 * @return AdminMenuRegistry
	 */
	public function get_registry(): AdminMenuRegistry {
		return $this->registry;
	}

	/**
	 * Get the current metadata.
	 *
	 * @return array
	 */
	public function get_meta(): array {
		return $this->meta;
	}
}
