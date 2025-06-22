<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use WP_Mock;
use Mockery;
use Mockery\MockInterface;

/**
 * Concrete implementation of ScriptsEnqueueTrait for testing script-related methods.
 */
class ConcreteEnqueueForScriptsTesting extends AssetEnqueueBaseAbstract {
	use ScriptsEnqueueTrait;

	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	public function load(): void {
		// Minimal implementation for testing purposes.
	}

	// Expose protected property for testing
	public function get_internal_inline_scripts_array(): array {
		return $this->inline_scripts;
	}
}

/**
 * Class ScriptsEnqueueTraitTest
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait
 * @property ConcreteEnqueueForScriptsTesting&MockInterface $instance
 */
class ScriptsEnqueueTraitTest extends PluginLibTestCase {
	private static int $hasActionCallCount = 0;

	/** @var ConcreteEnqueueForScriptsTesting&MockInterface */
	protected $instance; // Mockery will handle the type

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp(); // This sets up config_mock and logger_mock

		self::$hasActionCallCount = 0;

		// Ensure the logger is considered active for all tests in this class.
		$this->logger_mock->shouldReceive('is_active')->andReturn(true)->byDefault();
		$this->logger_mock->shouldReceive('is_verbose')->andReturn(true)->byDefault();

		// Set up default, permissive expectations for all log levels.
		// Individual tests can override these with more specific expectations.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('info')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('error')->withAnyArgs()->andReturnNull()->byDefault();

