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

use Ran\PluginLib\Settings\AdminSettingsPageBuilder;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

/**
 * Builder used by `AdminSettings` to configure grouped admin menus.
 *
 * The Menu group builder is the top level builder for a group of admin menus.
 * It does not implement the same Builder interface as the other builders.
 *
 * @see ComponentBuilderInterface
 * @see SectionBuilder
 * @see GroupBuilder
 * @see AdminSettingsPageBuilder
 * @see UserSettingsSectionBuilder
 */
final class AdminSettingsMenuGroupBuilder {
	use BuilderImmediateUpdateTrait;
	private AdminSettings $settings;
	private string $container_id;
	/** @var array{heading:string, menu_title:string, capability:string, parent:?string, icon:?string, position:?int, order:int} */
	private array $meta;
	/** @var callable */
	private $updateFn;
	/** @var array<string, AdminSettingsPageBuilder> */
	private array $active_pages = array();
	private bool $committed     = false;

	/**
	 * Create a new AdminSettingsMenuGroupBuilder.
	 *
	 * @param AdminSettings $settings The settings object.
	 * @param string $container_id The ID of this group.
	 * @param array $initial_meta The initial meta for this group.
	 * @param callable $updateFn The update function for immediate data flow.
	 */
	public function __construct(AdminSettings $settings, string $container_id, array $initial_meta, callable $updateFn) {
		$this->settings     = $settings;
		$this->container_id = $container_id;
		$this->meta         = $initial_meta;
		$this->updateFn     = $updateFn;

		$this->dispatch_group_update();
	}

	/**
	 * Set the page heading (displayed atop the admin screen).
	 *
	 * @param string $heading The page heading text.
	 *
	 * @return self
	 */
	public function heading(string $heading): self {
		$this->_update_meta('heading', $heading);
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
		$this->_update_meta('menu_title', $menu_title);
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
		$this->_update_meta('capability', $capability);
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
		$this->_update_meta('parent', $parent_slug);
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
		$this->_update_meta('icon', $icon);
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
		$this->_update_meta('position', $position);
		return $this;
	}

	/**
	 * Add a child page to this menu group.
	 *
	 * @param string $page_slug The slug of the page.
	 * @param string|callable|null $template Root template override (registered key or callable)
	 * @param array<string,mixed> $args Additional page metadata (heading, menu_title, capability, order, description)
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function page(
		string $page_slug,
		string|callable|null $template = null,
		array $args = array()
	): AdminSettingsPageBuilder {
		$heading    = (string) ($args['heading'] ?? ($this->meta['heading'] ?? ucwords(str_replace(array('-', '_'), ' ', $page_slug))));
		$menu_title = (string) ($args['menu_title'] ?? ($this->meta['menu_title'] ?? $heading));
		$capability = (string) ($args['capability'] ?? ($this->meta['capability'] ?? 'manage_options'));
		$order      = isset($args['order']) ? max(0, (int) $args['order']) : count($this->active_pages);

		$initial_meta = array(
		    'heading'     => $heading,
		    'menu_title'  => $menu_title,
		    'capability'  => $capability,
		    'order'       => $order,
		    'description' => $args['description'] ?? null,
		);

		$builder = new AdminSettingsPageBuilder(
			$this,
			$page_slug,
			$initial_meta,
			$this->updateFn
		);

		$this->active_pages[$page_slug] = $builder;

		if ($template !== null) {
			$builder->template($template);
		}

		if (array_key_exists('description', $args) && $args['description'] !== null) {
			$builder->description((string) $args['description']);
		}

		return $builder;
	}

	/**
	 * Called by AdminSettings to finalize this group definition.
	 *
	 * @return AdminSettings The settings object.
	 */
	public function end_group(): AdminSettings {
		if (!$this->committed) {
			if (!array_key_exists('parent', $this->meta)) {
				$this->meta['parent'] = 'options-general.php';
			}
			foreach ($this->active_pages as $builder) {
				$builder->end_page();
			}

			// Collect all page data for the group commit
			$pages_data = array();
			foreach ($this->active_pages as $page_slug => $builder) {
				// Pages should have already committed their data via updateFn
				// We just need to signal the group is complete
			}

			// Use updateFn to send complete group data
			($this->updateFn)('menu_group_commit', array(
				'container_id' => $this->container_id,
				'group_data'   => $this->meta
			));
			$this->committed = true;
		}

		return $this->settings;
	}

	public function end(): AdminSettings {
		return $this->end_group();
	}

	public function end_menu_group(): AdminSettings {
		return $this->end_group();
	}

	/**
	 * Get the main AdminSettings instance.
	 * Provides clean access to the settings instance for page builders.
	 *
	 * @return AdminSettings The main settings instance.
	 */
	public function get_settings(): AdminSettings {
		return $this->settings;
	}

	/**
	 * Get the container ID for this group.
	 * Used by page builders to reference their parent group.
	 *
	 * @return string The container ID
	 */
	public function get_container_id(): string {
		return $this->container_id;
	}

	/**
	 * Override cleanup active section to handle local active_pages array.
	 *
	 * @param string $page_slug The page slug to cleanup
	 * @return void
	 */
	protected function _cleanup_active_page(string $page_slug): void {
		unset($this->active_pages[$page_slug]);
	}

	private function dispatch_group_update(): void {
		($this->_get_update_callback())($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}

	protected function _apply_meta_update(string $key, mixed $value): void {
		$this->meta[$key] = $value;
	}

	protected function _get_update_callback(): callable {
		return $this->updateFn;
	}

	protected function _get_update_event_name(): string {
		return 'menu_group';
	}

	protected function _build_update_payload(string $key, mixed $value): array {
		return array(
			'container_id' => $this->container_id,
			'group_data'   => $this->meta,
		);
	}
}
