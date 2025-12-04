<?php
/**
 * AdminSettingsBuilderRootInterface: Specialized root interface for AdminSettings builders.
 *
 * Extends BuilderRootInterface with semantic end_page() method for AdminSettings context.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

/**
 * Specialized root interface for AdminSettings page builders.
 *
 * Provides semantic end_page() method that returns to the menu group builder.
 */
interface AdminSettingsBuilderRootInterface extends BuilderRootInterface {
	/**
	 * Complete the page and return to the menu group builder.
	 *
	 * Semantic alias for navigating up the builder hierarchy in AdminSettings context.
	 *
	 * @return AdminSettingsMenuGroupBuilder The menu group builder instance.
	 */
	public function end_page(): AdminSettingsMenuGroupBuilder;
}