		// Default WP_Mock function mocks for script functions
		WP_Mock::userFunction('wp_register_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_script')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_add_inline_script')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault(); // 0 means false
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault(); // Default to not admin context
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_script_is')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('wp_json_encode', array(
			'return' => static fn($data) => json_encode($data),
		))->byDefault();
		WP_Mock::userFunction('esc_attr', array(
			'return' => static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'),
		))->byDefault();

		// Create a partial mock of ConcreteEnqueueForScriptsTesting
		$this->instance = Mockery::mock(
			ConcreteEnqueueForScriptsTesting::class,
			array($this->config_mock) // Pass the config_mock from parent
		)->makePartial();
		$this->instance->shouldAllowMockingProtectedMethods();

		// Ensure the instance uses the correct logger mock from the parent setup.
		$this->instance->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();

		// Mock has_action to control its return value for specific tests
		WP_Mock::userFunction('has_action')
			->with(Mockery::any(), Mockery::any())
			->andReturnUsing(function ($hook, $callback) {
				// Default behavior: no action exists.
				// Tests can add more specific expectations.
				return false;
			})
			->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	// ------------------------------------------------------------------------
	// Test Methods for Script Functionalities
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::get_scripts
	 */
	public function test_add_scripts_should_store_scripts_correctly(): void {
		$scripts_to_add = array(
			array(
				'handle'    => 'my-script-1',
				'src'       => 'path/to/my-script-1.js',
				'deps'      => array('jquery-ui-script'),
				'version'   => '1.0.0',
				'media'     => 'screen',
				'condition' => static fn() => true,
			),
			array(
				'handle'  => 'my-script-2',
				'src'     => 'path/to/my-script-2.js',
				'deps'    => array(),
				'version' => false, // Use plugin version
				'media'   => 'all',
				// No condition, should default to true
			),
		);

		// Logger expectations for ScriptsEnqueueTrait::add_scripts()
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Entered. Current script count: 0. Adding 2 new script(s).')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Adding script. Key: 0, Handle: my-script-1, Src: path/to/my-script-1.js')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Adding script. Key: 1, Handle: my-script-2, Src: path/to/my-script-2.js')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Adding 2 script definition(s). Current total: 0')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: 2')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - All current script handles: my-script-1, my-script-2')->once();

		// Call the method under test
		$result = $this->instance->add_scripts($scripts_to_add);

		// Assert chainability
		$this->assertSame($this->instance, $result, 'add_scripts() should be chainable.');

		// Retrieve and check stored scripts
		$retrieved_scripts_array = $this->instance->get_scripts();
		$this->assertArrayHasKey('general', $retrieved_scripts_array);
		// print the retrieved scripts array for debugging
		// fwrite(STDERR, var_export($retrieved_scripts_array['general'], true) . "\n");

		$general_scripts = $retrieved_scripts_array['general'];
		$this->assertCount(count($scripts_to_add), $general_scripts, 'Should have the same number of scripts added.');

		// Check first script (my-script-1)
		if (isset($general_scripts[0])) {
			$this->assertEquals('my-script-1', $general_scripts[0]['handle']);
			$this->assertEquals('path/to/my-script-1.js', $general_scripts[0]['src']);
			$this->assertEquals(array('jquery-ui-script'), $general_scripts[0]['deps']);
			$this->assertEquals('1.0.0', $general_scripts[0]['version']);
			$this->assertEquals('screen', $general_scripts[0]['media']);
			$this->assertTrue(is_callable($general_scripts[0]['condition']));
			$this->assertTrue(($general_scripts[0]['condition'])());
		}

		// Check second script (my-script-2)
		if (isset($general_scripts[1])) {
			$this->assertEquals('my-script-2', $general_scripts[1]['handle']);
			$this->assertEquals('path/to/my-script-2.js', $general_scripts[1]['src']);
			$this->assertEquals(array(), $general_scripts[1]['deps']);
			$this->assertEquals(false, $general_scripts[1]['version']); // As per input
			$this->assertEquals('all', $general_scripts[1]['media']);
			// Check that 'condition' is not present if not provided and not yet processed to default
			$this->assertArrayNotHasKey('condition', $scripts_to_add[1]);
			$this->assertArrayNotHasKey('condition', $general_scripts[1]);
		}

		//Check script properties
		$this->assertEquals('path/to/my-script-1.js', $retrieved_scripts_array['general'][0]['src']);
		$this->assertEquals('path/to/my-script-2.js', $retrieved_scripts_array['general'][1]['src']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 */
	public function test_add_scripts_handles_single_script_definition_correctly(): void {
		$script_to_add = array(
			'handle' => 'single-script',
			'src'    => 'path/to/single.js',
			'deps'   => array(),
		);

		// Logger expectations for ScriptsEnqueueTrait::add_scripts() for a single script
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Adding script. Key: 0, Handle: single-script, Src: path/to/single.js')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Adding 1 script definition(s). Current total: 0')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: 1')->once();
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - All current script handles: single-script')->once();

		// Call the method under test
		$this->instance->add_scripts($script_to_add);

		// Retrieve and check stored scripts
		$retrieved_scripts = $this->instance->get_scripts()['general'];
		$this->assertCount(1, $retrieved_scripts);
		$this->assertEquals('single-script', $retrieved_scripts[0]['handle']);
		$this->assertEquals('path/to/single.js', $retrieved_scripts[0]['src']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::add_scripts
	 */
	public function test_add_scripts_handles_empty_input_gracefully(): void {
		// Logger expectations for ScriptsEnqueueTrait::add_scripts() with an empty array.
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Entered with empty array. No scripts to add.')->once();

		// Call the method under test
		$this->instance->add_scripts(array());

		// Retrieve and check stored scripts
		$retrieved_scripts = $this->instance->get_scripts()['general'];
		$this->assertCount(0, $retrieved_scripts, 'The scripts queue should be empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 */
	public function test_register_scripts_registers_non_hooked_script_correctly(): void {
		$script_to_add = array(
			'handle'  => 'my-script',
			'src'     => 'path/to/my-script.js',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
		);

		// --- Logger Mocks for ScriptsEnqueueTrait::add_scripts() ---
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Adding script. Key: 0, Handle: my-script, Src: path/to/my-script.js')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Adding 1 script definition(s). Current total: 0')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: 1')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::add_scripts - All current script handles: my-script')->once()->ordered('add');

		$this->instance->add_scripts(array($script_to_add));

		// --- WP_Mock and Logger Mocks for register_scripts() ---
		$is_registered = false;

		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::register_scripts - Entered. Processing 1 script definition(s) for registration.')->once()->ordered('register');
		$this->logger_mock->shouldReceive('debug')->with('ScriptsEnqueueTrait::register_scripts - Processing script: "my-script", original index: 0.')->once()->ordered('register');
		$this->logger_mock->shouldReceive('debug')->with("ScriptsEnqueueTrait::_process_single_script - Processing script 'my-script' in context 'register_scripts'.")->once()->ordered('register');

		// Mock wp_script_is to reflect the state change
		WP_Mock::userFunction('wp_script_is', array(
			'args'   => array('my-script', 'registered'),
			'return' => function () use ( &$is_registered ) {
				return $is_registered;
			},
		))->atLeast()->once();

		$this->logger_mock->shouldReceive('debug')->with("ScriptsEnqueueTrait::_process_single_script - Registering script 'my-script'.")->once()->ordered('register');

		// Mock wp_register_script to simulate the state change
		WP_Mock::userFunction('wp_register_script', array(
			'args'   => array('my-script', 'path/to/my-script.js', array(), '1.0', false),
			'times'  => 1,
			'return' => function () use ( &$is_registered ) {
				$is_registered = true;
				return true;
			},
		));

		// Mocks for inline script processing
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'my-script'.")->ordered('register');
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'my-script'.")->ordered('register');

		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Finished processing script 'my-script'.")->ordered('register');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Exited. Remaining immediate scripts: 1. Deferred scripts: 0.')->ordered('register');

		// Call the method under test
		$this->instance->register_scripts();

		// Assert that the script is still in the queue for enqueuing later
		$retrieved_scripts = $this->instance->get_scripts();
		$this->assertCount(1, $retrieved_scripts['general']);
		$this->assertEquals('my-script', $retrieved_scripts['general'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::register_scripts
	 */
	public function test_register_scripts_defers_hooked_script_correctly(): void {
		$script_to_add = array(
			'handle' => 'my-deferred-script',
			'src'    => 'path/to/deferred.js',
			'hook'   => 'admin_enqueue_scripts',
		);

		// --- Logger Mocks for add_scripts() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Adding script. Key: 0, Handle: my-deferred-script, Src: path/to/deferred.js');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Adding 1 script definition(s). Current total: 0');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: 1');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - All current script handles: my-deferred-script');

		$this->instance->add_scripts(array($script_to_add));

		// --- WP_Mock and Logger Mocks for register_scripts() ---
		WP_Mock::userFunction('has_action')
			->with('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_scripts'))
			->andReturn(false); // Mock that the action hasn't been added yet

		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_scripts'), 10, 1);

		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Entered. Processing 1 script definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Processing script: "my-deferred-script", original index: 0.');
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::register_scripts - Deferring registration of script 'my-deferred-script' (original index 0) to hook: admin_enqueue_scripts.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::register_scripts - Added action for 'enqueue_deferred_scripts' on hook: admin_enqueue_scripts.");
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Exited. Remaining immediate scripts: 0. Deferred scripts: 4.');

		// Call the method under test
		$this->instance->register_scripts();

		// Assert that the script is in the deferred queue
		// Access protected property $deferred_scripts for assertion
		$reflection            = new \ReflectionClass($this->instance);
		$deferred_scripts_prop = $reflection->getProperty('deferred_scripts');
		$deferred_scripts_prop->setAccessible(true);
		$deferred_scripts = $deferred_scripts_prop->getValue($this->instance);

		$this->assertArrayHasKey('admin_enqueue_scripts', $deferred_scripts);
		$this->assertCount(1, $deferred_scripts['admin_enqueue_scripts']);
		// The key for the script definition within the hook's array is its original index.
		// In this test, we add a single script, so its original index is 0.
		$this->assertArrayHasKey(0, $deferred_scripts['admin_enqueue_scripts']);
		$this->assertEquals('my-deferred-script', $deferred_scripts['admin_enqueue_scripts'][0]['handle']);

		// Assert that the main scripts queue is empty as the script was deferred
		// Access protected property $scripts for assertion
		$scripts_prop = $reflection->getProperty('scripts');
		$scripts_prop->setAccessible(true);
		$scripts_array = $scripts_prop->getValue($this->instance);
		$this->assertEmpty($scripts_array);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::register_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 */
	public function test_register_scripts_skips_script_if_condition_is_false(): void {
		$script_to_add = array(
			'handle'    => 'my-conditional-script',
			'src'       => 'path/to/conditional.js',
			'condition' => function () {
				return false;
			},
		);

		// --- Logger Mocks for add_scripts() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Adding script. Key: 0, Handle: my-conditional-script, Src: path/to/conditional.js');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Adding 1 script definition(s). Current total: 0');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: 1');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - All current script handles: my-conditional-script');

		$this->instance->add_scripts(array($script_to_add));

		// --- WP_Mock and Logger Mocks for register_scripts() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Entered. Processing 1 script definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Processing script: "my-conditional-script", original index: 0.');
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Processing script 'my-conditional-script' in context 'register_scripts'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Condition not met for script 'my-conditional-script'. Skipping.");

		// Assert that wp_register_script is never called
		WP_Mock::userFunction('wp_register_script')->never();
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Exited. Remaining immediate scripts: 0. Deferred scripts: 0.');

		// Call the method under test
		$this->instance->register_scripts();

		// Assert that the script was processed and removed from the immediate queue
		$this->assertEmpty($this->instance->get_scripts()['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_enqueue_scripts_enqueues_registered_script(): void {
		$script_to_add = array(
			'handle'  => 'my-basic-script',
			'src'     => 'path/to/basic.js',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
		);

		// --- Mocks for add_scripts() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Adding script. Key: 0, Handle: my-basic-script, Src: path/to/basic.js');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Adding 1 script definition(s). Current total: 0');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: 1');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::add_scripts - All current script handles: my-basic-script');
		$this->instance->add_scripts(array($script_to_add));

		// --- Consolidated WP_Mock::userFunction('wp_script_is', ...) calls for 'my-basic-script' ---
		// For 'registered' status - 4 calls expected:
		// 1. In register_scripts->_process_single_script (trait L551): before wp_register_script -> returns false
		// 2. In register_scripts->_process_single_script->_process_inline_scripts (trait L440): after wp_register_script -> returns true
		// 3. In enqueue_scripts->_process_single_script (trait L551): script already registered -> returns true
		// 4. In enqueue_scripts->_process_single_script->_process_inline_scripts (trait L440): script still registered -> returns true (for short-circuiting)
		WP_Mock::userFunction('wp_script_is')
		    ->with('my-basic-script', 'registered')
		    ->times(4)
		    ->andReturnValues(array(false, true, true, true));

		// For 'enqueued' status - 1 call expected:
		// 1. In enqueue_scripts->_process_single_script (trait L570): returns false (before wp_enqueue_script call).
		// The call at _process_inline_scripts (trait L440) for 'enqueued' status is NOT expected if short-circuiting works correctly.
		WP_Mock::userFunction('wp_script_is')
		    ->with('my-basic-script', 'enqueued')
		    ->times(1)
		    ->andReturnValues(array(false));

		// --- Mocks for register_scripts() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Entered. Processing 1 script definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Processing script: "my-basic-script", original index: 0.');
		// _process_single_script (called by register_scripts)
		// Call to wp_script_is('my-basic-script', 'registered') handled by consolidated mock (1st call, returns false)
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Processing script 'my-basic-script' in context 'register_scripts'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Registering script 'my-basic-script'.");
		WP_Mock::userFunction('wp_register_script', array('args' => array('my-basic-script', 'path/to/basic.js', array(), '1.0', false), 'times' => 1, 'return' => true));
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Finished processing script 'my-basic-script'.");
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::register_scripts - Exited. Remaining immediate scripts: 1. Deferred scripts: 0.');
		$this->instance->register_scripts();

		// --- Mocks for enqueue_scripts() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::enqueue_scripts - Entered. Processing 1 script definition(s) from internal queue.');
		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::enqueue_scripts - Processing script: "my-basic-script", original index: 0.');

		// _process_single_script (called by enqueue_scripts)
		// The call to wp_script_is('my-basic-script', 'registered') at trait line 551 is handled by the
		// second call to the consolidated mock (defined earlier), which returns true.
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Processing script 'my-basic-script' in context 'enqueue_scripts'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Script 'my-basic-script' already registered. Skipping wp_register_script."); // Log from line 553
		// Enqueue check within _process_single_script (do_enqueue=true)
		// The call to wp_script_is('my-basic-script', 'enqueued') at trait line 570 is handled by the
		// consolidated mock for 'enqueued' status (defined earlier), which returns false.
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Enqueuing script 'my-basic-script'.");
		WP_Mock::userFunction('wp_enqueue_script', array('args' => array('my-basic-script'), 'times' => 1));
		// Inline script check within _process_single_script
		// _process_inline_scripts (called by _process_single_script from enqueue_scripts)
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'my-basic-script'.");
		// Parent script 'my-basic-script' will have been enqueued by wp_enqueue_script just before _process_inline_scripts is called.
		// Parent check in _process_inline_scripts (trait line 440): `if ( ! wp_script_is( $parent_handle, 'registered' ) && ! wp_script_is( $parent_handle, 'enqueued' ) )`
		// The call to wp_script_is('my-basic-script', 'registered') at trait line 440 is handled by the
		// third call to the consolidated mock for 'registered' status (defined earlier), which returns true.
		// Because `!wp_script_is(..., 'registered')` evaluates to `!true` (which is `false`),
		// the `&&` condition short-circuits.
		// Therefore, the `wp_script_is('my-basic-script', 'enqueued')` part of the condition at trait line 440 is NOT executed.
		// This prevents the "No matching handler found" error for that specific call.
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'my-basic-script'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_single_script - Finished processing script 'my-basic-script'.");

		$this->logger_mock->shouldReceive('debug')->once()->with('ScriptsEnqueueTrait::enqueue_scripts - Exited. Deferred scripts count: 0.');

		$this->instance->enqueue_scripts();

		$this->assertEmpty($this->instance->get_scripts()['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_deferred_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_enqueue_deferred_scripts_processes_and_enqueues_script_on_hook(): void {
		// Arrange
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$script_handle = 'my-deferred-script';
		$hook_name     = 'wp_footer';

		$deferred_script = array(
			'handle'  => $script_handle,
			'src'     => 'path/to/deferred.js',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
			'hook'    => $hook_name,
		);

		// --- Act 1: Add the script ---
		$add_scripts_prefix = 'ScriptsEnqueueTrait::add_scripts';
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_scripts_prefix} - Entered. Current script count: 0. Adding 1 new script(s).");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_scripts_prefix} - Adding script. Key: 0, Handle: {$script_handle}, Src: path/to/deferred.js");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_scripts_prefix} - Adding 1 script definition(s). Current total: 0");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_scripts_prefix} - Exiting. New total script count: 1");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_scripts_prefix} - All current script handles: {$script_handle}");
		$this->instance->add_scripts(array($deferred_script));

		// --- Act 2: Register the script (which defers it) ---
		$register_scripts_prefix = 'ScriptsEnqueueTrait::register_scripts - ';
		$this->logger_mock->shouldReceive('debug')->once()->with($register_scripts_prefix . 'Entered. Processing 1 script definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with($register_scripts_prefix . "Processing script: \"{$script_handle}\", original index: 0.");
		$this->logger_mock->shouldReceive('debug')->once()->with($register_scripts_prefix . "Deferring registration of script '{$script_handle}' (original index 0) to hook: {$hook_name}.");
		WP_Mock::userFunction('has_action', array(
			'args'   => array($hook_name, array($this->instance, 'enqueue_deferred_scripts')),
			'times'  => 1,
			'return' => false
		));
		WP_Mock::expectActionAdded($hook_name, array($this->instance, 'enqueue_deferred_scripts'), 10, 1);
		$this->logger_mock->shouldReceive('debug')->once()->with($register_scripts_prefix . "Added action for 'enqueue_deferred_scripts' on hook: {$hook_name}.");
		$this->logger_mock->shouldReceive('debug')
			->once()
			->withArgs(function ($message) {
				return str_starts_with($message, 'ScriptsEnqueueTrait::register_scripts - Exited. Remaining immediate scripts: 0') && str_contains($message, '. Deferred scripts:');
			});
		$this->instance->register_scripts();

		// --- Assert state after registration ---
		$deferred_scripts_prop = new \ReflectionProperty($this->instance, 'deferred_scripts');
		$deferred_scripts_prop->setAccessible(true);
		$current_deferred = $deferred_scripts_prop->getValue($this->instance);
		$this->assertArrayHasKey($hook_name, $current_deferred);
		$this->assertEquals($script_handle, $current_deferred[$hook_name][0]['handle']);

		// --- Act 3: Trigger the deferred enqueue ---
		$enqueue_deferred_prefix = 'ScriptsEnqueueTrait::enqueue_deferred_scripts - ';
		$process_single_prefix   = 'ScriptsEnqueueTrait::_process_single_script - ';

		// This is the key fix: "Entered hook" not "Entered for hook"
		$this->logger_mock->shouldReceive('debug')->once()->with($enqueue_deferred_prefix . "Entered hook: \"{$hook_name}\".");
		$this->logger_mock->shouldReceive('debug')->once()->with($enqueue_deferred_prefix . "Processing deferred script: \"{$script_handle}\" (original index 0) for hook: \"{$hook_name}\".");

		// _process_single_script mocks
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Processing script '{$script_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.");
		WP_Mock::userFunction('wp_script_is')->with($script_handle, 'registered')->times(2)->andReturnValues(array(false, true));
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Registering script '{$script_handle}' on hook '{$hook_name}'.");
		WP_Mock::userFunction('wp_register_script', array('args' => array($script_handle, 'path/to/deferred.js', array(), '1.0', false), 'times' => 1, 'return' => true));
		WP_Mock::userFunction('wp_script_is')->with($script_handle, 'enqueued')->once()->andReturn(false);
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Enqueuing script '{$script_handle}' on hook '{$hook_name}'.");
		WP_Mock::userFunction('wp_enqueue_script', array('args' => array($script_handle), 'times' => 1));
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Finished processing script '{$script_handle}' on hook '{$hook_name}'.");

		// Final log in enqueue_deferred_scripts
		$this->logger_mock->shouldReceive('debug')->once()->with($enqueue_deferred_prefix . "Exited for hook: \"{$hook_name}\".");

		// Execute
		$this->instance->enqueue_deferred_scripts($hook_name);

		// --- Assert final state ---
		$current_deferred_after = $deferred_scripts_prop->getValue($this->instance);
		$this->assertArrayNotHasKey($hook_name, $current_deferred_after, 'Deferred scripts for the hook should be cleared after processing.');
	} // End of test_enqueue_deferred_scripts_processes_and_enqueues_script_on_hook

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_deferred_scripts
	 */
	public function test_enqueue_deferred_scripts_skips_if_hook_not_set(): void {
		$this->enable_console_logging = true;
		// Arrange
		$hook_name  = 'non_existent_hook';
		$log_prefix = 'ScriptsEnqueueTrait::enqueue_deferred_scripts - ';
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Entered hook: "' . $hook_name . '".');

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Hook "' . $hook_name . '" not found in deferred scripts. Nothing to process.');

		// Act
		$this->instance->enqueue_deferred_scripts($hook_name);

		// Assert
		// Mockery handles the primary assertions. This is a final state check for robustness.
		$deferred_scripts_prop = new \ReflectionProperty($this->instance, 'deferred_scripts');
		$deferred_scripts_prop->setAccessible(true);
		$current_deferred = $deferred_scripts_prop->getValue($this->instance);
		$this->assertArrayNotHasKey($hook_name, $current_deferred, 'Hook should not have been added to deferred_scripts.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_deferred_scripts
	 */
	public function test_enqueue_deferred_scripts_skips_if_hook_set_but_empty(): void {
		// Arrange
		$hook_name  = 'empty_hook_for_scripts';
		$log_prefix = 'ScriptsEnqueueTrait::enqueue_deferred_scripts - ';

		// Set the deferred_scripts property to have the hook, but with an empty array of scripts.
		$deferred_scripts_prop = new \ReflectionProperty($this->instance, 'deferred_scripts');
		$deferred_scripts_prop->setAccessible(true);
		$deferred_scripts_prop->setValue($this->instance, array($hook_name => array()));

		// Ensure the logger is considered active for the conditional log statements.
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Entered hook: "' . $hook_name . '".');

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Hook "' . $hook_name . '" was set but had no scripts. It has now been cleared.');

		// Act
		$this->instance->enqueue_deferred_scripts($hook_name);

		// Assert
		$current_deferred = $deferred_scripts_prop->getValue($this->instance);
		$this->assertArrayNotHasKey($hook_name, $current_deferred, 'The hook should be cleared from deferred scripts.');
	}

	/**
	 * Tests basic addition of an inline script when the logger is inactive.
	 */
	public function test_add_inline_scripts_basic_no_logger() {
		// Arrange: Configure logger to be inactive for this test
		$this->logger_mock->shouldReceive('is_active')->andReturn(false);

		$handle            = 'test-script-handle';
		$content           = '.test-class { color: blue; }';
		$expected_position = 'after'; // Default

		// Act: Call the method under test
		$this->instance->add_inline_scripts( $handle, $content );

		// Assert: Check the internal $inline_scripts property
		$inline_scripts = $this->get_protected_property_value( $this->instance, 'inline_scripts' );

		$this->assertCount( 1, $inline_scripts, 'Expected one inline script to be added.' );
		$added_script = $inline_scripts[0];

		$this->assertEquals( $handle, $added_script['handle'], 'Handle does not match.' );
		$this->assertEquals( $content, $added_script['content'], 'Content does not match.' );
		$this->assertEquals( $expected_position, $added_script['position'], 'Position does not match default.' );
		$this->assertNull( $added_script['condition'], 'Condition should be null by default.' );
		$this->assertNull( $added_script['parent_hook'], 'Parent hook should be null by default.' );
	} // End of test_add_inline_scripts_basic_no_logger

	/**
	 * Tests addition of an inline script with an active logger and ensures correct log messages.
	 */
	public function test_add_inline_scripts_with_active_logger() {
		// Arrange: Configure logger to be active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$handle  = 'test-log-handle';
		$content = '.log-class { font-weight: bold; }';
		// inline_scripts is reset to [] in setUp, so initial count is 0.
		$initial_inline_scripts_count = 0;

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::add_inline_scripts - Entered. Current inline script count: {$initial_inline_scripts_count}. Adding new inline script for handle: " . \esc_html($handle));

		// No parent hook finding log expected in this basic case as $this->scripts is empty.

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('ScriptsEnqueueTrait::add_inline_scripts - Exiting. New total inline script count: ' . ($initial_inline_scripts_count + 1));

		// Act: Call the method under test
		$this->instance->add_inline_scripts($handle, $content);

		// Assert: Check the internal $inline_scripts property (basic check, primary assertion is logger)
		$inline_scripts = $this->get_protected_property_value( $this->instance, 'inline_scripts' );
		$this->assertCount($initial_inline_scripts_count + 1, $inline_scripts, 'Expected one inline script to be added.');
		// Get the last added script (which will be the first if initial_inline_scripts_count is 0)
		$added_script = $inline_scripts[$initial_inline_scripts_count];

		$this->assertEquals($handle, $added_script['handle']);
		$this->assertEquals($content, $added_script['content']);
	} // End of test_add_inline_scripts_with_active_logger

	/**
	 * Tests that add_inline_scripts correctly associates a parent_hook
	 * from an existing registered script if not explicitly provided.
	 */
	public function test_add_inline_scripts_associates_parent_hook_from_registered_scripts() {
		// Arrange: Configure logger to be active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$parent_handle                = 'parent-for-inline';
		$parent_hook_name             = 'my_custom_parent_hook';
		$inline_content               = 'console.log("Inline script for parent: " + ' . \esc_html($parent_handle) . ');';
		$initial_inline_scripts_count = 0; // As it's reset in setUp

		// Pre-populate the $scripts property with a parent script
		$parent_script_definition = array(
			'handle'    => $parent_handle,
			'src'       => 'path/to/parent.js',
			'deps'      => array(),
			'ver'       => '1.0',
			'media'     => 'all',
			'hook'      => $parent_hook_name, // This is key
			'condition' => null,
			'extra'     => array(),
		);
		$scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'scripts');
		$scripts_property->setAccessible(true);
		$scripts_property->setValue($this->instance, array($parent_script_definition));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::add_inline_scripts - Entered. Current inline script count: {$initial_inline_scripts_count}. Adding new inline script for handle: " . \esc_html($parent_handle));

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::add_inline_scripts - Inline script for '{$parent_handle}' associated with parent hook: '{$parent_hook_name}'. Original parent script hook: '{$parent_hook_name}'.");

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('ScriptsEnqueueTrait::add_inline_scripts - Exiting. New total inline script count: ' . ($initial_inline_scripts_count + 1));

		// Act: Call the method under test, $parent_hook is null by default
		$this->instance->add_inline_scripts($parent_handle, $inline_content);

		// Assert: Check the internal $inline_scripts property
		$inline_scripts_array = $this->get_protected_property_value($this->instance, 'inline_scripts');
		$this->assertCount($initial_inline_scripts_count + 1, $inline_scripts_array, 'Expected one inline script to be added.');

		$added_inline_script = $inline_scripts_array[$initial_inline_scripts_count];
		$this->assertEquals($parent_handle, $added_inline_script['handle']);
		$this->assertEquals($inline_content, $added_inline_script['content']);
		$this->assertEquals($parent_hook_name, $added_inline_script['parent_hook'], 'Parent hook was not correctly associated from the registered script.');
	} // End of test_add_inline_scripts_associates_parent_hook_from_registered_scripts

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_process_inline_scripts_logs_and_exits_if_inline_scripts_globally_empty() {
		// Arrange
		$script_handle      = 'test-parent-script';
		$processing_context = 'direct_call'; // Context for this direct test
		$script_definition  = array(
		    'handle'  => $script_handle,
		    'src'     => 'path/to/script.js',
		    'deps'    => array(),
		    'version' => false,
		    'media'   => 'all',
		);

		// Ensure $this->inline_scripts is globally empty
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array());

		// Logger active for debug messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Ensure wp_script_is returns false so registration/enqueueing is attempted
		\WP_Mock::userFunction('wp_script_is')
		    ->with($script_handle, 'registered')
		    ->andReturn(false);
		\WP_Mock::userFunction('wp_script_is')
		    ->with($script_handle, 'enqueued')
		    ->andReturn(false);

		// --- Ordered Logger and WP_Mock expectations ---

		// 1. Log from _process_single_script entry
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("ScriptsEnqueueTrait::_process_single_script - Processing script '{$script_handle}' in context '{$processing_context}'.");

		// 2. Log before wp_register_script
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("ScriptsEnqueueTrait::_process_single_script - Registering script '{$script_handle}'.");

		// 3. Mock wp_register_script call, ensuring it returns true
		\WP_Mock::userFunction('wp_register_script')->once()
		    ->with($script_handle, $script_definition['src'], $script_definition['deps'], $script_definition['version'], false)
		    ->andReturn(true);

		// 4. Log before wp_enqueue_script
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("ScriptsEnqueueTrait::_process_single_script - Enqueuing script '{$script_handle}'.");

		// 5. Mock wp_enqueue_script call
		\WP_Mock::userFunction('wp_enqueue_script')->once()->with($script_handle);

		// 6. Log before _process_inline_scripts
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("ScriptsEnqueueTrait::_process_single_script - Checking for inline scripts for '{$script_handle}'.");

		// 7. Target Log from _process_inline_scripts (Gap 1)
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("ScriptsEnqueueTrait::_process_inline_scripts (context: _{$processing_context}) - No inline scripts defined globally. Nothing to process for handle '{$script_handle}'.");

		// 8. Log from _process_single_script completion
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("ScriptsEnqueueTrait::_process_single_script - Finished processing script '{$script_handle}'.");

		// Act: Call _process_single_script directly using reflection
		$method = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$method->invoke($this->instance, $script_definition, $processing_context, null, true, true); // do_register=true, do_enqueue=true

		// Assert: Mockery will assert its expectations automatically.
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_process_inline_scripts_skips_if_condition_is_false() {
		// Arrange
		$parent_handle      = 'test-parent-script';
		$processing_context = 'direct_call';
		$script_definition  = array(
			'handle'  => $parent_handle,
			'src'     => 'path/to/parent.js',
			'deps'    => array(),
			'version' => false,
			'media'   => 'all',
		);

		// Define an inline script with a condition that returns false
		$inline_script_with_condition = array(
			'parent_handle' => $parent_handle,
			'content'       => '.conditional-script { display: none; }',
			'condition'     => function () {
				return false;
			},
		);
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($inline_script_with_condition));

		// Logger active for debug messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Mocks for parent script processing
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(false);
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'enqueued')->andReturn(false);
		\WP_Mock::userFunction('wp_register_script')->once()->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_script')->once();

		// Crucially, wp_add_inline_script should NOT be called
		\WP_Mock::userFunction('wp_add_inline_script')->never();

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Processing script '{$parent_handle}' in context '{$processing_context}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Registering script '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Enqueuing script '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Checking for inline scripts for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: _{$processing_context}) - Processing inline scripts for parent handle '{$parent_handle}'.");

		// Target Log for this test
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: _{$processing_context}) - Condition not met for inline script with parent '{$parent_handle}'. Skipping.");

		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: _{$processing_context}) - Finished processing inline scripts for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Finished processing script '{$parent_handle}'.");

		// Act
		$method = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$method->invoke($this->instance, $script_definition, $processing_context, null, true, true);

		// Assert
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_process_inline_scripts_skips_if_content_is_empty() {
		// Arrange
		$parent_handle      = 'test-parent-script';
		$processing_context = 'direct_call';
		$script_definition  = array(
			'handle'  => $parent_handle,
			'src'     => 'path/to/parent.js',
			'deps'    => array(),
			'version' => false,
			'media'   => 'all',
		);

		// Define an inline script with empty content
		$inline_script_empty_content = array(
			'parent_handle' => $parent_handle,
			'content'       => '', // Empty content
		);
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($inline_script_empty_content));

		// Logger active for debug/warning messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Mocks for parent script processing
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(false);
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'enqueued')->andReturn(false);
		\WP_Mock::userFunction('wp_register_script')->once()->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_script')->once();

		// Crucially, wp_add_inline_script should NOT be called
		\WP_Mock::userFunction('wp_add_inline_script')->never();

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Processing script '{$parent_handle}' in context '{$processing_context}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Registering script '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Enqueuing script '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Checking for inline scripts for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: _{$processing_context}) - Processing inline scripts for parent handle '{$parent_handle}'.");

		// Target Log for this test
		$this->logger_mock->shouldReceive('warning')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: _{$processing_context}) - Invalid inline script definition for parent '{$parent_handle}'. Missing content. Skipping.");

		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: _{$processing_context}) - Finished processing inline scripts for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_single_script - Finished processing script '{$parent_handle}'.");

		// Act
		$method = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_single_script');
		$method->setAccessible(true);
		$method->invoke($this->instance, $script_definition, $processing_context, null, true, true);

		// Assert
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_process_inline_scripts_adds_script_successfully() {
		// Arrange
		$parent_handle      = 'test-parent-script';
		$processing_context = 'direct_call';
		$script_definition  = array(
			'handle'  => $parent_handle,
			'src'     => 'path/to/parent.js',
			'deps'    => array(),
			'version' => false,
			'media'   => 'all',
		);

		$inline_content  = 'console.log("Inline script for parent: " + ' . \esc_html($parent_handle) . ');';
		$inline_position = 'after';

		// Define a valid inline script
		$valid_inline_script = array(
			'handle'   => $parent_handle,
			'content'  => $inline_content,
			'position' => $inline_position,
		);
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($valid_inline_script));

		// Logger active for debug messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Mocks for parent script processing. We assume the script is already registered and enqueued.
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(true);
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'enqueued')->andReturn(true);

		// Because the script is already registered/enqueued, these should not be called.
		\WP_Mock::userFunction('wp_register_script')->never();
		\WP_Mock::userFunction('wp_enqueue_script')->never();

		// Crucially, wp_add_inline_script SHOULD be called and return true
		\WP_Mock::userFunction('wp_add_inline_script')->once()
			->with($parent_handle, $inline_content, $inline_position)
			->andReturn(true);


		// --- Ordered Logger expectations ---
		// For public enqueue_inline_scripts()
		$this->logger_mock->shouldReceive('debug')->ordered()->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Entered method.');
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::enqueue_inline_scripts - Found 1 unique parent handle(s) with immediate inline scripts to process: {$parent_handle}");

		// For protected _process_inline_scripts()
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Checking for inline scripts for parent handle '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Adding inline script for '{$parent_handle}' (key: 0, position: {$inline_position}).");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Removed processed inline script with key '0' for handle '{$parent_handle}'.");

		// For public enqueue_inline_scripts() - exit
		$this->logger_mock->shouldReceive('debug')->ordered()->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Exited method.');
		// Act
		$this->instance->enqueue_inline_scripts();

		// Assert: WP_Mock and Mockery will verify expectations.
		$this->assertTrue(true); // Assertion handled by Mockery expectations
	}

	private function setup_wp_mocks(array $expectations): void {
		foreach ($expectations as $func_name => $details) {
			$expectation = WP_Mock::userFunction($func_name);

			$times = $details['times'] ?? 1;
			$expectation->times($times);

			if (isset($details['args'])) {
				$expectation->with(...$details['args']);
			} else {
				$expectation->withAnyArgs();
			}

			if (isset($details['return'])) {
				if (is_array($details['return'])) {
					$expectation->andReturn(...$details['return']);
				} else {
					$expectation->andReturn($details['return']);
				}
			}
		}
	}

	/**
	 * @dataProvider provide_script_edge_cases
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 *
	 * @param array       $script_definition The script definition array.
	 * @param array       $wp_mocks          The WordPress function mocks.
	 * @param array       $expected_logs     The expected log messages.
	 * @param string|bool $expected_return   The expected return value.
	 * @param array|null  $sut_mocks         Optional. Mocks for the System Under Test.
	 *
	 * @return void
	 * @throws \ReflectionException
	 */
	public function test_process_single_script_handles_edge_cases(array $test_case): void {
		$script_definition = $test_case['script_definition'];
		$wp_mocks          = $test_case['wp_mocks'];
		$logger_expects    = $test_case['logger_expects'];
		$expected_return   = $test_case['expected_return'];
		$sut_mocks         = $test_case['sut_mocks'] ?? null;

		if ( ! empty( $sut_mocks ) ) {
			$this->setup_sut_mocks( $sut_mocks );
		}

		$this->setup_wp_mocks($wp_mocks);

		// Set up logger expectations
		$this->logger_mock->shouldReceive('is_active')->andReturn(true)->byDefault();
		foreach ($logger_expects as $level => $expectations) {
			foreach ($expectations as $expectation) {
				$this->logger_mock->shouldReceive($level)
					->once()
					->withArgs(function ($message) use ($expectation) {
						return preg_match($expectation['pattern'], $message) === 1;
					});
			}
		}

		$result = $this->invoke_protected_method(
			$this->instance,
			'_process_single_script',
			array(
				$script_definition,
				'test_context', // This context is generic for the data provider
				$test_case['hook_name']   ?? null,
				$test_case['do_register'] ?? true,
				$test_case['do_enqueue']  ?? true,
			)
		);

		$this->assertEquals($expected_return, $result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 */
	public function test_process_single_script_logs_warning_for_id_attribute(): void {
		// Arrange
		$script_definition = array(
			'handle'     => 'id-script',
			'src'        => 'path/id.js',
			'attributes' => array('id' => 'custom-id'),
		);

		WP_Mock::userFunction('wp_script_is')->with('id-script', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_register_script')->with('id-script', 'path/id.js', array(), false, false)->andReturn(true);
		WP_Mock::userFunction('wp_script_is')->with('id-script', 'enqueued')->andReturn(false);
		WP_Mock::userFunction('wp_enqueue_script')->with('id-script')->andReturnNull();

		$this->instance->shouldReceive('_add_filter')
			->with('script_loader_tag', Mockery::type('callable'), 10, 3)
			->once();

		$this->logger_mock->shouldReceive('warning')
			->with(Mockery::pattern("/Attempting to set 'id' attribute for 'id-script'/"))
			->once();

		// Act
		$result = $this->invoke_protected_method(
			$this->instance,
			'_process_single_script',
			array($script_definition, 'test_context')
		);

		// Assert
		$this->assertEquals('id-script', $result);
	}

	public function provide_script_edge_cases(): array {
		return array(
			'registration_fails'        => array(array('script_definition' => array('handle' => 'fail-script', 'src' => 'path/fail.js'), 'wp_mocks' => array('wp_script_is' => array('args' => array('fail-script', 'registered'), 'return' => false), 'wp_register_script' => array('args' => array('fail-script', 'path/fail.js', array(), false, false), 'return' => false), ), 'logger_expects' => array('warning' => array(array('pattern' => '/wp_register_script\\(\\) failed for handle/'))), 'expected_return' => false, )),
			'invalid_definition_no_src' => array(array('script_definition' => array('handle' => 'no-src-script'), 'wp_mocks' => array(), 'logger_expects' => array('warning' => array(array('pattern' => '/Invalid script definition. Missing handle or src/'))), 'expected_return' => false, )),
			'async_add_data_fails'      => array(array('script_definition' => array('handle' => 'async-fail', 'src' => 'path/async.js', 'attributes' => array('async' => true)), 'wp_mocks' => array('wp_script_is' => array('args' => array('async-fail', 'registered'), 'return' => false, 'times' => 2), 'wp_register_script' => array('return' => true), 'wp_script_add_data' => array('args' => array('async-fail', 'strategy', 'async'), 'return' => false), ), 'logger_expects' => array('warning' => array(array('pattern' => "/Failed to add 'async' strategy/"))), 'expected_return' => 'async-fail', )),
			'defer_add_data_fails'      => array(array('script_definition' => array('handle' => 'defer-fail', 'src' => 'path/defer.js', 'attributes' => array('defer' => true)), 'wp_mocks' => array('wp_script_is' => array('args' => array('defer-fail', 'registered'), 'return' => false, 'times' => 2), 'wp_register_script' => array('return' => true), 'wp_script_add_data' => array('args' => array('defer-fail', 'strategy', 'defer'), 'return' => false), ), 'logger_expects' => array('warning' => array(array('pattern' => "/Failed to add 'defer' strategy/"))), 'expected_return' => 'defer-fail', )),
			'src_attribute_ignored'     => array(array('script_definition' => array('handle' => 'src-script', 'src' => 'path/src.js', 'attributes' => array('src' => 'ignored.js')), 'wp_mocks' => array('wp_script_is' => array('args' => array('src-script', 'registered'), 'return' => false, 'times' => 2), 'wp_register_script' => array('return' => true), ), 'logger_expects' => array('debug' => array(array('pattern' => "/Ignoring 'src' attribute/"))), 'expected_return' => 'src-script', )),
		);
	}

	protected function setup_sut_mocks(array $sut_mocks): void {
		foreach ($sut_mocks as $method => $details) {
			$expectation = $this->instance->shouldReceive($method);

			if (isset($details['args'])) {
				$expectation->withArgs($details['args']);
			} else {
				$expectation->withAnyArgs();
			}

			if (array_key_exists('return', $details)) {
				$expectation->andReturn($details['return']);
			}

			if (isset($details['times'])) {
				$expectation->times($details['times']);
			} else {
				$expectation->once();
			}
		}
	}

	/**
	 * Invokes a protected method on an object.
	 *
	 * @param object $object The object to call the method on.
	 * @param string $methodName The name of the method to call.
	 * @param array  $parameters An array of parameters to pass to the method.
	 * @return mixed The result of the method call.
	 * @throws \ReflectionException If the method does not exist.
	 */
	protected function invoke_protected_method(object $object, string $methodName, array $parameters = array()) {
		$reflection = new \ReflectionClass(get_class($object));
		$method     = $reflection->getMethod($methodName);
		$method->setAccessible(true);

		return $method->invokeArgs($object, $parameters);
	}

	// Expose protected method for testing
	public function call_enqueue_deferred_scripts(string $hook_name): void {
		$this->invoke_protected_method($this->instance, '_enqueue_deferred_scripts', array($hook_name));
	}

	// Expose protected method for testing
	public function call_enqueue_inline_scripts(?string $hook_name = null): void {
		$this->invoke_protected_method($this->instance, '_enqueue_inline_scripts', array($hook_name));
	}

	// Expose protected property for testing
	public function get_internal_scripts_array(): array {
		return $this->instance->get_scripts();
	}

	/**
	 * Tests enqueue_inline_scripts when only deferred inline scripts are present.
	 * (i.e., all inline scripts have a parent_hook set)
	 */
	public function test_enqueue_inline_scripts_only_deferred_scripts_present() {
		// Arrange: Logger is active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Pre-populate $this->inline_scripts with a deferred script
		$deferred_inline_script = array(
			'handle'      => 'deferred-handle',
			'content'     => '.deferred { color: red; }',
			'position'    => 'after',
			'condition'   => null,
			'parent_hook' => 'some_action_hook', // Key: this makes it deferred
		);
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($deferred_inline_script));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Entered method.');

		// This is the crucial log for this case
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('ScriptsEnqueueTrait::enqueue_inline_scripts - No immediate inline scripts found needing processing.');



		// Act: Call the method under test
		$result = $this->instance->enqueue_inline_scripts();

		// Assert: Method returns $this for chaining
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		// Mockery will assert that all expected log calls were made.
	} // End of test_enqueue_inline_scripts_only_deferred_scripts_present

	/**
	 * Tests enqueue_inline_scripts processes a single immediate inline script,
	 * including logging and call to wp_add_inline_script.
	 */
	public function test_enqueue_inline_scripts_processes_one_immediate_script() {
		// Arrange: Logger is active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$handle   = 'test-immediate-handle';
		$content  = '.immediate { border: 1px solid green; }';
		$position = 'after'; // This is logged by _process_inline_scripts

		$immediate_script = array(
			'handle'      => $handle,
			'content'     => $content,
			'position'    => $position,
			'condition'   => null,
			'parent_hook' => null, // Key: makes it an "immediate" script for enqueue_inline_scripts
		);
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($immediate_script));

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Entered method.');
		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Found 1 unique parent handle(s) with immediate inline scripts to process: ' . \esc_html($handle));

		// Logs from the call to _process_inline_scripts($handle, null, 'enqueue_inline_scripts')
		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Checking for inline scripts for parent handle '{$handle}'.");

		// WP_Mock expectation for wp_script_is (called within _process_inline_scripts)
		// This call happens between the "Processing item" log and "Successfully added" log.
		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($handle, 'registered'),
			'times'  => 1,
			'return' => true,
		));

		// Note: If wp_script_is($handle, 'registered') was false, it would also check 'enqueued'.
		// For this test, we assume 'registered' is true, so the second check is skipped.

		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Processing inline script item for handle '{$handle}'. Content: {$content}. Position: {$position}.");

		\WP_Mock::userFunction('wp_add_inline_script', array(
			'args'   => array($handle, $content, 'after'), // Added 'after' for default position
			'times'  => 1,
			'return' => true, // Simulate successful addition
		));

		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Successfully added inline script for '{$handle}' with wp_add_inline_script.");

		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Exited method.');

		// Act: Call the method under test
		$result = $this->instance->enqueue_inline_scripts();

		// Assert: Method returns $this for chaining
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		// Mockery will assert all logger expectations.
		// WP_Mock will assert its expectations during tearDown.
	} // End of test_enqueue_inline_scripts_processes_one_immediate_script

	/**
	 * Tests that enqueue_inline_scripts skips invalid (non-array) inline script data
	 * and processes any valid immediate scripts that might also be present.
	 */
	public function test_enqueue_inline_scripts_skips_invalid_non_array_inline_script_data() {
		// Arrange: Logger is active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$valid_handle   = 'valid-handle-amidst-invalid';
		$valid_content  = '.valid-content { background: white; }';
		$valid_position = 'before';

		$valid_immediate_script = array(
			'handle'      => $valid_handle,
			'content'     => $valid_content,
			'position'    => $valid_position,
			'condition'   => null,
			'parent_hook' => null, // Immediate
		);
		$invalid_script_item = 'this is definitely not an array'; // The invalid item

		// Set $inline_scripts with the invalid item first, then the valid one.
		// Keys will be 0 (invalid) and 1 (valid).
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($invalid_script_item, $valid_immediate_script));

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')->ordered()->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Entered method.');

		// Expect warning for the invalid item at key '0' from the outer loop in enqueue_inline_scripts
		$this->logger_mock->shouldReceive('warning')->ordered()->with("ScriptsEnqueueTrait::enqueue_inline_scripts - Invalid inline script data at key '0'. Skipping.");

		// Then, normal processing for the valid item
		$this->logger_mock->shouldReceive('debug')->ordered()->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Found 1 unique parent handle(s) with immediate inline scripts to process: ' . \esc_html($valid_handle));

		// Logs from _process_inline_scripts for the valid_handle
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Checking for inline scripts for parent handle '{$valid_handle}'.");

		// Mocks for wp_script_is check inside _process_inline_scripts
		// We need the first check to be false to force the evaluation of the second check.
		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($valid_handle, 'registered'),
			'times'  => 1,
			'return' => false, // Mock wp_script_is($valid_handle, 'registered') to return false.
			// In the typical condition `!wp_script_is(..., 'registered') && !wp_script_is(..., 'enqueued')`,
			// this `!false` part evaluates to `true`.
		));
		// We need the second check to be true so the overall condition is false and the method continues.
		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($valid_handle, 'enqueued'),
			'times'  => 1,
			'return' => true,  // Mock wp_script_is($valid_handle, 'enqueued') to return true.
			// In the typical condition `!wp_script_is(..., 'registered') && !wp_script_is(..., 'enqueued')`,
			// this `!true` part evaluates to `false`.
			// Thus, the overall condition `(true && false)` becomes `false`,
			// allowing inline script processing to proceed.
		));

		// This warning is logged by _process_inline_scripts when it encounters the non-array item at key 0
		$this->logger_mock->shouldReceive('warning')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Invalid inline script data at key '0'. Skipping.");

		// This is the log for the valid item
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Processing inline script item for handle '{$valid_handle}'. Content: {$valid_content}. Position: {$valid_position}.");

		// The actual inline script call
		\WP_Mock::userFunction('wp_add_inline_script', array(
			'args'   => array($valid_handle, $valid_content, $valid_position),
			'times'  => 1,
			'return' => true,
		));

		// The success log after the call
		$this->logger_mock->shouldReceive('debug')->ordered()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: enqueue_inline_scripts) - Successfully added inline script for '{$valid_handle}' with wp_add_inline_script.");

		// The final exit log
		$this->logger_mock->shouldReceive('debug')->ordered()->with('ScriptsEnqueueTrait::enqueue_inline_scripts - Exited method.');

		// Act
		$result = $this->instance->enqueue_inline_scripts();

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		// Mockery and WP_Mock will assert their expectations.
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::register_scripts
	 * Tests that the correct log message is emitted when attempting to add a deferred script action
	 * for a hook that already has that action registered.
	 */
	public function test_register_scripts_logs_when_deferred_action_already_exists() {
		// Arrange
		$this->logger_mock->shouldReceive('is_active')->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldIgnoreMissing();

		$hook_name      = 'my_custom_hook';
		$script1_handle = 'deferred-script-1';
		$script2_handle = 'deferred-script-2';

		$scripts_data = array(
			$script1_handle => array(
				'handle' => $script1_handle,
				'src'    => 'path/to/script1.js',
				'deps'   => array(),
				'ver'    => false,
				'media'  => 'all',
				'hook'   => $hook_name,
			),
			$script2_handle => array(
				'handle' => $script2_handle,
				'src'    => 'path/to/script2.js',
				'hook'   => $hook_name, // Same hook
			),
		);

		$this->instance->add_scripts($scripts_data);

		$testInstance    = $this->instance; // Capture $this->instance for the closure
		$callbackMatcher = \Mockery::on(function ($actual_callback_arg) use ($testInstance) {
			if (!is_array($actual_callback_arg) || count($actual_callback_arg) !== 2) {
				return false;
			}
			// Exact instance check
			if ($actual_callback_arg[0] !== $testInstance) {
				return false;
			}
			if ($actual_callback_arg[1] !== 'enqueue_deferred_scripts') {
				return false;
			}
			return true;
		});

		$has_action_call_count = 0;
		\WP_Mock::userFunction('has_action')
			->times(2) // Expect has_action to be called twice
			->with($hook_name, $callbackMatcher)
			->andReturnUsing(function () use (&$has_action_call_count) {
				$has_action_call_count++;
				if ($has_action_call_count === 1) {
					return false; // First call, action doesn't exist
				}
				return true; // Second call, action now exists
			});

		\WP_Mock::expectActionAdded($hook_name, array( $this->instance, 'enqueue_deferred_scripts' ), 10, 1);

		// Logger expectations (Mockery ordering removed to avoid conflict with WP_Mock ordering)
		$this->logger_mock->shouldReceive('debug')
			->with("ScriptsEnqueueTrait::register_scripts - Added action for 'enqueue_deferred_scripts' on hook: {$hook_name}.") // LOG A1
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("ScriptsEnqueueTrait::register_scripts - Action for 'enqueue_deferred_scripts' on hook '{$hook_name}' already exists.") // LOG B
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("ScriptsEnqueueTrait::register_scripts - Deferred action for hook '{$hook_name}' was already added by this instance.") // LOG C
			->never();

		// Act
		$this->instance->register_scripts();

		// Assert (WP_Mock and Mockery will verify expectations)
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * Tests that _process_single_script logs and skips registration if a script is already registered.
	 */
	public function test_process_single_script_logs_when_script_already_registered_and_skips_reregistration(): void {
		$script_handle = 'my-already-registered-script';
		$script_src    = 'path/to/script.js';
		$scripts_data  = array(
			$script_handle => array(
				'handle' => $script_handle,
				'src'    => $script_src,
				'hook'   => null, // Process immediately
			),
		);

		// Set up logger expectations for add_scripts
		$this->logger_mock->shouldReceive('debug')
			->with('ScriptsEnqueueTrait::add_scripts - Entered. Current script count: 0. Adding 1 new script(s).')
			->ordered();
		$this->logger_mock->shouldReceive('debug')
			->with("ScriptsEnqueueTrait::add_scripts - Adding script. Key: {$script_handle}, Handle: {$script_handle}, Src: {$script_src}")
			->ordered();
		$this->logger_mock->shouldReceive('debug')
			->with('ScriptsEnqueueTrait::add_scripts - Exiting. New total script count: 1')
			->ordered();

		$this->instance->add_scripts($scripts_data);

		// Assert that inline_scripts array is empty after add_scripts and before register_scripts is called
		$this->assertEmpty($this->instance->get_internal_inline_scripts_array(), 'Inline scripts array should be empty before register_scripts call.');

		// Mock wp_script_is to indicate the script is already registered
		// Called once in _process_single_script, once in _process_inline_scripts
		\WP_Mock::userFunction('wp_script_is')
			->times(2)
			->with($script_handle, 'registered')
			->andReturn(true);

		// Mock wp_script_is for 'enqueued' check within _process_inline_scripts
		// This should NOT be called if 'registered' is true due to short-circuiting in the IF condition.
		\WP_Mock::userFunction('wp_script_is')
			->never()
			->with($script_handle, 'enqueued');

		// Expect wp_register_script NOT to be called
		\WP_Mock::userFunction('wp_register_script')
			->never();

		// Expect the specific debug log messages in order
		// From _process_single_script
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::_process_single_script - Processing script '{$script_handle}' in context 'register_scripts'.")
			->ordered();

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::_process_single_script - Script '{$script_handle}' already registered. Skipping wp_register_script.")
			->ordered();

		// From _process_inline_scripts (called by _process_single_script)
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle '{$script_handle}'.")
			->ordered();

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for '{$script_handle}'.")
			->ordered();

		// From _process_single_script (finish)
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("ScriptsEnqueueTrait::_process_single_script - Finished processing script '{$script_handle}'.")
			->ordered();

		// Act: register_scripts will call _process_single_script with $do_register = true
		$this->instance->register_scripts();

		// Assert: Mockery and WP_Mock will verify expectations.
		$this->assertTrue(true); // Placeholder assertion
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * Tests that _process_single_script logs and skips enqueueing if a script is already enqueued.
	 */
	public function test_process_single_script_logs_when_script_already_enqueued_and_skips_re_enqueue(): void {
		// Arrange
		$sut = new ConcreteEnqueueForScriptsTesting($this->config_mock);

		$script_handle      = 'my-already-enqueued-script';
		$hook_name          = 'my_custom_hook';
		$processing_context = 'test_context';
		$script_definition  = array(
			'handle' => $script_handle,
			'src'    => 'path/to/script.js',
		);

		// Mock WordPress functions
		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($script_handle, 'registered'),
			'return' => true,
		));
		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($script_handle, 'enqueued'),
			'return' => true,
		));

		// Ordered expectations ONLY for is_active calls (when hook_name is NOT null, inline processing in _process_single_script is skipped)
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 1. Entry log (_process_single_script)
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 2. "already registered" log (_process_single_script)
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 3. "already enqueued" log (_process_single_script)
		// Final log from _process_single_script
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 4. Exit log (_process_single_script)

		// Non-ordered expectations for debug calls that WILL occur
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"ScriptsEnqueueTrait::_process_single_script - Processing script '{$script_handle}' on hook '{$hook_name}' in context '{$processing_context}'."
		);
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"ScriptsEnqueueTrait::_process_single_script - Script '{$script_handle}' on hook '{$hook_name}' already registered. Skipping wp_register_script."
		);
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"ScriptsEnqueueTrait::_process_single_script - Script '{$script_handle}' on hook '{$hook_name}' already enqueued. Skipping wp_enqueue_script."
		);
		// Logs for inline script processing are NOT expected here because $hook_name is not null
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"ScriptsEnqueueTrait::_process_single_script - Finished processing script '{$script_handle}' on hook '{$hook_name}'."
		);

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_single_script');
		$reflection->setAccessible(true);
		$result = $reflection->invoke(
			$sut,
			$script_definition,
			$processing_context,
			$hook_name,
			true, // do_register
			true  // do_enqueue
		);

		// Assert
		$this->assertSame($script_handle, $result, 'Method should return the handle on success.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * Tests that _process_single_script returns false for an invalid script definition.
	 */
	public function test_process_single_script_returns_false_for_invalid_definition(): void {
		// Arrange
		$sut               = new ConcreteEnqueueForScriptsTesting($this->config_mock);
		$script_definition = array(
			'handle' => 'my-invalid-script',
			'src'    => '', // Invalid src makes it invalid when do_register is true
		);
		$hook_name          = 'test_hook';
		$processing_context = 'test_context';

		$this->logger_mock->shouldReceive('debug')
			->once()
			->withArgs(function ($message) use ($script_definition, $hook_name, $processing_context) {
				return str_contains($message, "Processing script '{$script_definition['handle']}'") && str_contains($message, "on hook '{$hook_name}'") && str_contains($message, "in context '{$processing_context}'");
			})
			->ordered();

		$this->logger_mock->shouldReceive('warning')
			->once()
			->with("ScriptsEnqueueTrait::_process_single_script - Invalid script definition. Missing handle or src. Skipping. Handle: '{$script_definition['handle']}' on hook '{$hook_name}'.")
			->ordered();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_single_script');
		$reflection->setAccessible(true);
		$result = $reflection->invoke(
			$sut,
			$script_definition,
			$processing_context,
			$hook_name,
			true,  // do_register
			false  // do_enqueue
		);

		// Assert
		$this->assertFalse($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * Tests that _process_single_script returns false when wp_register_script fails.
	 */
	public function test_process_single_script_returns_false_on_registration_failure(): void {
		// Arrange
		$sut                = new ConcreteEnqueueForScriptsTesting($this->config_mock);
		$script_handle      = 'my-failing-script';
		$hook_name          = 'test_hook';
		$processing_context = 'test_context';
		$script_definition  = array(
			'handle' => $script_handle,
			'src'    => 'path/to/script.js',
		);

		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($script_handle, 'registered'),
			'return' => false,
			'times'  => 1,
		));

		\WP_Mock::userFunction('wp_register_script', array(
			'args' => array(
				$script_handle,
				$script_definition['src'],
				array(),      // deps
				false,   // version
				false    // in_footer
			),
			'return' => false, // Simulate failure
			'times'  => 1,
		));

		$this->logger_mock->shouldReceive('debug')->once()->with(Mockery::pattern("/Processing script '{$script_handle}'/"))->ordered();
		$this->logger_mock->shouldReceive('debug')->once()->with(Mockery::pattern("/Registering script '{$script_handle}'/"))->ordered();
		$this->logger_mock->shouldReceive('warning')->once()->with(Mockery::pattern("/wp_register_script\\(\\) failed for handle '{$script_handle}'/"))->ordered();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_single_script');
		$reflection->setAccessible(true);
		$result = $reflection->invoke(
			$sut,
			$script_definition,
			$processing_context,
			$hook_name,
			true,  // do_register
			false  // do_enqueue
		);

		// Assert
		$this->assertFalse($result);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function testProcessInlineScriptsHandlesDeferredStyleWithMatchingHook(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);
		$parent_handle = 'parent-script';
		$hook_name     = 'a_custom_hook';
		$inline_script = array(
			'handle'      => $parent_handle,
			'content'     => '.my-class { color: red; }',
			'parent_hook' => $hook_name,
			'position'    => 'after',
		);
		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('inline_scripts');
		$property->setAccessible(true);
		$property->setValue($sut, array($inline_script));

		WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: deferred) - Checking for inline scripts for parent handle 'parent-script' on hook 'a_custom_hook'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: deferred) - Adding inline script for 'parent-script' (key: 0, position: after) on hook 'a_custom_hook'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: deferred) - Removed processed inline script with key '0' for handle 'parent-script' on hook 'a_custom_hook'.");

		WP_Mock::userFunction('wp_add_inline_script')->with('parent-script', '.my-class { color: red; }', 'after')->once();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_inline_scripts');
		$reflection->setAccessible(true);
		$reflection->invoke($sut, $parent_handle, $hook_name, 'deferred');

		WP_Mock::assertActionsCalled();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_process_inline_scripts_skips_style_when_condition_is_false(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);
		$parent_handle = 'parent-script';
		$inline_script = array(
			'handle'    => $parent_handle,
			'content'   => '.my-class { color: blue; }',
			'condition' => fn() => false,
		);
		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('inline_scripts');
		$property->setAccessible(true);
		$property->setValue($sut, array($inline_script));

		WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Checking for inline scripts for parent handle 'parent-script'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Condition false for inline script targeting 'parent-script' (key: 0).");
		WP_Mock::userFunction('wp_add_inline_script')->never();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_inline_scripts');
		$reflection->setAccessible(true);
		$reflection->invoke($sut, $parent_handle, null, 'immediate');

		WP_Mock::assertActionsCalled();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_process_inline_scripts_skips_style_with_empty_content(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$parent_handle = 'parent-script';
		$inline_script = array(
			'handle'  => $parent_handle,
			'content' => '', // Empty content
		);
		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('inline_scripts');
		$property->setAccessible(true);
		$property->setValue($sut, array($inline_script));

		WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Checking for inline scripts for parent handle 'parent-script'.");
		$this->logger_mock->shouldReceive('warning')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Empty content for inline script targeting 'parent-script' (key: 0). Skipping addition.");
		$this->logger_mock->shouldReceive('debug')->once()->with("ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Removed processed inline script with key '0' for handle 'parent-script'.");
		WP_Mock::userFunction('wp_add_inline_script')->never();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForScriptsTesting::class, '_process_inline_scripts');
		$reflection->setAccessible(true);
		$reflection->invoke($sut, $parent_handle, null, 'immediate');

		WP_Mock::assertActionsCalled();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_scripts
	 */
	public function test_enqueue_scripts_throws_exception_for_deferred_style_in_queue(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();

		$deferred_script = array(
			'handle' => 'deferred-script',
			'src'    => 'path/to/script.js',
			'hook'   => 'wp_footer',
		);

		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('scripts');
		$property->setAccessible(true);
		$property->setValue($sut, array($deferred_script));

		// Expect
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage(
			"ScriptsEnqueueTrait::enqueue_scripts - Found a deferred script ('deferred-script') in the immediate queue. " .
			'The `register_scripts()` method must be called before `enqueue_scripts()` to correctly process deferred scripts.'
		);

		// Act
		$sut->enqueue_scripts();
	}

	// region Script-Specific Attribute and Processing Tests
	// ==========================================================================
	// The following tests are specific to ScriptsEnqueueTrait and cover methods
	// like _modify_script_tag_for_attributes and aspects of _process_single_script
	// that do not have direct equivalents in StylesEnqueueTrait.
	// ==========================================================================

	/**
	 * Helper method to call the protected _modify_script_tag_for_attributes method.
	 *
	 * @param string $tag The script tag to modify.
	 * @param string $handle The handle of the script.
	 * @param array  $attributes_to_apply Attributes to apply to the tag.
	 * @return string The modified script tag.
	 * @throws \ReflectionException
	 */
	protected function call_modify_script_tag_for_attributes(string $tag, string $handle, array $attributes_to_apply): string {
		$method = new \ReflectionMethod($this->instance, '_modify_script_tag_for_attributes');
		$method->setAccessible(true);
		return $method->invoke($this->instance, $tag, $handle, $handle, $attributes_to_apply);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_script_tag_for_attributes
	 * @dataProvider provide_script_tag_modification_cases
	 */
	public function test_modify_script_tag_for_attributes(string $original_tag, string $handle, array $attributes, string $expected_tag, ?array $logger_expects = null): void {
		if ($logger_expects) {
			$this->logger_mock->shouldReceive('is_active')->andReturn(true)->byDefault();
			foreach ($logger_expects as $log_level => $messages_for_level) {
				// Check if $messages_for_level is an array of argument arrays (multiple calls)
				// or a single argument array (single call).
				// Example for single call: 'debug' => ['message', []]
				// Example for multiple calls: 'debug' => [['message1', []], ['message2', []]]
				if (isset($messages_for_level[0]) && is_array($messages_for_level[0])) {
					// Multiple calls expected for this log level
					foreach ($messages_for_level as $individual_call_args) {
						$this->logger_mock->shouldReceive($log_level)
							->once() // Each specific message combination is expected once
							->with(...$individual_call_args); // Spread arguments for this specific call
					}
				} else {
					// Single call expected for this log level (or $messages_for_level is empty)
					if (!empty($messages_for_level)) {
						$this->logger_mock->shouldReceive($log_level)
							->once()
							->with(...$messages_for_level); // Spread arguments
					} elseif (empty($messages_for_level) && is_array($messages_for_level)) {
						// If $messages_for_level is an empty array, it implies a call with no arguments.
						$this->logger_mock->shouldReceive($log_level)->once()->with();
					}
					// If $messages_for_level was null or not an array, it's an issue with test data structure,
					// but the existing ?array type hint for $logger_expects should prevent this.
				}
			}
		} else {
			$this->logger_mock->shouldReceive('is_active')->andReturn(false)->byDefault(); // Assume no logging unless specified
		}
		$modified_tag = $this->call_modify_script_tag_for_attributes($original_tag, $handle, $attributes);
		$this->assertEquals($expected_tag, $modified_tag);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_modify_script_tag_for_attributes
	 */
	public function test_modify_script_tag_for_attributes_returns_unmodified_on_handle_mismatch(): void {
		$original_tag           = '<script src="test.js"></script>';
		$filter_handle          = 'handle-being-filtered';
		$script_handle_to_match = 'a-different-handle';
		$attributes             = array('async' => true);

		// The logger should not be called at all in this case, not even is_active.
		$this->logger_mock->shouldNotReceive('is_active');
		$this->logger_mock->shouldNotReceive('debug');
		$this->logger_mock->shouldNotReceive('warning');

		$result = $this->invoke_protected_method(
			$this->instance,
			'_modify_script_tag_for_attributes',
			array($original_tag, $filter_handle, $script_handle_to_match, $attributes)
		);

		$this->assertSame($original_tag, $result);
	}

	public static function provide_script_tag_modification_cases(): array {
		$complex_attrs        = array('type' => 'module', 'async' => true, 'defer' => false, 'data-version' => '1.2', 'integrity' => 'sha384-xyz', 'crossorigin' => 'anonymous', 'custom-empty' => '');
		$complex_expected_tag = '<script type="module" id="main-script" src="./app.js?v=1.2" async data-version="1.2" integrity="sha384-xyz" crossorigin="anonymous"></script>';

		return array(
			'basic_async' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('async' => true),
				'<script src="test.js" async></script>',
				null
			),
			'basic_defer' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('defer' => true),
				'<script src="test.js" defer></script>',
				null
			),
			'type_module' => array(
				'<script src="module.js"></script>',
				'module-handle',
				array('type' => 'module'),
				'<script type="module" src="module.js"></script>',
				array(
					'debug' => array(
						array( // Expectation for 1st debug call (from line 701)
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle 'module-handle'. Attributes: {\"type\":\"module\"}"
						),
						array( // Expectation for 2nd debug call (from line 710)
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Script 'module-handle' is a module. Modifying tag accordingly."
						),
						array( // Expectation for 3rd debug call (diagnostic - matching Mockery error report)
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for 'module-handle'. New tag: <script type=\"module\" src=\"module.js\"></script>"
						)
					)
				)
			),
			'custom_attribute' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('data-custom' => 'my-value'),
				'<script src="test.js" data-custom="my-value"></script>',
				null
			),
			'multiple_attributes' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('async' => true, 'defer' => true, 'data-id' => '123'),
				'<script src="test.js" async defer data-id="123"></script>',
				null
			),
			'src_attribute_ignored' => array( // src should not be duplicated or moved by this method
				'<script src="test.js"></script>',
				'test-handle',
				array('src' => 'ignored.js', 'async' => true),
				'<script src="test.js" async></script>',
				array(
					'debug' => array(
						array(
							'ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle \'test-handle\'. Attributes: {"src":"ignored.js","async":true}'
						)
					)
				)
			),
			'false_attribute_skipped' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('async' => false, 'data-id' => '123'),
				'<script src="test.js" data-id="123"></script>',
				null
			),
			'null_attribute_skipped' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('defer' => null, 'data-id' => '123'),
				'<script src="test.js" data-id="123"></script>',
				null
			),
			'empty_string_attribute_skipped' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('data-custom' => '', 'data-id' => '123'),
				'<script src="test.js" data-id="123"></script>',
				null
			),
			'malformed_tag_no_closing_bracket' => array(
				'<script src="test.js"', // Malformed
				'test-handle',
				array('async' => true),
				'<script src="test.js"', // Expect original tag
				array(
					'debug' => array(
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle 'test-handle'. Attributes: " . json_encode(array('async' => true))
						)
					),
					'warning' => array(
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Malformed script tag for 'test-handle'. Original tag: " . esc_html('<script src="test.js"') . '. Skipping attribute modification.'
						)
					)
				)
			),
			'malformed_tag_no_script_opening' => array(
				'<div></div>', // Malformed
				'test-handle',
				array('async' => true),
				'<div></div>', // Expect original tag
				array(
					'debug' => array(
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle 'test-handle'. Attributes: {\"async\":true}"
						)
					),
					'warning' => array(
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Malformed script tag for 'test-handle'. Original tag: " . esc_html('<div></div>') . '. Skipping attribute modification.'
						)
					)
				)
			),
			'attribute_value_escaping' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('data-value' => 'needs "escaping" & stuff'),
				'<script src="test.js" data-value="needs &quot;escaping&quot; &amp; stuff"></script>',
				null
			),
			'empty_attributes_array_returns_original_tag' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array(), // attributes
				'<script src="test.js"></script>', // expected_tag
				array( // Expected logger calls
					'debug' => array(
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js"></script>')
						)
					)
				)
			),
			'attribute_with_zero_integer_value' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('data-count' => 0),
				'<script src="test.js" data-count="0"></script>',
				array(
					'debug' => array(
						array("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle 'test-handle'. Attributes: {\"data-count\":0}"),
						array("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js" data-count="0"></script>'))
					)
				)
			),
			'attribute_with_zero_string_value' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('data-count' => '0'),
				'<script src="test.js" data-count="0"></script>',
				array(
					'debug' => array(
						array("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle 'test-handle'. Attributes: {\"data-count\":\"0\"}"),
						array("ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js" data-count="0"></script>'))
					)
				)
			),
			'wp_managed_attribute_id_ignored' => array(
                '<script src="test.js"></script>',
                'test-handle',
                array('id' => 'new-id', 'async' => true),
                '<script src="test.js" async></script>',
                array(
                    'debug' => array(
                        array(
                            "ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle 'test-handle'. Attributes: " . json_encode(array('id' => 'new-id', 'async' => true))
                        ),
                        array(
                            "ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js" async></script>')
                        )
                    ),
                    'warning' => array(
                        array(
                            sprintf(
                            	"%s - Attempt to override WordPress-managed attribute '%s' for script handle '%s'. This attribute will be ignored.",
                            	'Ran\\PluginLib\\EnqueueAccessory\\ScriptsEnqueueTrait::_modify_script_tag_for_attributes',
                            	'id',
                            	'test-handle'
                            ),
                            array(
                                'handle'    => 'test-handle',
                                'attribute' => 'id',
                            )
                        )
                    ),
                )
            ),
            'wp_managed_attribute_id_ignored_logger_inactive' => array(
                '<script src="test.js"></script>',
                'test-handle',
                array('id' => 'new-id', 'async' => true),
                '<script src="test.js" async></script>',
                array()
            ),
			'All attributes are ignored' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('src' => 'ignored.js', 'id' => 'new-id'),
				'<script src="test.js"></script>',
				array(
					'debug' => array(
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js"></script>')
						)
					),
					'warning' => array(
						array(
							sprintf(
								"%s - Attempt to override WordPress-managed attribute '%s' for script handle '%s'. This attribute will be ignored.",
								'Ran\\PluginLib\\EnqueueAccessory\\ScriptsEnqueueTrait::_modify_script_tag_for_attributes',
								'id',
								'test-handle'
							),
							array(
								'handle'    => 'test-handle',
								'attribute' => 'id',
							)
						)
					),
				)
			),
			'complex_case_with_module_and_various_attrs' => array(
				'<script id="main-script" src="./app.js?v=1.2"></script>',
				'app-main',
				$complex_attrs,
				$complex_expected_tag,
				array(
					'debug' => array(
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Modifying tag for handle 'app-main'. Attributes: " . json_encode($complex_attrs)
						),
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Script 'app-main' is a module. Modifying tag accordingly."
						),
						array(
							"ScriptsEnqueueTrait::_modify_script_tag_for_attributes - Successfully modified tag for 'app-main'. New tag: " . esc_html($complex_expected_tag)
						)
					)
				)
			),
			'type_module_logger_inactive' => array(
				'<script src="module.js"></script>',
				'module-handle',
				array('type' => 'module'),
				'<script type="module" src="module.js"></script>',
				array() // Expect no logger calls
			),
			'malformed_tag_logger_inactive' => array(
				'<script src="test.js"', // Malformed
				'test-handle',
				array('async' => true),
				'<script src="test.js"', // Expect original tag
				array() // Expect no logger calls
			),
		);
	}

	// endregion

	// region Process Single Script Tests
	// ==========================================================================
	// Tests for the _process_single_script method, covering various scenarios
	// including registration, enqueuing, conditional loading, and extras.
	// ==========================================================================

	/**
	 * Helper method to call the protected _process_single_script method.
	 *
	 * @param array       $script_definition    The script definition array.
	 * @param string      $processing_context   The context (e.g., 'register_scripts').
	 * @param string|null $hook_name            Hook name if deferred.
	 * @param bool        $do_register          If true, attempt registration.
	 * @param bool        $do_enqueue           If true, attempt enqueuing.
	 * @return string|false The handle on success, false on failure/skip.
	 * @throws \ReflectionException
	 */
	protected function call_process_single_script(
		array $script_definition,
		string $processing_context,
		?string $hook_name = null,
		bool $do_register = true,
		bool $do_enqueue = false
	): string|false {
		$method = new \ReflectionMethod($this->instance, '_process_single_script');
		$method->setAccessible(true);
		return $method->invoke($this->instance, $script_definition, $processing_context, $hook_name, $do_register, $do_enqueue);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 * @dataProvider provide_process_single_script_cases
	 */
	public function test_process_single_script(
		array $script_def,
		string $context,
		?string $hook_name,
		bool $do_register,
		bool $do_enqueue,
		$expected_result,
		array $wp_mock_expects,
		array $logger_expects
	): void {
		// Set up logger expectations
		if (!empty($logger_expects)) {
			$this->logger_mock->shouldReceive('is_active')->andReturn(true)->byDefault();
			foreach ($logger_expects as $level => $messages) {
				foreach ((array)$messages as $message) {
					$this->logger_mock->shouldReceive($level)->once()->with($message);
				}
			}
		} else {
			$this->logger_mock->shouldReceive('is_active')->andReturn(false)->byDefault();
		}

		// Set up WP_Mock expectations
		foreach ($wp_mock_expects as $expectation) {
			$mock           = \WP_Mock::userFunction($expectation['function']);
			$mock_with_args = $mock->withArgs($expectation['args']);
			if (isset($expectation['return'])) {
				if (is_array($expectation['return']) && (empty($expectation['return']) || array_keys($expectation['return']) === range(0, count($expectation['return']) - 1))) {
					$mock_with_args->andReturn(...$expectation['return']);
				} else {
					$mock_with_args->andReturn($expectation['return']);
				}
			}
			$mock_with_args->times($expectation['times'] ?? 1);
		}
		// Ensure no other WP functions are called unexpectedly
		// \WP_Mock::passthru(); // Use if some WP functions should pass through

		$result = $this->call_process_single_script($script_def, $context, $hook_name, $do_register, $do_enqueue);
		$this->assertSame($expected_result, $result);
	}

	public static function provide_process_single_script_cases(): array {
		return array(
			'basic_registration_success' => array(
				'script_def'      => array('handle' => 'test-handle', 'src' => 'test.js', 'deps' => array(), 'version' => false, 'in_footer' => false),
				'context'         => 'test_context_reg',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => false,
				'expected_result' => 'test-handle',
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'registered'), 'return' => false, 'times' => 1),
					array('function' => 'wp_register_script', 'args' => array('test-handle', 'test.js', array(), false, false), 'return' => true, 'times' => 1),
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'registered'), 'return' => true, 'times' => 1),
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'test-handle' in context 'test_context_reg'.",
						"ScriptsEnqueueTrait::_process_single_script - Registering script 'test-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'test-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'test-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Finished processing script 'test-handle'.",
					),
				),
			),
			'basic_enqueue_success' => array(
				'script_def'      => array('handle' => 'test-handle', 'src' => 'test.js', 'deps' => array('jquery'), 'version' => '1.0', 'in_footer' => true),
				'context'         => 'test_context_reg_enq',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => true,
				'expected_result' => 'test-handle',
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'registered'), 'return' => false, 'times' => 1),
					array('function' => 'wp_register_script', 'args' => array('test-handle', 'test.js', array('jquery'), '1.0', true), 'return' => true, 'times' => 1),
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'enqueued'), 'return' => false, 'times' => 1),
					array('function' => 'wp_enqueue_script', 'args' => array('test-handle'), 'return' => null, 'times' => 1),
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'registered'), 'return' => true, 'times' => 1),
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'test-handle' in context 'test_context_reg_enq'.",
						"ScriptsEnqueueTrait::_process_single_script - Registering script 'test-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Enqueuing script 'test-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'test-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'test-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Finished processing script 'test-handle'.",
					),
				),
			),
			'already_registered_skips_registration' => array(
				'script_def'      => array('handle' => 'test-handle', 'src' => 'test.js'),
				'context'         => 'test_context_already_reg',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => false,
				'expected_result' => 'test-handle',
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'registered'), 'return' => true, 'times' => 2),
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'test-handle' in context 'test_context_already_reg'.",
						"ScriptsEnqueueTrait::_process_single_script - Script 'test-handle' already registered. Skipping wp_register_script.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'test-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'test-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Finished processing script 'test-handle'.",
					),
				),
			),
			'already_enqueued_skips_enqueuing' => array(
				'script_def'      => array('handle' => 'test-handle', 'src' => 'test.js'),
				'context'         => 'test_context_already_enq',
				'hook_name'       => null,
				'do_register'     => true, // Assumes registration or already registered
				'do_enqueue'      => true,
				'expected_result' => 'test-handle',
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'registered'), 'return' => true, 'times' => 2),
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'enqueued'), 'return' => true, 'times' => 1),
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'test-handle' in context 'test_context_already_enq'.",
						"ScriptsEnqueueTrait::_process_single_script - Script 'test-handle' already registered. Skipping wp_register_script.",
						"ScriptsEnqueueTrait::_process_single_script - Script 'test-handle' already enqueued. Skipping wp_enqueue_script.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'test-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'test-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Finished processing script 'test-handle'.",
					),
				),
			),
			'condition_false_skips_processing' => array(
				'script_def'      => array('handle' => 'test-handle', 'src' => 'test.js', 'condition' => static fn() => false),
				'context'         => 'test_context_cond_false',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => true,
				'expected_result' => false,
				'wp_mock_expects' => array(),
				'logger_expects'  => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'test-handle' in context 'test_context_cond_false'.",
						"ScriptsEnqueueTrait::_process_single_script - Condition not met for script 'test-handle'. Skipping.",
					),
				),
			),
			'missing_handle_returns_false' => array(
				'script_def'      => array('src' => 'test.js'), // Missing handle
				'context'         => 'test_context_no_handle',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => false,
				'expected_result' => false,
				'wp_mock_expects' => array(),
				'logger_expects'  => array(
					'debug'   => array("ScriptsEnqueueTrait::_process_single_script - Processing script 'N/A' in context 'test_context_no_handle'."),
					'warning' => array("ScriptsEnqueueTrait::_process_single_script - Invalid script definition. Missing handle or src. Skipping. Handle: 'N/A'."),
				),
			),
			'missing_src_when_registering_returns_false' => array(
				'script_def'      => array('handle' => 'test-handle'), // Missing src
				'context'         => 'test_context_no_src',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => false,
				'expected_result' => false,
				'wp_mock_expects' => array(),
				'logger_expects'  => array(
					'debug'   => array("ScriptsEnqueueTrait::_process_single_script - Processing script 'test-handle' in context 'test_context_no_src'."),
					'warning' => array("ScriptsEnqueueTrait::_process_single_script - Invalid script definition. Missing handle or src. Skipping. Handle: 'test-handle'."),
				),
			),
			'registration_failure_returns_false' => array(
				'script_def'      => array('handle' => 'test-handle', 'src' => 'test.js'),
				'context'         => 'test_context_reg_fail',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => false,
				'expected_result' => false,
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('test-handle', 'registered'), 'return' => false, 'times' => 1),
					array('function' => 'wp_register_script', 'args' => array('test-handle', 'test.js', array(), false, false), 'return' => false, 'times' => 1),
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'test-handle' in context 'test_context_reg_fail'.",
						"ScriptsEnqueueTrait::_process_single_script - Registering script 'test-handle'.",
					),
					'warning' => array("ScriptsEnqueueTrait::_process_single_script - wp_register_script() failed for handle 'test-handle'. Skipping further processing for this script."),
				),
			),
			'defer_attribute_routing' => array(
				'script_def'      => array('handle' => 'defer-handle', 'src' => 'defer.js', 'attributes' => array('defer' => true)),
				'context'         => 'test_context_defer_route',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => true,
				'expected_result' => 'defer-handle',
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('defer-handle', 'registered'), 'return' => false, 'times' => 1),
					array('function' => 'wp_register_script', 'args' => array('defer-handle', 'defer.js', array(), false, false), 'return' => true, 'times' => 1),
					array('function' => 'wp_script_add_data', 'args' => array('defer-handle', 'strategy', 'defer'), 'return' => true, 'times' => 1),
					array('function' => 'wp_script_is', 'args' => array('defer-handle', 'enqueued'), 'return' => false, 'times' => 1),
					array('function' => 'wp_enqueue_script', 'args' => array('defer-handle'), 'return' => null, 'times' => 1),
					array('function' => 'wp_script_is', 'args' => array('defer-handle', 'registered'), 'return' => true, 'times' => 1),
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'defer-handle' in context 'test_context_defer_route'.",
						"ScriptsEnqueueTrait::_process_single_script - Registering script 'defer-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Routing 'defer' attribute for 'defer-handle' to wp_script_add_data('strategy', 'defer').",
						"ScriptsEnqueueTrait::_process_single_script - Enqueuing script 'defer-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'defer-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'defer-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Finished processing script 'defer-handle'.",
					),
				),
			),
			'async_and_custom_attribute_routing' => array(
				'script_def'      => array('handle' => 'async-custom-handle', 'src' => 'ac.js', 'attributes' => array('async' => true, 'data-foo' => 'bar')),
				'context'         => 'test_context_async_custom_route',
				'hook_name'       => null,
				'do_register'     => true,
				'do_enqueue'      => true,
				'expected_result' => 'async-custom-handle',
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('async-custom-handle', 'registered'), 'return' => false, 'times' => 1),
					array('function' => 'wp_register_script', 'args' => array('async-custom-handle', 'ac.js', array(), false, false), 'return' => true, 'times' => 1),
					array('function' => 'wp_script_add_data', 'args' => array('async-custom-handle', 'strategy', 'async'), 'return' => true, 'times' => 1),
					array('function' => 'wp_script_is', 'args' => array('async-custom-handle', 'enqueued'), 'return' => false, 'times' => 1),
					array('function' => 'wp_enqueue_script', 'args' => array('async-custom-handle'), 'return' => null, 'times' => 1),
					// REVISIT THIS
					// ['function' => 'add_filter', 'args' => ['script_loader_tag', \Mockery::any(), 10, 3], 'return' => true, 'times' => 1],
					array('function' => 'wp_script_is', 'args' => array('async-custom-handle', 'registered'), 'return' => true, 'times' => 1),
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'async-custom-handle' in context 'test_context_async_custom_route'.",
						"ScriptsEnqueueTrait::_process_single_script - Registering script 'async-custom-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Routing 'async' attribute for 'async-custom-handle' to wp_script_add_data('strategy', 'async').",
						"ScriptsEnqueueTrait::_process_single_script - Enqueuing script 'async-custom-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Adding attributes for script 'async-custom-handle' via script_loader_tag. Attributes: {\"data-foo\":\"bar\"}",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - Checking for inline scripts for parent handle 'async-custom-handle'.",
						"ScriptsEnqueueTrait::_process_inline_scripts (context: immediate_after_registration) - No inline scripts found or processed for 'async-custom-handle'.",
						"ScriptsEnqueueTrait::_process_single_script - Finished processing script 'async-custom-handle'.",
					),
				),
			),
			'deferred_context_skips_extras' => array(
				'script_def'      => array('handle' => 'deferred-handle', 'src' => 'deferred.js', 'attributes' => array('data-test' => 'value'), 'wp_data' => array('key' => 'val')),
				'context'         => 'test_context_deferred',
				'hook_name'       => 'some_hook',
				'do_register'     => true,
				'do_enqueue'      => true,
				'expected_result' => 'deferred-handle',
				'wp_mock_expects' => array(
					array('function' => 'wp_script_is', 'args' => array('deferred-handle', 'registered'), 'return' => false, 'times' => 1),
					array('function' => 'wp_register_script', 'args' => array('deferred-handle', 'deferred.js', array(), false, false), 'return' => true, 'times' => 1),
					array('function' => 'wp_script_is', 'args' => array('deferred-handle', 'enqueued'), 'return' => false, 'times' => 1),
					array('function' => 'wp_enqueue_script', 'args' => array('deferred-handle'), 'return' => null, 'times' => 1),
					// No add_filter or wp_script_add_data expected here
				),
				'logger_expects' => array(
					'debug' => array(
						"ScriptsEnqueueTrait::_process_single_script - Processing script 'deferred-handle' on hook 'some_hook' in context 'test_context_deferred'.",
						"ScriptsEnqueueTrait::_process_single_script - Registering script 'deferred-handle' on hook 'some_hook'.",
						"ScriptsEnqueueTrait::_process_single_script - Enqueuing script 'deferred-handle' on hook 'some_hook'.",
						"ScriptsEnqueueTrait::_process_single_script - Finished processing script 'deferred-handle' on hook 'some_hook'.",
					),
				),
			),
		);
	}



	// endregion Process Single Script Tests

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 */
	public function test_process_single_script_handles_registration_failure(): void {
		$script_def = array('handle' => 'fail-handle', 'src' => 'fail.js');

		WP_Mock::userFunction('wp_script_is')->with('fail-handle', 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_register_script')->with('fail-handle', 'fail.js', array(), false, false)->andReturn(false);

		$this->logger_mock->shouldReceive('warning')
			->with("ScriptsEnqueueTrait::_process_single_script - wp_register_script() failed for handle 'fail-handle'. Skipping further processing for this script.")
			->once();

		$result = $this->invoke_protected_method($this->instance, '_process_single_script', array($script_def, 'test_context', null, true, false));

		$this->assertFalse($result);
	}

	/**
	 * @test
	 * @dataProvider provide_add_data_failure_scenarios
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 */
	public function test_process_single_script_logs_warning_on_add_data_failure(string $strategy): void {
		$handle     = "{$strategy}-fail-handle";
		$script_def = array('handle' => $handle, 'src' => 'fail.js', 'attributes' => array($strategy => true));

		WP_Mock::userFunction('wp_script_is')->with($handle, 'registered')->andReturn(false);
		WP_Mock::userFunction('wp_register_script')->andReturn(true);
		WP_Mock::userFunction('wp_script_add_data')->with($handle, 'strategy', $strategy)->andReturn(false);

		$this->logger_mock->shouldReceive('warning')
			->with("ScriptsEnqueueTrait::_process_single_script - Failed to add '{$strategy}' strategy for '{$handle}' via wp_script_add_data.")
			->once();

		$result = $this->invoke_protected_method($this->instance, '_process_single_script', array($script_def, 'test_context', null, true, false));
		$this->assertSame($handle, $result);
	}

	public function provide_add_data_failure_scenarios(): array {
		return array(
			'async failure' => array('async'),
			'defer failure' => array('defer'),
		);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 */
	public function test_process_single_script_ignores_src_attribute(): void {
		$script_def = array('handle' => 'src-ignore-handle', 'src' => 'original.js', 'attributes' => array('src' => 'ignored.js'));

		WP_Mock::userFunction('wp_script_is')->andReturn(false);
		WP_Mock::userFunction('wp_register_script')->with('src-ignore-handle', 'original.js', array(), false, false)->andReturn(true)->once();

		$this->logger_mock->shouldReceive('debug')
			->with("ScriptsEnqueueTrait::_process_single_script - Ignoring 'src' attribute for 'src-ignore-handle' as it is managed by WordPress during registration.")
			->once();

		$result = $this->invoke_protected_method($this->instance, '_process_single_script', array($script_def, 'test_context', null, true, false));
		$this->assertSame('src-ignore-handle', $result);
	}

	/**
	 * @test
	 * @dataProvider provide_invalid_script_definitions
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_single_script
	 */
	public function test_process_single_script_handles_invalid_definition(array $script_def, string $expected_log_handle):
	void {
		$this->logger_mock->shouldReceive('warning')
			->with("ScriptsEnqueueTrait::_process_single_script - Invalid script definition. Missing handle or src. Skipping. Handle: '{$expected_log_handle}'.")
			->once();

		$result = $this->invoke_protected_method($this->instance, '_process_single_script', array($script_def, 'test_context', null, true, false));

		$this->assertFalse($result);
	}

	public function provide_invalid_script_definitions(): array {
		return array(
			'missing handle' => array(array('src' => 'some.js'), 'N/A'),
			'missing src'    => array(array('handle' => 'no-src-handle'), 'no-src-handle'),
		);
	}
}
