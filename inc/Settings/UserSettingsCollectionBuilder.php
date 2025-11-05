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
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\BuilderImmediateUpdateTrait;

/**
 * UserSettingsCollectionBuilder: Fluent builder for user settings collections.
 */
class UserSettingsCollectionBuilder implements BuilderRootInterface {
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
	public function heading(string $heading): self {
		$this->_update_meta('heading', $heading);

		return $this;
	}

	/**
	 * Set the page description displayed atop the admin screen.
	 *
	 * @param string $description The page description text.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function description(string $description): self {
		$this->_update_meta('description', $description);

		return $this;
	}

	/**
	 * Set the order for this collection within the user profile page.
	 * Higher numbers mean that the collection will be rendered higher in the list of collections on the profile page.
	 * Defaults to 10, which will render the collection at the end of the list.
	 *
	 * @param int $order The order (must be >= 0).
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function order(int $order): UserSettingsCollectionBuilder {
		$order = $order < 0 ? 0 : $order;
		$this->_update_meta('order', $order);

		return $this;
	}

	/**
	 * Define a new section within this custom profile collection.
	 *
	 * @param string        $section_id      The section ID.
	 * @param string        $title           The section title.
	 * @param callable|null        $description_cb  The section description callback.
	 * @param array<string,mixed>|null $args Optional configuration (order, before/after callbacks, classes, etc.).
	 *
	 * @return UserSettingsSectionBuilder The UserSettingsSectionBuilder instance.
	 */
	public function section(string $section_id, string $title, ?callable $description_cb = null, ?array $args = null): UserSettingsSectionBuilder {
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
			$this->container_id,
			$section_id,
			$title,
			$this->updateFn,
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
	public function template(string|callable|null $template): self {
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
	 * before() method returns this UserSettingsCollectionBuilder instance.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function before(callable $before): self {
		return $this;
	}

	/**
	 * after() method returns this UserSettingsCollectionBuilder instance.
	 *
	 * @return UserSettingsCollectionBuilder The UserSettingsCollectionBuilder instance.
	 */
	public function after(callable $after): self {
		return $this;
	}

	/**
	 * Return to the Settings instance for chaining or boot.
	 * Alias of end().
	 *
	 * @return UserSettings The Settings instance.
	 */
	public function end_collection(): UserSettings {
		$this->commit();
		return $this->end();
	}

	/**
	 * Return to the Settings instance for chaining or boot.
	 *
	 * @return UserSettings The Settings instance.
	 */
	public function end(): UserSettings {
		$this->commit();
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

	public function get_forms(): FormsInterface {
		return $this->settings;
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
	private function _emit_collection_metadata(): void {
		($this->_get_update_callback())($this->_get_update_event_name(), $this->_build_update_payload('', null));
	}

	private function commit(): void {
		if ($this->committed) {
			return;
		}
		($this->updateFn)('collection_commit', array(
			'container_id' => $this->container_id,
		));
		$this->committed = true;
	}
}
