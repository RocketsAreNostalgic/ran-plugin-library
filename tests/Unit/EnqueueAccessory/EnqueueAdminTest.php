<?php

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use Mockery\MockInterface;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\EnqueueAdmin;
use Ran\PluginLib\Util\Logger;
use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAdmin
 */
class EnqueueAdminTest extends TestCase {
	/**
	 * @var ConfigInterface|MockInterface
	 */
	protected $config_mock;

	/**
	 * @var Logger|MockInterface
	 */
	protected $logger_mock;

	/**
	 * @var EnqueueAdmin
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

		// Use a real SUT to test the integration of its methods.
		$this->sut = new EnqueueAdmin( $this->config_mock );
		$this->reflection = new \ReflectionClass( EnqueueAdmin::class );

		// Default logger to inactive. Tests that need logging should explicitly enable it.
		// Use byDefault() to allow tests to override this behavior easily.
		$this->logger_mock->shouldReceive( 'is_active' )->andReturn( false )->byDefault();
	}

	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	/**
	 * Helper to set protected properties for testing.
	 */
	protected function set_protected_property( $name, $value ) {
		$class = $this->reflection;
		while ( $class && ! $class->hasProperty( $name ) ) {
			$class = $class->getParentClass();
		}
		if ( ! $class ) {
			throw new \ReflectionException( "Property {$name} does not exist in class " . $this->reflection->getName() . " or its parents." );
		}
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $this->sut, $value );
	}

	public function test_load_does_nothing_if_not_admin() {
		WP_Mock::userFunction( 'is_admin' )->andReturn( false );

		// With the guard clause, no other functions should be called.
		WP_Mock::expectActionNotAdded( 'admin_enqueue_scripts', [ $this->sut, 'enqueue' ] );
		WP_Mock::expectActionNotAdded( 'admin_head', [ $this->sut, 'render_head' ] );
		WP_Mock::expectActionNotAdded( 'admin_footer', [ $this->sut, 'render_footer' ] );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	public function test_load_does_nothing_if_not_admin_with_logging() {
		$this->logger_mock->shouldReceive( 'is_active' )->andReturn( true );
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Method entered.' )->once();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Not an admin request. Bailing.' )->once();
		// No 'Method exited' log should be called if it bails early.

		WP_Mock::userFunction( 'is_admin' )->andReturn( false );

		$this->sut->load();
		$this->assertConditionsMet(); // For WP_Mock
		Mockery::getContainer()->mockery_verify(); // For Mockery
	}

	public function test_load_hooks_enqueue_to_admin_enqueue_scripts_when_not_fired() {
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( false );

		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', [ $this->sut, 'enqueue' ] );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	public function test_load_calls_enqueue_directly_when_hook_already_fired() {
		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( true );

		// Add a permissive logger mock to ignore any debug calls.
		$this->logger_mock->shouldReceive( 'debug' )->withAnyArgs()->zeroOrMoreTimes();

		// We expect `enqueue()` to be called, which is in the abstract parent.
		// We don't want to test the full enqueue logic here, just that the flow is correct.
		// We can mock the methods called by `enqueue()` to avoid errors.
		WP_Mock::userFunction( 'wp_enqueue_style' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_enqueue_script' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_add_inline_style' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_add_inline_script' )->andReturnNull()->zeroOrMoreTimes();

		WP_Mock::expectActionNotAdded( 'admin_enqueue_scripts', [ $this->sut, 'enqueue' ] );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	public function test_load_hooks_render_head_when_callbacks_exist_and_hook_not_fired() {
		$this->set_protected_property( 'head_callbacks', [ function() {} ] );

		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( false );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_head' )->andReturn( false );

		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', [ $this->sut, 'enqueue' ] );
		WP_Mock::expectActionAdded( 'admin_head', [ $this->sut, 'render_head' ] );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	public function test_load_calls_render_head_directly_when_callbacks_exist_and_hook_fired() {
		// Use a real closure to avoid issues with `is_callable`.
		$callback = function() { echo "test"; };
		$this->set_protected_property( 'head_callbacks', [ $callback ] );

		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_head' )->andReturn( true );

		// Add a permissive logger mock to ignore any debug calls.
		$this->logger_mock->shouldReceive( 'debug' )->withAnyArgs()->zeroOrMoreTimes();

		// Mock functions that would be called by `enqueue()` and `render_head()`.
		WP_Mock::userFunction( 'wp_enqueue_style' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_enqueue_script' )->andReturnNull()->zeroOrMoreTimes();

		// No need to mock `is_callable` or `call_user_func` as they are internal PHP functions.
		// WP_Mock cannot and should not mock them. Providing a real callable is the correct approach.

		WP_Mock::expectActionNotAdded( 'admin_head', [ $this->sut, 'render_head' ] );

		// Expect the callback to be executed.
		$this->expectOutputString( 'test' );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	public function test_load_hooks_render_footer_when_callbacks_exist_and_hook_not_fired() {
		$this->set_protected_property( 'footer_callbacks', [ function() {} ] );

		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( false );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_footer' )->andReturn( false );

		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', [ $this->sut, 'enqueue' ] );
		WP_Mock::expectActionAdded( 'admin_footer', [ $this->sut, 'render_footer' ] );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	public function test_load_calls_render_footer_directly_when_callbacks_exist_and_hook_fired() {
		$callback = function() { echo "test_footer"; };
		$this->set_protected_property( 'footer_callbacks', [ $callback ] );

		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_footer' )->andReturn( true );

		// Add a permissive logger mock to ignore any debug calls.
		$this->logger_mock->shouldReceive( 'debug' )->withAnyArgs()->zeroOrMoreTimes();

		WP_Mock::userFunction( 'wp_enqueue_style' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_enqueue_script' )->andReturnNull()->zeroOrMoreTimes();

		WP_Mock::expectActionNotAdded( 'admin_footer', [ $this->sut, 'render_footer' ] );

		$this->expectOutputString( 'test_footer' );

		$this->sut->load();
		$this->assertConditionsMet();
	}

	public function test_load_with_logging_hooks_all_actions_when_hooks_not_fired() {
		// Setup: Callbacks exist, but no hooks have fired yet.
		$this->set_protected_property( 'head_callbacks', [ function() {} ] );
		$this->set_protected_property( 'footer_callbacks', [ function() {} ] );

		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( false );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_head' )->andReturn( false );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_footer' )->andReturn( false );

		// Expectations: Logger calls in order.
		$this->logger_mock->shouldReceive( 'is_active' )->andReturn( true );

		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Method entered.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Hooking enqueue() to admin_enqueue_scripts.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Checking for head callbacks. Count: 1' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Hooking render_head() to admin_head.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Checking for footer callbacks. Count: 1' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Hooking render_footer() to admin_footer.' )->once()->ordered();
		$this->logger_mock->shouldReceive( 'debug' )->with( 'EnqueueAdmin::load() - Method exited.' )->once()->ordered();

		// Expectations: Actions are added.
		WP_Mock::expectActionAdded( 'admin_enqueue_scripts', [ $this->sut, 'enqueue' ] );
		WP_Mock::expectActionAdded( 'admin_head', [ $this->sut, 'render_head' ] );
		WP_Mock::expectActionAdded( 'admin_footer', [ $this->sut, 'render_footer' ] );

		// Execute
		$this->sut->load();

		// Verify
		$this->assertConditionsMet(); // For WP_Mock
		Mockery::getContainer()->mockery_verify(); // For Mockery
	}

	public function test_load_with_logging_calls_all_methods_directly_when_hooks_fired() {
		// Override logger mock from setUp, allowing multiple calls.
		$this->logger_mock->shouldReceive( 'is_active' )->andReturn( true )->zeroOrMoreTimes();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAdmin::load() - Method entered.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAdmin::load() - admin_enqueue_scripts already fired. Calling enqueue() directly.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAdmin::load() - Checking for head callbacks. Count: 1')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAdmin::load() - admin_head already fired. Calling render_head() directly.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAdmin::load() - admin_footer already fired. Calling render_footer() directly.')->once();
		// Add a permissive expectation for any other debug messages.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes();

		$this->set_protected_property('head_callbacks', ['test']);
		$this->set_protected_property('footer_callbacks', ['test']);

		WP_Mock::userFunction( 'is_admin' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_enqueue_scripts' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_head' )->andReturn( true );
		WP_Mock::userFunction( 'did_action' )->with( 'admin_footer' )->andReturn( true );

		// Since we are using a real object, we need to mock the methods called by enqueue(), etc.
		WP_Mock::userFunction( 'wp_enqueue_style' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_enqueue_script' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_add_inline_style' )->andReturnNull()->zeroOrMoreTimes();
		WP_Mock::userFunction( 'wp_add_inline_script' )->andReturnNull()->zeroOrMoreTimes();

		$this->sut->load();
		$this->assertConditionsMet();
	}
}
