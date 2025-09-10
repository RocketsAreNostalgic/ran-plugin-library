<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Config\Config as ProdConfig;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

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

		$optsViaConfig  = $cfg->options(array());
		$optsViaFactory = RegisterOptions::_from_config($cfg, true, null, array());

		$scopeA = $this->_get_protected_property_value($optsViaConfig, 'storage_scope');
		$argsA  = $this->_get_protected_property_value($optsViaConfig, 'storage_args');
		$scopeB = $this->_get_protected_property_value($optsViaFactory, 'storage_scope');
		$argsB  = $this->_get_protected_property_value($optsViaFactory, 'storage_args');

		$this->assertSame(null, $scopeA);
		$this->assertSame(array(), $argsA);
		$this->assertSame($scopeA, $scopeB);
		$this->assertSame($argsA, $argsB);
	}

	public function test_network_scope_parity(): void {
		$cfg = $this->makeProdConfig();

		$optsViaConfig  = $cfg->options(array('scope' => 'network'));
		$optsViaFactory = RegisterOptions::_from_config($cfg, true, 'network', array());

		$scopeA = $this->_get_protected_property_value($optsViaConfig, 'storage_scope');
		$argsA  = $this->_get_protected_property_value($optsViaConfig, 'storage_args');
		$scopeB = $this->_get_protected_property_value($optsViaFactory, 'storage_scope');
		$argsB  = $this->_get_protected_property_value($optsViaFactory, 'storage_args');

		$this->assertSame('network', $scopeA);
		$this->assertSame(array(), $argsA);
		$this->assertSame($scopeA, $scopeB);
		$this->assertSame($argsA, $argsB);
	}

	public function test_blog_scope_with_entity_parity(): void {
		$cfg    = $this->makeProdConfig();
		$entity = new BlogEntity(123);

		$optsViaConfig  = $cfg->options(array('scope' => 'blog', 'entity' => $entity));
		$optsViaFactory = RegisterOptions::_from_config($cfg, true, 'blog', $entity->toStorageArgs());

		$scopeA = $this->_get_protected_property_value($optsViaConfig, 'storage_scope');
		$argsA  = $this->_get_protected_property_value($optsViaConfig, 'storage_args');
		$scopeB = $this->_get_protected_property_value($optsViaFactory, 'storage_scope');
		$argsB  = $this->_get_protected_property_value($optsViaFactory, 'storage_args');

		$this->assertSame('blog', $scopeA);
		$this->assertSame(array('blog_id' => 123), $argsA);
		$this->assertSame($scopeA, $scopeB);
		$this->assertSame($argsA, $argsB);
	}

	public function test_user_scope_with_entity_parity(): void {
		$cfg    = $this->makeProdConfig();
		$entity = new UserEntity(5, true, 'option');

		$optsViaConfig  = $cfg->options(array('scope' => 'user', 'entity' => $entity));
		$optsViaFactory = RegisterOptions::_from_config($cfg, true, 'user', $entity->toStorageArgs());

		$scopeA = $this->_get_protected_property_value($optsViaConfig, 'storage_scope');
		$argsA  = $this->_get_protected_property_value($optsViaConfig, 'storage_args');
		$scopeB = $this->_get_protected_property_value($optsViaFactory, 'storage_scope');
		$argsB  = $this->_get_protected_property_value($optsViaFactory, 'storage_args');

		$this->assertSame('user', $scopeA);
		$this->assertSame(array(
		    'user_id'      => 5,
		    'user_global'  => true,
		    'user_storage' => 'option',
		), $argsA);
		$this->assertSame($scopeA, $scopeB);
		$this->assertSame($argsA, $argsB);
	}

	public function test_unknown_scope_falls_back_to_site_parity(): void {
		$cfg = $this->makeProdConfig();

		$optsViaConfig  = $cfg->options(array('scope' => 'unknown-scope'));
		$optsViaFactory = RegisterOptions::_from_config($cfg, true, null, array());

		$scopeA = $this->_get_protected_property_value($optsViaConfig, 'storage_scope');
		$argsA  = $this->_get_protected_property_value($optsViaConfig, 'storage_args');
		$scopeB = $this->_get_protected_property_value($optsViaFactory, 'storage_scope');
		$argsB  = $this->_get_protected_property_value($optsViaFactory, 'storage_args');

		$this->assertSame(null, $scopeA);
		$this->assertSame(array(), $argsA);
		$this->assertSame($scopeA, $scopeB);
		$this->assertSame($argsA, $argsB);
	}
}
