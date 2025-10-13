<?php
/**
 * PageBuilderInterface: Fluent builder for Settings pages.
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
use Ran\PluginLib\Settings\SectionBuilderInterface;

interface CollectionBuilderInterface {
	/**
	 * section() method returns a SectionBuilderInterface instance.
	 *
	 * @param string $section_id The section ID.
	 * @param string $title The section title.
	 * @param callable|null $description_cb The section description callback.
	 * @param int|null $order The section order.
	 *
	 * @return SectionBuilderInterface The SectionBuilderInterface instance.
	 */
	public function section(string $section_id, string $title, ?callable $description_cb = null, ?int $order = null): SectionBuilderInterface;

	/**
	 * end() method returns the original SettingsInterface instance.
	 *
	 * @return SettingsInterface The SettingsInterface instance.
	 */
	public function end(): SettingsInterface;
}
