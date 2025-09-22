<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;

/**
 * Covers blog scope forwarding of blog_id in Config::options() (line 112).
 *
 * @covers \Ran\PluginLib\Config\Config::options
 */
final class ConfigOptionsBlogScopeTest extends ConfigTestCase {
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

		// Ensure BlogOptionStorage::supports_autoload() returns false by making current blog differ
		WP_Mock::userFunction('get_current_blog_id')->andReturn(123)->byDefault();

		// Guard blog option calls used for reads; return empty structure by default
		WP_Mock::userFunction('get_blog_option')->andReturn(array())->byDefault();

		// Ensure no writes occur on set_main_autoload(false) when autoload unsupported
		WP_Mock::userFunction('delete_blog_option')->never();
		WP_Mock::userFunction('add_blog_option')->never();
		WP_Mock::userFunction('update_blog_option')->never();

		// Also guard site option calls in case anything falls back
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();
		WP_Mock::userFunction('update_option')->andReturn(true)->byDefault();
	}

	public function tearDown(): void {
		if (is_file($this->plugin_file)) {
			@unlink($this->plugin_file);
		}
		if (is_dir($this->plugin_dir)) {
			@rmdir($this->plugin_dir);
		}
		WP_Mock::tearDown();
		parent::tearDown();
	}

	public function test_blog_scope_forwards_blog_id(): void {
		$config = $this->configFromPluginFileWithLogger($this->plugin_file);

		// Acquire accessor with explicit blog entity (blog_id = 999)
		$opts = $config->options(\Ran\PluginLib\Options\Storage\StorageContext::forBlog(999));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);

		// Verify blog scope is properly configured
		$this->assertFalse($opts->supports_autoload()); // Blog scope with non-current blog doesn't support autoload
	}
}
