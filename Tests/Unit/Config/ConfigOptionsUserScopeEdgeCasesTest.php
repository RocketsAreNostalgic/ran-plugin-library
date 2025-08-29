<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;

/**
 * Edge cases for Config::options() when using user scope.
 *
 * @covers \Ran\PluginLib\Config\Config::options
 * @covers \Ran\PluginLib\Options\RegisterOptions::from_config
 */
final class ConfigOptionsUserScopeEdgeCasesTest extends ConfigTestCase {
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

		WP_Mock::setUp();
		WP_Mock::userFunction('plugin_dir_path')->with($this->plugin_file)->andReturn($this->plugin_dir . '/')->byDefault();
		WP_Mock::userFunction('plugin_dir_url')->with($this->plugin_file)->andReturn($this->plugin_url)->byDefault();
		WP_Mock::userFunction('plugin_basename')->with($this->plugin_file)->andReturn($this->plugin_basename)->byDefault();
		WP_Mock::userFunction('get_plugin_data')->with($this->plugin_file, false, false)->andReturn($this->plugin_data)->byDefault();
		WP_Mock::userFunction('sanitize_key')->andReturnUsing(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', (string)$v)))->byDefault();
		// Guard site option calls if any
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

	public function test_missing_user_id_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$config = $this->configFromPluginFileWithLogger($this->plugin_file);
		// Trigger factory make('user') without required user_id
		$config->options(array('scope' => 'user'));
	}

	public function test_non_array_initial_read_is_handled(): void {
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();
		$userId  = 555;

		// Non-array initial payload should be normalized to [] (meta default backend)
		WP_Mock::userFunction('get_user_meta')->with($userId, $mainKey, true)->once()->andReturn('oops');

		$opts = $config->options(array(
			'scope'   => 'user',
			'user_id' => $userId,
		));

		// A subsequent write should work normally and not include any prior structure (meta backend)
		WP_Mock::userFunction('update_user_meta')
			->with($userId, $mainKey, array('k' => array('value' => 'v', 'autoload_hint' => null)), '')
			->once()
			->andReturn(true);

		$this->assertTrue($opts->set_option('k', 'v'));
	}
}
