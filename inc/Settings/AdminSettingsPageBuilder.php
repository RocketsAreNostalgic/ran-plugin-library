<?php
/**
 * AdminSettingsPageBuilder: Fluent builder for Settings pages.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\AdminSettingsSectionBuilder;
use Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder;
use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use Ran\PluginLib\Forms\Builders\SubmitControlsBuilder;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

/**
 * AdminSettingsPageBuilder: Fluent builder for Admin Settings pages.
 *
 * @method $this heading(string $heading)
 * @method $this description(string $description)
 * @method $this menu_label(string $menu_title)
 * @method $this capability(string $capability)
 * @method $this order(?int $order)
 * @method AdminSettingsSectionBuilder section(string $section_id, string $title, ?callable $description_cb = null, ?array $args = null)
 * @method AdminSettingsPageBuilder|AdminSettingsMenuGroupBuilder page(string $page_slug, ?callable $configure = null)
 * @method AdminSettingsMenuGroupBuilder end_page()
 * @method AdminSettings end()
 */
class AdminSettingsPageBuilder implements BuilderRootInterface {
	use BuilderImmediateUpdateTrait;

	private const SUBMIT_CONTROLS_ZONE_ID = 'primary-controls';
	private const DEFAULT_CONTROL_ID      = 'primary';
	private const DEFAULT_ALIGNMENT       = 'right';
	private const DEFAULT_LAYOUT          = 'inline';
	private const DEFAULT_BUTTON_LABEL    = 'Save Changes';
	private AdminSettingsMenuGroupBuilder $menu_group;
	private string $container_id;
	/** @var array{heading:string, description:?string, menu_title:string, capability:string, template:?callable, order:int} */
	private array $meta;
	/** @var callable */
	private $updateFn;

	/** @var array<string, AdminSettingsSectionBuilder> */
	private array $active_sections        = array();
	private bool $submit_zone_emitted     = false;
	private bool $default_controls_seeded = false;
	private bool $submit_controls_cleared = false;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingsMenuGroupBuilder $menu_group The menu group builder.
	 * @param string $container_id The page slug.
	 * @param array $initial_meta The initial meta data.
	 * @param callable $updateFn The update function for immediate data flow.
	 */
	public function __construct(AdminSettingsMenuGroupBuilder $menu_group, string $container_id, array $initial_meta, callable $updateFn) {
		$this->menu_group   = $menu_group;
		$this->container_id = $container_id;
		$this->meta         = $initial_meta;
		$this->updateFn     = $updateFn;

		$this->_emit_page_metadata();
		$this->seed_default_submit_controls();
	}

	/**
	 * Set the page heading displayed atop the admin screen.
	 *
	 * @param string $heading The page heading text.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function heading(string $heading): self {
		$this->_update_meta('heading', $heading);

		return $this;
	}

	/**
	 * Set the page description displayed atop the admin screen.
	 *
	 * @param string $description The page description text.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function description(string $description): self {
		$this->_update_meta('description', $description);

		return $this;
	}

	/**
	 * Set the sidebar menu label.
	 *
	 * @param string $menu_title The menu label text.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function menu_label(string $menu_title): self {
		$this->_update_meta('menu_title', $menu_title);



		return $this;
	}

	/**
	 * Set the capability.
	 * @see https://developer.wordpress.org/reference/functions/current_user_can/
	 *
	 * @param string $capability The capability required to access the page. e.g. 'manage_options'
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function capability(string $capability): self {
		$this->_update_meta('capability', $capability);



		return $this;
	}

	/**
	 * Set the order.
	 *
	 * @param int|null $order The order (must be >= 0).
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function order(?int $order): self {
		$order = $order < 0 ? 0 : $order;
		$this->_update_meta('order', $order);



		return $this;
	}

	/**
	 * Define a section within this page.
	 *
	 * @param string $section_id The section ID.
	 * @param string $title The section title.
	 * @param callable|null $description_cb The section description callback.
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return AdminSettingsSectionBuilder The section builder instance.
	 */
	public function  section(string $section_id, string $title, ?callable $description_cb = null, ?array $args = null): AdminSettingsSectionBuilder {
		$args  = $args          ?? array();
		$order = $args['order'] ?? null;

		// Store section meta immediately via updateFn
		($this->updateFn)('section', array(
			'container_id' => $this->container_id,
			'section_id'   => $section_id,
			'section_data' => array(
				'title'          => $title,
				'description_cb' => $description_cb,
				'order'          => ($order !== null ? (int) $order : 0),
			)
		));

		$builder = new AdminSettingsSectionBuilder(
			$this,
			$this,
			$this->container_id,
			$section_id,
			$title,
			$this->updateFn,
		);
		$this->active_sections[$section_id] = $builder;
		return $builder;
	}

	/**
	 * Set the page template for this specific page instance.
	 * Configures Tier 2 individual root template override via FormsServiceSession.
	 *
	 * @param string $template_key The registered template key.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function template(string $template_key): self {
		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'root',
			'element_id'   => $this->container_id,
			'overrides'    => array('root-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * before() method returns this AdminSettingsPageBuilder instance.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function before(callable $before): AdminSettingsPageBuilder {
		return $this;
	}

	/**
	 * after() method returns this AdminSettingsPageBuilder instance.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function after(callable $after): AdminSettingsPageBuilder {
		return $this;
	}

	/**
	 * Commit the current page and begin configuring a sibling page on the same menu group.
	 *
	 * @param string $page_slug The next page slug.
	 * @param callable|null $configure Optional configuration callback executed on the new page builder.
	 *
	 * @return AdminSettingsPageBuilder|AdminSettingsMenuGroupBuilder
	 */
	public function page(string $page_slug, ?callable $configure = null): AdminSettingsPageBuilder|AdminSettingsMenuGroupBuilder {
		return $this->end_page()->page($page_slug, $configure);
	}

