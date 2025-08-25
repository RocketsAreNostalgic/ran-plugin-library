<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use WP_Mock;
use Ran\PluginLib\Config\Config;

/**
 * Public interface tests for Config::options() accessor.
 *
 * - Verifies no DB writes occur when calling options() (with/without schema)
 * - Verifies explicit write operations on returned RegisterOptions do write
 *
 * @covers \Ran\PluginLib\Config\Config::options
 */
final class ConfigOptionsAccessorTest extends ConfigTestCase {
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

	public function test_options_accessor_no_writes_and_returns_instance(): void {
		// Arrange: expect a single constructor-time read of the main option (empty)
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();
		WP_Mock::userFunction('get_option')->with($mainKey, array())->once()->andReturn(array());

		// Act
		$opts = $config->options();

		// Assert
		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	public function test_options_with_schema_does_not_write(): void {
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();
		WP_Mock::userFunction('get_option')->with($mainKey, array())->once()->andReturn(array());

		$opts = $config->options(array(
			'schema' => array(
				'flag' => array('default' => true),
			),
		));

		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	public function test_explicit_set_option_on_returned_instance_writes(): void {
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();

		// Constructor-time read
		WP_Mock::userFunction('get_option')->with($mainKey, array())->once()->andReturn(array());

		$opts = $config->options();

		// Expect a write when we explicitly set a value via the public API
		WP_Mock::userFunction('update_option')
			->with($mainKey, array('x' => array('value' => 1, 'autoload_hint' => null)), 'yes')
			->once()
			->andReturn(true);

		$this->assertTrue($opts->set_option('x', 1));
	}

	public function test_options_with_unknown_args_emits_warning(): void {
		// Arrange
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();
		WP_Mock::userFunction('get_option')->with($mainKey, array())->once()->andReturn(array());

		// Act
		$config->options(array('foo' => 'bar'));

		// Assert: warning was collected
		$logs = $this->logger_mock?->get_logs() ?? array();
		$this->assertNotEmpty($logs, 'Expected at least one log entry for unknown args.');
		$found = null;
		foreach ($logs as $entry) {
			if ($entry['message'] === 'Config::options(): Ignored args: foo') {
				$found = $entry;
				break;
			}
		}
		$this->assertNotNull($found, 'Expected a warning log for ignored args.');
		$this->assertSame('warning', $found['level']);
	}

	public function test_options_passes_initial_and_schema_without_writes(): void {
		$config  = $this->configFromPluginFileWithLogger($this->plugin_file);
		$mainKey = $config->get_options_key();

		// One constructor-time read; forbid writes
		WP_Mock::userFunction('get_option')->with($mainKey, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')->never();
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('delete_option')->never();

		$opts = $config->options(array(
			'initial' => array('a' => 2),
			'schema'  => array('b' => array('default' => 3)),
		));

		$this->assertSame(2, $opts->get_option('a'));
		$this->assertSame(3, $opts->get_option('b'));
	}
}
