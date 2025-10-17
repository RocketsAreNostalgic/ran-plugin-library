<?php
/**
 * AdminSettingsMenuGroupBuilder: Fluent builder for grouped admin settings menus.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\SettingsInterface;
use Ran\PluginLib\Settings\AdminSettingsPageBuilder;

/**
 * Builder used by `AdminSettings` to configure grouped admin menus.
 */
final class AdminSettingsMenuGroupBuilder {
	private SettingsInterface $settings;
	private string $group_slug;
	/** @var array{page_title:string, menu_title:string, capability:string, parent:?string, icon:?string, position:?int, order:int} */
	private array $meta;
	/**
	 * Pages buffered for this group keyed by page slug.
	 *
	 * @var array<string, array{
	 *     meta: array{page_title:string, menu_title:string, capability:string, template:?callable, order:int},
	 *     sections: array<string, array{title:string, description_cb:?callable, order:int, index:int}>,
	 *     fields: array<string, array<int, array{id:string,label:string,render:callable, order:int, index:int}>>,
	 *     groups: array<string, array{group_id:string, fields:array<int, array{id:string,label:string,render:callable, order:int, index:int}>, before:?callable, after:?callable, order:int, index:int}>
	 * }>
	 */
	private array $pages = array();
	/** @var array<string, AdminSettingsPageBuilder> */
	private array $active_pages = array();
	/** @var callable */
	private $finalize;
	private bool $committed = false;

	/**
	 * Create a new AdminSettingsMenuGroupBuilder.
	 *
	 * @param SettingsInterface $settings The settings object.
	 * @param string $group_slug The slug of this group.
	 * @param array $initial_meta The initial meta for this group.
	 * @param callable $finalize The finalize callback.
	 */
	public function __construct(SettingsInterface $settings, string $group_slug, array $initial_meta, callable $finalize) {
		$this->settings   = $settings;
		$this->group_slug = $group_slug;
		$this->meta       = $initial_meta;
		$this->finalize   = $finalize;
	}

	/**
	 * Set the page heading (displayed atop the admin screen).
	 *
	 * @param string $page_title The page heading text.
	 *
	 * @return self
	 */
	public function page_heading(string $page_title): self {
		$this->meta['page_title'] = $page_title;
		return $this;
	}

	/**
	 * Set the sidebar menu label for this group.
	 *
	 * @param string $menu_title The sidebar menu label.
	 *
	 * @return self
	 */
	public function menu_label(string $menu_title): self {
		$this->meta['menu_title'] = $menu_title;
		return $this;
	}

	/**
	 * Set the capability required to access this group.
	 *
	 * @param string $capability The capability required to access this group.
	 *
	 * @return self
	 */
	public function capability(string $capability): self {
		$this->meta['capability'] = $capability;
		return $this;
	}

	/**
	 * Set the parent of this group.
	 * If not set the group will be a top-level menu.
	 *
	 * @param string $parent_slug The parent of this group.
	 *
	 * @return self
	 */
	public function parent(?string $parent_slug): self {
		$this->meta['parent'] = $parent_slug;
		return $this;
	}

	/**
	 * Set the icon of this group.
	 * If not set the group WP will fallback to the default gear icon (dashicons-admin-generic).
	 *
	 * @param string $icon The icon of this group.
	 *
	 * @return self
	 */
	public function icon(?string $icon): self {
		$this->meta['icon'] = $icon;
		return $this;
	}

	/**
	 * Set the position of this group.
	 * If not set the group will be ordered by insertion order.
	 *
	 * @param int $position The position of this group.
	 *
	 * @return self
	 */
	public function position(?int $position): self {
		$this->meta['position'] = $position;
		return $this;
	}

	/**
	 * Add a child page to this menu group.
	 *
	 * @param string $page_slug The slug of the page.
	 * @param callable $configure The configure callback.
	 *
	 * @return AdminSettingsPageBuilder|self
	 */
	public function page(string $page_slug, ?callable $configure = null): AdminSettingsPageBuilder|self {
		$initial_meta = array(
		    'page_title' => $this->meta['page_title'],
		    'menu_title' => $this->meta['menu_title'],
		    'capability' => $this->meta['capability'],
		    'template'   => null,
		    'order'      => isset($this->pages[$page_slug]) ? $this->pages[$page_slug]['meta']['order'] : count($this->pages),
		);

		$builder = new AdminSettingsPageBuilder(
			$this,
			$page_slug,
			$initial_meta,
			function (string $slug, array $meta, array $sections, array $fields, array $groups): void {
				$this->pages[$slug] = array(
				    'meta'     => $meta,
				    'sections' => $sections,
				    'fields'   => $fields,
				    'groups'   => $groups,
				);
				unset($this->active_pages[$slug]);
			}
		);

		$this->active_pages[$page_slug] = $builder;

		if ($configure !== null) {
			$configure($builder);
			if (!$builder->is_committed()) {
				$builder->end_page();
			}
			return $this;
		}

		return $builder;
	}

	/**
	 * Called by AdminSettings to finalize this group definition.
	 *
	 * @return SettingsInterface The settings object.
	 */
	public function end_group(): SettingsInterface {
		if (!$this->committed) {
			if (!array_key_exists('parent', $this->meta)) {
				$this->meta['parent'] = 'options-general.php';
			}
			foreach ($this->active_pages as $builder) {
				$builder->end_page();
			}
			($this->finalize)($this->group_slug, $this->meta, $this->pages);
			$this->committed = true;
		}

		return $this->settings;
	}
}
