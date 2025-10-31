<?php
/**
 * Settings entry point fluent builder tests.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder;
use Ran\PluginLib\Settings\AdminSettingsPageBuilder;
use Ran\PluginLib\Settings\Settings;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\UserSettingsCollectionBuilder;
use Ran\PluginLib\Settings\UserSettingsSectionBuilder;
use Ran\PluginLib\Util\Logger;
use WP_Mock\Tools\TestCase;

final class SettingsEntryPointFluentBuilderTest extends TestCase {
	public function setUp(): void {
		\WP_Mock::setUp();

		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('is_network_admin')->andReturn(false);
		\WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		\WP_Mock::userFunction('get_transient')->andReturn(false);
		\WP_Mock::userFunction('set_transient')->andReturn(true);
		\WP_Mock::userFunction('delete_transient')->andReturn(true);
		\WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		\WP_Mock::userFunction('get_user_meta')->andReturn(array());
		\WP_Mock::userFunction('update_user_meta')->andReturn(true);
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	/**
	 * @covers \Ran\PluginLib\Settings\Settings
	 * @covers \Ran\PluginLib\Settings\AdminSettings
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder
	 * @covers \Ran\PluginLib\Settings\AdminSettingsPageBuilder
	 */
	public function test_settings_provides_admin_builder_chain(): void {
		$logger     = new Logger();
		$components = $this->create_component_manifest($logger);
		$options    = RegisterOptions::site('entry_admin_settings', true, $logger);

		$options->register_schema(array(
			'dummy_field' => array(
				'default'  => '',
				'validate' => array(static function($value, callable $emitWarning) {
					return is_string($value);
				}),
			),
		));

		$settings = new Settings($options, $logger, $components);

		$menu_group_builder = $settings->menu_group('entry-admin-group');

		$this->assertInstanceOf(AdminSettingsMenuGroupBuilder::class, $menu_group_builder);

		$page_builder = $menu_group_builder->page('entry-admin-page');

		$this->assertInstanceOf(AdminSettingsPageBuilder::class, $page_builder);

		$this->assertInstanceOf(AdminSettings::class, $page_builder->end_page()->end_menu_group());
	}

	/**
	 * @covers \Ran\PluginLib\Settings\Settings
	 * @covers \Ran\PluginLib\Settings\UserSettings
	 * @covers \Ran\PluginLib\Settings\UserSettingsCollectionBuilder
	 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder
	 */
	public function test_settings_provides_user_builder_chain(): void {
		$logger     = new Logger();
		$components = $this->create_component_manifest($logger);
		$options    = RegisterOptions::user('entry_user_settings', 123, false, $logger);

		$options->register_schema(array(
			'display_name' => array(
				'default'  => '',
				'validate' => array(static function($value, callable $emitWarning) {
					return is_string($value);
				}),
			),
		));

		$settings = new Settings($options, $logger, $components);

		$collection_builder = $settings->collection('entry-profile');

		$this->assertInstanceOf(UserSettingsCollectionBuilder::class, $collection_builder);

		$section_builder = $collection_builder->section('entry-section', 'Entry Section');

		$this->assertInstanceOf(UserSettingsSectionBuilder::class, $section_builder);

		$this->assertInstanceOf(UserSettings::class, $section_builder->end_section()->end_collection());
	}

	private function create_component_manifest(Logger $logger): ComponentManifest {
		$base_dir = dirname(__DIR__, 3) . '/inc/Forms/Components';
		$loader   = new ComponentLoader($base_dir, array(), $logger);

		return new ComponentManifest($loader, $logger);
	}
}
