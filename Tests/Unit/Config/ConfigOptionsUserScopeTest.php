<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;

/**
 * Tests for Config::options() with scope='user'.
 *
 * Verifies:
 * - Accessor performs no writes and returns a RegisterOptions instance
 * - Writes go through user meta APIs by default (meta is default storage)
 * - Global flag still accepted but ignored for meta backend
 *
 * @covers \Ran\PluginLib\Config\Config::options
 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
 */
final class ConfigOptionsUserScopeTest extends ConfigTestCase {
	private string $plugin_dir;
	private string $plugin_file;
	private string $plugin_basename;
	private string $plugin_url;
	private array $plugin_data;

	public function setUp(): void {
		parent::setUp();

		$this->plugin_dir      = sys_get_temp_dir() . '/mock-plugin-' . uniqid();
		$this->plugin_file     = $this->plugin_dir . '/mock-plugin-file.php';
		$this->plugin_basename = 'mock-plugin-' . basename($this->plugin_dir) . '/mock-plugin-file.php';
		$this->plugin_url      = 'http://example.com/wp-content/plugins/mock-plugin/' . basename($this->plugin_dir) . '/';

		if (!is_dir($this->plugin_dir)) {
			mkdir($this->plugin_dir, 0777, true);
		}

		$this->plugin_data = array(
			'Name'       => 'Mock Plugin for Testing',
			'Version'    => '1.0.0',
			'PluginURI'  => 'http://example.com',
			'TextDomain' => 'mock-plugin-textdomain'
		);

		file_put_contents(
			$this->plugin_file,
			"<?php\n/**\n * Plugin Name: {$this->plugin_data['Name']}\n * Version: {$this->plugin_data['Version']}\n * Text Domain: {$this->plugin_data['TextDomain']}\n */\n"
		);

		// Mock WP functions used during hydration and options access
		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($this->plugin_file)->andReturn($this->plugin_dir . '/')->byDefault();
		WP_Mock::userFunction('plugin_dir_url')->with($this->plugin_file)->andReturn($this->plugin_url)->byDefault();
		WP_Mock::userFunction('plugin_basename')->with($this->plugin_file)->andReturn($this->plugin_basename)->byDefault();
		WP_Mock::userFunction('get_plugin_data')->with($this->plugin_file, false, false)->andReturn($this->plugin_data)->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)))->byDefault();

		// Guard against constructor-time site storage access before scope override is applied
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('update_option')->andReturn(true)->byDefault();
	}

	public function tearDown(): void {
		if (is_file($this->plugin_file)) {
			unlink($this->plugin_file);
		}
		if (is_dir($this->plugin_dir)) {
			rmdir($this->plugin_dir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_user_scope_accessor_no_writes_and_returns_instance(): void {
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();
		$userId  = 1234;

		// Expect constructor-time read via user meta storage (default)
		WP_Mock::userFunction('get_user_meta')
			->with($userId, $mainKey, true)
			->once()
			->andReturn(array());

		$opts = $config->options(array(
			'scope'   => 'user',
			'user_id' => $userId,
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	public function test_user_scope_set_option_writes_with_global_default_false(): void {
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();
		$userId  = 42;

		// Initial read (default meta backend)
		WP_Mock::userFunction('get_user_meta')
			->with($userId, $mainKey, true)
			->once()
			->andReturn(array());

		$opts = $config->options(array(
			'scope'   => 'user',
			'user_id' => $userId,
		));

		// Expect write via update_user_meta (global flag irrelevant for meta)
		WP_Mock::userFunction('update_user_meta')
			->with($userId, $mainKey, array('x' => array('value' => 1, 'autoload_hint' => null)), '')
			->once()
			->andReturn(true);

		$this->assertTrue($opts->set_option('x', 1));
	}

	public function test_user_scope_set_option_writes_with_global_true(): void {
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();
		$userId  = 7;

		// Initial read (default meta backend)
		WP_Mock::userFunction('get_user_meta')
			->with($userId, $mainKey, true)
			->once()
			->andReturn(array());

		$opts = $config->options(array(
			'scope'       => 'user',
			'user_id'     => $userId,
			'user_global' => true,
		));

		// Expect write via update_user_meta (global ignored for meta)
		WP_Mock::userFunction('update_user_meta')
			->with($userId, $mainKey, array('x' => array('value' => 2, 'autoload_hint' => null)), '')
			->once()
			->andReturn(true);

		$this->assertTrue($opts->set_option('x', 2));
	}
}
