<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\Options\_helpers\RegisterOptionsWpMocksTrait;

class RegisterOptionsMigrateTest extends PluginLibTestCase {
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

	public function test_migrate_applies_transform_and_persists(): void {
		$opts      = new RegisterOptions($this->mainOption, array('x' => '1', 'y' => '2'), true, $this->config_mock->get_logger(), $this->config_mock);
		$transform = function (array $current) {
			$current['x'] = '10';
			unset($current['y']);
			$current['z'] = '3';
			return $current;
		};
		// Force get_option($main, stdClass) to return an existing stored structure so migrate proceeds
		\WP_Mock::userFunction('get_option')
		    ->with($this->mainOption, \Mockery::type('object'))
		    ->once()
		    ->andReturn(array(
		        'x' => array('value' => '1', 'autoload_hint' => null),
		        'y' => array('value' => '2', 'autoload_hint' => null),
		    ));

		// Expect core update_option with two args (autoload preserved by WP)
		\WP_Mock::userFunction('update_option')
		    ->with($this->mainOption, \Mockery::on(function ($arr) {
		    	return is_array($arr)
		    	    && isset($arr['x'], $arr['z'])
		    	    && $arr['x']['value'] === '10'
		    	    && $arr['z']['value'] === '3'
		    	    && !isset($arr['y']);
		    }))
		    ->once()
		    ->andReturn(true);

		$result = $opts->migrate($transform);
		$this->assertInstanceOf(RegisterOptions::class, $result);
		// In-memory sync assertions
		$this->assertSame('10', $opts->get_option('x'));
		$this->assertNull($opts->get_option('y', null));
		$this->assertSame('3', $opts->get_option('z'));
	}

	public function test_migrate_veto_returns_self_and_does_not_persist(): void {
		// Subclass to veto the migrate write via allow_persist gate
		$opts = new class($this->mainOption, array('a' => '1'), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? null) === 'migrate') {
					return false; // veto
				}
				return $value;
			}
		};

		// Ensure the option row exists so migrate proceeds to the gate
		\WP_Mock::userFunction('get_option')
		    ->with($this->mainOption, \Mockery::type('object'))
		    ->once()
		    ->andReturn(array('a' => array('value' => '1', 'autoload_hint' => null)));

		// No expectations for update_option; it must not be called when vetoed

		$transform = function (array $current) {
			$current['a'] = '2'; // ensure change so we reach the write gate
			return $current;
		};

		$result = $opts->migrate($transform);
		$this->assertInstanceOf(RegisterOptions::class, $result);
		// In-memory should remain unchanged due to veto
		$this->assertSame('1', $opts->get_option('a'));
	}
}
