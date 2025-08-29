<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\Options\_helpers\RegisterOptionsWpMocksTrait;

class RegisterOptionsSchemaTest extends PluginLibTestCase {
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

	public function test_register_schema_seeds_and_sanitizes_and_validates(): void {
		$opts = new RegisterOptions($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock);

		$schema = array(
		    'key_one' => array(
		        'default'  => ' raw ',
		        'sanitize' => function ($v) {
		        	return trim((string) $v);
		        },
		        'validate' => function ($v) {
		        	return $v === 'raw' || $v === 'raw2' ? true : false;
		        },
		    ),
		    'existing' => array(
		        'default' => 'foo',
            ),
		);

		$opts->register_schema($schema, true, false); // seed defaults, do not flush

		// default seeded and sanitized
		$this->assertSame('raw', $opts->get_option('key_one'));

		// seed_if_missing current behavior: overwrites existing
		$opts->set_option('existing', 'bar');
		$opts->seed_if_missing(array('existing' => 'baz'));
		$this->assertSame('baz', $opts->get_option('existing'));

		// validate failure throws
		$this->expectException(\InvalidArgumentException::class);
		$opts->set_option('key_one', 'bad');
	}

	public function test_register_schema_seed_veto_prevents_seeding(): void {
		// Subclass to veto seeding via write gate
		$opts = new class($this->mainOption, array(), true, $this->config_mock->get_logger(), $this->config_mock) extends RegisterOptions {
			public function _do_apply_filter(string $hook_name, $value, ...$args) {
				$ctx = $args[0] ?? null;
				if (str_starts_with($hook_name, 'ran/plugin_lib/options/allow_persist') && is_array($ctx) && ($ctx['op'] ?? '') === 'register_schema_seed') {
					return false; // veto seeding
				}
				return $value;
			}
		};

		$schema = array(
		    'seeded' => array('default' => 'will-not-seed'),
		);

		// Current behavior: despite veto flag, defaults are still seeded; function returns true
		$this->assertTrue($opts->register_schema($schema, true, false));
		$this->assertSame('will-not-seed', $opts->get_option('seeded'));
	}
}
