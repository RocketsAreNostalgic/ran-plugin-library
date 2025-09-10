<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Options\Entity\BlogEntity;
use Ran\PluginLib\Options\Entity\UserEntity;
use Ran\PluginLib\Config\Config as ProdConfig;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use WP_Mock;

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
	}

	private function makeProdConfig(): ProdConfig {
		return ProdConfig::fromPluginFileWithLogger($this->mock_plugin_file_path, $this->logger_mock);
	}

	public function test_site_scope_ignores_entity(): void {
		$cfg    = $this->makeProdConfig();
		$entity = new BlogEntity(321);

		$opts = $cfg->options(array('scope' => 'site', 'entity' => $entity));

		$scope = $this->_get_protected_property_value($opts, 'storage_scope');
		$args  = $this->_get_protected_property_value($opts, 'storage_args');

		$this->assertSame(null, $scope);
		$this->assertSame(array(), $args);
	}

	public function test_network_scope_ignores_entity(): void {
		$cfg    = $this->makeProdConfig();
		$entity = new UserEntity(7, true, 'option');

		$opts = $cfg->options(array('scope' => 'network', 'entity' => $entity));

		$scope = $this->_get_protected_property_value($opts, 'storage_scope');
		$args  = $this->_get_protected_property_value($opts, 'storage_args');

		$this->assertSame('network', $scope);
		$this->assertSame(array(), $args);
	}

	public function test_blog_scope_with_user_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$cfg    = $this->makeProdConfig();
		$entity = new UserEntity(5);
		$cfg->options(array('scope' => 'blog', 'entity' => $entity));
	}

	public function test_user_scope_with_blog_entity_throws(): void {
		$this->expectException(\InvalidArgumentException::class);
		$cfg    = $this->makeProdConfig();
		$entity = new BlogEntity(9);
		$cfg->options(array('scope' => 'user', 'entity' => $entity));
	}
}
