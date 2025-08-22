<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

final class RegisterOptionsMergeTest extends PluginLibTestCase {
	private RegisterOptions $options;

	public function setUp(): void {
		parent::setUp();
		// Ensure sanitize_key is defined consistently for these tests
		WP_Mock::userFunction('sanitize_key')
		    ->andReturnUsing(function ($v) {
		    	$s = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $v));
		    	return trim($s, '_');
		    })
		    ->byDefault();
		// Provide a config mock that returns a real CollectingLogger
		$config = Mockery::mock(ConfigInterface::class);
		$config->shouldReceive('get_logger')->andReturn(new CollectingLogger())->byDefault();

		// Default: constructor get_option call returns an empty array unless overridden per-test
		WP_Mock::userFunction('get_option')->andReturn(array())->byDefault();

		// Create instance with a known option name and injected config
		$this->options = new RegisterOptions('test_main_option', /* initial */ array(), /* autoload */ true, /* logger */ null, $config);
	}

	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	public function test_save_all_options_without_merge_updates_and_mirrors(): void {
		// Arrange: set two values in memory via fluent add (no write yet)
		$this->options->add_options(array(
		    'alpha' => 'A',
		    'beta'  => array('value' => 'B'),
		));

		// Expect a single update_option call with the in-memory structure; autoload true
		WP_Mock::userFunction('update_option')
		    ->with('test_main_option', Mockery::on(function ($val) {
		    	return is_array($val)
		    	    && isset($val['alpha']['value']) && $val['alpha']['value'] === 'A'
		    	    && isset($val['beta']['value']) && $val['beta']['value']   === 'B';
		    }), 'yes')
		    ->andReturn(true)
		    ->once();

		// Act
		$result = $this->options->flush(false);

		// Assert
		$this->assertTrue($result);
		$all = $this->options->get_options();
		$this->assertSame('A', $all['alpha']['value']);
		$this->assertSame('B', $all['beta']['value']);
	}

	public function test_save_all_options_with_mergeFromDb_merges_and_updates(): void {
		// Arrange: DB has one key; memory sets a different key
		WP_Mock::userFunction('get_option')
		    ->with('test_main_option', array())
		    ->andReturn(array('db_only' => array('value' => 1)))
		    ->once();

		// Set in-memory value without writing
		$this->options->add_option('mem_only', 2);

		// Expect merged structure on save: both db_only and mem_only present
		WP_Mock::userFunction('update_option')
		    ->with('test_main_option', Mockery::on(function ($val) {
		    	return is_array($val)
		    	    && isset($val['db_only']['value']) && $val['db_only']['value']   === 1
		    	    && isset($val['mem_only']['value']) && $val['mem_only']['value'] === 2;
		    }), 'yes')
		    ->andReturn(true)
		    ->once();

		// Act
		$result = $this->options->flush(true);

		// Assert
		$this->assertTrue($result);
		$all = $this->options->get_options();
		$this->assertSame(1, $all['db_only']['value']);
		$this->assertSame(2, $all['mem_only']['value']);
	}

	public function test_register_schema_returns_false_on_empty_schema(): void {
		// No update_option should be called; we simply assert return value
		$result = $this->options->register_schema(array(), seedDefaults: true, flush: true);
		$this->assertFalse($result);
	}

	public function test_flush_mergeFromDb_handles_non_array_db_value(): void {
		// Arrange: DB returns a non-array value for the option
		WP_Mock::userFunction('get_option')
		    ->with('test_main_option', array())
		    ->andReturn('not-an-array')
		    ->once();

		// Set an in-memory key; DB side should be coerced to [] and then merged
		$this->options->add_option('only_mem', 'val');

		// Expect update_option to receive only the in-memory structure
		WP_Mock::userFunction('update_option')
		    ->with('test_main_option', Mockery::on(function ($val) {
		    	return is_array($val)
		    	    && isset($val['only_mem']['value'])
		    	    && $val['only_mem']['value'] === 'val';
		    }), 'yes')
		    ->andReturn(true)
		    ->once();

		// Act
		$result = $this->options->flush(true);

		// Assert
		$this->assertTrue($result);
		$all = $this->options->get_options();
		$this->assertSame('val', $all['only_mem']['value']);
	}
}


