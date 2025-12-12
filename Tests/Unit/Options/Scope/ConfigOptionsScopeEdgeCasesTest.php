<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Config\Config;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

final class ConfigOptionsScopeEdgeCasesTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Production Config factories expect core WP functions; set minimal mocks
		WP_Mock::userFunction('plugin_dir_path')
		    ->with($this->mock_plugin_file_path)
		    ->andReturn($this->mock_plugin_dir_path);
		WP_Mock::userFunction('plugin_dir_url')
		    ->with($this->mock_plugin_file_path)
		    ->andReturn($this->mock_plugin_dir_url);
		WP_Mock::userFunction('plugin_basename')
		    ->with($this->mock_plugin_file_path)
		    ->andReturn($this->mock_plugin_basename);
		WP_Mock::userFunction('get_plugin_data')
		    ->with($this->mock_plugin_file_path, false, false)
		    ->andReturn($this->mock_plugin_data);
		WP_Mock::userFunction('sanitize_title')
		    ->with($this->mock_plugin_data['TextDomain'])
		    ->andReturn($this->mock_plugin_data['TextDomain']);
		// Mock storage adapter functions for various scopes
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_site_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('get_blog_option')->andReturn(array());
	}

	private function makeConfig(): Config {
		return Config::fromPluginFileWithLogger($this->mock_plugin_file_path, $this->logger_mock);
	}

	public function test_site_scope_ignores_entity(): void {
		$cfg    = $this->makeConfig();
		$entity = new BlogEntity(321);

		$opts = $cfg->options(StorageContext::forSite());

		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	public function test_network_scope_ignores_entity(): void {
		$cfg    = $this->makeConfig();
		$entity = new UserEntity(7, true, 'option');

		$opts = $cfg->options(StorageContext::forNetwork());

		$this->assertInstanceOf(RegisterOptions::class, $opts);
	}

	public function test_blog_scope_with_user_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$cfg = $this->makeConfig();
		// In typed API, pass invalid blog id to trigger exception at context construction
		$cfg->options(StorageContext::forBlog(0));
	}

	public function test_user_scope_with_blog_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$cfg = $this->makeConfig();
		// In typed API, pass invalid user id to trigger exception
		$cfg->options(StorageContext::forUserId(0, 'meta', false));
	}
}
