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
use Ran\PluginLib\Settings\AdminSettingsBuilderRootInterface;
use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use Ran\PluginLib\Forms\Builders\SubmitControlsBuilder;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

/**
 * AdminSettingsPageBuilder: Fluent builder for Admin Settings pages.
 *
 */
class AdminSettingsPageBuilder implements AdminSettingsBuilderRootInterface {
	use BuilderImmediateUpdateTrait;

	private const SUBMIT_CONTROLS_ZONE_ID = 'primary-controls';
	private const DEFAULT_CONTROL_ID      = 'primary';
	private const DEFAULT_BUTTON_LABEL    = 'Save Changes';
	private AdminSettingsMenuGroupBuilder $menu_group;
	private string $container_id;
	/** @var array{heading:string, description:?string, menu_title:string, capability:string, order:int} */
	private array $meta;
	/** @var callable */
	private $updateFn;

	/** @var array<string, AdminSettingsSectionBuilder> */
	private array $active_sections    = array();
	private bool $submit_zone_emitted = false;

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
	}

	/**
	 * Set the page heading displayed atop the admin screen.
	 *
	 * @param string $heading The page heading text.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function heading(string $heading): static {
		$this->_update_meta('heading', $heading);

		return $this;
	}

	/**
	 * Set the page description displayed atop the admin screen.
	 *
	 * @param string|callable $description The page description (string or callback).
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function description(string|callable $description): static {
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
	public function menu_label(string $menu_title): static {
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
	public function capability(string $capability): static {
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
	public function order(?int $order): static {
		$order = $order < 0 ? 0 : $order;
		$this->_update_meta('order', $order);

		return $this;
	}

	/**
	 * Define a section within this page.
	 *
	 * @param string                   $section_id     The section ID.
	 * @param string                   $title          The section title (optional, can be set via heading()).
	 * @param string|callable|null     $description_cb The section description (string or callback).
	 * @param array<string,mixed>|null $args           Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return AdminSettingsSectionBuilder The section builder instance.
	 */
	public function section(string $section_id, string $title = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsSectionBuilder {
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
				'before'         => $args['before'] ?? null,
				'after'          => $args['after']  ?? null,
			)
		));

		$builder = new AdminSettingsSectionBuilder(
			$this,
			$this,
			$this->container_id,
			$section_id,
			$title,
			$this->updateFn,
			$args['before'] ?? null,
			$args['after']  ?? null,
			$order instanceof \Closure || is_callable($order) ? null : ($order === null ? null : (int) $order)
		);
		$this->active_sections[$section_id] = $builder;
		return $builder;
	}

	/**
	 * Set the page template override for this page instance.
	 * Accepts either a registered template key, a callable render override, or null to clear.
	 *
	 * @param string|callable|null $template Template key, callable, or null to reset.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function template(string|callable|null $template): static {
		if ($template === null) {
			($this->updateFn)('template_override', array(
				'element_type' => 'root',
				'element_id'   => $this->container_id,
				'overrides'    => array(),
				'callback'     => null,
			));

			return $this;
		}

		if (is_callable($template)) {
			($this->updateFn)('template_override', array(
				'element_type' => 'root',
				'element_id'   => $this->container_id,
				'overrides'    => array(),
				'callback'     => $template,
			));

			return $this;
		}

		$template_key = trim($template);
		if ($template_key === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		($this->updateFn)('template_override', array(
			'element_type' => 'root',
			'element_id'   => $this->container_id,
			'overrides'    => array('root-wrapper' => $template_key),
		));

		return $this;
	}

	/**
	 * before() method returns this AdminSettingsPageBuilder instance.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function before(callable $before): static {
		$this->_update_meta('before', $before);
		return $this;
	}

	/**
	 * after() method returns this AdminSettingsPageBuilder instance.
	 *
	 * @return AdminSettingsPageBuilder The AdminSettingsPageBuilder instance.
	 */
	public function after(callable $after): static {
		$this->_update_meta('after', $after);
		return $this;
	}

	/**
	 * Set the visual style for this page.
	 *
	 * @param string|callable $style The style identifier or resolver returning a string.
	 *
	 * @return self
	 */
	public function style(string|callable $style): static {
		$normalized = $style === '' ? '' : $this->_resolve_style_arg($style);
		$this->_update_meta('style', $normalized);
		return $this;
	}

	/**
	 * Commit the current page and begin configuring a sibling page on the same menu group.
	 *
	 * @param string $page_slug The next page slug.
	 * @return AdminSettingsPageBuilder
	 */
	public function page(
		string $page_slug,
		string|callable|null $template = null,
		array $args = array()
	): AdminSettingsPageBuilder {
		return $this->end_page()->page($page_slug, $template, $args);
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
	 * @param string|null $template Optional submit controls wrapper template key.
	 * @return SubmitControlsBuilder Fluent submit controls builder.
	 */
	public function submit_controls(?string $template = null): SubmitControlsBuilder {
		$this->_ensure_submit_controls_zone();

		$builder = new SubmitControlsBuilder(
			$this,
			$this->container_id,
			self::SUBMIT_CONTROLS_ZONE_ID,
			$this->updateFn
		);

		if ($template !== null && $template !== '') {
			$builder->template($template);
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
			case 'style':
				$this->meta['style'] = trim((string) $value);
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
	protected function _emit_page_metadata(): void {
		($this->_get_update_callback())($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}

	/**
	 * Ensure the submit controls zone metadata has been emitted.
	 *
	 * Note: Default submit buttons are now created lazily at render time via
	 * AdminSettings::_ensure_submit_controls_fallback() when no controls exist.
	 * This eliminates redundant seed-then-clear cycles during builder composition.
	 */
	protected function _ensure_submit_controls_zone(): void {
		if ($this->submit_zone_emitted) {
			return;
		}

		($this->updateFn)('submit_controls_zone', array(
			'container_id' => $this->container_id,
			'zone_id'      => self::SUBMIT_CONTROLS_ZONE_ID,
		));

		$this->submit_zone_emitted = true;
	}

	/**
	 * Normalize a style argument to a trimmed string.
	 *
	 * @param string|callable $style
	 *
	 * @return string
	 * @throws \InvalidArgumentException When the resolved value is not a string.
	 */
	protected function _resolve_style_arg(string|callable $style): string {
		$resolved = is_callable($style) ? $style() : $style;
		if (!is_string($resolved)) {
			throw new \InvalidArgumentException('AdminSettings style callback must return a string.');
		}
		return trim($resolved);
	}
}
