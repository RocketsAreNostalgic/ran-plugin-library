<?php
/**
 * TestableConfigWithHeaderMock class for testing ConfigAbstract methods.
 *
 * @package Ran\PluginLib\Tests\Unit\TestClasses
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit\TestClasses;

use Ran\PluginLib\Config\Config;

/**
 * TestableConfigWithHeaderMock class for testing ConfigAbstract methods.
 *
 * This class extends Config and provides a mock implementation of
 * _read_plugin_file_header_content to avoid file system access.
 */
class TestableConfigWithHeaderMock extends Config {
	/**
	 * Mock plugin header content.
	 *
	 * @var string
	 */
	private static string $mock_header_content = '';

	/**
	 * Set the mock header content to be returned.
	 *
	 * @param string $content The mock header content.
	 * @return void
	 */
	public static function set_mock_header_content(string $content): void {
		self::$mock_header_content = $content;
	}

	/**
	 * Override _read_plugin_file_header_content to return mock content.
	 *
	 * @param string $file_path The full path to the plugin file.
	 * @return string The mock content.
	 */
	protected function _read_plugin_file_header_content(string $file_path): string {
		return self::$mock_header_content;
	}
}
