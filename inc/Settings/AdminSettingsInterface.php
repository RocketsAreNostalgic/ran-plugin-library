<?php
/**
 * AdminSettingsInterface: Interface for admin settings.
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
use Ran\PluginLib\Options\RegisterOptions;

interface AdminSettingsInterface extends SettingsInterface {
	/**
	 * Start a menu group for admin settings pages.
	 *
	 * Returns a mutable builder; callers should configure pages, sections, and metadata,
	 * then invoke {@see AdminSettingsMenuGroupBuilder::end_group()} to finalize the definition.
	 *
	 * @param string $group_slug Unique slug used for the menu group.
	 * @return AdminSettingsMenuGroupBuilder
	 */
	public function menu_group(string $group_slug): AdminSettingsMenuGroupBuilder;


	/**
	 * Render a settings page.
	 *
	 * @param string $page_id_or_slug The page id or slug. Used both as an identifier internal identifier and as the settings page slug.
	 * @param array $context optional context.
	 *
	 * @return void
	 */
	public function render_page(string $page_id_or_slug, ?array $context = null): void;

	/**
	 * Set template overrides for a specific page.
	 *
	 * @param string $page_slug The page slug.
	 * @param array<string, string> $template_overrides Template overrides keyed by template type.
	 *
	 * @return void
	 */
	public function set_page_template_overrides(string $page_slug, array $template_overrides): void;

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
	 * Set default template overrides for this AdminSettings instance.
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
	 * Validate template override and provide error handling.
	 *
	 * @param string $template_key The template key to validate.
	 * @param string $template_type The template type for context.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_template_override(string $template_key, string $template_type): bool;
}
