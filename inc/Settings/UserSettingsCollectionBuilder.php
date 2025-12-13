<?php
/**
 * UserSettingsCollectionBuilder: Fluent builder for user profile pages.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\UserSettingsSectionBuilder;
use Ran\PluginLib\Settings\UserSettingsBuilderRootInterface;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;
use Ran\PluginLib\Forms\Builders\BuilderContextInterface;

/**
 * UserSettingsCollectionBuilder: Fluent builder for user settings collections.
 */
class UserSettingsCollectionBuilder implements UserSettingsBuilderRootInterface {
	use BuilderImmediateUpdateTrait;

	private UserSettings $settings;

	private string $container_id;

	/** @var array{template:?callable, priority:int} */
	private array $meta;

	/** @var callable */
	private $updateFn;
	private bool $committed = false;

	/** @var array<string, SectionBuilder> */
	private array $active_sections = array();

	/** @var BuilderContextInterface|null */
	private ?BuilderContextInterface $context = null;

	/**
	 * Constructor.
	 *
	 * @param UserSettings $settings The settings instance.
	 * @param string $container_id The container ID.
	 * @param array $initial_meta The initial meta data.
	 * @param callable $updateFn The update function for immediate data flow.
	 */
	public function __construct(UserSettings $settings, string $container_id, array $initial_meta, callable $updateFn) {
		$this->settings     = $settings;
		$this->container_id = $container_id;
		$this->meta         = $initial_meta;
		$this->updateFn     = $updateFn;

		$this->_emit_collection_metadata();
	}

	/**
	 * Set the page heading displayed atop the admin screen.
	 *
	 * @param string $heading The page heading text.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
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
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function description(string|callable $description): static {
		$this->_update_meta('description', $description);

		return $this;
	}

	/**
	 * Set the priority for this collection's WordPress action hook.
	 *
	 * This controls the order of your collections relative to each other and to other
	 * plugins using the `show_user_profile` / `edit_user_profile` hooks. Lower numbers
	 * execute earlier (appear higher among plugin sections).
	 *
	 * **Important limitation:** This does NOT control position relative to WordPress core
	 * profile sections (Name, Contact Info, About Yourself, Account Management). Core
	 * sections are hardcoded in `wp-admin/user-edit.php` and the profile hooks fire
	 * after all core sections. There is no WordPress hook to insert content between
	 * core profile sections.
	 *
	 * @param int $order The hook priority (must be >= 0). Default is 10.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function order(int $order): static {
		$order = $order < 0 ? 0 : $order;
		$this->_update_meta('order', $order);

		return $this;
	}

	/**
	 * Define a new section within this custom profile collection.
	 *
	 * @param string                   $section_id     The section ID.
	 * @param string                   $title          The section title (optional, can be set via heading()).
	 * @param string|callable|null     $description_cb The section description (string or callback).
	 * @param array<string,mixed>|null $args           Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return UserSettingsSectionBuilder The UserSettingsSectionBuilder instance.
	 */
	public function section(string $section_id, string $title = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsSectionBuilder {
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

		$builder = new UserSettingsSectionBuilder(
			$this,
			$this->_get_context(),
			$section_id,
			$title,
			null,
			null,
			$order
		);
		$this->active_sections[$section_id] = $builder;
		return $builder;
	}

	/**
	 * Set the collection template override for this collection.
	 * Accepts a registered template key, a callable render override, or null to clear.
	 *
	 * @param string|callable|null $template Template key, callable, or null.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
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
	 * Set a callback to render content before this collection.
	 *
	 * The callback receives an array with 'container_id' and 'values' keys
	 * and should return an HTML string.
	 *
	 * @param callable $before Callback that returns HTML string.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function before(callable $before): static {
		$this->_update_meta('before', $before);
		return $this;
	}

	/**
	 * Set a callback to render content after this collection.
	 *
	 * The callback receives an array with 'container_id' and 'values' keys
	 * and should return an HTML string.
	 *
	 * @param callable $after Callback that returns HTML string.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function after(callable $after): static {
		$this->_update_meta('after', $after);
		return $this;
	}

	/**
	 * Set the visual style for this collection.
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
	 * Return to the Settings instance for chaining or boot.
	 * Alias of end().
	 *
	 * @return UserSettings The Settings instance.
	 */
	public function end_collection(): UserSettings {
		$this->_commit();
		return $this->end();
	}

	/**
	 * Return to the Settings instance for chaining or boot.
	 *
	 * @return UserSettings The Settings instance.
	 */
	public function end(): UserSettings {
		$this->_commit();
		return $this->settings;
	}

	/**
	 * Get the UserSettings instance.
	 *
	 * @return UserSettings
	 */
	public function get_settings(): UserSettings {
		return $this->settings;
	}

	/**
	 * Get the RegisterOptions instance for schema registration.
	 *
	 * Useful in on_render callbacks when you need to register validation schemas
	 * before defining fields.
	 *
	 * @return \Ran\PluginLib\Options\RegisterOptions
	 */
	public function get_options(): \Ran\PluginLib\Options\RegisterOptions {
		return $this->settings->get_base_options();
	}

	/**
	 * Expose the root FormsInterface to child builders.
	 *
	 * @internal
	 *
	 * @return FormsInterface
	 */
	public function __get_forms(): FormsInterface {
		return $this->settings;
	}

	/**
	 * Get or create the builder context for child builders.
	 *
	 * @return BuilderContextInterface
	 */
	protected function _get_context(): BuilderContextInterface {
		if ($this->context === null) {
			$this->context = new SettingsBuilderContext(
				$this->settings,
				$this->container_id,
				$this->updateFn
			);
		}
		return $this->context;
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
	 * Apply metadata changes and emit collection updates immediately.
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
				$this->meta['description'] = (string) $value;
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

		$this->_emit_collection_metadata();
	}

	/**
	 * Return the update callback for collection metadata.
	 */
	protected function _get_update_callback(): callable {
		return $this->updateFn;
	}

	/**
	 * Return the update event name for collection metadata.
	 */
	protected function _get_update_event_name(): string {
		return 'collection';
	}

	/**
	 * Build the payload sent with collection metadata updates.
	 *
	 * @param string $key   Meta key being updated (unused).
	 * @param mixed  $value New value for the meta key (unused).
	 * @return array<string,mixed>
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
		return array(
			'container_id'    => $this->container_id,
			'collection_data' => $this->meta,
		);
	}

	/**
	 * Emit collection metadata via the update callback.
	 */
	protected function _emit_collection_metadata(): void {
		($this->_get_update_callback())($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}

	protected function _commit(): void {
		if ($this->committed) {
			return;
		}
		($this->updateFn)('collection_commit', array(
			'container_id' => $this->container_id,
		));
		$this->committed = true;
	}

	/**
	 * Normalize a style argument to a trimmed string.
	 *
	 * @param string|callable $style Style value or resolver callback returning a string.
	 *
	 * @return string
	 * @throws \InvalidArgumentException When the resolved value is not a string.
	 */
	protected function _resolve_style_arg(string|callable $style): string {
		$resolved = is_callable($style) ? $style() : $style;
		if (!is_string($resolved)) {
			throw new \InvalidArgumentException('Collection style callback must return a string.');
		}
		return trim($resolved);
	}
}
