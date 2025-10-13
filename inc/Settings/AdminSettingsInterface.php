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
}
