<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use InvalidArgumentException;
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

		// is_active mock is now handled per-test to avoid conflicts.
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

		// Logger expectations for ScriptsEnqueueTrait::add_scripts() using the new helper.
		$this->expectLog('debug', array('Entered', 'Adding 2 new script(s)'));
		$this->expectLog('debug', array('Adding script', 'Handle: my-script-1'));
		$this->expectLog('debug', array('Adding script', 'Handle: my-script-2'));
		$this->expectLog('debug', array('Adding 2 script definition(s)'));
		$this->expectLog('debug', array('Exiting', 'New total script count: 2'));
		$this->expectLog('debug', array('All current script handles', 'my-script-1, my-script-2'));

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
		$this->expectLog('debug', array('Entered', 'Adding 1 new script(s)'));
		$this->expectLog('debug', array('Adding script', 'Handle: single-script'));
		$this->expectLog('debug', array('Adding 1 script definition(s)'));
		$this->expectLog('debug', array('Exiting', 'New total script count: 1'));
		$this->expectLog('debug', array('All current script handles', 'single-script'));

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
		$this->expectLog('debug', 'Entered with empty array. No scripts to add.');

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
		$this->expectLog('debug', array('Entered', 'Adding 1 new script(s)'));
		$this->expectLog('debug', array('Adding script', 'Handle: my-script'));
		$this->expectLog('debug', array('Adding 1 script definition(s)'));
		$this->expectLog('debug', array('Exiting', 'New total script count: 1'));
		$this->expectLog('debug', array('All current script handles: my-script'));

		$this->instance->add_scripts(array($script_to_add));

		// --- WP_Mock and Logger Mocks for register_scripts() ---
		$is_registered = false;

		$this->expectLog('debug', array('register_scripts - Entered', 'Processing 1 script'));
		$this->expectLog('debug', array('Processing script: "my-script"'));
		$this->expectLog('debug', array("Processing script 'my-script' in context 'register_scripts'"));

		// Mock wp_script_is to reflect the state change
		WP_Mock::userFunction('wp_script_is', array(
			'args'   => array('my-script', 'registered'),
			'return' => function () use ( &$is_registered ) {
				return $is_registered;
			},
		))->atLeast()->once();

		$this->expectLog('debug', array("Registering script 'my-script'"));

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
		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle 'my-script'"));
		$this->expectLog('debug', array('_process_inline_scripts', "No inline scripts found or processed for 'my-script'"));

		$this->expectLog('debug', array("Finished processing script 'my-script'"));
		$this->expectLog('debug', array('register_scripts - Exited', 'Remaining immediate scripts: 1'));

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
		$this->expectLog('debug', array('Entered', 'Adding 1 new script(s)'));
		$this->expectLog('debug', array('Adding script', 'Handle: my-deferred-script'));
		$this->expectLog('debug', array('Adding 1 script definition(s)'));
		$this->expectLog('debug', array('Exiting', 'New total script count: 1'));
		$this->expectLog('debug', array('All current script handles: my-deferred-script'));

		$this->instance->add_scripts(array($script_to_add));

		// --- WP_Mock and Logger Mocks for register_scripts() ---
		WP_Mock::userFunction('has_action')
			->with('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_scripts'))
			->andReturn(false); // Mock that the action hasn't been added yet

		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_scripts'), 10, 1);

		$this->expectLog('debug', array('register_scripts - Entered', 'Processing 1 script'));
		$this->expectLog('debug', array('Processing script: "my-deferred-script"'));
		$this->expectLog('debug', array("Deferring registration of script 'my-deferred-script'", 'hook: admin_enqueue_scripts'));
		$this->expectLog('debug', array("Added action for 'enqueue_deferred_scripts'", 'hook: admin_enqueue_scripts'));
		$this->expectLog('debug', array('register_scripts - Exited', 'Remaining immediate scripts: 0', 'Deferred scripts: 1'));

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
		$this->expectLog('debug', array('Entered', 'Adding 1 new script(s)'));
		$this->expectLog('debug', array('Adding script', 'Handle: my-conditional-script'));
		$this->expectLog('debug', array('Adding 1 script definition(s)'));
		$this->expectLog('debug', array('Exiting', 'New total script count: 1'));
		$this->expectLog('debug', array('All current script handles: my-conditional-script'));

		$this->instance->add_scripts(array($script_to_add));

		// --- WP_Mock and Logger Mocks for register_scripts() ---
		$this->expectLog('debug', array('register_scripts - Entered', 'Processing 1 script'));
		$this->expectLog('debug', array('Processing script: "my-conditional-script"'));
		$this->expectLog('debug', array("Processing script 'my-conditional-script' in context 'register_scripts'"));
		$this->expectLog('debug', array("Condition not met for script 'my-conditional-script'. Skipping."));

		// Assert that wp_register_script is never called
		WP_Mock::userFunction('wp_register_script')->never();
		$this->expectLog('debug', array('register_scripts - Exited', 'Remaining immediate scripts: 0', 'Deferred scripts: 0'));

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
		$this->expectLog('debug', array('Entered', 'Adding 1 new script(s)'));
		$this->expectLog('debug', array('Adding script', 'Handle: my-basic-script'));
		$this->expectLog('debug', array('Adding 1 script definition(s)'));
		$this->expectLog('debug', array('Exiting', 'New total script count: 1'));
		$this->expectLog('debug', array('All current script handles: my-basic-script'));
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
		$this->expectLog('debug', array('register_scripts - Entered', 'Processing 1 script'));
		$this->expectLog('debug', array('Processing script: "my-basic-script"'));
		$this->expectLog('debug', array("Processing script 'my-basic-script' in context 'register_scripts'"));
		$this->expectLog('debug', array("Registering script 'my-basic-script'"));

		WP_Mock::userFunction('wp_register_script', array('args' => array('my-basic-script', 'path/to/basic.js', array(), '1.0', false), 'times' => 1, 'return' => true));

		$this->expectLog('debug', array("Finished processing script 'my-basic-script'"));
		$this->expectLog('debug', array('register_scripts - Exited', 'Remaining immediate scripts: 1', 'Deferred scripts: 0'));
		$this->instance->register_scripts();

		// --- Mocks for enqueue_scripts() ---
		$this->expectLog('debug', array('enqueue_scripts - Entered', 'Processing 1 script'));
		$this->expectLog('debug', array('Processing script: "my-basic-script"'));
		$this->expectLog('debug', array("Processing script 'my-basic-script' in context 'enqueue_scripts'"));
		$this->expectLog('debug', array("Script 'my-basic-script' already registered. Skipping wp_register_script.")); // Log from line 553
		$this->expectLog('debug', array("Enqueuing script 'my-basic-script'"));

		WP_Mock::userFunction('wp_enqueue_script', array('args' => array('my-basic-script'), 'times' => 1));

		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle 'my-basic-script'"));
		$this->expectLog('debug', array('_process_inline_scripts', "No inline scripts found or processed for 'my-basic-script'"));
		$this->expectLog('debug', array("Finished processing script 'my-basic-script'"));
		$this->expectLog('debug', array('enqueue_scripts - Exited', 'Deferred scripts count: 0'));

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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

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
		$this->expectLog('debug', array('add_scripts - Entered', 'Adding 1 new script(s)'));
		$this->expectLog('debug', array('add_scripts - Adding script', "Handle: {$script_handle}"));
		$this->expectLog('debug', array('add_scripts - Adding 1 script definition(s)'));
		$this->expectLog('debug', array('add_scripts - Exiting', 'New total script count: 1'));
		$this->expectLog('debug', array('add_scripts - All current script handles', $script_handle));
		$this->instance->add_scripts(array($deferred_script));

		// --- Act 2: Register the script (which defers it) ---
		$this->expectLog('debug', array('register_scripts - Entered', 'Processing 1 script definition(s)'));
		$this->expectLog('debug', array('register_scripts - Processing script', $script_handle));
		$this->expectLog('debug', array('Deferring registration of script', $script_handle, "hook: {$hook_name}"));
		WP_Mock::userFunction('has_action', array(
			'args'   => array($hook_name, array($this->instance, 'enqueue_deferred_scripts')),
			'times'  => 1,
			'return' => false
		));
		WP_Mock::expectActionAdded($hook_name, array($this->instance, 'enqueue_deferred_scripts'), 10, 1);
		$this->expectLog('debug', array('Added action for', 'enqueue_deferred_scripts', "hook: {$hook_name}"));
		$this->expectLog('debug', array('register_scripts - Exited', 'Remaining immediate scripts: 0', 'Deferred scripts:'));
		$this->instance->register_scripts();

		// --- Assert state after registration ---
		$deferred_scripts_prop = new \ReflectionProperty($this->instance, 'deferred_scripts');
		$deferred_scripts_prop->setAccessible(true);
		$current_deferred = $deferred_scripts_prop->getValue($this->instance);
		$this->assertArrayHasKey($hook_name, $current_deferred);
		$this->assertEquals($script_handle, $current_deferred[$hook_name][0]['handle']);

		// --- Act 3: Trigger the deferred enqueue ---
		// This is the key fix: "Entered hook" not "Entered for hook"
		$this->expectLog('debug', array('enqueue_deferred_scripts - Entered hook', $hook_name));
		$this->expectLog('debug', array('Processing deferred script', $script_handle, "hook: \"{$hook_name}\""));

		// _process_single_script mocks
		$this->expectLog('debug', array("Processing script '{$script_handle}'", "on hook '{$hook_name}'", "in context 'enqueue_deferred'"));
		WP_Mock::userFunction('wp_script_is')->with($script_handle, 'registered')->times(2)->andReturnValues(array(false, true));
		$this->expectLog('debug', array("Registering script '{$script_handle}'", "on hook '{$hook_name}'"));
		WP_Mock::userFunction('wp_register_script', array('args' => array($script_handle, 'path/to/deferred.js', array(), '1.0', false), 'times' => 1, 'return' => true));
		WP_Mock::userFunction('wp_script_is')->with($script_handle, 'enqueued')->once()->andReturn(false);
		$this->expectLog('debug', array("Enqueuing script '{$script_handle}'", "on hook '{$hook_name}'"));
		WP_Mock::userFunction('wp_enqueue_script', array('args' => array($script_handle), 'times' => 1));
		$this->expectLog('debug', array("Finished processing script '{$script_handle}'", "on hook '{$hook_name}'"));

		// Final log in enqueue_deferred_scripts
		$this->expectLog('debug', array('enqueue_deferred_scripts - Exited for hook', $hook_name));

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
		$hook_name = 'non_existent_hook';
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

		// Logger expectations
		$this->expectLog('debug', array('enqueue_deferred_scripts - Entered hook', $hook_name));
		$this->expectLog('debug', array('Hook', $hook_name, 'not found in deferred scripts'));

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
		$hook_name = 'empty_hook_for_scripts';
		// Set the deferred_scripts property to have the hook, but with an empty array of scripts.
		$deferred_scripts_prop = new \ReflectionProperty($this->instance, 'deferred_scripts');
		$deferred_scripts_prop->setAccessible(true);
		$deferred_scripts_prop->setValue($this->instance, array($hook_name => array()));

		// Logger expectations
		$this->expectLog('debug', array('enqueue_deferred_scripts - Entered hook', $hook_name));
		$this->expectLog('debug', array('Hook', $hook_name, 'was set but had no scripts. It has now been cleared.'));

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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(false);

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
	}

	/**
	 * Tests addition of an inline script with an active logger and ensures correct log messages.
	 */
	public function test_add_inline_scripts_with_active_logger() {
		// Arrange
		$handle  = 'test-log-handle';
		$content = '.log-class { font-weight: bold; }';
		// inline_scripts is reset to [] in setUp, so initial count is 0.
		$initial_inline_scripts_count = 0;

		// Logger expectations
		$this->expectLog('debug', array('add_inline_scripts - Entered', 'handle: ' . \esc_html($handle)));

		// No parent hook finding log expected in this basic case as $this->scripts is empty.

		$this->expectLog('debug', array('add_inline_scripts - Exiting', 'New total inline script count: ' . ($initial_inline_scripts_count + 1)));

		// Act: Call the method under test
		$this->instance->add_inline_scripts($handle, $content);

		// Assert: Check the internal $inline_scripts property (basic check, primary assertion is logger)
		$inline_scripts = $this->get_protected_property_value( $this->instance, 'inline_scripts' );
		$this->assertCount($initial_inline_scripts_count + 1, $inline_scripts, 'Expected one inline script to be added.');
		// Get the last added script (which will be the first if initial_inline_scripts_count is 0)
		$added_script = $inline_scripts[$initial_inline_scripts_count];

		$this->assertEquals($handle, $added_script['handle']);
		$this->assertEquals($content, $added_script['content']);
	}

	/**
	 * Tests that add_inline_scripts correctly associates a parent_hook
	 * from an existing registered script if not explicitly provided.
	 */
	public function test_add_inline_scripts_associates_parent_hook_from_registered_scripts() {
		// Arrange: Configure logger to be active
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

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
		$this->expectLog('debug', array('add_inline_scripts - Entered', "Adding new inline script for handle: {$parent_handle}"));
		$this->expectLog('debug', array("associated with parent hook: '{$parent_hook_name}'", "Original parent script hook: '{$parent_hook_name}'"));
		$this->expectLog('debug', array('add_inline_scripts - Exiting', 'New total inline script count: ' . ($initial_inline_scripts_count + 1)));

		// Act: Call the method under test, $parent_hook is null by default
		$this->instance->add_inline_scripts($parent_handle, $inline_content);

		// Assert: Check the internal $inline_scripts property
		$inline_scripts_array = $this->get_protected_property_value($this->instance, 'inline_scripts');
		$this->assertCount($initial_inline_scripts_count + 1, $inline_scripts_array, 'Expected one inline script to be added.');

		$added_inline_script = $inline_scripts_array[$initial_inline_scripts_count];
		$this->assertEquals($parent_handle, $added_inline_script['handle']);
		$this->assertEquals($inline_content, $added_inline_script['content']);
		$this->assertEquals($parent_hook_name, $added_inline_script['parent_hook'], 'Parent hook was not correctly associated from the registered script.');
	}

	// End of test_add_inline_scripts_associates_parent_hook_from_registered_scripts

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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

		// Ensure wp_script_is returns false so registration/enqueueing is attempted
		\WP_Mock::userFunction('wp_script_is')
			->with($script_handle, 'registered')
		    ->andReturn(false);
		\WP_Mock::userFunction('wp_script_is')
			->with($script_handle, 'enqueued')
		    ->andReturn(false);

		// --- Ordered Logger and WP_Mock expectations ---

		$order_group = 'process_script_flow';

		// 1. Log from _process_single_script entry
		$this->expectLog('debug', array('_process_single_script', "Processing script '{$script_handle}'", "in context '{$processing_context}'"), 1, $order_group);

		// 2. Log before wp_register_script
		$this->expectLog('debug', array('_process_single_script', "Registering script '{$script_handle}'"), 1, $order_group);

		// 3. Mock wp_register_script call, ensuring it returns true
		\WP_Mock::userFunction('wp_register_script')->once()
		    ->with($script_handle, $script_definition['src'], $script_definition['deps'], $script_definition['version'], false)
		    ->andReturn(true);

		// 4. Log before wp_enqueue_script
		$this->expectLog('debug', array('_process_single_script', "Enqueuing script '{$script_handle}'"), 1, $order_group);

		// 5. Mock wp_enqueue_script call
		\WP_Mock::userFunction('wp_enqueue_script')->once()->with($script_handle);

		// 6. Log from _process_inline_scripts entry
		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle '{$script_handle}'"), 1, $order_group);

		// 7. Log from _process_single_script completion
		$this->expectLog('debug', array('_process_single_script', "Finished processing script '{$script_handle}'"), 1, $order_group);

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
			'handle'    => $parent_handle,
			'content'   => '.conditional-script { display: none; }',
			'condition' => function () {
				return false;
			},
		);
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($inline_script_with_condition));

		// Logger active for debug messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);
		$order_group = 'process_script_flow';

		// Mocks for parent script processing
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(false, true)->ordered($order_group);
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'enqueued')->andReturn(false)->ordered($order_group);
		\WP_Mock::userFunction('wp_register_script')->once()->andReturn(true)->ordered($order_group);
		\WP_Mock::userFunction('wp_enqueue_script')->once()->ordered($order_group);

		// Crucially, wp_add_inline_script should NOT be called
		\WP_Mock::userFunction('wp_add_inline_script')->never()->ordered($order_group);

		// --- Ordered Logger expectations ---
		$this->expectLog('debug', array('_process_single_script', "Processing script '{$parent_handle}' in context '{$processing_context}'"), 1, $order_group);
		$this->expectLog('debug', array('_process_single_script', "Registering script '{$parent_handle}'"), 1, $order_group);
		$this->expectLog('debug', array('_process_single_script', "Enqueuing script '{$parent_handle}'"), 1, $order_group);

		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle '{$parent_handle}'"), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', "Condition false for inline script targeting '{$parent_handle}'"), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', 'Removed processed inline script'), 1, $order_group);

		$this->expectLog('debug', array('_process_single_script', "Finished processing script '{$parent_handle}'."), 1, $order_group);

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
			'handle' => $parent_handle,
			'src'    => 'path/to/parent.js',
		);

		// Define an inline script with empty content
		$inline_script_empty_content = array(
			'handle'  => $parent_handle,
			'content' => '', // Empty content
		);
		$inline_scripts_property = new \ReflectionProperty(ConcreteEnqueueForScriptsTesting::class, 'inline_scripts');
		$inline_scripts_property->setAccessible(true);
		$inline_scripts_property->setValue($this->instance, array($inline_script_empty_content));

		// Logger active for debug/warning messages
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

		$order_group = 'process_script_flow';

		// Mocks for parent script processing
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(false, true)->ordered($order_group);
		\WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'enqueued')->andReturn(false)->ordered($order_group);
		\WP_Mock::userFunction('wp_register_script')->once()->andReturn(true)->ordered($order_group);
		\WP_Mock::userFunction('wp_enqueue_script')->once()->ordered($order_group);

		// Crucially, wp_add_inline_script should NOT be called
		\WP_Mock::userFunction('wp_add_inline_script')->never()->ordered($order_group);

		// --- Ordered Logger expectations ---
		$this->expectLog('debug', array('_process_single_script', "Processing script '{$parent_handle}'"), 1, $order_group);
		$this->expectLog('debug', array('_process_single_script', "Registering script '{$parent_handle}'"), 1, $order_group);
		$this->expectLog('debug', array('_process_single_script', "Enqueuing script '{$parent_handle}'"), 1, $order_group);

		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle '{$parent_handle}'"), 1, $order_group);
		// Target Log for this test
		$this->expectLog('warning', array('_process_inline_scripts', 'Empty content for inline script'), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', 'Removed processed inline script'), 1, $order_group);

		$this->expectLog('debug', array('_process_single_script', "Finished processing script '{$parent_handle}'."), 1, $order_group);

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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

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
		$order_group = 'log_order';
		$this->expectLog('debug', array('enqueue_inline_scripts', 'Entered method.'), 1, $order_group);
		$this->expectLog('debug', array('enqueue_inline_scripts', 'Found 1 unique parent handle(s)', $parent_handle), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle '{$parent_handle}'"), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', "Adding inline script for '{$parent_handle}'", "position: {$inline_position}"), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', 'Removed processed inline script', $parent_handle), 1, $order_group);
		$this->expectLog('debug', array('enqueue_inline_scripts', 'Exited.', 'Remaining deferred inline scripts'), 1, $order_group);
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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true)->byDefault();
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

		$this->instance->shouldReceive('_do_add_filter')
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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

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

		$this->expectLog('debug', array('Entered method.'), 1);
		$this->expectLog('debug', array('No immediate inline scripts found needing processing.'), 1);

		// Act: Call the method under test
		$result = $this->instance->enqueue_inline_scripts();

		// Assert: Method returns $this for chaining
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		// Mockery will assert that all expected log calls were made.
	} // End of test_enqueue_inline_scripts_only_deferred_scripts_present

	/**
	 * Tests enqueue_inline_scripts processes a single immediate inline script,
	 * including logging and call to wp_add_inline_script.
	 *
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::enqueue_inline_scripts
	 * @covers \Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait::_process_inline_scripts
	 */
	public function test_enqueue_inline_scripts_processes_one_immediate_script(): void {
		// Arrange
		$handle      = 'test-immediate-handle';
		$content     = '.immediate { border: 1px solid green; }';
		$position    = 'after';
		$order_group = 'enqueue_inline_scripts';

		// Use the public method to add the script, which is cleaner than reflection.
		$this->instance->add_inline_scripts($handle, $content, $position);

		// Logger must be active for logs to be processed.
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Set up the full, ordered sequence of expected log messages.
		$this->expectLog('debug', array('enqueue_inline_scripts', 'Entered method.'), 1, $order_group);
		$this->expectLog('debug', array('enqueue_inline_scripts', 'Found 1 unique parent handle(s) with immediate inline scripts to process: test-immediate-handle'), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle 'test-immediate-handle'"), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', "Adding inline script for 'test-immediate-handle' (key: 0, position: {$position})"), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', "Successfully added inline script for 'test-immediate-handle' with wp_add_inline_script"), 1, $order_group);
		$this->expectLog('debug', array('_process_inline_scripts', "Removed processed inline script with key '0' for handle 'test-immediate-handle'"), 1, $order_group);
		$this->expectLog('debug', array('enqueue_inline_scripts', 'Exited. Processed 1 parent handle(s). Remaining deferred inline scripts: 0.'), 1, $order_group);

		// Mock WordPress functions that are called during processing.
		WP_Mock::userFunction('wp_script_is', array('args' => array($handle, 'registered'), 'return' => true, 'times' => 1))->ordered($order_group);
		WP_Mock::userFunction('wp_add_inline_script', array('args' => array($handle, $content, $position), 'return' => true, 'times' => 1))->ordered($order_group);

		// Act
		$result = $this->instance->enqueue_inline_scripts();

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		$this->assertEmpty($this->get_protected_property_value($this->instance, 'inline_scripts'), 'Inline scripts queue should be empty after processing.');
	}

	/**
	 * Tests that enqueue_inline_scripts skips invalid (non-array) inline script data
	 * and processes any valid immediate scripts that might also be present.
	 */
	public function test_enqueue_inline_scripts_skips_invalid_non_array_inline_script_data() {
		// Arrange: Logger is active
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);

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

		// --- Simplified Expectations ---
		// The core behavior we're testing is that invalid data is identified and skipped.
		// The warning is logged twice, so we expect it twice.
		$this->expectLog('warning', "Invalid inline script data at key '0'. Skipping.", 2);

		// We still need to mock the WordPress functions that are called for the *valid* script,
		// otherwise the test will fail when those functions are called.
		// Note: No 'ordered()' call, making the test less brittle.
		\WP_Mock::userFunction('wp_script_is')->with($valid_handle, 'registered')->andReturn(false);
		\WP_Mock::userFunction('wp_script_is')->with($valid_handle, 'enqueued')->andReturn(true);
		\WP_Mock::userFunction('wp_add_inline_script')->with($valid_handle, $valid_content, $valid_position)->andReturn(true);

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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->zeroOrMoreTimes()->andReturn(true);
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

		// Expect that the action is added exactly once.
		\WP_Mock::expectActionAdded($hook_name, array( $this->instance, 'enqueue_deferred_scripts' ), 10, 1);

		// --- Corrected Logger Expectations ---
		// Based on the source code, these are the two logs that should appear, once each.
		$this->expectLog('debug', "Added action for 'enqueue_deferred_scripts' on hook: {$hook_name}.", 1);
		$this->expectLog('debug', "Action for 'enqueue_deferred_scripts' on hook '{$hook_name}' already exists.", 1);


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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldIgnoreMissing();

		$group_name = 'process_single_script';

		// Set up logger expectations for add_scripts
		$this->expectLog('debug', array('add_scripts', 'Entered. Current script count: 0. Adding 1 new script(s).'), 1);
		$this->expectLog('debug', array('add_scripts', "Adding script. Key: {$script_handle}, Handle: {$script_handle}, Src: {$script_src}"), 1);
		$this->expectLog('debug', array('add_scripts', 'Exiting. New total script count: 1'), 1);

		$this->instance->add_scripts($scripts_data);

		// Assert that inline_scripts array is empty after add_scripts and before register_scripts is called
		$this->assertEmpty($this->instance->get_internal_inline_scripts_array(), 'Inline scripts array should be empty before register_scripts call.');

		// Mock wp_script_is to indicate the script is already registered
		// Called once in _process_single_script, once in _process_inline_scripts
		\WP_Mock::userFunction('wp_script_is')
			->times(2)
			->with($script_handle, 'registered')
			->andReturn(true)
			->ordered($group_name);

		// Mock wp_script_is for 'enqueued' check within _process_inline_scripts
		// This should NOT be called if 'registered' is true due to short-circuiting in the IF condition.
		\WP_Mock::userFunction('wp_script_is')
			->never()
			->with($script_handle, 'enqueued')
			->ordered($group_name);

		// Expect wp_register_script NOT to be called
		\WP_Mock::userFunction('wp_register_script')
			->never()
			->ordered($group_name);

		// Expect the specific debug log messages in order
		// From _process_single_script
		$this->expectLog('debug', array('_process_single_script', "Processing script '{$script_handle}' in context 'register_scripts'."), 1);
		$this->expectLog('debug', array('_process_single_script', "Script '{$script_handle}' already registered. Skipping wp_register_script."), 1);

		// From _process_inline_scripts (called by _process_single_script)
		$this->expectLog('debug', array('_process_inline_scripts', "Checking for inline scripts for parent handle '{$script_handle}'."), 1);
		$this->expectLog('debug', array('_process_inline_scripts', "No inline scripts found or processed for '{$script_handle}'."), 1);

		// From _process_single_script (finish)
		$this->expectLog('debug', array('_process_single_script', "Finished processing script '{$script_handle}'."), 1);

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

		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldIgnoreMissing();
		$group_name = 'process_single_script';

		// Mock WordPress functions
		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($script_handle, 'registered'),
			'return' => true,
		))->ordered($group_name);
		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($script_handle, 'enqueued'),
			'return' => true,
		))->ordered($group_name);

		// Ordered expectations ONLY for is_active calls (when hook_name is NOT null, inline processing in _process_single_script is skipped)
		$this->expectLog('debug', array('_process_single_script', "Processing script '{$script_handle}' on hook '{$hook_name}' in context '{$processing_context}'."), 1, $group_name);
		$this->expectLog('debug', array('_process_single_script', "Script '{$script_handle}' on hook '{$hook_name}' already enqueued. Skipping wp_enqueue_script."), 1, $group_name);
		$this->expectLog('debug', array('_process_single_script', "Script '{$script_handle}' on hook '{$hook_name}' already registered. Skipping wp_register_script."), 1, $group_name);
		$this->expectLog('debug', array('_process_single_script', "Finished processing script '{$script_handle}' on hook '{$hook_name}'."), 1, $group_name);

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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldIgnoreMissing();
		$group_name = 'process_single_script';

		$this->expectLog('debug', array('_process_single_script', "Processing script '{$script_definition['handle']}' on hook '{$hook_name}' in context '{$processing_context}'."), 1, $group_name, true);
		$this->expectLog('warning', array('_process_single_script', "Invalid script definition. Missing handle or src. Skipping. Handle: '{$script_definition['handle']}' on hook '{$hook_name}'."), 1, $group_name);

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

		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldIgnoreMissing();
		$group_name = 'process_single_script';

		$script_definition = array(
			'handle' => $script_handle,
			'src'    => 'path/to/script.js',
		);

		\WP_Mock::userFunction('wp_script_is', array(
			'args'   => array($script_handle, 'registered'),
			'return' => false,
			'times'  => 1,
		))->ordered($group_name);

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
		))->ordered($group_name);

		$this->expectLog('debug', array('_process_single_script', "Processing script '{$script_handle}' on hook '{$hook_name}' in context '{$processing_context}'."), 1, $group_name);
		$this->expectLog('debug', array('_process_single_script', "Registering script '{$script_handle}' on hook '{$hook_name}'"), 1, $group_name);
		$this->expectLog('warning', array('_process_single_script', "wp_register_script() failed for handle '{$script_handle}' on hook '{$hook_name}'. Skipping further processing for this script."), 1, $group_name, true);

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
	public function test_process_inline_scripts_handles_deferred_script_with_matching_hook(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForScriptsTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);
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
		$group_name = 'deferred';

		// The order of mock expectations must match the order of execution in the SUT.
		// 1. Log "Checking..."
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: deferred) - Checking for inline scripts for parent handle 'parent-script' on hook 'a_custom_hook'.", 1, $group_name);

		// 2. wp_script_is() is called. Because it returns true, the second call is short-circuited.
		WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(true)->once()->ordered($group_name);

		// 3. Log "Adding..."
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: deferred) - Adding inline script for 'parent-script' (key: 0, position: after) on hook 'a_custom_hook'.", 1, $group_name);

		// 4. wp_add_inline_script() is called and must return true for the script to be marked as processed.
		WP_Mock::userFunction('wp_add_inline_script')->with('parent-script', '.my-class { color: red; }', 'after')->andReturn(true)->once()->ordered($group_name);

		// 5. Log "Successfully added..."
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: deferred) - Successfully added inline script for 'parent-script' with wp_add_inline_script.", 1, $group_name);

		// 6. Log "Removed..." - This is the final log message we need to see.
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: deferred) - Removed processed inline script with key '0' for handle 'parent-script' on hook 'a_custom_hook'.", 1, $group_name, true);

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
		$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true);
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

		$group_name = 'immediate';
		WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(true)->once()->ordered($group_name);

		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Checking for inline scripts for parent handle 'parent-script'.", 1, $group_name);
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Condition false for inline script targeting 'parent-script' (key: 0).", 1, $group_name);
		WP_Mock::userFunction('wp_add_inline_script')->never()->ordered($group_name);

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
		$group_name = 'immediate';
		WP_Mock::userFunction('wp_script_is')->with($parent_handle, 'registered')->andReturn(true)->ordered($group_name);

		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Checking for inline scripts for parent handle 'parent-script'.", 1, $group_name);
		$this->expectLog('warning', "ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Empty content for inline script targeting 'parent-script' (key: 0). Skipping addition.", 1, $group_name);
		$this->expectLog('debug', "ScriptsEnqueueTrait::_process_inline_scripts (context: immediate) - Removed processed inline script with key '0' for handle 'parent-script'.", 1, $group_name);
		WP_Mock::userFunction('wp_add_inline_script')->never()->ordered($group_name);

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
			$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true)->byDefault();
			foreach ($logger_expects as $expectation) {
				if (!is_array($expectation) || count($expectation) < 2) {
					throw new InvalidArgumentException('Each log expectation must be an array with at least a level and a message array.');
				}
				list($level, $messages) = $expectation;
				$this->expectLog($level, $messages);
			}
		} else {
			$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(false)->byDefault();
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
					array('debug', array('_process_single_script', "Processing script 'test-handle'", 'test_context_reg')),
					array('debug', array('_process_single_script', "Registering script 'test-handle'")),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'Checking for inline scripts', 'test-handle')),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'No inline scripts found', 'test-handle')),
					array('debug', array('_process_single_script', "Finished processing script 'test-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'test-handle'", 'test_context_reg_enq')),
					array('debug', array('_process_single_script', "Registering script 'test-handle'")),
					array('debug', array('_process_single_script', "Enqueuing script 'test-handle'")),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'Checking for inline scripts', 'test-handle')),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'No inline scripts found', 'test-handle')),
					array('debug', array('_process_single_script', "Finished processing script 'test-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'test-handle'", 'test_context_already_reg')),
					array('debug', array('_process_single_script', "Script 'test-handle' already registered")),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'Checking for inline scripts', 'test-handle')),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'No inline scripts found', 'test-handle')),
					array('debug', array('_process_single_script', "Finished processing script 'test-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'test-handle'", 'test_context_already_enq')),
					array('debug', array('_process_single_script', "Script 'test-handle' already registered")),
					array('debug', array('_process_single_script', "Script 'test-handle' already enqueued")),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'Checking for inline scripts', 'test-handle')),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'No inline scripts found', 'test-handle')),
					array('debug', array('_process_single_script', "Finished processing script 'test-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'test-handle'", 'test_context_cond_false')),
					array('debug', array('_process_single_script', "Condition not met for script 'test-handle'. Skipping.")),
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
					array('debug', array('_process_single_script', "Processing script 'N/A'", 'test_context_no_handle')),
					array('warning', array('_process_single_script', 'Invalid script definition', "Handle: 'N/A'")),
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
					array('debug', array('_process_single_script', "Processing script 'test-handle'", 'test_context_no_src')),
					array('warning', array('_process_single_script', 'Invalid script definition', "Handle: 'test-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'test-handle'", 'test_context_reg_fail')),
					array('debug', array('_process_single_script', "Registering script 'test-handle'")),
					array('warning', array('_process_single_script', "wp_register_script() failed for handle 'test-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'defer-handle'", 'test_context_defer_route')),
					array('debug', array('_process_single_script', "Registering script 'defer-handle'")),
					array('debug', array('_process_single_script', "Routing 'defer' attribute for 'defer-handle'", "wp_script_add_data('strategy', 'defer')")),
					array('debug', array('_process_single_script', "Enqueuing script 'defer-handle'")),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'Checking for inline scripts', 'defer-handle')),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'No inline scripts found', 'defer-handle')),
					array('debug', array('_process_single_script', "Finished processing script 'defer-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'async-custom-handle'", 'test_context_async_custom_route')),
					array('debug', array('_process_single_script', "Registering script 'async-custom-handle'")),
					array('debug', array('_process_single_script', "Routing 'async' attribute for 'async-custom-handle'", "wp_script_add_data('strategy', 'async')")),
					array('debug', array('_process_single_script', "Enqueuing script 'async-custom-handle'")),
					array('debug', array('_process_single_script', "Adding attributes for script 'async-custom-handle'", 'script_loader_tag', '{"data-foo":"bar"}')),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'Checking for inline scripts', 'async-custom-handle')),
					array('debug', array('_process_inline_scripts', 'immediate_after_registration', 'No inline scripts found', 'async-custom-handle')),
					array('debug', array('_process_single_script', "Finished processing script 'async-custom-handle'")),
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
					array('debug', array('_process_single_script', "Processing script 'deferred-handle' on hook 'some_hook'", 'test_context_deferred')),
					array('debug', array('_process_single_script', "Registering script 'deferred-handle' on hook 'some_hook'")),
					array('debug', array('_process_single_script', "Enqueuing script 'deferred-handle' on hook 'some_hook'")),
					array('debug', array('_process_single_script', "Finished processing script 'deferred-handle' on hook 'some_hook'")),
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

	/**
	 * Data provider for `test_process_single_script_with_invalid_definition`.
	 *
	 * Provides script definitions that are missing required keys (`handle` or `src`).
	 *
	 * @return array
	 */
	public function provide_invalid_script_definitions(): array {
		return array(
			'missing handle' => array(array('src' => 'some.js'), 'N/A'),
			'missing src'    => array(array('handle' => 'no-src-handle'), 'no-src-handle'),
		);
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
		if (!empty($logger_expects)) {
			$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(true)->byDefault();

			foreach ($logger_expects as $log_level => $messages_for_level) {
				// This handles the case where multiple calls are defined for the same log level.
				// e.g., 'debug' => array( array('msg1_part1', 'msg1_part2'), array('msg2') )
				if (isset($messages_for_level[0]) && is_array($messages_for_level[0])) {
					foreach ($messages_for_level as $log_arguments) {
						// Pass the entire array of substrings to create a specific expectation.
						$this->expectLog($log_level, $log_arguments);
					}
				} else {
					// This handles a single call for a log level where the arguments are not nested.
					// e.g., 'warning' => array('warn_msg_part1', 'warn_msg_part2')
					if (!empty($messages_for_level)) {
						$this->expectLog($log_level, $messages_for_level);
					}
				}
			}
		} else {
			$this->logger_mock->shouldReceive('is_active')->withAnyArgs()->andReturn(false)->byDefault(); // Assume no logging unless specified
		}

		$modified_tag = $this->call_modify_script_tag_for_attributes($original_tag, $handle, $attributes);
		$this->assertEquals($expected_tag, $modified_tag);
	}

	/**
	 * Data provider for `test_modify_script_tag_for_attributes`.
	 *
	 * @return array
	 */
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
						array(
							'_modify_script_tag_for_attributes', "Modifying tag for handle 'module-handle'. Attributes: " . json_encode(array('type' => 'module'))
						),
						array(
							'_modify_script_tag_for_attributes', "Script 'module-handle' is a module. Modifying tag accordingly."
						),
						array(
							'_modify_script_tag_for_attributes', "Successfully modified tag for 'module-handle'. New tag: " . esc_html('<script type="module" src="module.js"></script>')
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
			'src_attribute_ignored' => array(
				'<script src="test.js"></script>',
				'test-handle',
				array('src' => 'ignored.js', 'async' => true),
				'<script src="test.js" async></script>',
				array(
					'debug' => array(
						array(
							'_modify_script_tag_for_attributes',
							'Modifying tag for handle',
							'test-handle',
							'{"src":"ignored.js","async":true}'
						),
						array(
							'_modify_script_tag_for_attributes',
							'Successfully modified tag for',
							'test-handle',
							'<script src="test.js" async>'
						)
					),
					'warning' => array(
						array(
							'_modify_script_tag_for_attributes',
							"Attempt to override managed attribute 'src'",
							"for script handle 'test-handle'"
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
							'_modify_script_tag_for_attributes', "Modifying tag for handle 'test-handle'. Attributes: " . json_encode(array('async' => true))
						)
					),
					'warning' => array(
						array(
							'_modify_script_tag_for_attributes', "Malformed script tag for 'test-handle'. Original tag: " . esc_html('<script src="test.js"') . '. Skipping attribute modification.'
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
							'_modify_script_tag_for_attributes', "Modifying tag for handle 'test-handle'. Attributes: {\"async\":true}"
						)
					),
					'warning' => array(
						array(
							'_modify_script_tag_for_attributes', "Malformed script tag for 'test-handle'. Original tag: " . esc_html('<div></div>') . '. Skipping attribute modification.'
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
							'_modify_script_tag_for_attributes', "Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js"></script>')
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
						array('_modify_script_tag_for_attributes', "Modifying tag for handle 'test-handle'. Attributes: {\"data-count\":0}"),
						array('_modify_script_tag_for_attributes', "Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js" data-count="0"></script>'))
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
						array('_modify_script_tag_for_attributes', "Modifying tag for handle 'test-handle'. Attributes: {\"data-count\":\"0\"}"),
						array('_modify_script_tag_for_attributes', "Successfully modified tag for 'test-handle'. New tag: " . esc_html('<script src="test.js" data-count="0"></script>'))
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
							'_modify_script_tag_for_attributes',
							'Modifying tag for handle',
							'test-handle',
							'{"id":"new-id","async":true}'
						),
						array(
							'_modify_script_tag_for_attributes',
							'Successfully modified tag for',
							'test-handle',
							'<script src="test.js" async>'
						)
					),
					'warning' => array(
						array(
							'_modify_script_tag_for_attributes',
							"Attempt to override managed attribute 'id'",
							"for script handle 'test-handle'"
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
							'_modify_script_tag_for_attributes',
							'Modifying tag for handle',
							'test-handle',
							'{"src":"ignored.js","id":"new-id"}'
						),
						array(
							'_modify_script_tag_for_attributes',
							'Successfully modified tag for',
							'test-handle',
							'<script src="test.js">'
						)
					),
					'warning' => array(
						array(
							'_modify_script_tag_for_attributes',
							"Attempt to override managed attribute 'src'",
							"for script handle 'test-handle'"
						),
						array(
							'_modify_script_tag_for_attributes',
							"Attempt to override managed attribute 'id'",
							"for script handle 'test-handle'"
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
							'_modify_script_tag_for_attributes', "Modifying tag for handle 'app-main'. Attributes: " . json_encode($complex_attrs)
						),
						array(
							'_modify_script_tag_for_attributes', "Script 'app-main' is a module. Modifying tag accordingly."
						),
						array(
							'_modify_script_tag_for_attributes', "Successfully modified tag for 'app-main'. New tag: " . esc_html($complex_expected_tag)
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

	// endregion
}
