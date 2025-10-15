<?php
/**
 * UserSettingsInterface: Interface for user settings.
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
use Ran\PluginLib\Settings\UserSettingsCollectionBuilder;

interface UserSettingsInterface extends SettingsInterface {
	/**
	 * Add a profile collection (new group) onto the user profile page.
	 *
	 * @param string $collection_id The collection id, defaults to 'profile'.
	 * @param callable|null $template An optional collection template.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function add_collection(string $collection_id = 'profile', ?callable $template = null): UserSettingsCollectionBuilder;

	/**
	 * Render a profile collection.
	 *
	 * @param string $collection_id The collection id, defaults to 'profile'.
	 * @param array $context optional context.
	 *
	 * @return void
	 */
	public function render_collection(string $collection_id = 'profile', ?array $context = null): void;

	/**
	 * Normalize and persist posted values for a user.
		 * @param int $user_id
	 * @param mixed $raw
	 */
	public function save_settings(array $payload, array $context): void;

	/**
	 * Set template overrides for a specific collection.
	 *
	 * @param string $collection_id The collection ID.
	 * @param array<string, string> $template_overrides Template overrides map.
	 *
	 * @return void
	 */
	public function set_collection_template_overrides(string $collection_id, array $template_overrides): void;

	/**
	 * Set template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 * @param array<string, string> $template_overrides Template overrides map.
	 *
	 * @return void
	 */
	public function set_section_template_overrides(string $section_id, array $template_overrides): void;

	/**
	 * Set default template overrides for this UserSettings instance.
	 *
	 * @param array<string, string> $template_overrides Template overrides map.
	 *
	 * @return void
	 */
	public function set_default_template_overrides(array $template_overrides): void;

	/**
	 * Resolve template with hierarchical fallback.
	 *
	 * @param string $template_type The template type to resolve.
	 * @param array<string, mixed> $context Optional context for resolution.
	 *
	 * @return string The resolved template key.
	 */
	public function resolve_template(string $template_type, array $context = array()): string;
}
