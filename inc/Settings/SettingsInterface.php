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
}
