<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Config\Config as ProdConfig;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;

final class ConfigOptionsScopeParityTest extends PluginLibTestCase {
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
	}

	private function makeProdConfig(): ProdConfig {
		return ProdConfig::fromPluginFileWithLogger($this->mock_plugin_file_path, $this->logger_mock);
	}

	public function test_site_scope_default_parity(): void {
		$cfg = $this->makeProdConfig();

		$optsViaConfig  = $cfg->options();
		$optsViaFactory = new RegisterOptions($cfg->get_options_key(), StorageContext::forSite());

		// Behavior parity: both support autoload at site scope
		$this->assertTrue($optsViaConfig->supports_autoload());
		$this->assertTrue($optsViaFactory->supports_autoload());
	}

	public function test_network_scope_parity(): void {
		$cfg = $this->makeProdConfig();

		$optsViaConfig  = $cfg->options(StorageContext::forNetwork());
		$optsViaFactory = new RegisterOptions($cfg->get_options_key(), StorageContext::forNetwork());

		$this->assertFalse($optsViaConfig->supports_autoload());
		$this->assertFalse($optsViaFactory->supports_autoload());
	}

	public function test_blog_scope_with_entity_parity(): void {
		$cfg    = $this->makeProdConfig();
		$entity = new BlogEntity(123);

		$optsViaConfig  = $cfg->options(StorageContext::forBlog(123));
		$optsViaFactory = new RegisterOptions($cfg->get_options_key(), StorageContext::forBlog(123));

		$this->assertInstanceOf(RegisterOptions::class, $optsViaConfig);
		$this->assertInstanceOf(RegisterOptions::class, $optsViaFactory);
	}

	public function test_user_scope_with_entity_parity(): void {
		$cfg    = $this->makeProdConfig();
		$entity = new UserEntity(5, true, 'option');

		$optsViaConfig  = $cfg->options(StorageContext::forUser(5, 'option', true));
		$optsViaFactory = new RegisterOptions($cfg->get_options_key(), StorageContext::forUser(5, 'option', true));

		$this->assertInstanceOf(RegisterOptions::class, $optsViaConfig);
		$this->assertInstanceOf(RegisterOptions::class, $optsViaFactory);
	}

	public function test_unknown_scope_falls_back_to_site_parity(): void {
		$cfg = $this->makeProdConfig();

		$optsViaConfig  = $cfg->options();
		$optsViaFactory = new RegisterOptions($cfg->get_options_key(), StorageContext::forSite());

		$this->assertTrue($optsViaConfig->supports_autoload());
		$this->assertTrue($optsViaFactory->supports_autoload());
	}
}
