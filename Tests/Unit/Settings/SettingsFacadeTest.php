<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\Settings;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Util\CollectingLogger;
use WP_Mock\Tools\TestCase;
use BadMethodCallException;

/**
 * @covers \Ran\PluginLib\Settings\Settings
 */
final class SettingsFacadeTest extends TestCase {
	private CollectingLogger $logger;

	public function setUp(): void {
		parent::setUp();
		$this->logger = new CollectingLogger();
	}

	public function test_construct_uses_user_settings_for_user_scope(): void {
		$options = RegisterOptions::user('user_option', 123, false, $this->logger);
		$facade  = new Settings($options);
		$inner   = $facade->inner();

		$this->assertInstanceOf(UserSettings::class, $inner);
	}

	public function test_construct_uses_admin_settings_for_site_scope(): void {
		$options = RegisterOptions::site('site_option', false, $this->logger);
		$facade  = new Settings($options);
		$inner   = $facade->inner();

		$this->assertInstanceOf(AdminSettings::class, $inner);
	}

	public function test_call_delegates_to_inner(): void {
		$options = RegisterOptions::site('site_option', true, $this->logger);

		$facade = new Settings($options);

		$this->expectNotToPerformAssertions();
		$facade->boot();
	}

	public function test_call_throws_for_undefined_method(): void {
		$options = RegisterOptions::site('site_option', true, $this->logger);
		$facade  = new Settings($options);

		$this->expectException(BadMethodCallException::class);
		$facade->nonExistingMethod();
	}
}
