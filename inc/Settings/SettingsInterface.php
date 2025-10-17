<?php
/**
 * SettingsInterface: Interface for settings.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Options\RegisterOptions;
interface SettingsInterface {
	/**
	 * Render a profile collection.
	 *
	 * @param string $id_or_slug The collection id, defaults to 'profile'.
	 * @param array $context optional context.
	 *
	 * @return void
	 */
	public function render(string $id_slug, ?array $context = null): void;

	/**
	 * Resolve the correctly scoped RegisterOptions instance for current context.
	 * Callers can chain fluent API on the returned object.
	 *
	 * @param ?array $context optional context.
	 *
	 * @return RegisterOptions The RegisterOptions instance.
	 */
	public function resolve_options(?array $context = null): RegisterOptions;

	/**
	 * Bootstrap the settings.
	 *
	 * @return void
	 */
	public function boot(): void;

	/**
	 * Set template overrides for a specific section.
	 *
	 * @param string $section_id The section ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_section_template_overrides(string $section_id, array $template_overrides): void;

	/**
	 * Set template overrides for a specific group.
	 *
	 * @param string $group_id The group ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_group_template_overrides(string $group_id, array $template_overrides): void;

	/**
	 * Set template overrides for a specific field.
	 *
	 * @param string $field_id The field ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_field_template_overrides(string $field_id, array $template_overrides): void;

	/**
	 * Set default template overrides for this Settings instance.
	 *
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_default_template_overrides(array $template_overrides): void;

	/**
	 * Resolve template with hierarchical fallback.
	 *
	 * @param string $template_type The template type.
	 * @param array<string, mixed> $context Resolution context.
	 *
	 * @return string The resolved template key.
	 */
	public function resolve_template(string $template_type, array $context = array()): string;

	/**
	 * Set template overrides for the root container (page for AdminSettings, collection for UserSettings).
	 *
	 * @param string $root_id The root container ID.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_root_template_overrides(string $root_id, array $template_overrides): void;

	/**
	 * Get template overrides for the root container (page for AdminSettings, collection for UserSettings).
	 *
	 * @param string $root_id The root container ID.
	 *
	 * @return array<string, string>
	 */
	public function get_root_template_overrides(string $root_id): array;
}
