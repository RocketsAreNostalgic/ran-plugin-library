<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config {
	use WP_Mock;
	use RanTestCase; // Declared in test_bootstrap.php
	use Ran\PluginLib\Config\ConfigType;
	use Ran\PluginLib\Config\ConfigAbstract;
	use Ran\PluginLib\Config\PluginHeaderProvider;

	/**
	 * @covers \Ran\PluginLib\Config\PluginHeaderProvider
	 */
	final class PluginHeaderProviderTest extends RanTestCase {
		private string $pluginFile;

		public function setUp(): void {
			parent::setUp();
			WP_Mock::setUp();
			$this->pluginFile = __DIR__ . '/mock-plugin-file.php';
			if (!file_exists($this->pluginFile)) {
				touch($this->pluginFile);
			}
		}

		public function tearDown(): void {
			if (file_exists($this->pluginFile)) {
				@unlink($this->pluginFile);
			}
			WP_Mock::tearDown();
			parent::tearDown();
		}

		public function test_get_standard_headers_delegates_to_config(): void {
			$cfg      = $this->createMock(ConfigAbstract::class);
			$expected = array('Name' => 'Mock');
			$cfg->expects($this->once())
			    ->method('__get_standard_plugin_headers')
			    ->with($this->pluginFile)
			    ->willReturn($expected);

			$provider = new PluginHeaderProvider($this->pluginFile, $cfg);
			$this->assertSame($expected, $provider->get_standard_headers());
		}

		public function test_get_base_identifiers_uses_wp_functions(): void {
			$pluginFile = $this->pluginFile;

			// Mock WordPress functions that the provider now calls directly via WPWrappersTrait
			WP_Mock::userFunction('plugin_dir_url')
				->once()
				->with($pluginFile)
				->andReturn('https://example.com/wp-content/plugins/mock/');

			WP_Mock::userFunction('plugin_dir_path')
				->once()
				->with($pluginFile)
				->andReturn('/var/www/html/wp-content/plugins/mock/');

			WP_Mock::userFunction('plugin_basename')
				->once()
				->with($pluginFile)
				->andReturn('mock/mock-plugin-file.php');

			$cfg      = $this->createMock(ConfigAbstract::class);
			$provider = new PluginHeaderProvider($pluginFile, $cfg);

			[$path, $url, $name] = $provider->get_base_identifiers();

			$this->assertSame('/var/www/html/wp-content/plugins/mock/', $path);
			$this->assertSame('https://example.com/wp-content/plugins/mock/', $url);
			$this->assertSame('mock/mock-plugin-file.php', $name);
		}

		public function test_comment_path_type_defaults_and_env_specific_keys(): void {
			$cfg      = $this->createMock(ConfigAbstract::class);
			$provider = new PluginHeaderProvider($this->pluginFile, $cfg);

			$this->assertSame($this->pluginFile, $provider->get_comment_source_path());
			$this->assertSame(ConfigType::Plugin, $provider->get_type());
			$this->assertSame('my_slug', $provider->get_default_app_option_slug('my_slug'));

			$env = $provider->get_env_specific_normalized_keys(array('base_name' => 'mock/mock-plugin-file.php'));
			$this->assertSame(
				array('Basename' => 'mock/mock-plugin-file.php', 'File' => $this->pluginFile),
				$env
			);
		}
	}
}


