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
}
