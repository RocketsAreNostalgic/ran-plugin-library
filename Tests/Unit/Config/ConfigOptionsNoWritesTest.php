<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Config;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Tests\Unit\TestClasses\TestableConfig;
use WP_Mock;

/**
 * Tests for Config::options() no-write semantics (Task 2.3)
 * and explicit operations causing writes (Task 2.4).
 *
 * @covers \Ran\PluginLib\Config\Config::options
 */
final class ConfigOptionsNoWritesTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
		// Common sanitize_key stub (mirrors other tests)
		WP_Mock::userFunction('sanitize_key')
			->andReturnUsing(function ($v) {
				$s = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $v));
				return trim($s, '_');
			})
			->byDefault();
	}

	/**
	 * Ensure default options() call performs zero writes.
	 */
	public function test_options_no_args_performs_no_writes(): void {
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		// Allow a read during RegisterOptions construction
		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());
		// Forbid any writes
		WP_Mock::userFunction('update_option')->never();
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('delete_option')->never();

		$opts = $cfg->options();
		$this->assertInstanceOf(\Ran\PluginLib\Options\RegisterOptions::class, $opts);
	}

	/**
	 * Schema provided → register only; still no writes.
	 */
	public function test_options_with_schema_registers_only_no_writes(): void {
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')->never();
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('delete_option')->never();

		$schema = array(
			'flag' => array('default' => false, 'validate' => fn($v) => is_bool($v)),
		);
		$opts = $cfg->options(array('schema' => $schema));
		$this->assertSame(false, $opts->get_option('flag'));
	}

	/**
	 * Initial values provided → TestableConfig::options ignores 'initial'; still no writes.
	 */
	public function test_options_with_initial_only_no_writes(): void {
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')->never();
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('delete_option')->never();

		$opts = $cfg->options(array('initial' => array('foo' => 'bar')));
		// TestableConfig::options() intentionally does not pass 'initial' through; ensure no value present
		$this->assertFalse($opts->get_option('foo'));
	}

	/**
	 * Schema + Initial → schema default in-memory; 'initial' ignored; no writes.
	 */
	public function test_options_with_schema_and_initial_no_writes(): void {
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		WP_Mock::userFunction('get_option')->with($main, array())->once()->andReturn(array());
		WP_Mock::userFunction('update_option')->never();
		WP_Mock::userFunction('add_option')->never();
		WP_Mock::userFunction('delete_option')->never();

		$schema = array(
			'timeout' => array('default' => 5, 'validate' => fn($v) => is_int($v) && $v > 0),
		);
		$opts = $cfg->options(array(
			'initial'  => array('foo' => 'bar'),
			'schema'   => $schema,
			'autoload' => false,
		));
		// 'initial' ignored by TestableConfig::options(); schema registered without seeding -> default not applied
		$this->assertFalse($opts->get_option('foo'));
		$this->assertFalse($opts->get_option('timeout'));
	}

	/**
	 * Explicit operations: register_schema with seed+flush → one write expected.
	 */
	public function test_explicit_register_schema_seed_and_flush_writes_once(): void {
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		// Comprehensive mock setup for get_option calls
		WP_Mock::userFunction('get_option')
			->andReturnUsing(function ($name, $default = false) use ($main) {
				if ($name === $main && $default === array()) {
					return array(); // Main option doesn't exist
				}
				if (is_object($default)) {
					return $default; // Sentinel case - option doesn't exist
				}
				return false; // Default for other cases
			});

		// One write on flush after seeding - direct values now (option doesn't exist, so add_option)
		WP_Mock::userFunction('add_option')->with($main, array(
			'flag' => true,
		), '', 'yes')->once()->andReturn(true);

		$opts = $cfg->options();
		$this->assertTrue($opts->register_schema(array(
			'flag' => array('default' => true, 'validate' => fn($v) => is_bool($v)),
		), seed_defaults: true, flush: true));
		$this->assertTrue($opts->get_option('flag'));
	}

	/**
	 * Explicit operations: add_options + flush → one write expected.
	 */
	public function test_explicit_add_options_then_flush_writes_once(): void {
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		// Comprehensive mock setup for get_option calls
		WP_Mock::userFunction('get_option')
			->andReturnUsing(function ($name, $default = false) use ($main) {
				if ($name === $main && $default === array()) {
					return array(); // Main option doesn't exist
				}
				if (is_object($default)) {
					return $default; // Sentinel case - option doesn't exist
				}
				return false; // Default for other cases
			});
		// One write on flush - direct values now (option doesn't exist, so add_option)
		WP_Mock::userFunction('add_option')->with($main, array(
			'a' => 1,
			'b' => 'x',
		), '', 'yes')->once()->andReturn(true);

		$opts = $cfg->options();
		$opts->add_options(array('a' => 1, 'b' => 'x'));
		$this->assertTrue($opts->flush());
	}



	/**
	 * Explicit operations: set_option changed value → writes once.
	 */
	public function test_explicit_set_option_changed_value_writes_once(): void {
		WP_Mock::userFunction('sanitize_title')->andReturn('test-plugin');
		$cfg  = new TestableConfig();
		$main = $cfg->get_config()['RAN']['AppOption'];

		// Comprehensive mock setup for get_option calls
		WP_Mock::userFunction('get_option')
			->andReturnUsing(function ($name, $default = false) use ($main) {
				if ($name === $main && $default === array()) {
					return array('k' => 1); // Main option exists with existing value
				}
				if (is_object($default)) {
					return array('k' => 1); // Sentinel case - return existing value (option exists)
				}
				return false; // Default for other cases
			});
		WP_Mock::userFunction('update_option')->with($main, array('k' => 2))->once()->andReturn(true);

		$opts = $cfg->options();
		$this->assertTrue($opts->set_option('k', 2));
	}
}
