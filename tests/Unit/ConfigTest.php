<?php
/**
 * Unit tests for the Config class.
 *
 * This file contains tests for the Config class functionality.
 *
 * @package  Ran\PluginLib\Tests\Unit
 */

declare(strict_types = 1);

namespace Ran\PluginLib\Tests\Unit;

use RanTestCase; // Declared in test_bootstrap.php.
use Ran\PluginLib\Config\Config;
use WP_Mock;

/**
 * Test for Config class.
 *
 * @covers Ran\PluginLib\Config\ConfigAbstract
 */
final class ConfigTest extends RanTestCase {
	/**
	 * The Config instance being tested.
	 *
	 * @var Config Instance of the Config class under test.
	 */
	public Config $config;

	/**
	 * The plugin directory name.
	 *
	 * @var string Directory name for the plugin.
	 */
	private string $plugin_dir = 'ran_plugin/';
	/**
	 * The plugin main file name.
	 *
	 * @var string Main PHP file name for the plugin.
	 */
	private string $plugin_file = 'ran-plugin.php';
	/**
	 * The plugin directory path.
	 *
	 * @var string Full server path to the plugins directory.
	 */
	private string $plugin_dir_path = '/var/www/html/wp-content/plugins/';
	/**
	 * The plugin directory URL.
	 *
	 * @var string Web URL to the plugins directory.
	 */
	private string $plugin_dir_url = 'http://example.com/wp-content/plugins/';
	/**
	 * Mock data for get_plugin_data function.
	 *
	 * @var array<string, string>
	 */
	private array $plugin_data = array(
		'Name'        => 'Ran Plugin',
		'Version'     => '1.0.0',
		'Description' => 'Test plugin description',
		'UpdatesURI'  => 'https://example.com/plugin/updates',
		'PluginURI'   => 'https://example.com/plugin/',
		'Author'      => 'John Doe',
		'AuthorURI'   => 'https://example.com/author',
		'TextDomain'  => 'ran-plugin',
		'DomainPath'  => '/languages',
		'RequiresPHP' => '7.0',
		'RequiresWP'  => '5.0',
	);

	/**
	 * Tests the Config constructor.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_config_contruct(): void {
		// Create Config object.
		$config = $this->get_config();

		$this->assertTrue( $config instanceof Config );
		$this->assertTrue( \property_exists( $config, 'plugin_array' ) );
	}

	/**
	 * Tests the plugin array property.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract
	 * @uses Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_plugin_array(): void {
		$wp_runtime_data = array(
			// WP adds these fields at runtime.
			'Network'    => '',
			'Title'      => '<a href="' . $this->plugin_data['PluginURI'] . '">' . $this->plugin_data['Name'] . '</a>',
			'AuthorName' => '<a href="' . $this->plugin_data['AuthorURI'] . '">' . $this->plugin_data['Author'] . '</a>',
		);

		// Set up expected plugin array.
		$expected_plugin_array = array(
			'PATH'         => $this->plugin_dir_path . $this->plugin_dir,
			'URL'          => $this->plugin_dir_url . $this->plugin_dir,
			'FileName'     => $this->plugin_file,
			'File'         => $this->plugin_file,
			'Name'         => $this->plugin_data['Name'],
			'PluginURI'    => $this->plugin_data['PluginURI'],
			'Version'      => $this->plugin_data['Version'],
			'Description'  => $this->plugin_data['Description'],
			'Author'       => $this->plugin_data['Author'],
			'AuthorURI'    => $this->plugin_data['AuthorURI'],
			'TextDomain'   => $this->plugin_data['TextDomain'],
			'DomainPath'   => $this->plugin_data['DomainPath'],
			'PluginOption' => str_replace( '-', '_', $this->plugin_data['TextDomain'] ),
			'RequiresWP'   => $this->plugin_data['RequiresWP'],
			'RequiresPHP'  => $this->plugin_data['RequiresPHP'],
			'UpdatesURI'   => $this->plugin_data['UpdatesURI'],
			// WP adds these fields at runtime.
			'Network'    => '',
			'Title'      => '<a href="' . $this->plugin_data['PluginURI'] . '">' . $this->plugin_data['Name'] . '</a>',
			'AuthorName' => '<a href="' . $this->plugin_data['AuthorURI'] . '">' . $this->plugin_data['Author'] . '</a>',
		);

		// Create Config object.
		$config = $this->get_config();

		// Assert that plugin_array property matches expected_plugin_array.
		$this->assertEquals( $expected_plugin_array, array_merge( $config->plugin_array, $wp_runtime_data ) );
	}
	/**
	 * This should throw an Exception.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract::validate_plugin_array
	 * @uses Ran\PluginLib\Config\ConfigAbstract::__construct
	 */
	public function test_validate_plugin_array(): void {
		// Create Config object.
		$config = $this->get_config();

		// Config::validate_plugin_array should throw if the array doesn't contain the required keys.
		$this->expectException( \Exception::class );
		$config->validate_plugin_array( array() );
	}

	/**
	 * Tests the get_plugin_options method.
	 *
	 * @covers Ran\PluginLib\Config\ConfigAbstract::get_plugin_options
	 */
	public function test_get_plugin_options(): void {
		// Mock the plugin option id.
		$plugin_opt_id = str_replace( '-', '_', $this->plugin_data['TextDomain'] );
		$moc_options   = array(
			'Version' => '0.0.1',
		);

		// Set up additional mock of get_option.
		WP_Mock::userFunction( 'get_option' )
			->with( $plugin_opt_id, false )
			->andReturn( $moc_options );

		// Create Config object.
		$config = $this->get_config();

		$options = $config->get_plugin_options( $plugin_opt_id, false );
		$this->assertEquals( $moc_options, $options );
	}

	/**
	 * Creates and returns a configured Config instance for testing.
	 *
	 * @return Config The configured Config instance.
	 */
	public function get_config(): Config {
		// Set up mock functions.
		WP_Mock::passthruFunction( 'sanitize_title' );
		WP_Mock::userFunction( 'plugin_basename' )
			->with( $this->plugin_dir_path )
			->andReturn( $this->plugin_file );

		WP_Mock::userFunction( 'get_plugin_array' )
			->with( dirname( __DIR__, 4 ) )
			->andReturn( $this->plugin_data );

		WP_Mock::userFunction( 'get_file_data' )
			->with( dirname( __DIR__, 4 ) )
			->andReturn( $this->plugin_dir_path );

		WP_Mock::userFunction( 'plugin_dir_path' )
			->with( dirname( __DIR__, 4 ) )
			->andReturn( $this->plugin_dir_path . $this->plugin_dir );

		WP_Mock::userFunction( 'plugin_dir_url' )
			->with( dirname( __DIR__, 4 ) )
			->andReturn( $this->plugin_dir_url . $this->plugin_dir );

		WP_Mock::userFunction( 'plugin_basename' )
			->with( $this->plugin_file )
			->andReturn( $this->plugin_file );

		WP_Mock::userFunction( 'get_plugin_data' )
			->with( $this->plugin_file )
			->andReturn( $this->plugin_data );

		// Create Config object.
		return new Config( $this->plugin_file );
	}
}
