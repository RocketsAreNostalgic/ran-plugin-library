<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\OptionScope;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\Options\_helpers\RegisterOptionsWpMocksTrait;

class RegisterOptionsAutoloadTest extends PluginLibTestCase {
	use RegisterOptionsWpMocksTrait;

	private string $mainOption = 'ran_plugin_public_api_test';

	public function setUp(): void {
		parent::setUp();
		$this->init_wp_mocks($this->mainOption);
	}

	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	// supports_autoload()
	public function test_supports_autoload_site_scope_true(): void {
		$opts = new RegisterOptions($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock);
		$this->assertTrue($this->_invoke_protected_method($opts, 'supports_autoload'));
	}

	public function test_supports_autoload_blog_current_true_and_other_false(): void {
		// Current blog (1)
		$opts = RegisterOptions::from_config($this->config_mock, array(), true, null, array(), OptionScope::Blog, array('blog_id' => 1));
		$this->assertTrue($this->_invoke_protected_method($opts, 'supports_autoload'));
		// Other blog (2)
		$opts2 = RegisterOptions::from_config($this->config_mock, array(), true, null, array(), OptionScope::Blog, array('blog_id' => 2));
		$this->assertFalse($this->_invoke_protected_method($opts2, 'supports_autoload'));
	}

	public function test_supports_autoload_network_and_user_false(): void {
		$net = RegisterOptions::from_config($this->config_mock, array(), true, null, array(), OptionScope::Network, array());
		$usr = RegisterOptions::from_config($this->config_mock, array(), true, null, array(), OptionScope::User, array('user_id' => 123));
		$this->assertFalse($this->_invoke_protected_method($net, 'supports_autoload'));
		$this->assertFalse($this->_invoke_protected_method($usr, 'supports_autoload'));
	}

	// load_all_autoloaded()
	public function test_load_all_autoloaded_returns_array_for_supported_scope(): void {
		WP_Mock::userFunction('wp_load_alloptions')->andReturn(array('a' => 'x'))->byDefault();
		$opts   = new RegisterOptions($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock);
		$result = $this->_invoke_protected_method($opts, 'load_all_autoloaded');
		$this->assertIsArray($result);
		$this->assertSame(array('a' => 'x'), $result);
	}

	public function test_load_all_autoloaded_returns_null_for_unsupported_scope(): void {
		$usr = RegisterOptions::from_config($this->config_mock, array(), true, null, array(), OptionScope::User, array('user_id' => 5));
		$this->assertNull($this->_invoke_protected_method($usr, 'load_all_autoloaded'));
	}
}
