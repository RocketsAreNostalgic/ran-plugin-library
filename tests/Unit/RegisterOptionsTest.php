<?php
/**
 * Tests for RegisterOptions class.
 *
 * @package  Ran/PluginLib
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit;

use RanTestCase;
use Ran\PluginLib\RegisterOptions;
use WP_Mock;

/**
 * Tests for RegisterOptions class.
 *
 * @covers Ran\PluginLib\RegisterOptions
 */
final class RegisterOptionsTest extends RanTestCase {
	/**
	 * The plugin data array.
	 *
	 * @var array<string, string>
	 */
	private array $plugin_data = array(
		// The text domain with spaces and dashes for underscores.
		'PluginOption' => 'ran_plugin',
	);

	/**
	 * We should be able to add an option to the RegisterOptions class.
	 */
	public function test_add_option(): void {
		$some_data = array( 'some key' => 'some value' );

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'activate_plugins' )
			->andReturnTrue();

		// Mock for RegisterOptions constructor call to get_option($this->main_wp_option_name, [])
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( $this->plugin_data['PluginOption'], [] )
			->andReturn( [] );

		// Mock for refresh_option's call to get_option($option_name, null) which happens during $registery->get_options()
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( $this->plugin_data['PluginOption'], null )
			->andReturnValues( [$some_data] );

		WP_Mock::userFunction( 'update_option' )
			->with( $this->plugin_data['PluginOption'], $some_data, null )
			->andReturnTrue();

		$registery = new RegisterOptions( $this->plugin_data['PluginOption'] );
		// The set_option method stores the value as the first element of an array and adds an 'autoload' key.
		// The autoload parameter defaults to null if not provided to set_option.
		$registery->set_option( $this->plugin_data['PluginOption'], $some_data );

		// $this->options should match what was just set.
		$this->assertEquals(
			array(
				$this->plugin_data['PluginOption'] => array(
					0          => $some_data,
					'autoload' => null, // Default autoload for set_option when not specified.
				),
			),
			$registery->get_options()
		);
	}

	/**
	 * We should be able to add multiple options to the RegisterOptions class on instantiation.
	 */
	public function test_add_mutiple_options_on_instantiation(): void {
		$some_data_value       = array( 'some key' => 'some value' ); // Value for the first option
		$other_data_value_part = array( 'some key' => 'some other value' ); // Value part for the 'taco_truck' option
		$other_data_for_constructor = array(
			'value'    => $other_data_value_part, // Explicit 'value' key
			'autoload' => false,                 // Explicit 'autoload' key
		);

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'activate_plugins' )
			->andReturnTrue();

		// Mock update_option as it's called by the constructor via set_option -> register_option.
		// It will be called for each option set by the constructor.
		WP_Mock::userFunction( 'update_option' )->andReturnTrue();

		// The first argument to RegisterOptions is the main WordPress option name.
		$main_wp_option_name = $this->plugin_data['PluginOption']; // e.g., 'ran_plugin'

		// Mock get_option for the constructor call, ensuring it returns an array.
		WP_Mock::userFunction('get_option')
			->once()
			->with($main_wp_option_name, array())
			->andReturn(array());

		// The first argument to RegisterOptions is the main WordPress option name.
		$main_wp_option_name = $this->plugin_data['PluginOption']; // e.g., 'ran_plugin'

		// The second argument is an array of sub-options to initialize.
		$sub_options_for_constructor = array(
			'first_option_key' => $some_data_value, // Passed directly as value, autoload will be null.
			'taco_truck'       => $other_data_for_constructor, // Passed as a structured array with 'value' and 'autoload'.
		);

		$registery = new RegisterOptions($main_wp_option_name, $sub_options_for_constructor);

		// $options retrieved by get_options() should be the sub-options.
		$expected_retrieved_sub_options = array(
			'first_option_key' => array(
				0          => $some_data_value,
				'autoload' => null, // Constructor's set_option defaults autoload to null if not in input array for this item.
			),
			'taco_truck' => array( // Note: constructor will normalize 'taco truck' to 'taco_truck'.
				0          => $other_data_value_part,
				'autoload' => false, // Autoload is taken from $other_data_for_constructor.
			),
		);
		// Mocks for refresh_options called by get_options() in assertion
		WP_Mock::userFunction('get_option')
			->atLeast()->once()
			->with('first_option_key', null)
			->andReturn($sub_options_for_constructor['first_option_key']);
		WP_Mock::userFunction('get_option')
			->atLeast()->once()
			->with('taco_truck', null)
			->andReturn($sub_options_for_constructor['taco_truck']['value']);

		$this->assertEquals( $expected_retrieved_sub_options, $registery->get_options() );
	}

	/**
	 * We should be able to register multiple options to the RegisterOptions class.
	 */
	public function test_register_mutiple_options(): void {
		$some_data = array(
			'taco truck' => 'yummy on wheels',
		);

		WP_Mock::userFunction( 'current_user_can' )
			->with( 'activate_plugins' )
			->andReturnTrue();

		// Mock for RegisterOptions constructor call to get_option($this->main_wp_option_name, [])
		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( $this->plugin_data['PluginOption'], [] )
			->andReturn( [] );

		$registery = new RegisterOptions( $this->plugin_data['PluginOption'] );

		// Mock update_option as it's called by set_option -> register_option.
		WP_Mock::userFunction( 'update_option' )
			->andReturnTrue();

		$registery->set_option( 'some option key', 'some value' ); // Autoload will be null.
		$registery->set_option(
			'some other key',
			array(
				'taco truck' => 'yummy on wheels',
			),
			true // Explicit autoload true.
		);
		$registery->set_option(
			'yet other key',
			array(
				'burrito truck' => array(
					'obv Im hungry',
				),
			),
			false // Explicit autoload false.
		);

		// $options should match what was just set.
		$expected_options = array(
			'some_option_key' => array( // Note: set_option normalizes keys.
				0          => 'some value',
				'autoload' => null,
			),
			'some_other_key' => array(
				0          => array( 'taco truck' => 'yummy on wheels' ),
				'autoload' => true,
			),
			'yet_other_key' => array(
				0          => array( 'burrito truck' => array( 'obv Im hungry' ) ),
				'autoload' => false,
			),
		);
		// Mocks for refresh_options called by get_options() in assertion
		WP_Mock::userFunction('get_option')
			->atLeast()->once()
			->with('some_option_key', null)
			->andReturn('some value');
		WP_Mock::userFunction('get_option')
			->atLeast()->once()
			->with('some_other_key', null)
			->andReturn(array('taco truck' => 'yummy on wheels'));
		WP_Mock::userFunction('get_option')
			->atLeast()->once()
			->with('yet_other_key', null)
			->andReturn(array('burrito truck' => array('obv Im hungry')));

		$this->assertEquals( $expected_options, $registery->get_options() );

		WP_Mock::userFunction( 'update_option' )
			->andReturnTrue();

		WP_Mock::userFunction( 'register_options' )
			->andReturnTrue();

		$registery->register_options();
	}
}
