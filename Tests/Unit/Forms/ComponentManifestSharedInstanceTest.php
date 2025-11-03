<?php
/**
 * Test ComponentManifest shared instance pattern.
 *
 * @package Ran\PluginLib\Tests\Unit\Forms
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Mockery;

class ComponentManifestSharedInstanceTest extends PluginLibTestCase {
	use ExpectLogTrait;

	private ComponentManifest $manifest;
	private ComponentLoader $loader;

	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions for ComponentManifest
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');

		// Mock WordPress functions for RegisterOptions and Settings
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('add_user_meta')->andReturn(true);
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('add_action')->andReturn(true);
		WP_Mock::userFunction('add_filter')->andReturn(true);
		WP_Mock::userFunction('register_setting')->andReturn(true);
		WP_Mock::userFunction('add_settings_section')->andReturn(true);
		WP_Mock::userFunction('add_settings_field')->andReturn(true);
		WP_Mock::userFunction('get_current_user_id')->andReturn(1);

		// Mock the options persistence filter
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(WP_Mock\Functions::type('bool'), WP_Mock\Functions::type('array'))
			->reply(true);

		$this->loader = Mockery::mock(ComponentLoader::class);
		$this->loader->shouldReceive('aliases')->andReturn(array());
		$this->loader->shouldReceive('register')->zeroOrMoreTimes();

		$this->logger_mock->collected_logs = array();
		$this->manifest                    = new ComponentManifest($this->loader, $this->logger_mock);
	}

	/**
	 * Test that AdminSettings and UserSettings can use the same ComponentManifest instance.
	 */
	public function test_shared_component_manifest_instance(): void {
		$adminOptions = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$userOptions  = new RegisterOptions('test_user_settings', StorageContext::forUser(1), true, $this->logger_mock);

		// Create AdminSettings and UserSettings with the same ComponentManifest
		$adminSettings = new AdminSettings($adminOptions, $this->manifest, $this->logger_mock);
		$userSettings  = new UserSettings($userOptions, $this->manifest, $this->logger_mock);

		// Verify both instances exist
		$this->assertInstanceOf(AdminSettings::class, $adminSettings);
		$this->assertInstanceOf(UserSettings::class, $userSettings);

		// Verify they both have access to get_form_session method (new API)
		$this->assertTrue(method_exists($adminSettings, 'get_form_session'));
		$this->assertTrue(method_exists($userSettings, 'get_form_session'));

		// Verify they both have the new template override methods
		$this->assertTrue(method_exists($adminSettings, 'override_form_defaults'));
		$this->assertTrue(method_exists($userSettings, 'override_form_defaults'));
	}

	/**
	 * Test that ComponentManifest integration works with AdminSettings constructor.
	 */
	public function test_admin_settings_component_manifest_integration(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);

		// Should not throw exception with ComponentManifest parameter
		$adminSettings = new AdminSettings($options, $this->manifest, $this->logger_mock);

		// Verify the FormsServiceSession is accessible
		$formSession = $adminSettings->get_form_session();
		$this->assertNotNull($formSession);

		// Verify fluent API methods exist
		$this->assertTrue(method_exists($adminSettings, 'menu_group'));
		$this->assertTrue(method_exists($adminSettings, 'settings_page'));
	}

	/**
	 * Test that ComponentManifest integration works with UserSettings constructor.
	 */
	public function test_user_settings_component_manifest_integration(): void {
		$options = new RegisterOptions('test_user_settings', StorageContext::forUser(1), true, $this->logger_mock);

		// Should not throw exception with ComponentManifest parameter
		$userSettings = new UserSettings($options, $this->manifest, $this->logger_mock);

		// Verify the FormsServiceSession is accessible
		$formSession = $userSettings->get_form_session();
		$this->assertNotNull($formSession);

		// Verify fluent API methods exist
		$this->assertTrue(method_exists($userSettings, 'collection'));
		$this->assertTrue(method_exists($userSettings, 'render'));
	}

	/**
	 * Test that old API methods are not available (ensuring they were properly removed).
	 */
	public function test_old_api_methods_removed(): void {
		$adminOptions = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$userOptions  = new RegisterOptions('test_user_settings', StorageContext::forUser(1), true, $this->logger_mock);

		$adminSettings = new AdminSettings($adminOptions, $this->manifest, $this->logger_mock);
		$userSettings  = new UserSettings($userOptions, $this->manifest, $this->logger_mock);

		// Verify old methods are gone
		$this->assertFalse(method_exists($adminSettings, 'set_default_template_overrides'));
		$this->assertFalse(method_exists($adminSettings, 'resolve_template'));
		$this->assertFalse(method_exists($userSettings, 'set_default_template_overrides'));
		$this->assertFalse(method_exists($userSettings, 'resolve_template'));
	}
}