	/**
	 * Return to the menu group builder for chaining.
	 *
	 * @return AdminSettingsMenuGroupBuilder
	 */
	public function end_page(): AdminSettingsMenuGroupBuilder {
		return $this->menu_group;
	}

	/**
	 * Configure submit controls for this page.
	 *
	 * @param callable|null $configure Optional configurator receiving the builder.
	 * @return SubmitControlsBuilder|self
	 */
	public function submit_controls(?callable $configure = null): SubmitControlsBuilder|self {
		$this->ensure_submit_controls_zone();
		$this->clear_default_submit_controls();

		$builder = new SubmitControlsBuilder(
			$this,
			$this->container_id,
			self::SUBMIT_CONTROLS_ZONE_ID,
			$this->updateFn,
			array(
				'alignment' => self::DEFAULT_ALIGNMENT,
				'layout'    => self::DEFAULT_LAYOUT,
			)
		);

		if ($configure !== null) {
			$configure($builder);
			return $this;
		}

		return $builder;
	}

	/**
	 * Return to the Settings instance for chaining or boot.
	 *
	 * @return FormsInterface The FormsInterface instance.
	 */
	public function end(): AdminSettings {
		return $this->end_page()->end_group();
	}

	/**
	 * Get the AdminSettings instance from the group builder.
	 *
	 * @return AdminSettings
	 */
	public function get_settings(): AdminSettings {
		return $this->menu_group->get_settings();
	}

	/**
	 * Provide the active FormsInterface to nested builders.
	 *
	 * @return AdminSettings
	 */
	public function get_forms(): AdminSettings {
		return $this->get_settings();
	}

	/**
	 * Override cleanup active section to handle local active_sections array.
	 *
	 * @param string $section_id The section ID to cleanup
	 * @return void
	 */
	protected function _cleanup_active_section(string $section_id): void {
		unset($this->active_sections[$section_id]);
	}

	/**
	 * Apply metadata changes immediately and emit update to AdminSettings.
	 *
	 * @param string $key   Meta key being updated.
	 * @param mixed  $value New value for the meta key.
	 * @return void
	 */
	protected function _apply_meta_update(string $key, mixed $value): void {
		switch ($key) {
			case 'heading':
				$this->meta['heading'] = (string) $value;
				break;
			case 'description':
				$this->meta['description'] = $value === null ? null : (string) $value;
				break;
			case 'menu_title':
				$this->meta['menu_title'] = (string) $value;
				break;
			case 'capability':
				$this->meta['capability'] = (string) $value;
				break;
			case 'order':
				$this->meta['order'] = $value === null ? 0 : max(0, (int) $value);
				break;
			default:
				$this->meta[$key] = $value;
		}

		$this->_emit_page_metadata();
	}

	/**
	 * Return the update callback.
	 */
	protected function _get_update_callback(): callable {
		return $this->updateFn;
	}

	/**
	 * Return the update event name for page metadata.
	 */
	protected function _get_update_event_name(): string {
		return 'page';
	}

	/**
	 * Build the payload sent with page metadata updates.
	 *
	 * @param string $key   Meta key being updated (unused).
	 * @param mixed  $value New value for the meta key (unused).
	 * @return array<string,mixed>
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
		return array(
			'container_id' => $this->container_id,
			'page_data'    => $this->meta,
			'group_id'     => $this->menu_group->get_container_id(),
		);
	}

	/**
	 * Emit current page metadata through the update callback.
	 */
	private function _emit_page_metadata(): void {
		($this->_get_update_callback())($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}

	/**
	 * Seed the default primary submit button when no customization occurs.
	 */
	private function seed_default_submit_controls(): void {
		if ($this->default_controls_seeded) {
			return;
		}

		$this->ensure_submit_controls_zone();

		$button = (new ButtonBuilder(self::DEFAULT_CONTROL_ID, self::DEFAULT_BUTTON_LABEL))
			->type('submit')
			->variant('primary');

		($this->updateFn)('submit_controls_set', array(
			'container_id' => $this->container_id,
			'zone_id'      => self::SUBMIT_CONTROLS_ZONE_ID,
			'controls'     => array($button->to_array()),
		));

		$this->default_controls_seeded = true;
	}

	/**
	 * Ensure the submit controls zone metadata has been emitted.
	 */
	private function ensure_submit_controls_zone(): void {
		if ($this->submit_zone_emitted) {
			return;
		}

		($this->updateFn)('submit_controls_zone', array(
			'container_id' => $this->container_id,
			'zone_id'      => self::SUBMIT_CONTROLS_ZONE_ID,
			'alignment'    => self::DEFAULT_ALIGNMENT,
			'layout'       => self::DEFAULT_LAYOUT,
		));

		$this->submit_zone_emitted = true;
	}

	/**
	 * Clear the default submit controls when author customizes them.
	 */
	private function clear_default_submit_controls(): void {
		if ($this->submit_controls_cleared || !$this->default_controls_seeded) {
			return;
		}

		($this->updateFn)('submit_controls_set', array(
			'container_id' => $this->container_id,
			'zone_id'      => self::SUBMIT_CONTROLS_ZONE_ID,
			'controls'     => array(),
		));

		$this->submit_controls_cleared = true;
	}
}
