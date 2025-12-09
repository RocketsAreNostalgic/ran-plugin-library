<?php
/**
 * UserSettingsBuilderRootInterface: Specialized root interface for UserSettings builders.
 *
 * Extends BuilderRootInterface with semantic end_collection() method for UserSettings context.
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
 * Specialized root interface for UserSettings collection builders.
 *
 * Provides semantic end_collection() method that returns to the UserSettings instance.
 */
interface UserSettingsBuilderRootInterface extends BuilderRootInterface {
	/**
	 * Complete the collection and return to the UserSettings instance.
	 *
	 * Semantic alias for end() in the UserSettings context.
	 *
	 * @return UserSettings The UserSettings instance.
	 */
	public function end_collection(): UserSettings;
}
