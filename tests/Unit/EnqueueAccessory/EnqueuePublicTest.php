<?php

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Mockery\MockInterface;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueuePublic;
use Ran\PluginLib\Util\Logger;
use WP_Mock;

/**
 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueuePublic
 */
class EnqueuePublicTest extends \RanTestCase {
	/**
	 * @var ConfigInterface|MockInterface
	 */
	protected $config_mock;

	/**
	 * @var Logger|MockInterface
	 */
	protected $logger_mock;

	/**
	 * @var EnqueuePublic|MockInterface
	 */
	protected $sut;

	/**
	 * @var \ReflectionClass
	 */
	protected $reflection;


	public function setUp(): void {
		parent::setUp();
		$this->config_mock = Mockery::mock( ConfigInterface::class );
		$this->logger_mock = Mockery::mock( Logger::class );
		$this->config_mock->shouldReceive( 'get_logger' )->andReturn( $this->logger_mock );
		$this->logger_mock->shouldReceive( 'is_active' )->andReturn( false )->byDefault(); // Default to inactive, allows overriding.

		// Use a partial mock to test the `load` method while being able to mock others.
		$this->sut        = Mockery::mock( EnqueuePublic::class, array( $this->config_mock ) )->makePartial()->shouldAllowMockingProtectedMethods();
		$this->reflection = new \ReflectionClass( EnqueuePublic::class );
	}

	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	/**
	 * Helper to set protected properties on the SUT.
	 *
	 * @param string $property_name
	 * @param mixed  $value
	 */
	protected function set_protected_property( $name, $value ) {
		$class = $this->reflection; // Use the reflection of the original class
		while ( $class && ! $class->hasProperty( $name ) ) {
			$class = $class->getParentClass();
		}
		if ( ! $class ) {
			throw new \ReflectionException( "Property {$name} does not exist in class " . $this->reflection->getName() . ' or its parents.' );
		}
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $this->sut, $value );
	}

	/**
	 * @test
	 */
	public function load_hooks_enqueue_to_wp_enqueue_scripts(): void {
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		WP_Mock::expectActionAdded( 'wp_enqueue_scripts', array( $this->sut, 'enqueue' ) );

		// No other actions should be added if callbacks/deferred are empty.
		WP_Mock::expectActionNotAdded( 'wp_head', array( $this->sut, 'render_head' ) );
		WP_Mock::expectActionNotAdded( 'wp_footer', array( $this->sut, 'render_footer' ) );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 */
	public function load_hooks_render_head_when_head_callbacks_exist(): void {
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		$this->set_protected_property( 'head_callbacks', array( 'some_callback' ) );

		WP_Mock::expectActionAdded( 'wp_enqueue_scripts', array( $this->sut, 'enqueue' ) );
		WP_Mock::expectActionAdded( 'wp_head', array( $this->sut, 'render_head' ) );
		WP_Mock::expectActionNotAdded( 'wp_footer', array( $this->sut, 'render_footer' ) );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 */
	public function load_hooks_render_footer_when_footer_callbacks_exist(): void {
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		$this->set_protected_property( 'footer_callbacks', array( 'some_callback' ) );

		WP_Mock::expectActionAdded( 'wp_enqueue_scripts', array( $this->sut, 'enqueue' ) );
		WP_Mock::expectActionNotAdded( 'wp_head', array( $this->sut, 'render_head' ) );
		WP_Mock::expectActionAdded( 'wp_footer', array( $this->sut, 'render_footer' ) );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 */
	public function load_hooks_deferred_scripts_to_their_actions(): void {
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		$deferred_scripts = array(
			'hook_one' => array( 'script1' ),
			'hook_two' => array( 'script2' ),
		);
		$this->set_protected_property( 'deferred_scripts', $deferred_scripts );

		WP_Mock::expectActionAdded( 'wp_enqueue_scripts', array( $this->sut, 'enqueue' ) );

		// Expect actions to be added for each deferred hook.
		// The callback is a closure, so we can't match it directly.
		// We can use Mockery::type('callable') or a more specific check.
		WP_Mock::expectActionAdded( 'hook_one', Mockery::type( 'callable' ) );
		WP_Mock::expectActionAdded( 'hook_two', Mockery::type( 'callable' ) );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 */
	public function load_does_nothing_when_is_admin_is_true(): void {
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );

		// Also test the logging path
		$this->logger_mock->shouldReceive( 'is_active' )->andReturn( true );
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Method entered.' )->once();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - In admin area. Bailing.' )->once();


		// No actions should be added.
		WP_Mock::expectActionNotAdded( 'wp_enqueue_scripts', Mockery::any() );
		WP_Mock::expectActionNotAdded( 'wp_head', Mockery::any() );
		WP_Mock::expectActionNotAdded( 'wp_footer', Mockery::any() );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	/**
	 * @test
	 */
	public function load_with_logging_hooks_all_actions(): void {
		// Setup
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );
		$this->set_protected_property( 'head_callbacks', array( 'head_callback' ) );
		$this->set_protected_property( 'footer_callbacks', array( 'footer_callback' ) );
		$this->set_protected_property( 'deferred_scripts', array( 'deferred_hook' => array( 'script' ) ) );

		// Logger Expectations (ordered)
		$this->logger_mock->shouldReceive( 'is_active' )->andReturn( true );

		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Method entered.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Hooking enqueue() to wp_enqueue_scripts.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Checking for head callbacks. Count: 1' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Hooking render_head() to wp_head.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Checking for footer callbacks. Count: 1' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Hooking render_footer() to wp_footer.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Checking for deferred script hooks. Count: 1' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Hooking enqueue_deferred_scripts() to action \'deferred_hook\'.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueuePublic::load() - Method exited.' )->once()->ordered();

		// Action Expectations
		WP_Mock::expectActionAdded( 'wp_enqueue_scripts', array( $this->sut, 'enqueue' ) );
		WP_Mock::expectActionAdded( 'wp_head', array( $this->sut, 'render_head' ) );
		WP_Mock::expectActionAdded( 'wp_footer', array( $this->sut, 'render_footer' ) );
		WP_Mock::expectActionAdded( 'deferred_hook', Mockery::type( 'callable' ) );

		// Execute
		$this->sut->load();

		// Verify
		$this->assertConditionsMet();
	}
}
