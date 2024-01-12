<?php

/**
 * Tests for RegisterOptions class.
 *
 * @package  Ran/PluginLib
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit;

use RanTestCase;
use Ran\PluginLib\RegisterOptions;
use WP;
use WP_Mock;

/**
 *
 * @covers Ran\PluginLib\RegisterOptions
 */
final class RegisterOptionsTest extends RanTestCase
{

	private $plugin_data = [
		// The text domain with spaces and dashes for underscores.
		'PluginOption' => 'ran_plugin',
	];

	/**
	 * We should be able to add an option to the RegisterOptions class.
	 */
	public function test_add_option()
	{
		$some_data = array('some key' => 'some value');

		WP_Mock::userFunction('current_user_can')
			->with('activate_plugins')
			->andReturnTrue();

		WP_Mock::userFunction('get_option')
			->with($this->plugin_data['PluginOption'])
			->andReturnFalse();

		WP_Mock::userFunction('update_option')
			->with($this->plugin_data['PluginOption'], $some_data)
			->andReturnTrue();

		$registery = new RegisterOptions();
		$registery->add_option($this->plugin_data['PluginOption'], $some_data);

		// $this->options should match what was just set.
		$this->assertEquals(
			$registery->get_options(),
			array(
				$this->plugin_data['PluginOption'] => array(
					...$some_data, // Unpack the array.
					'autoload' => true, // WordPress defaults to true here, so RegisterOptions does too.
				)
			)
		);
	}

	public function test_add_mutiple_options_on_instantiation()
	{
		$some_data = array('some key' => 'some value');
		$some_other_data = array(
			'some key' => 'some other value',
			'autoload' => false
		);

		WP_Mock::userFunction('current_user_can')
			->with('activate_plugins')
			->andReturnTrue();

		$registery = new RegisterOptions([
			$this->plugin_data['PluginOption'] => $some_data,
			'taco truck' => $some_other_data
		]);

		// $options should match
		$this->assertEquals(
			$registery->get_options(),
			array(
				$this->plugin_data['PluginOption'] => array(
					...$some_data,
					'autoload' => true
				),
				'taco truck' => array(
					...$some_other_data
				)
			)
		);
	}


	public function test_register_mutiple_options()
	{
		$some_data = array(
			'taco truck' => 'yummy on wheels',
		);

		WP_Mock::userFunction('current_user_can')
			->with('activate_plugins')
			->andReturnTrue();

		$registery = new RegisterOptions();

		$registery->add_option('some option key', 'some value');
		$registery->add_option('some other key', array(
			'taco truck' => 'yummy on wheels',
		), true);
		$registery->add_option('yet other key', array(
			'burrito truck' => array(
				'obv Im hungry'
			),
		), false);

		// fwrite(STDERR, print_r(">>>>\n", true));
		// fwrite(STDERR, print_r($registery->get_options(), true));

		// $options should match what was just set.
		$this->assertEquals(
			array(
				'some option key' => array(
					'ran_string' => 'some value',
					'autoload' => true
				),
				'some other key' => array(
					'taco truck' => 'yummy on wheels',
					'autoload' => true
				),
				'yet other key' => array(
					'burrito truck' => 'obv Im hungry',
					'autoload' => false
				),
			),
			$registery->get_options(),
		);

		WP_Mock::userFunction('update_option')
			->andReturnTrue();

		WP_Mock::userFunction('register_options')
			->andReturnTrue();

		$registery->register_options();
	}
}
