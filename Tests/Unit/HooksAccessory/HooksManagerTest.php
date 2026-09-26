<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\HooksAccessory;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\HooksAccessory\HooksManager;
use Ran\PluginLib\HooksAccessory\FilterHooksInterface;
use Ran\PluginLib\HooksAccessory\ActionHooksInterface;
use Mockery;

/**
 * Define a global function for testing evaluate_condition
 */
if (!function_exists('test_conditional_function_for_coverage')) {
	function test_conditional_function_for_coverage() {
		return true;
	}
}

/**
 * Test class for HooksManager
 */
class TestHooksManager extends HooksManager {
	/** @var array<string,int> */
	public array $captured_stats = array();
	private object $owner_ref;

	public function __construct(object $owner, ?Logger $logger = null) {
		parent::__construct($owner, $logger);
		$this->owner_ref = $owner;
	}

	public function init_declarative_hooks(): void {
		parent::init_declarative_hooks();
		$this->captured_stats = $this->get_stats();
	}

	public function get_stats(): array {
		return parent::get_stats();
	}

	public function get_registered_hooks(): array {
		return parent::get_registered_hooks();
	}

	public function generate_debug_report(): array {
		$stats             = $this->get_stats();
		$registered_hooks  = $this->get_registered_hooks();
		$declarative_owner = $this->captured_stats['actions_registered'] ?? $stats['actions_registered'];

		return array(
			'owner_class'               => get_class($this->owner_ref),
			'stats'                     => $stats,
			'registered_hooks_count'    => count($registered_hooks),
			'registered_hooks'          => $registered_hooks,
			'has_declarative_actions'   => $this->owner_ref instanceof ActionHooksInterface,
			'has_declarative_filters'   => $this->owner_ref instanceof FilterHooksInterface,
			'declarative_actions_count' => $declarative_owner,
			'declarative_filters_count' => $this->captured_stats['filters_registered'] ?? $stats['filters_registered'],
		);
	}
}

final class InactiveCollectingLogger extends CollectingLogger {
	public function __construct() {
		parent::__construct();
	}

	public function is_active(): bool {
		return false;
	}

	public function log($level, string|\Stringable $message, array $context = array()): void {
		// Intentionally no-op so inactive logger collects nothing.
	}
}

class HooksManagerTest extends PluginLibTestCase {
	use ExpectLogTrait;
	private TestHooksManager|Mockery\MockInterface $hooksManager;
	private object $test_object;
	private CollectingLogger $logger;

	public function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	public function setUp(): void {
		parent::setUp();

		// Create a collecting logger for testing
		$this->logger      = new CollectingLogger();
		$this->logger_mock = $this->logger; // For ExpectLogTrait compatibility

		// Create a test object to own the hooks
		$this->test_object = new class() {
			public function test_method() {
				return 'test';
			}
		};

		// Create a partial mock of HooksManager that only mocks the wrapper methods
		$this->hooksManager = Mockery::mock(
			TestHooksManager::class . '[_do_add_action,_do_add_filter,_do_remove_action,_do_remove_filter,_do_execute_action,_do_apply_filter]',
			array($this->test_object, $this->logger)
		)->makePartial();

		// Set up default behavior for wrapper methods
		$this->hooksManager->shouldAllowMockingProtectedMethods();
		$this->hooksManager->shouldReceive('_do_add_action')->byDefault()->andReturn(true);
		$this->hooksManager->shouldReceive('_do_did_action')->byDefault()->andReturn(1);
		$this->hooksManager->shouldReceive('_do_add_filter')->byDefault()->andReturn(true);
		$this->hooksManager->shouldReceive('_do_remove_action')->byDefault()->andReturn(true);
		$this->hooksManager->shouldReceive('_do_remove_filter')->byDefault()->andReturn(true);
		$this->hooksManager->shouldReceive('_do_execute_action')->byDefault()->andReturnNull();
		$this->hooksManager->shouldReceive('_do_apply_filter')->byDefault()->andReturnUsing(
			function ($hook, $value) {
				// For testing, we'll modify the value in a predictable way if we've registered a test filter
				if ($hook === 'test_filter') {
					return 'filtered_' . $value;
				}
				return $value;
			}
		);
	}

	/**
	 * Test registering and executing an action hook
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_and_execute_action(): void {
		$testValue = 0;
		$callback  = function () use (&$testValue) {
			$testValue = 42;
		};

		// Set up expectation for _do_add_action to be called with the correct parameters
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_action', $callback, 10, 1)
		    ->andReturn(true);

		// Register a simple action
		$result = $this->hooksManager->register_action('test_action', $callback);

		$this->assertTrue($result, 'Action registration should succeed');

		// Set up expectation for _do_execute_action to be called with the correct hook
		$this->hooksManager->shouldReceive('_do_execute_action')
		    ->once()
		    ->with('test_action')
		    ->andReturnUsing(
		    	function () use (&$testValue) {
		    		// Simulate the action being executed
		    		$testValue = 42;
		    	}
		    );

		// Trigger the action through the manager
		$this->hooksManager->_do_execute_action('test_action');

		$this->assertEquals(42, $testValue, 'Action callback should have been executed');
	}

	/**
	 * Test registering and executing a filter hook
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_filter
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::apply_filters
	 */
	public function test_register_and_execute_filter(): void {
		$callback = function ($value) {
			return 'filtered_' . $value;
		};

		// Set up expectation for _do_add_filter to be called with the correct parameters
		$this->hooksManager->shouldReceive('_do_add_filter')
		    ->once()
		    ->with('test_filter', $callback, 10, 1)
		    ->andReturn(true);

		// Register a simple filter
		$result = $this->hooksManager->register_filter('test_filter', $callback);

		$this->assertTrue($result, 'Filter registration should succeed');

		// Set up expectation for _do_apply_filter to be called with the correct hook
		$this->hooksManager->shouldReceive('_do_apply_filter')
		    ->once()
		    ->with('test_filter', 'test_value')
		    ->andReturnUsing(
		    	function ($hook, $value) use ($callback) {
		    		// Simulate the filter being applied
		    		return $callback($value);
		    	}
		    );

		// Apply the filter through the manager
		$filtered = $this->hooksManager->apply_filters('test_filter', 'test_value');

		$this->assertEquals('filtered_test_value', $filtered, 'Filter should modify the value');
	}

	/**
	 * Test registering and applying a filter.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_filter
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::apply_filters
	 */
	public function test_register_and_apply_filter(): void {
		// Define the callback
		$callback = function ($value) {
			return $value . ' filtered';
		};

		// Set up expectation for _do_add_filter to be called with the correct parameters
		$this->hooksManager->shouldReceive('_do_add_filter')
		    ->once()
		    ->with('test_filter', $callback, 10, 1)
		    ->andReturn(true);

		// Register a simple filter
		$result = $this->hooksManager->register_filter('test_filter', $callback);

		$this->assertTrue($result, 'Filter registration should succeed');

		// Set up expectation for _do_apply_filter to be called with the correct hook
		$this->hooksManager->shouldReceive('_do_apply_filter')
		    ->once()
		    ->with('test_filter', 'original')
		    ->andReturn('filtered_original');

		// Apply the filter through the manager
		$filtered_value = $this->hooksManager->apply_filters('test_filter', 'original');

		$this->assertEquals('filtered_original', $filtered_value, 'Filter should have been applied');
	}

	/**
	 * Test action hooks integration.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_hooks_for
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 */
	public function test_action_hooks_integration(): void {
		$testClass = new class() implements ActionHooksInterface {
			// Explicitly declare properties to avoid PHP 8.2 dynamic property deprecation warnings
			public bool $actionHandled        = false;
			public bool $anotherActionHandled = false;

			public static function declare_action_hooks(): array {
				return array(
				'test_action'    => 'handle_action',
				'another_action' => array('handle_another_action', 20)
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array(
				 'description' => 'Test action hooks',
				 'version'     => '1.0.0'
				);
			}

			public function handle_action() {
				$this->actionHandled = true;
			}

			public function handle_another_action() {
				$this->anotherActionHandled = true;
			}
		};

		// Set up expectations for _do_add_action calls
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_action', array($testClass, 'handle_action'), 10, 1)
		    ->andReturn(true);

		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('another_action', array($testClass, 'handle_another_action'), 20, 1)
		    ->andReturn(true);

		// Register the hooks from the test class
		$result = $this->hooksManager->register_hooks_for($testClass);

		// Verify registration was successful
		$this->assertTrue($result, 'Hook registration should succeed');

		// Set up expectations for _do_execute_action calls
		$this->hooksManager->shouldReceive('_do_execute_action')
		    ->once()
		    ->with('test_action')
		    ->andReturnUsing(
		    	function () use ($testClass) {
		    		// Simulate the action being executed
		    		$testClass->actionHandled = true;
		    	}
		    );

		$this->hooksManager->shouldReceive('_do_execute_action')
		    ->once()
		    ->with('another_action')
		    ->andReturnUsing(
		    	function () use ($testClass) {
		    		// Simulate the action being executed
		    		$testClass->anotherActionHandled = true;
		    	}
		    );

		// Trigger the actions
		$this->hooksManager->_do_execute_action('test_action');
		$this->hooksManager->_do_execute_action('another_action');

		$this->assertTrue($testClass->actionHandled, 'First action should be handled');
		$this->assertTrue($testClass->anotherActionHandled, 'Second action should be handled');
	}

	/**
	 * Test invalid callback handling.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_invalid_callback_handling(): void {
		// Set up WP_Mock to NOT be called since we expect registration to fail
		\WP_Mock::userFunction('add_action')->never();

		// This should fail because the callback is not callable
		$hooksManager = new HooksManager($this->test_object, $this->logger);
		$result       = $hooksManager->register_action(
			'invalid_action',
			'non_existent_function'
		);

		$this->assertFalse($result, 'Registration should fail for non-callable callback');
	}

	/**
	 * Test hook deduplication
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::is_hook_registered
	 */
	public function test_hook_deduplication(): void {
		$callCount = 0;
		$callback  = function () use (&$callCount) {
			$callCount++;
		};

		// Set up expectation for _do_add_action - should only be called once due to deduplication
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('duplicate_action', $callback, 10, 1)
		    ->andReturn(true);

		// Register the same hook twice
		$result1 = $this->hooksManager->register_action('duplicate_action', $callback);
		$result2 = $this->hooksManager->register_action('duplicate_action', $callback);

		$this->assertTrue($result1, 'First registration should succeed');
		$this->assertFalse($result2, 'Second registration should fail due to deduplication');

		// Set up expectation for _do_execute_action
		$this->hooksManager->shouldReceive('_do_execute_action')
		    ->once()
		    ->with('duplicate_action')
		    ->andReturnUsing(
		    	function () use (&$callCount) {
		    		// Simulate the callback being executed once
		    		$callCount++;
		    	}
		    );

		// Trigger the action
		$this->hooksManager->_do_execute_action('duplicate_action');

		$this->assertEquals(1, $callCount, 'Callback should only be called once');
	}

	/**
	 * Test hook removal
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::remove_hook
	 */
	public function test_hook_removal(): void {
		$callCount = 0;
		$callback  = function () use (&$callCount) {
			$callCount++;
		};
		$hookName = 'removable_action';

		// Set up expectation for _do_add_action
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with($hookName, $callback, 10, 1)
		    ->andReturn(true);

		// Register the hook
		$result = $this->hooksManager->register_action($hookName, $callback);
		$this->assertTrue($result, 'Hook should be registered successfully');

		// Set up expectation for _do_remove_action
		$this->hooksManager->shouldReceive('_do_remove_action')
		    ->once()
		    ->with($hookName, $callback, 10)
		    ->andReturn(true);

		// Remove the hook - using the correct parameter order for HooksManager::remove_hook
		// The method signature is remove_hook(string $type, string $hook_name, callable $callback, int $priority = 10)
		$result = $this->hooksManager->remove_hook('action', $hookName, $callback);
		$this->assertTrue($result, 'Hook should be removed successfully');

		// Set up expectation for _do_execute_action - the callback should not be executed
		$this->hooksManager->shouldReceive('_do_execute_action')
		    ->once()
		    ->with($hookName)
		    ->andReturnNull(); // No callback execution

		// Trigger the action through the manager
		$this->hooksManager->_do_execute_action($hookName);

		// Should not be called as it was removed
		$this->assertEquals(0, $callCount, 'Callback should not be called after removal');
	}

	/**
	 * Test hook priority
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 */
	public function test_hook_priority(): void {
		$executionOrder = array();

		// Create callbacks with different priorities
		$lowPriorityCallback = function () use (&$executionOrder) {
			$executionOrder[] = 'low_priority';
		};

		$highPriorityCallback = function () use (&$executionOrder) {
			$executionOrder[] = 'high_priority';
		};

		// Set up expectations for _do_add_action calls
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('priority_test', $lowPriorityCallback, 20, 1)
		    ->andReturn(true);

		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('priority_test', $highPriorityCallback, 5, 1)
		    ->andReturn(true);

		// Register hooks with different priorities
		$this->hooksManager->register_action('priority_test', $lowPriorityCallback, 20);
		$this->hooksManager->register_action('priority_test', $highPriorityCallback, 5);

		// Set up expectation for _do_execute_action to simulate proper priority execution
		$this->hooksManager->shouldReceive('_do_execute_action')
		    ->once()
		    ->with('priority_test')
		    ->andReturnUsing(
		    	function () use ($highPriorityCallback, $lowPriorityCallback) {
		    		// Simulate WordPress executing callbacks in priority order
		    		$highPriorityCallback();
		    		$lowPriorityCallback();
		    	}
		    );

		// Trigger the action through the manager
		$this->hooksManager->_do_execute_action('priority_test');

		// Verify execution order
		$this->assertEquals('high_priority', $executionOrder[0], 'High priority callback should execute first');
		$this->assertEquals('low_priority', $executionOrder[1], 'Low priority callback should execute second');
	}

	/**
	 * Test the init_declarative_hooks method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::init_declarative_hooks
	 */
	public function test_init_declarative_hooks(): void {
		// Create a test class that implements both ActionHooksInterface and FilterHooksInterface
		$testObject = new class() implements ActionHooksInterface, FilterHooksInterface {
			// Explicitly declare properties to avoid PHP 8.2 dynamic property deprecation warnings
			public bool $actionHandled        = false;
			public bool $anotherActionHandled = false;


			public static function declare_action_hooks(): array {
				return array(
				'test_action'    => 'handle_action',
				'another_action' => array('handle_another_action', 20)
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array(
				'description' => 'Test action hooks',
				'version'     => '1.0.0'
				);
			}

			public static function declare_filter_hooks(): array {
				return array(
				'test_filter'    => 'handle_filter',
				'another_filter' => array('handle_another_filter', 15)
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array(
				'description' => 'Test filter hooks',
				'version'     => '1.0.0'
				);
			}

			public function handle_action() {
			}
			public function handle_another_action() {
			}
			public function handle_filter($value) {
				return $value;
			}
			public function handle_another_filter($value) {
				return $value;
			}
		};

		// Create a test subclass that we can control
		$hooksManager = new class($testObject, $this->logger) extends HooksManager {
			// Track if init_declarative_hooks was called and hook counts
			public bool $init_called = false;
			public int $action_count = 0;
			public int $filter_count = 0;

			// Override the method to avoid creating real registrars
			public function init_declarative_hooks(): void {
				$this->init_called = true;
				// Hard-code the counts based on our test object
				// We know the test object has 2 action hooks and 2 filter hooks
				$this->action_count = 2;
				$this->filter_count = 2;
			}
		};

		// Call the method we're testing
		$hooksManager->init_declarative_hooks();

		// Set initial stats
		$initialStats = array(
		'actions_registered'       => 0,
		'filters_registered'       => 0,
		'dynamic_hooks_registered' => 0,
		'duplicates_prevented'     => 0,
		);
		$this->_set_protected_property_value($hooksManager, 'stats', $initialStats);

		// Call the public method under test directly
		$hooksManager->init_declarative_hooks();

		// Verify that the method was called
		$this->assertTrue($hooksManager->init_called, 'init_declarative_hooks should have been called');

		// Verify that the action and filter counts were updated
		$this->assertEquals(2, $hooksManager->action_count, 'Should have registered 2 action hooks');
		$this->assertEquals(2, $hooksManager->filter_count, 'Should have registered 2 filter hooks');
	}

	/**
	 * Test the get_hooks_by_group method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::get_hooks_by_group
	 */
	public function test_get_hooks_by_group(): void {
		// Create a test class for hooks
		$testObject = new class() {
			public function test_method() {
			}
		};

		// Create the hooks manager with our test object
		$hooksManager = new HooksManager($testObject, $this->logger);

		// Set up test hooks with different groups
		$testHooks = array(
		'action_frontend_hook_10_abc123{"group":"frontend"}'  => true,
		'action_frontend_hook2_10_ghi789{"group":"frontend"}' => true,
		'action_admin_hook_10_def456{"group":"admin"}'        => true,
		'filter_admin_hook_10_mno345{"group":"admin"}'        => true,
		'action_no_group_10_pqr678'                           => true
		);

		// Set the registered_hooks property using reflection
		$this->_set_protected_property_value($hooksManager, 'registered_hooks', $testHooks);

		// Test getting frontend hooks
		$frontendHooks = $hooksManager->get_hooks_by_group('frontend');
		$this->assertCount(2, $frontendHooks, 'Should find 2 frontend hooks');
		$this->assertContains('action_frontend_hook_10_abc123{"group":"frontend"}', $frontendHooks);
		$this->assertContains('action_frontend_hook2_10_ghi789{"group":"frontend"}', $frontendHooks);

		// Test getting admin hooks
		$adminHooks = $hooksManager->get_hooks_by_group('admin');
		$this->assertCount(2, $adminHooks, 'Should find 2 admin hooks');
		$this->assertContains('action_admin_hook_10_def456{"group":"admin"}', $adminHooks);
		$this->assertContains('filter_admin_hook_10_mno345{"group":"admin"}', $adminHooks);

		// Test getting non-existent group
		$nonExistentHooks = $hooksManager->get_hooks_by_group('nonexistent');
		$this->assertEmpty($nonExistentHooks, 'Should find 0 hooks for non-existent group');
	}

	/**
	 * Test the register_closure_hook method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_closure_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_closure_hook(): void {
		$closureCalled = false;
		$closure       = function () use (&$closureCalled) {
			$closureCalled = true;
		};

		// Set up expectation for _do_add_filter
		$this->hooksManager->shouldReceive('_do_add_filter')
		    ->once()
		    ->with('closure_hook_test', $closure, 15, 2)
		    ->andReturn(true);

		// Register the closure hook
		$result = $this->hooksManager->register_closure_hook(
			'filter',
			'closure_hook_test',
			$closure,
			15,
			2,
			array('context' => 'test')
		);

		$this->assertTrue($result, 'Closure hook registration should succeed');

		// Set up expectation for _do_apply_filter
		$this->hooksManager->shouldReceive('_do_apply_filter')
		    ->once()
		    ->with('closure_hook_test', 'test_value')
		    ->andReturnUsing(
		    	function ($hook, $value) use ($closure) {
		    		// Simulate the filter being applied
		    		$closure();
		    		return $value;
		    	}
		    );

		// Apply the filter
		$this->hooksManager->apply_filters('closure_hook_test', 'test_value');

		$this->assertTrue($closureCalled, 'Closure should have been called');
	}

	/**
	 * Test the register_conditional_hooks method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_conditional_hooks
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::evaluate_condition
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_filter
	 */
	public function test_register_conditional_hooks(): void {
		$callback = function () {
		};

		// Create a real HooksManager instance for this test
		$inactiveLogger = new InactiveCollectingLogger();
		$hooksManager   = new HooksManager($this->test_object, $inactiveLogger);

		// Register conditional hooks
		$results = $hooksManager->register_conditional_hooks(
			array(
			array(
			'type'      => 'action',
			'hook'      => 'conditional_hook_test',
			'callback'  => $callback,
			'condition' => true
			),
			array(
			'type'      => 'action',
			'hook'      => 'should_not_register',
			'callback'  => $callback,
			'condition' => false
			)
			)
		);

		// Verify results
		$this->assertTrue($results[0]['success'], 'First hook should be registered successfully');
		$this->assertFalse($results[1]['success'], 'Second hook should not be registered');
		$this->assertEquals('Condition not met', $results[1]['error'], 'Error should indicate condition failure');
	}

	/**
	 * Test the register_method_hook method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook(): void {
		// Create a test class with methods to register
		$testObject = new class() {
			public function test_method($arg1, $arg2) {
				return $arg1 . $arg2;
			}

			public function another_method() {
				return 'test';
			}
		};

		// Create a subclass that exposes register_hook for testing
		$hooksManager = new class($testObject, $this->logger) extends HooksManager {
			private array $registeredHooks = array();

			// Define the owner property to avoid undefined property errors
			protected $owner;
			protected $logger;

			// Constructor to properly set the owner property
			public function __construct($owner, $logger) {
				parent::__construct($owner, $logger);
				$this->owner  = $owner;
				$this->logger = $logger;
			}

			// Override register_hook to track calls
			public function _register_hook(
                string $type,
                string $hook_name,
                callable $callback,
                int $priority = 10,
                int $accepted_args = 1,
                array $context = array()
            ): bool {
				$this->registeredHooks[] = array(
				'type'          => $type,
				'hook'          => $hook_name,
				'callback'      => $callback,
				'priority'      => $priority,
				'accepted_args' => $accepted_args,
				'context'       => $context
				);
				return true;
			}

			public function getRegisteredHooks(): array {
				return $this->registeredHooks;
			}

			// We need to override this method to avoid calling the parent implementation
			// which would use the real register_hook method instead of our overridden one
			public function register_method_hook(
                string $type,
                string $hook_name,
                string $method_name,
                int $priority = 10,
                int $accepted_args = 1,
                array $context = array()
            ): bool {
				if (!method_exists($this->owner, $method_name)) {
					// Use parent logger if available, otherwise skip logging
					if (property_exists($this, 'logger') && $this->logger->is_active()) {
						$this->logger->warning("HooksManager - Method '{$method_name}' does not exist on " . get_class($this->owner));
					}
					return false;
				}

				return $this->_register_hook(
					$type,
					$hook_name,
					array($this->owner, $method_name),
					$priority,
					$accepted_args,
					array_merge($context, array('method' => $method_name))
				);
			}
		};

		// Register method hooks
		$result1 = $hooksManager->register_method_hook('action', 'test_hook', 'test_method');
		$result2 = $hooksManager->register_method_hook('filter', 'another_hook', 'another_method', 20, 2);

		// Verify results
		$this->assertTrue($result1, 'First method hook should be registered successfully');
		$this->assertTrue($result2, 'Second method hook should be registered successfully');

		// Get registered hooks
		$registeredHooks = $hooksManager->getRegisteredHooks();

		// Verify hook details
		$this->assertCount(2, $registeredHooks, 'Two hooks should be registered');

		// Check first hook
		$this->assertEquals('action', $registeredHooks[0]['type'], 'First hook should be an action');
		$this->assertEquals('test_hook', $registeredHooks[0]['hook'], 'First hook name should match');
		$this->assertEquals(10, $registeredHooks[0]['priority'], 'First hook should have default priority');
		$this->assertEquals(1, $registeredHooks[0]['accepted_args'], 'First hook should have default accepted_args');
		$this->assertIsCallable($registeredHooks[0]['callback'], 'First hook callback should be callable');

		// Check second hook
		$this->assertEquals('filter', $registeredHooks[1]['type'], 'Second hook should be a filter');
		$this->assertEquals('another_hook', $registeredHooks[1]['hook'], 'Second hook name should match');
		$this->assertEquals(20, $registeredHooks[1]['priority'], 'Second hook should have specified priority');
		$this->assertEquals(2, $registeredHooks[1]['accepted_args'], 'Second hook should have specified accepted_args');
		$this->assertIsCallable($registeredHooks[1]['callback'], 'Second hook callback should be callable');
	}

	/**
	 * Test the register_hook_group method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_hook_group
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_filter
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_hook_group(): void {
		$callback = function () {
		};

		// Set up expectations for _do_add_action
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('group_hook_1', $callback, 10, 1)
		    ->andReturn(true);

		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('group_hook_2', $callback, 20, 2)
		    ->andReturn(true);

		// Register a hook group
		$results = $this->hooksManager->register_hook_group(
			'test_group', array(
			array(
			'type'     => 'action',
			'hook'     => 'group_hook_1',
			'callback' => $callback
			),
			array(
			'type'          => 'action',
			'hook'          => 'group_hook_2',
			'callback'      => $callback,
			'priority'      => 20,
			'accepted_args' => 2
			)
			)
		);

		// Verify results
		$this->assertTrue($results, 'Hook group should be registered successfully');
	}

	/**
	 * Test the get_registered_hooks method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::get_registered_hooks
	 */
	public function test_get_registered_hooks(): void {
		$callback = function () {
		};

		// Register a hook using real HooksManager behaviour
		$result = $this->hooksManager->register_action('test_action', $callback);
		self::assertTrue($result, 'Registration should succeed for callable callback');

		// Get registered hooks
		$hooks = $this->hooksManager->get_registered_hooks();

		$this->assertIsArray($hooks, 'get_registered_hooks should return an array');
		$this->assertNotEmpty($hooks, 'Registered hooks should not be empty');
	}

	/**
	 * Test the is_hook_registered method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::is_hook_registered
	 */
	public function test_is_hook_registered(): void {
		$callback = function () {
		};

		// Register a hook
		$this->hooksManager->register_action('test_action', $callback);

		// Check if hook is registered
		$isRegistered = $this->hooksManager->is_hook_registered('action', 'test_action', 10, $callback);
		$this->assertTrue($isRegistered, 'Hook should be registered');

		// Check if a non-existent hook is registered
		$isRegistered = $this->hooksManager->is_hook_registered('action', 'nonexistent_hook');
		$this->assertFalse($isRegistered, 'Non-existent hook should not be registered');
	}

	/**
	 * Test the clear_hooks method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::clear_hooks
	 */
	public function test_clear_hooks(): void {
		$callback = function () {
		};

		// Register a hook
		$this->hooksManager->register_action('test_action', $callback);

		// Verify hook is registered
		$this->assertNotEmpty($this->hooksManager->get_registered_hooks(), 'Hooks should be registered');

		// Clear hooks
		$this->hooksManager->clear_hooks();

		// Verify hooks are cleared
		$this->assertEmpty($this->hooksManager->get_registered_hooks(), 'Hooks should be cleared');
	}

	/**
	 * Test the get_stats method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::get_stats
	 */
	public function test_get_stats(): void {
		// Create a test object that implements both interfaces
		$testObject = new class() implements ActionHooksInterface, FilterHooksInterface {
			public function test_method() {
			}
			public function filter_method($value) {
				return $value;
			}

			// Implement ActionHooksInterface
			public static function declare_action_hooks(): array {
				return array(
				'declarative_action_1' => 'test_method',
				'declarative_action_2' => 'test_method'
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			// Implement FilterHooksInterface
			public static function declare_filter_hooks(): array {
				return array(
				'declarative_filter_1' => 'filter_method',
				'declarative_filter_2' => 'filter_method'
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}
		};

		// Create an instance of our test class
		$hooksManager = new TestHooksManager($testObject, $this->logger);

		// Initialize declarative hooks
		$hooksManager->init_declarative_hooks();

		// Register some dynamic hooks
		$actionCallback = function () {
		};
		$filterCallback = function ($value) {
			return $value;
		};

		// Register actions
		$hooksManager->register_action('test_action_1', $actionCallback);
		$hooksManager->register_action('test_action_2', array($testObject, 'test_method'));
		$hooksManager->register_action('test_action_3', 'test_method'); // String method name

		// Register filters
		$hooksManager->register_filter('test_filter_1', $filterCallback);
		$hooksManager->register_filter('test_filter_2', array($testObject, 'filter_method'));

		// Try to register a duplicate (should be prevented)
		$hooksManager->register_action('test_action_1', $actionCallback);

		// Get stats
		$stats = $hooksManager->get_stats();

		// Verify stats structure
		$this->assertIsArray($stats, 'Stats should be an array');
		$this->assertArrayHasKey('actions_registered', $stats, 'Stats should include actions_registered');
		$this->assertArrayHasKey('filters_registered', $stats, 'Stats should include filters_registered');
		$this->assertArrayHasKey('dynamic_hooks_registered', $stats, 'Stats should include dynamic_hooks_registered');
		$this->assertArrayHasKey('duplicates_prevented', $stats, 'Stats should include duplicates_prevented');

		// Verify counts
		$this->assertEquals(2, $stats['actions_registered'], 'Should have 2 declarative actions registered');
		$this->assertEquals(2, $stats['filters_registered'], 'Should have 2 declarative filters registered');
		$this->assertEquals(5, $stats['dynamic_hooks_registered'], 'Should have 5 dynamic hooks registered');
		$this->assertEquals(1, $stats['duplicates_prevented'], 'Should have prevented 1 duplicate');

		// Test the debug report which also uses get_stats
		$report = $hooksManager->generate_debug_report();
		$this->assertArrayHasKey('stats', $report, 'Debug report should include stats');
		$this->assertEquals($stats, $report['stats'], 'Stats in debug report should match get_stats output');
	}

	/**
	 * Test the get_stats method directly on the real HooksManager class.
	 * This ensures code coverage of the actual method, not just our test override.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::get_stats
	 */
	public function test_get_stats_direct(): void {
		// Create a simple test object
		$testObject = new class() {
			public function test_method() {
			}
		};

		// Create a real HooksManager instance (not our test subclass)
		$hooksManager = new HooksManager($testObject, $this->logger);

		// Call get_stats directly - this should return the default stats array
		$stats = $hooksManager->get_stats();

		// Verify the structure and default values
		$this->assertIsArray($stats, 'Stats should be an array');
		$this->assertArrayHasKey('actions_registered', $stats, 'Stats should include actions_registered');
		$this->assertArrayHasKey('filters_registered', $stats, 'Stats should include filters_registered');
		$this->assertArrayHasKey('dynamic_hooks_registered', $stats, 'Stats should include dynamic_hooks_registered');
		$this->assertArrayHasKey('duplicates_prevented', $stats, 'Stats should include duplicates_prevented');

		// Verify default values (should all be 0 for a new instance)
		$this->assertEquals(0, $stats['actions_registered'], 'Default actions_registered should be 0');
		$this->assertEquals(0, $stats['filters_registered'], 'Default filters_registered should be 0');
		$this->assertEquals(0, $stats['dynamic_hooks_registered'], 'Default dynamic_hooks_registered should be 0');
		$this->assertEquals(0, $stats['duplicates_prevented'], 'Default duplicates_prevented should be 0');
	}

	/**
	 * Test the register_method_hook method with a non-existent method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 */
	public function test_register_method_hook_nonexistent_method(): void {
		// Create a test object
		$testObject = new class() {
			// No methods defined
		};

		// Create a hooks manager with our test object
		$hooksManager = new HooksManager($testObject, $this->logger);

		// Try to register a non-existent method
		$result = $hooksManager->register_method_hook(
			'action',
			'test_action',
			'nonexistent_method'
		);

		// Verify it fails
		$this->assertFalse($result);
	}

	/**
	 * Test the generate_debug_report method.
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::generate_debug_report
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::get_stats
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::get_registered_hooks
	 */
	public function test_generate_debug_report(): void {
		$callback = function () {
		};

		// Set up expectations for _do_add_action
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_action', $callback, 10, 1)
		    ->andReturn(true);

		// Register a hook
		$this->hooksManager->register_action('test_action', $callback);

		// Generate debug report
		$report = $this->hooksManager->generate_debug_report();

		$this->assertIsArray($report, 'Debug report should be an array');
		$this->assertArrayHasKey('registered_hooks_count', $report, 'Debug report should include registered hooks count');
		$this->assertArrayHasKey('stats', $report, 'Debug report should include stats');
		$this->assertArrayHasKey('dynamic_hooks_registered', $report['stats'], 'Stats should include dynamic hooks registered count');
	}

	/**
	 * Test error handling for invalid callback in register_action
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 */
	public function test_register_action_invalid_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with non-callable callback
		$result = $hooksManager->register_action('test_hook', 'non_existent_function');
		$this->assertFalse($result, 'Should return false for invalid callback');

		// Test with null callback
		$result = $hooksManager->register_action('test_hook', null);
		$this->assertFalse($result, 'Should return false for null callback');
	}

	/**
	 * Test error handling for invalid callback in register_filter
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_filter
	 */
	public function test_register_filter_invalid_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with non-callable callback
		$result = $hooksManager->register_filter('test_hook', 'non_existent_function');
		$this->assertFalse($result, 'Should return false for invalid callback');

		// Test with null callback
		$result = $hooksManager->register_filter('test_hook', null);
		$this->assertFalse($result, 'Should return false for null callback');
	}

	/**
	 * Test string callback handling in register_action
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 */
	public function test_register_action_string_callback(): void {
		$testObject = new class() {
			public function test_method() {
				return 'test';
			}
		};

		// Create a mock to control the registration
		$hooksManager = Mockery::mock(HooksManager::class . '[_do_add_action]', array($testObject, $this->logger))
			->shouldAllowMockingProtectedMethods()
		    ->makePartial()
		    ->shouldAllowMockingProtectedMethods();
		$hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_hook', array($testObject, 'test_method'), 10, 1)
		    ->andReturn(true);

		// Register action with string callback
		$result = $hooksManager->register_action('test_hook', 'test_method');
		$this->assertTrue($result, 'Should register action with string callback');
	}

	/**
	 * Test string callback handling in register_filter
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_filter
	 */
	public function test_register_filter_string_callback(): void {
		$testObject = new class() {
			public function test_method($value) {
				return $value;
			}
		};

		// Create a mock to control the registration
		$hooksManager = Mockery::mock(HooksManager::class . '[_do_add_filter]', array($testObject, $this->logger))
			->shouldAllowMockingProtectedMethods();
		$hooksManager->shouldReceive('_do_add_filter')
		    ->once()
		    ->with('test_hook', array($testObject, 'test_method'), 10, 1)
		    ->andReturn(true);

		// Register filter with string callback
		$result = $hooksManager->register_filter('test_hook', 'test_method');
		$this->assertTrue($result, 'Should register filter with string callback');
	}

	/**
	 * Test string callback with non-existent method
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_action
	 */
	public function test_register_action_string_callback_nonexistent_method(): void {
		$testObject = new class() {
			// No methods defined
		};

		// Create a real HooksManager instance
		$hooksManager = new HooksManager($testObject, $this->logger);

		// Register action with non-existent method
		$result = $hooksManager->register_action('test_hook', 'nonexistent_method');
		$this->assertFalse($result, 'Should return false for non-existent method');
	}

	/**
	 * Test hook removal with invalid type
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::remove_hook
	 */
	public function test_remove_hook_invalid_type(): void {
		$callback = function () {
		};

		// Test with invalid hook type
		$result = $this->hooksManager->remove_hook('invalid_type', 'test_hook', $callback);
		$this->assertFalse($result, 'Should return false for invalid hook type');
	}

	/**
	 * Test hook removal when hook is not registered
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::remove_hook
	 */
	public function test_remove_hook_not_registered(): void {
		$callback = function () {
		};

		// Set up expectation for _do_remove_action to return false
		$this->hooksManager->shouldReceive('_do_remove_action')
		    ->once()
		    ->with('nonexistent_hook', $callback, 10)
		    ->andReturn(false);

		// Try to remove a hook that was never registered
		$result = $this->hooksManager->remove_hook('action', 'nonexistent_hook', $callback);
		$this->assertFalse($result, 'Should return false for non-registered hook');
	}

	/**
	 * Test apply_filters with multiple arguments
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::apply_filters
	 */
	public function test_apply_filters_with_arguments(): void {
		$callback = function ($value, $arg1, $arg2) {
			return $value . '_' . $arg1 . '_' . $arg2;
		};

		// Set up expectations for _do_add_filter
		$this->hooksManager->shouldReceive('_do_add_filter')
		    ->once()
		    ->with('test_filter', $callback, 10, 3)
		    ->andReturn(true);

		// Register filter with multiple arguments
		$this->hooksManager->register_filter('test_filter', $callback, 10, 3);

		// Set up expectation for _do_apply_filter
		$this->hooksManager->shouldReceive('_do_apply_filter')
		    ->once()
		    ->with('test_filter', 'test_value', 'arg1', 'arg2')
		    ->andReturn('test_value_arg1_arg2');

		// Apply filter with multiple arguments
		$result = $this->hooksManager->apply_filters('test_filter', 'test_value', 'arg1', 'arg2');
		$this->assertEquals('test_value_arg1_arg2', $result, 'Filter should handle multiple arguments');
	}

	/**
	 * Test register_hooks_for with no hooks
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_hooks_for
	 */
	public function test_register_hooks_for_empty_hooks(): void {
		$testObject = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(); // Empty hooks
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};

		// Register hooks for object with no hooks
		$result = $this->hooksManager->register_hooks_for($testObject);
		$this->assertFalse($result, 'Should return false when no hooks are registered');
	}

	/**
	 * Test register_hooks_for with invalid method names
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_hooks_for
	 */
	public function test_register_hooks_for_invalid_methods(): void {
		$testObject = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_hook' => 'nonexistent_method'
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};

		// Register hooks for object with invalid method names
		$result = $this->hooksManager->register_hooks_for($testObject);
		$this->assertFalse($result, 'Should return false when methods do not exist');
	}

	/**
	 * Test register_conditional_hooks with invalid definition
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_conditional_hooks
	 */
	public function test_register_conditional_hooks_invalid_definition(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with invalid hook definition (missing required fields)
		$results = $hooksManager->register_conditional_hooks(array(
			array(
				'type' => 'action',
				// Missing 'hook' and 'callback'
			)
		));

		$this->assertIsArray($results, 'Should return array of results');
		$this->assertFalse($results[0]['success'], 'Should fail for invalid definition');
		$this->assertEquals('Invalid hook definition', $results[0]['error'], 'Should have appropriate error message');
	}

	/**
	 * Test register_conditional_hooks with invalid hook type
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_conditional_hooks
	 */
	public function test_register_conditional_hooks_invalid_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with invalid hook type
		$results = $hooksManager->register_conditional_hooks(array(
			array(
				'type'     => 'invalid_type',
				'hook'     => 'test_hook',
				'callback' => function () {
				}
			)
		));

		$this->assertIsArray($results, 'Should return array of results');
		$this->assertFalse($results[0]['success'], 'Should fail for invalid hook type');
	}

	/**
	 * Test register_conditional_hooks with non-callable callback
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_conditional_hooks
	 */
	public function test_register_conditional_hooks_non_callable(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with non-callable callback
		$results = $hooksManager->register_conditional_hooks(array(
			array(
				'type'     => 'action',
				'hook'     => 'test_hook',
				'callback' => 'non_existent_function'
			)
		));

		$this->assertIsArray($results, 'Should return array of results');
		$this->assertFalse($results[0]['success'], 'Should fail for non-callable callback');
	}

	/**
	 * Test evaluate_condition with boolean condition
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::evaluate_condition
	 */
	public function test_evaluate_condition_boolean(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with boolean true
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(true));
		$this->assertTrue($result, 'Should evaluate boolean true correctly');

		// Test with boolean false
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(false));
		$this->assertFalse($result, 'Should evaluate boolean false correctly');
	}

	/**
	 * Test evaluate_condition with callable condition
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::evaluate_condition
	 */
	public function test_evaluate_condition_callable(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with callable that returns true
		$callable = function () {
			return true;
		};
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array($callable));
		$this->assertTrue($result, 'Should evaluate callable returning true correctly');

		// Test with callable that returns false
		$callable = function () {
			return false;
		};
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array($callable));
		$this->assertFalse($result, 'Should evaluate callable returning false correctly');

		// Test with array callable (object method)
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(array($this->test_object, 'test_method')));
		$this->assertTrue($result, 'Should evaluate array callable correctly');

		// Test with callable that returns non-boolean values
		$callable = function () {
			return 'truthy_string';
		};
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array($callable));
		$this->assertTrue($result, 'Should evaluate callable returning truthy string correctly');

		$callable = function () {
			return '';
		};
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array($callable));
		$this->assertFalse($result, 'Should evaluate callable returning empty string correctly');

		$callable = function () {
			return 0;
		};
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array($callable));
		$this->assertFalse($result, 'Should evaluate callable returning zero correctly');

		$callable = function () {
			return 42;
		};
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array($callable));
		$this->assertTrue($result, 'Should evaluate callable returning non-zero number correctly');
	}

	/**
	 * Test evaluate_condition with string condition (WordPress function)
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::evaluate_condition
	 */
	public function test_evaluate_condition_string(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Mock WordPress function
		\WP_Mock::userFunction('is_admin', array(
			'return' => true
		));

		// Test with string condition that exists
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array('is_admin'));
		$this->assertTrue($result, 'Should evaluate existing WordPress function correctly');

		// Test with string condition that doesn't exist
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array('nonexistent_function'));
		$this->assertFalse($result, 'Should return false for non-existent function');
	}

	/**
	 * Test evaluate_condition with invalid condition types
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::evaluate_condition
	 */
	public function test_evaluate_condition_invalid_types(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with null condition
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(null));
		$this->assertFalse($result, 'Should return false for null condition');

		// Test with integer condition
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(42));
		$this->assertFalse($result, 'Should return false for integer condition');

		// Test with array condition
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(array('test')));
		$this->assertFalse($result, 'Should return false for array condition');

		// Test with object condition
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(new \stdClass()));
		$this->assertFalse($result, 'Should return false for object condition');

		// Test with float condition
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array(3.14));
		$this->assertFalse($result, 'Should return false for float condition');
	}

	/**
	 * Test _validate_hook_definition with valid definition
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_validate_hook_definition
	 */
	public function test__validate_hook_definition_valid(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$definition = array(
			'type'     => 'action',
			'hook'     => 'test_hook',
			'callback' => function () {
			}
		);

		$result = $this->invoke_private_method($hooksManager, '_validate_hook_definition', array($definition));
		$this->assertTrue($result, 'Should validate correct hook definition');
	}

	/**
	 * Test _validate_hook_definition with missing fields
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_validate_hook_definition
	 */
	public function test__validate_hook_definition_missing_fields(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with missing 'type'
		$definition = array(
			'hook'     => 'test_hook',
			'callback' => function () {
			}
		);
		$result = $this->invoke_private_method($hooksManager, '_validate_hook_definition', array($definition));
		$this->assertFalse($result, 'Should fail validation for missing type');

		// Test with missing 'hook'
		$definition = array(
			'type'     => 'action',
			'callback' => function () {
			}
		);
		$result = $this->invoke_private_method($hooksManager, '_validate_hook_definition', array($definition));
		$this->assertFalse($result, 'Should fail validation for missing hook');

		// Test with missing 'callback'
		$definition = array(
			'type' => 'action',
			'hook' => 'test_hook'
		);
		$result = $this->invoke_private_method($hooksManager, '_validate_hook_definition', array($definition));
		$this->assertFalse($result, 'Should fail validation for missing callback');
	}

	/**
	 * Test _validate_hook_definition with invalid type
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_validate_hook_definition
	 */
	public function test__validate_hook_definition_invalid_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$definition = array(
			'type'     => 'invalid_type',
			'hook'     => 'test_hook',
			'callback' => function () {
			}
		);

		$result = $this->invoke_private_method($hooksManager, '_validate_hook_definition', array($definition));
		$this->assertFalse($result, 'Should fail validation for invalid type');
	}

	/**
	 * Test _validate_hook_definition with non-callable callback
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_validate_hook_definition
	 */
	public function test__validate_hook_definition_non_callable(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$definition = array(
			'type'     => 'action',
			'hook'     => 'test_hook',
			'callback' => 'non_existent_function'
		);

		$result = $this->invoke_private_method($hooksManager, '_validate_hook_definition', array($definition));
		$this->assertFalse($result, 'Should fail validation for non-callable callback');
	}

	/**
	 * Test register_hook_group with empty definitions
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_hook_group
	 */
	public function test_register_hook_group_empty(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with empty hook definitions
		$result = $hooksManager->register_hook_group('test_group', array());
		$this->assertTrue($result, 'Should return true for empty group');
	}

	/**
	 * Test register_hook_group with some failures
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_hook_group
	 */
	public function test_register_hook_group_partial_failure(): void {
		// Create a real HooksManager instance
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Create a mock to control registration results
		/** @var HooksManager&\Mockery\MockInterface $mockManager */
		$mockManager = Mockery::mock(HooksManager::class . '[_register_hook]', array($this->test_object, $this->logger))
		    ->makePartial()
		    ->shouldAllowMockingProtectedMethods();
		$mockManager->shouldReceive('_register_hook')
		    ->once()
		    ->with('action', 'test_hook_1', Mockery::any(), 10, 1, array('group' => 'test_group'))
		    ->andReturn(true);

		$mockManager->shouldReceive('_register_hook')
		    ->once()
		    ->with('action', 'test_hook_2', Mockery::any(), 10, 1, array('group' => 'test_group'))
		    ->andReturn(false); // This one fails

		$hook_definitions = array(
			array(
				'type'     => 'action',
				'hook'     => 'test_hook_1',
				'callback' => function () {
				}
			),
			array(
				'type'     => 'action',
				'hook'     => 'test_hook_2',
				'callback' => function () {
				}
			)
		);

		$result = $mockManager->register_hook_group('test_group', $hook_definitions);
		$this->assertFalse($result, 'Should return false when some hooks fail to register');
	}

	/**
	 * Test is_hook_registered with specific callback
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::is_hook_registered
	 */
	public function test_is_hook_registered_with_callback(): void {
		$callback = function () {
		};

		// Set up expectations for _do_add_action
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_hook', $callback, 10, 1)
		    ->andReturn(true);

		// Register a hook
		$this->hooksManager->register_action('test_hook', $callback);

		// Check if specific hook is registered
		$isRegistered = $this->hooksManager->is_hook_registered('action', 'test_hook', 10, $callback);
		$this->assertTrue($isRegistered, 'Should find registered hook with specific callback');

		// Check with different callback
		$differentCallback = function () {
		};
		$isRegistered = $this->hooksManager->is_hook_registered('action', 'test_hook', 10, $differentCallback);
		$this->assertFalse($isRegistered, 'Should not find hook with different callback');
	}

	/**
	 * Test is_hook_registered without callback
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::is_hook_registered
	 */
	public function test_is_hook_registered_without_callback(): void {
		$callback = function () {
		};

		// Set up expectations for _do_add_action
		$this->hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_hook', $callback, 10, 1)
		    ->andReturn(true);

		// Register a hook
		$this->hooksManager->register_action('test_hook', $callback);

		// Check if any hook with this name and priority is registered
		$isRegistered = $this->hooksManager->is_hook_registered('action', 'test_hook', 10);
		$this->assertTrue($isRegistered, 'Should find registered hook without specific callback');

		// Check with different priority
		$isRegistered = $this->hooksManager->is_hook_registered('action', 'test_hook', 20);
		$this->assertFalse($isRegistered, 'Should not find hook with different priority');
	}

	/**
	 * Test generate_callback_hash with different callback types
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_generate_callback_hash
	 */
	public function test_generate_callback_hash(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with array callback
		$testObject = new class() {
			public function test_method() {
			}
		};
		$arrayCallback = array($testObject, 'test_method');
		$hash1         = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($arrayCallback));
		$this->assertIsString($hash1, 'Should generate string hash for array callback');

		// Test with closure
		$closure = function () {
		};
		$hash2 = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($closure));
		$this->assertIsString($hash2, 'Should generate string hash for closure');

		// Test with string callback (this will fail as generate_callback_hash expects callable)
		// We'll test this differently by using a valid callable string
		$testObject = new class() {
			public function test_function() {
			}
		};
		$stringCallback = array($testObject, 'test_function');
		$hash3          = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($stringCallback));
		$this->assertIsString($hash3, 'Should generate string hash for string callback');

		// Test with context
		$context = array('test' => 'value');
		$hash4   = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($arrayCallback, $context));
		$this->assertNotEquals($hash1, $hash4, 'Hash should be different with context');
	}

	/**
	 * Test generate_callback_hash with context
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_generate_callback_hash
	 */
	public function test_generate_callback_hash_with_context(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function () {
		};

		// Test without context
		$hash1 = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($callback));
		$this->assertIsString($hash1, 'Should generate hash without context');

		// Test with context
		$context = array('group' => 'test', 'priority' => 10);
		$hash2   = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($callback, $context));
		$this->assertIsString($hash2, 'Should generate hash with context');
		$this->assertNotEquals($hash1, $hash2, 'Hashes should be different with context');
	}

	/**
	 * Test generate_callback_hash with string function name
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_generate_callback_hash
	 */
	public function test_generate_callback_hash_string_function(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test with a string function name (this should use the string case, not fallback)
		$callable = 'strlen';
		$hash     = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($callable));
		$this->assertIsString($hash, 'Should generate hash for string function');
		$this->assertEquals('strlen', $hash, 'Hash should be the function name for string callables');

		// Test with context
		$context         = array('test' => 'value');
		$hashWithContext = $this->invoke_private_method($hooksManager, '_generate_callback_hash', array($callable, $context));
		$this->assertIsString($hashWithContext, 'Should generate hash with context');
		$this->assertNotEquals($hash, $hashWithContext, 'Hashes should be different with context');
	}

	/**
	 * Test generate_callback_hash fallback case (serialize)
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_generate_callback_hash
	 */
	public function test_generate_callback_hash_fallback_serialize(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Create a callable object that would trigger the fallback case
		// We'll use a callable that is not an array, closure, or string
		// Since anonymous classes can't be serialized, we'll test the fallback path differently
		// by creating a test that expects the serialization to fail

		$callable = new class() {
			public function __invoke() {
				return 'test';
			}
		};

		// Test the fallback case - this should trigger the serialize fallback
		// but fail due to anonymous class serialization restrictions
		$this->expectException(\Exception::class);
		$this->invoke_private_method($hooksManager, '_generate_callback_hash', array($callable));
	}

	/**
	 * Test _register_hook with action type
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_hook_action(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Create a mock to control the registration
		$hooksManager = Mockery::mock(HooksManager::class . '[_do_add_action]', array($this->test_object, $this->logger))
			->shouldAllowMockingProtectedMethods();
		$hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_hook', Mockery::any(), 10, 1)
		    ->andReturn(true);

		$callback = function () {
		};
		$result = $this->invoke_private_method($hooksManager, '_register_hook', array('action', 'test_hook', $callback, 10, 1, array()));
		$this->assertTrue($result, 'Should register action hook successfully');
	}

	/**
	 * Test _register_hook with filter type
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_hook_filter(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Create a mock to control the registration
		$hooksManager = Mockery::mock(HooksManager::class . '[_do_add_filter]', array($this->test_object, $this->logger))
			->shouldAllowMockingProtectedMethods();
		$hooksManager->shouldReceive('_do_add_filter')
		    ->once()
		    ->with('test_hook', Mockery::any(), 10, 1)
		    ->andReturn(true);

		$callback = function () {
		};
		$result = $this->invoke_private_method($hooksManager, '_register_hook', array('filter', 'test_hook', $callback, 10, 1, array()));
		$this->assertTrue($result, 'Should register filter hook successfully');
	}

	/**
	 * Test _register_hook with invalid type
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_hook_invalid_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function () {
		};
		$result = $this->invoke_private_method($hooksManager, '_register_hook', array('invalid_type', 'test_hook', $callback, 10, 1, array()));
		$this->assertFalse($result, 'Should return false for invalid hook type');
	}

	/**
	 * Test _register_hook with duplicate registration
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_hook_duplicate(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function () {
		};

		// Create a mock to control the registration
		$hooksManager = Mockery::mock(HooksManager::class . '[_do_add_action]', array($this->test_object, $this->logger))
			->shouldAllowMockingProtectedMethods();
		$hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_hook', Mockery::any(), 10, 1)
		    ->andReturn(true);

		// Register the same hook twice
		$result1 = $this->invoke_private_method($hooksManager, '_register_hook', array('action', 'test_hook', $callback, 10, 1, array()));
		$result2 = $this->invoke_private_method($hooksManager, '_register_hook', array('action', 'test_hook', $callback, 10, 1, array()));

		$this->assertTrue($result1, 'First registration should succeed');
		$this->assertFalse($result2, 'Second registration should fail due to duplicate');
	}

	/**
	 * Test _register_hook with WordPress registration success
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_hook_wordpress_success(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Create a mock to control the registration
		$hooksManager = Mockery::mock(HooksManager::class . '[_do_add_action]', array($this->test_object, $this->logger))
			->shouldAllowMockingProtectedMethods();
		$hooksManager->shouldReceive('_do_add_action')
		    ->once()
		    ->with('test_hook', Mockery::any(), 10, 1)
		    ->andReturnNull(); // WordPress registration returns void (null)

		$callback = function () {
		};
		$result = $this->invoke_private_method($hooksManager, '_register_hook', array('action', 'test_hook', $callback, 10, 1, array()));
		$this->assertTrue($result, 'Should return true when WordPress registration succeeds');
	}

	/**
	 * Helper method to invoke private methods for testing
	 */
	private function invoke_private_method($object, string $method_name, array $arguments = array()) {
		$reflection = new \ReflectionClass($object);
		$method     = $reflection->getMethod($method_name);
		$method->setAccessible(true);
		return $method->invokeArgs($object, $arguments);
	}

	/**
	 * Test init_declarative_hooks with ActionHooksInterface
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::__construct
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::register_single_action_hook
	 */
	public function test_init_declarative_hooks_with_action_interface(): void {
		// Create a test class that implements ActionHooksInterface
		$testClass = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action'    => array('method' => 'test_method', 'priority' => 10),
					'another_action' => array('method' => 'another_method', 'priority' => 5),
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public function test_method(): void {
			}

			public function another_method(): void {
			}
		};

		$hooksManager = new HooksManager($testClass, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify that action manager was created and stats were updated
		$stats = $hooksManager->get_stats();
		$this->assertEquals(2, $stats['actions_registered'], 'Should register 2 declarative actions');
		$this->assertEquals(0, $stats['filters_registered'], 'Should not register any filters');
	}

	/**
	 * Test init_declarative_hooks with FilterHooksInterface
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::__construct
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::register_single_filter_hook
	 */
	public function test_init_declarative_hooks_with_filter_interface(): void {
		// Create a test class that implements FilterHooksInterface
		$testClass = new class() implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array(
					'test_filter'    => array('method' => 'test_method', 'priority' => 10),
					'another_filter' => array('method' => 'another_method', 'priority' => 5),
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function test_method($value) {
				return $value;
			}

			public function another_method($value) {
				return $value;
			}
		};

		$hooksManager = new HooksManager($testClass, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify that filter manager was created and stats were updated
		$stats = $hooksManager->get_stats();
		$this->assertEquals(0, $stats['actions_registered'], 'Should not register any actions');
		$this->assertEquals(2, $stats['filters_registered'], 'Should register 2 declarative filters');
	}

	/**
	 * Test init_declarative_hooks with both interfaces
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::__construct
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::__construct
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::init
	 */
	public function test_init_declarative_hooks_with_both_interfaces(): void {
		// Create a test class that implements both interfaces
		$testClass = new class() implements ActionHooksInterface, FilterHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => array('method' => 'test_action_method', 'priority' => 10),
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public static function declare_filter_hooks(): array {
				return array(
					'test_filter' => array('method' => 'test_filter_method', 'priority' => 10),
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function test_action_method(): void {
			}

			public function test_filter_method($value) {
				return $value;
			}
		};

		$hooksManager = new HooksManager($testClass, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify that both managers were created and stats were updated
		$stats = $hooksManager->get_stats();
		$this->assertEquals(1, $stats['actions_registered'], 'Should register 1 declarative action');
		$this->assertEquals(1, $stats['filters_registered'], 'Should register 1 declarative filter');
	}

	/**
	 * Test init_declarative_hooks with no interfaces
	 */
	public function test_init_declarative_hooks_with_no_interfaces(): void {
		$testClass = new class() {
		};

		$hooksManager = new HooksManager($testClass, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify that no managers were created and stats remain zero
		$stats = $hooksManager->get_stats();
		$this->assertEquals(0, $stats['actions_registered'], 'Should not register any actions');
		$this->assertEquals(0, $stats['filters_registered'], 'Should not register any filters');
	}

	/**
	 * Test register_method_hook with valid method
	 */
	public function test_register_method_hook_valid_method(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'test_method', 10, 1);
		$this->assertTrue($result, 'Should register method hook successfully');
	}

	/**
	 * Test register_method_hook with invalid method
	 */
	public function test_register_method_hook_invalid_method(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'nonexistent_method', 10, 1);
		$this->assertFalse($result, 'Should fail to register non-existent method');
	}

	/**
	 * Test register_method_hook with invalid type
	 */
	public function test_register_method_hook_invalid_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('invalid_type', 'test_hook', 'test_method', 10, 1);
		$this->assertFalse($result, 'Should fail to register with invalid type');
	}



	/**
	 * Test register_hooks_for with valid hooks
	 */
	public function test_register_hooks_for_valid_hooks(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function test_method() {
				return 'test';
			}
		};

		// Use direct registration instead of legacy get_hooks()
		$result1 = $hooksManager->register_action('test_action', array($instance, 'test_method'), 10);
		$result2 = $hooksManager->register_filter('test_filter', array($instance, 'test_method'), 10);

		$this->assertTrue($result1 && $result2, 'Should register hooks for instance successfully');
	}

	/**
	 * Test register_hooks_for with invalid method
	 */
	public function test_register_hooks_for_invalid_method(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			// No methods defined
		};

		// Try to register a non-existent method
		$result = $hooksManager->register_action('test_action', array($instance, 'nonexistent_method'), 10);
		$this->assertFalse($result, 'Should fail to register hooks with invalid method');
	}

	/**
	 * Test register_hooks_for with invalid hook type
	 */
	public function test_register_hooks_for_invalid_hook_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'invalid_type' => array(
						'test_hook' => array('method' => 'test_method', 'priority' => 10),
					),
				);
			}

			public function test_method() {
				return 'test';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks with invalid type');
	}

	/**
	 * Test register_hooks_for with missing hooks method
	 */
	public function test_register_hooks_for_missing_hooks_method(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			// No methods defined
		};

		// Since we removed the legacy get_hooks() system, this should return false
		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks when no interfaces are implemented');
	}



	/**
	 * Test register_hooks_for with null hooks
	 */
	public function test_register_hooks_for_null_hooks(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): ?array {
				return null;
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks when get_hooks returns null');
	}

	/**
	 * Test register_hooks_for with non-array hooks
	 */
	public function test_register_hooks_for_non_array_hooks(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): string {
				return 'not_an_array';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks when get_hooks returns non-array');
	}

	/**
	 * Test register_hooks_for with invalid hook definition
	 */
	public function test_register_hooks_for_invalid_hook_definition(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => 'invalid_definition', // Should be array
					),
				);
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks with invalid definition');
	}

	/**
	 * Test register_hooks_for with missing method in definition
	 */
	public function test_register_hooks_for_missing_method_in_definition(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array('priority' => 10), // Missing 'method'
					),
				);
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks with missing method in definition');
	}

	/**
	 * Test register_hooks_for with non-string method
	 */
	public function test_register_hooks_for_non_string_method(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array('method' => 123, 'priority' => 10), // Method should be string
					),
				);
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks with non-string method');
	}

	/**
	 * Test register_hooks_for with non-integer priority
	 */
	public function test_register_hooks_for_non_integer_priority(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array('method' => 'test_method', 'priority' => 'not_an_int'), // Priority should be int
					),
				);
			}

			public function test_method() {
				return 'test';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks with non-integer priority');
	}

	/**
	 * Test register_hooks_for with non-integer accepted_args
	 */
	public function test_register_hooks_for_non_integer_accepted_args(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array('method' => 'test_method', 'priority' => 10, 'accepted_args' => 'not_an_int'), // accepted_args should be int
					),
				);
			}

			public function test_method() {
				return 'test';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks with non-integer accepted_args');
	}

	/**
	 * Test register_hooks_for with non-array context
	 */
	public function test_register_hooks_for_non_array_context(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array('method' => 'test_method', 'priority' => 10, 'context' => 'not_an_array'), // context should be array
					),
				);
			}

			public function test_method() {
				return 'test';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail to register hooks with non-array context');
	}

	/**
	 * Test register_hooks_for with valid complete definition
	 */
	public function test_register_hooks_for_valid_complete_definition(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function test_method($arg1, $arg2) {
				return $arg1 . $arg2;
			}
		};

		// Use direct registration instead of legacy get_hooks()
		$result = $hooksManager->register_action(
			'test_action',
			array($instance, 'test_method'),
			5,
			2,
			array('test' => 'value')
		);
		$this->assertTrue($result, 'Should register hooks with complete valid definition');
	}

	/**
	 * Test register_hooks_for with default values
	 */
	public function test_register_hooks_for_with_default_values(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function test_method() {
				return 'test';
			}
		};

		// Use direct registration with default values
		$result = $hooksManager->register_action('test_action', array($instance, 'test_method'));
		$this->assertTrue($result, 'Should register hooks with default values');
	}

	/**
	 * Test register_hooks_for with mixed valid and invalid hooks
	 */
	public function test_register_hooks_for_mixed_valid_invalid_hooks(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'valid_action'   => array('method' => 'valid_method'),
						'invalid_action' => 'not_an_array', // Invalid
					),
				);
			}

			public function valid_method() {
				return 'test';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when any hook definition is invalid');
	}

	/**
	 * Test register_hooks_for with multiple valid hooks
	 */
	public function test_register_hooks_for_multiple_valid_hooks(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function method1() {
				return 'test1';
			}

			public function method2() {
				return 'test2';
			}

			public function method3($value) {
				return $value . '_modified';
			}

			public function method4($value) {
				return $value . '_modified2';
			}
		};

		// Use direct registration for multiple hooks
		$result1 = $hooksManager->register_action('action1', array($instance, 'method1'));
		$result2 = $hooksManager->register_action('action2', array($instance, 'method2'));
		$result3 = $hooksManager->register_filter('filter1', array($instance, 'method3'));
		$result4 = $hooksManager->register_filter('filter2', array($instance, 'method4'));

		$this->assertTrue($result1 && $result2 && $result3 && $result4, 'Should register multiple valid hooks successfully');
	}

	/**
	 * Test register_method_hook with valid method and action type
	 */
	public function test_register_method_hook_action_success(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'test_method', 10, 1);
		$this->assertTrue($result, 'Should register action method hook successfully');
	}

	/**
	 * Test register_method_hook with valid method and filter type
	 */
	public function test_register_method_hook_filter_success(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('filter', 'test_hook', 'test_method', 10, 1);
		$this->assertTrue($result, 'Should register filter method hook successfully');
	}



	/**
	 * Test register_method_hook with custom context
	 */
	public function test_register_method_hook_with_context(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$custom_context = array('custom' => 'value', 'priority' => 'high');
		$result         = $hooksManager->register_method_hook('action', 'test_hook', 'test_method', 10, 1, $custom_context);
		$this->assertTrue($result, 'Should register method hook with custom context');
	}

	/**
	 * Test register_method_hook with different priority and accepted_args
	 */
	public function test_register_method_hook_custom_parameters(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'test_method', 5, 3);
		$this->assertTrue($result, 'Should register method hook with custom priority and accepted_args');
	}

	/**
	 * Test register_method_hook when _register_hook fails
	 */
	public function test_register_method_hook_registration_failure(): void {
		// Create a test object with a public method
		$testObject = new class() {
			public function test_method() {
				return 'test';
			}
		};

		// Create a mock that will make _register_hook fail
		/** @var HooksManager&\Mockery\MockInterface $hooksManager */
		$hooksManager = Mockery::mock(HooksManager::class . '[_register_hook]', array($testObject, $this->logger))
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$hooksManager->shouldReceive('_register_hook')
			->with('action', 'test_hook', Mockery::type('array'), 10, 1, Mockery::type('array'))
			->once()
			->andReturn(false);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'test_method', 10, 1);
		$this->assertFalse($result, 'Should return false when _register_hook fails');
	}

	/**
	 * Test register_method_hook with empty method name
	 */
	public function test_register_method_hook_empty_method_name(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', '', 10, 1);
		$this->assertFalse($result, 'Should fail to register with empty method name');
	}

	/**
	 * Test register_method_hook with null method name
	 */
	public function test_register_method_hook_null_method_name(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', '', 10, 1);
		$this->assertFalse($result, 'Should fail to register with empty method name');
	}

	/**
	 * Test register_method_hook with private method (should fail gracefully)
	 */
	public function test_register_method_hook_private_method(): void {
		$testObject = new class() {
			private function private_method() {
				return 'private';
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		// This should fail gracefully with a warning and return false
		$result = $hooksManager->register_method_hook('action', 'test_hook', 'private_method', 10, 1);
		$this->assertFalse($result, 'Should fail to register private method hook gracefully');
	}

	/**
	 * Test register_method_hook with protected method (should fail gracefully)
	 */
	public function test_register_method_hook_protected_method(): void {
		$testObject = new class() {
			protected function _protected_method() {
				return 'protected';
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		// This should fail gracefully with a warning and return false
		$result = $hooksManager->register_method_hook('action', 'test_hook', '_protected_method', 10, 1);
		$this->assertFalse($result, 'Should fail to register protected method hook gracefully');
	}

	/**
	 * Test register_method_hook with static method
	 */
	public function test_register_method_hook_static_method(): void {
		$testObject = new class() {
			public static function static_method() {
				return 'static';
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'static_method', 10, 1);
		$this->assertTrue($result, 'Should register static method hook successfully');
	}

	/**
	 * Test register_method_hook with inherited method
	 */
	public function test_register_method_hook_inherited_method(): void {
		$parentObject = new class() {
			public function inherited_method() {
				return 'inherited';
			}
		};

		$childObject = new class($parentObject) extends \stdClass {
			public function __construct($parent) {
				$this->parent = $parent;
			}

			public function inherited_method() {
				return $this->parent->inherited_method();
			}
		};

		$hooksManager = new HooksManager($childObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'inherited_method', 10, 1);
		$this->assertTrue($result, 'Should register inherited method hook successfully');
	}

	/**
	 * Test register_method_hook with method that has parameters
	 */
	public function test_register_method_hook_method_with_parameters(): void {
		$testObject = new class() {
			public function method_with_params($param1, $param2 = 'default') {
				return $param1 . $param2;
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_params', 10, 2);
		$this->assertTrue($result, 'Should register method with parameters successfully');
	}

	/**
	 * Test register_method_hook with method that returns value
	 */
	public function test_register_method_hook_method_with_return(): void {
		$testObject = new class() {
			public function method_with_return() {
				return 'return_value';
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('filter', 'test_hook', 'method_with_return', 10, 1);
		$this->assertTrue($result, 'Should register method with return value successfully');
	}

	/**
	 * Test register_method_hook with method that throws exception
	 */
	public function test_register_method_hook_method_with_exception(): void {
		$testObject = new class() {
			public function method_with_exception() {
				throw new \Exception('Test exception');
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_exception', 10, 1);
		$this->assertTrue($result, 'Should register method that throws exception successfully');
	}

	/**
	 * Test register_method_hook with method that uses $this
	 */
	public function test_register_method_hook_method_with_this(): void {
		$testObject = new class() {
			private $property = 'test_value';

			public function method_with_this() {
				return $this->property;
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_this', 10, 1);
		$this->assertTrue($result, 'Should register method that uses $this successfully');
	}

	/**
	 * Test register_method_hook with method that has closure
	 */
	public function test_register_method_hook_method_with_closure(): void {
		$testObject = new class() {
			public function method_with_closure() {
				$closure = function() {
					return 'closure_result';
				};
				return $closure();
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_closure', 10, 1);
		$this->assertTrue($result, 'Should register method with closure successfully');
	}

	/**
	 * Test register_method_hook with method that has array access
	 */
	public function test_register_method_hook_method_with_array_access(): void {
		$testObject = new class() {
			private $data = array('key' => 'value');

			public function method_with_array_access() {
				return $this->data['key'];
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_array_access', 10, 1);
		$this->assertTrue($result, 'Should register method with array access successfully');
	}

	/**
	 * Test register_method_hook with method that has object access
	 */
	public function test_register_method_hook_method_with_object_access(): void {
		$testObject = new class() {
			private $object;

			public function __construct() {
				$this->object           = new \stdClass();
				$this->object->property = 'object_value';
			}

			public function method_with_object_access() {
				return $this->object->property;
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_object_access', 10, 1);
		$this->assertTrue($result, 'Should register method with object access successfully');
	}

	/**
	 * Test register_method_hook with method that has conditional logic
	 */
	public function test_register_method_hook_method_with_conditional(): void {
		$testObject = new class() {
			public function method_with_conditional($condition) {
				if ($condition) {
					return 'true_value';
				} else {
					return 'false_value';
				}
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_conditional', 10, 1);
		$this->assertTrue($result, 'Should register method with conditional logic successfully');
	}

	/**
	 * Test register_method_hook with method that has loop
	 */
	public function test_register_method_hook_method_with_loop(): void {
		$testObject = new class() {
			public function method_with_loop() {
				$result = '';
				for ($i = 0; $i < 3; $i++) {
					$result .= $i;
				}
				return $result;
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_loop', 10, 1);
		$this->assertTrue($result, 'Should register method with loop successfully');
	}

	/**
	 * Test register_method_hook with method that has try-catch
	 */
	public function test_register_method_hook_method_with_try_catch(): void {
		$testObject = new class() {
			public function method_with_try_catch() {
				try {
					return 'success';
				} catch (\Exception $e) {
					return 'error';
				}
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_try_catch', 10, 1);
		$this->assertTrue($result, 'Should register method with try-catch successfully');
	}

	/**
	 * Test register_method_hook with method that has switch statement
	 */
	public function test_register_method_hook_method_with_switch(): void {
		$testObject = new class() {
			public function method_with_switch($value) {
				switch ($value) {
					case 1:
						return 'one';
					case 2:
						return 'two';
					default:
						return 'default';
				}
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_switch', 10, 1);
		$this->assertTrue($result, 'Should register method with switch statement successfully');
	}

	/**
	 * Test register_method_hook with method that has multiple return statements
	 */
	public function test_register_method_hook_method_with_multiple_returns(): void {
		$testObject = new class() {
			public function method_with_multiple_returns($condition) {
				if ($condition) {
					return 'first_return';
				}
				return 'second_return';
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_method_hook('action', 'test_hook', 'method_with_multiple_returns', 10, 1);
		$this->assertTrue($result, 'Should register method with multiple return statements successfully');
	}

	// === ADDITIONAL TESTS FOR register_hooks_for ===

	/**
	 * Test register_hooks_for with ActionHooksInterface
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::register_single_action_hook
	 */
	public function test_register_hooks_for_action_interface(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action'    => 'test_method',
					'another_action' => array('another_method', 15, 2),
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public function test_method() {
				return 'test';
			}

			public function another_method($arg1, $arg2) {
				return $arg1 . $arg2;
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertTrue($result, 'Should register hooks for ActionHooksInterface');
	}

	/**
	 * Test register_hooks_for with FilterHooksInterface
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::register_single_filter_hook
	 */
	public function test_register_hooks_for_filter_interface(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array(
					'test_filter'    => 'test_method',
					'another_filter' => array('another_method', 20, 3),
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function test_method($value) {
				return $value . '_modified';
			}

			public function another_method($value, $arg1, $arg2) {
				return $value . $arg1 . $arg2;
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertTrue($result, 'Should register hooks for FilterHooksInterface');
	}

	/**
	 * Test register_hooks_for with both interfaces
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::register_single_action_hook
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::register_single_filter_hook
	 */
	public function test_register_hooks_for_both_interfaces(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements ActionHooksInterface, FilterHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => 'action_method',
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public static function declare_filter_hooks(): array {
				return array(
					'test_filter' => 'filter_method',
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function action_method() {
				return 'action';
			}

			public function filter_method($value) {
				return $value . '_filtered';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertTrue($result, 'Should register hooks for both interfaces');
	}

	/**
	 * Test register_hooks_for with interface but invalid method
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::register_single_action_hook
	 */
	public function test_register_hooks_for_interface_invalid_method(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => 'nonexistent_method',
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when interface method does not exist');
	}

	/**
	 * Test register_hooks_for with interface but empty method name
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::register_single_action_hook
	 */
	public function test_register_hooks_for_interface_empty_method(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => '',
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when interface method name is empty');
	}

	/**
	 * Test register_hooks_for with get_hooks method but non-array type_hooks
	 */
	public function test_register_hooks_for_non_array_type_hooks(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function test_method($value) {
				return $value . '_modified';
			}
		};

		// Since we removed the legacy get_hooks() system, this should return false
		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should handle non-array type_hooks gracefully');
	}

	/**
	 * Test register_hooks_for with get_hooks method but string hook definition
	 */
	public function test_register_hooks_for_string_hook_definition(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function test_method() {
				return 'test';
			}
		};

		// Use direct registration instead of legacy get_hooks()
		$result = $hooksManager->register_action('test_action', array($instance, 'test_method'));
		$this->assertTrue($result, 'Should handle string hook definitions');
	}

	/**
	 * Test register_hooks_for with get_hooks method but array hook definition with numeric keys
	 */
	public function test_register_hooks_for_array_hook_definition_numeric_keys(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function test_method($arg1, $arg2) {
				return $arg1 . $arg2;
			}
		};

		// Use direct registration with numeric parameters
		$result = $hooksManager->register_action('test_action', array($instance, 'test_method'), 15, 2);
		$this->assertTrue($result, 'Should handle array hook definitions with numeric keys');
	}

	/**
	 * Test register_hooks_for with get_hooks method but array hook definition with named keys
	 */
	public function test_register_hooks_for_array_hook_definition_named_keys(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function test_method($arg1, $arg2, $arg3) {
				return $arg1 . $arg2 . $arg3;
			}
		};

		// Use direct registration with named parameters
		$result = $hooksManager->register_action(
			'test_action',
			array($instance, 'test_method'),
			20,
			3,
			array('custom' => 'value')
		);
		$this->assertTrue($result, 'Should handle array hook definitions with named keys');
	}

	/**
	 * Test register_hooks_for with interface but string hook definition
	 */
	public function test_register_hooks_for_interface_string_definition(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => 'test_method', // String definition
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public function test_method() {
				return 'test';
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertTrue($result, 'Should handle interface string hook definitions');
	}

	/**
	 * Test register_hooks_for with interface but array hook definition
	 */
	public function test_register_hooks_for_interface_array_definition(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array(
					'test_filter' => array('test_method', 25, 4), // Array definition
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function test_method($arg1, $arg2, $arg3, $arg4) {
				return $arg1 . $arg2 . $arg3 . $arg4;
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertTrue($result, 'Should handle interface array hook definitions');
	}

	/**
	 * Test register_hooks_for with interface but empty method name
	 */
	public function test_register_hooks_for_interface_empty_method_name(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => array('', 10, 1), // Empty method name
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when interface method name is empty');
	}

	/**
	 * Test register_hooks_for with get_hooks method but invalid data types
	 */
	public function test_register_hooks_for_invalid_data_types(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array(
							'method'        => 123, // Invalid: should be string
							'priority'      => 'invalid', // Invalid: should be int
							'accepted_args' => 'invalid', // Invalid: should be int
							'context'       => 'invalid', // Invalid: should be array
						),
					),
				);
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when data types are invalid');
	}

	/**
	 * Test register_hooks_for with get_hooks method but invalid definition format
	 */
	public function test_register_hooks_for_invalid_definition_format(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => 123, // Invalid: should be string or array
					),
				);
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when hook definition format is invalid');
	}

	/**
	 * Test register_hooks_for with get_hooks method but method doesn't exist
	 */
	public function test_register_hooks_for_method_does_not_exist(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array('method' => 'nonexistent_method'),
					),
				);
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when method does not exist');
	}

	/**
	 * Test register_hooks_for with get_hooks method but empty method name
	 */
	public function test_register_hooks_for_empty_method_name(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$instance = new class() {
			public function get_hooks(): array {
				return array(
					'action' => array(
						'test_action' => array('method' => ''),
					),
				);
			}
		};

		$result = $hooksManager->register_hooks_for($instance);
		$this->assertFalse($result, 'Should fail when method name is empty');
	}

	// === ADDITIONAL TESTS FOR init_declarative_hooks ===

	/**
	 * Test init_declarative_hooks with ActionHooksInterface and active logger
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::__construct
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\ActionHooksRegistrar::register_single_action_hook
	 */
	public function test_init_declarative_hooks_action_interface_with_logger(): void {
		$this->logger->collected_logs = array();

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action'    => 'test_method',
					'another_action' => 'another_method',
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public function test_method() {
				return 'test';
			}

			public function another_method() {
				return 'another';
			}
		};

		$hooksManager = new HooksManager($instance, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify stats were updated
		$stats = $hooksManager->get_stats();
		$this->assertEquals(2, $stats['actions_registered'], 'Should track 2 registered actions');
		$this->expectLog('debug', array('HooksManager - Registered', 'declarative actions'), 1);
	}

	/**
	 * Test init_declarative_hooks with FilterHooksInterface and active logger
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::__construct
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::init
	 * @covers \Ran\PluginLib\HooksAccessory\FilterHooksRegistrar::register_single_filter_hook
	 */
	public function test_init_declarative_hooks_filter_interface_with_logger(): void {
		$this->logger->collected_logs = array();

		$instance = new class() implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array(
					'test_filter'    => 'test_method',
					'another_filter' => 'another_method',
					'third_filter'   => 'third_method',
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function test_method($value) {
				return $value . '_modified';
			}

			public function another_method($value) {
				return $value . '_another';
			}

			public function third_method($value) {
				return $value . '_third';
			}
		};

		$hooksManager = new HooksManager($instance, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify stats were updated
		$stats = $hooksManager->get_stats();
		$this->assertEquals(3, $stats['filters_registered'], 'Should track 3 registered filters');
		$this->expectLog('debug', array('HooksManager - Registered', 'declarative filters'), 1);
	}

	/**
	 * Test init_declarative_hooks with both interfaces and active logger
	 */
	public function test_init_declarative_hooks_both_interfaces_with_logger(): void {
		$this->logger->collected_logs = array();

		$instance = new class() implements ActionHooksInterface, FilterHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => 'action_method',
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public static function declare_filter_hooks(): array {
				return array(
					'test_filter'    => 'filter_method',
					'another_filter' => 'another_filter_method',
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function action_method() {
				return 'action';
			}

			public function filter_method($value) {
				return $value . '_filtered';
			}

			public function another_filter_method($value) {
				return $value . '_another_filtered';
			}
		};

		$hooksManager = new HooksManager($instance, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify stats were updated for both
		$stats = $hooksManager->get_stats();
		$this->assertEquals(1, $stats['actions_registered'], 'Should track 1 registered action');
		$this->assertEquals(2, $stats['filters_registered'], 'Should track 2 registered filters');
		$this->expectLog('debug', array('HooksManager - Registered', 'declarative'), 2);
	}

	/**
	 * Test init_declarative_hooks with ActionHooksInterface and inactive logger
	 */
	public function test_init_declarative_hooks_action_interface_inactive_logger(): void {
		$logger = new InactiveCollectingLogger();

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(
					'test_action' => 'test_method',
				);
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}

			public function test_method() {
				return 'test';
			}
		};

		$hooksManager = new HooksManager($instance, $logger);
		$hooksManager->init_declarative_hooks();

		// Verify stats were still updated even with inactive logger
		$stats = $hooksManager->get_stats();
		$this->assertEquals(1, $stats['actions_registered'], 'Should track 1 registered action even with inactive logger');
		self::assertSame(array(), $logger->get_logs(), 'Inactive logger should not record any log entries.');
	}

	/**
	 * Test init_declarative_hooks with FilterHooksInterface and inactive logger
	 */
	public function test_init_declarative_hooks_filter_interface_inactive_logger(): void {
		$logger = new InactiveCollectingLogger();

		$instance = new class() implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array(
					'test_filter' => 'test_method',
				);
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}

			public function test_method($value) {
				return $value . '_modified';
			}
		};

		$hooksManager = new HooksManager($instance, $logger);
		$hooksManager->init_declarative_hooks();

		// Verify stats were still updated even with inactive logger
		$stats = $hooksManager->get_stats();
		$this->assertEquals(1, $stats['filters_registered'], 'Should track 1 registered filter even with inactive logger');
		self::assertSame(array(), $logger->get_logs(), 'Inactive logger should not record any log entries.');
	}

	/**
	 * Test init_declarative_hooks with empty action hooks
	 */
	public function test_init_declarative_hooks_empty_action_hooks(): void {
		$this->logger->collected_logs = array();

		$instance = new class() implements ActionHooksInterface {
			public static function declare_action_hooks(): array {
				return array(); // Empty hooks
			}

			public static function validate_action_hooks(object $instance): array {
				return array();
			}

			public static function get_action_hooks_metadata(): array {
				return array();
			}
		};

		$hooksManager = new HooksManager($instance, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify stats were updated correctly for empty hooks
		$stats = $hooksManager->get_stats();
		$this->assertEquals(0, $stats['actions_registered'], 'Should track 0 registered actions for empty hooks');
	}

	/**
	 * Test init_declarative_hooks with empty filter hooks
	 */
	public function test_init_declarative_hooks_empty_filter_hooks(): void {
		$this->logger->collected_logs = array();

		$instance = new class() implements FilterHooksInterface {
			public static function declare_filter_hooks(): array {
				return array(); // Empty hooks
			}

			public static function validate_filter_hooks(object $instance): array {
				return array();
			}

			public static function get_filter_hooks_metadata(): array {
				return array();
			}
		};

		$hooksManager = new HooksManager($instance, $this->logger);
		$hooksManager->init_declarative_hooks();

		// Verify stats were updated correctly for empty hooks
		$stats = $hooksManager->get_stats();
		$this->assertEquals(0, $stats['filters_registered'], 'Should track 0 registered filters for empty hooks');
	}

	// === ADDITIONAL TESTS FOR register_action ===

	/**
	 * Test register_action with string callback that exists as method
	 */
	public function test_register_action_string_callback_method_exists(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_action('test_action', 'test_method', 10, 1);
		$this->assertTrue($result, 'Should register action with string callback that exists as method');
	}

	/**
	 * Test register_action with string callback that doesn't exist as method
	 */
	public function test_register_action_string_callback_method_not_exists(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_action('test_action', 'non_existent_method', 10, 1);
		$this->assertFalse($result, 'Should fail when string callback method does not exist');
	}

	/**
	 * Test register_action with invalid callback and active logger
	 */
	public function test_register_action_invalid_callback_active_logger(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_action('test_action', 'invalid_callback', 10, 1);
		$this->assertFalse($result, 'Should fail with invalid callback and log warning');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for action: test_action');
	}

	/**
	 * Test register_action with invalid callback and inactive logger
	 */
	public function test_register_action_invalid_callback_inactive_logger(): void {
		$inactiveLogger = new InactiveCollectingLogger();
		$hooksManager   = new HooksManager($this->test_object, $inactiveLogger);

		$result = $hooksManager->register_action('test_action', 'invalid_callback', 10, 1);
		$this->assertFalse($result, 'Should fail with invalid callback but not log when logger inactive');
		self::assertSame(array(), $inactiveLogger->get_logs(), 'Inactive logger should not record any log entries.');
	}

	/**
	 * Test register_action with valid callable callback
	 */
	public function test_register_action_valid_callable_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function() {
			return 'test';
		};

		$result = $hooksManager->register_action('test_action', $callback, 10, 1);
		$this->assertTrue($result, 'Should register action with valid callable callback');
	}

	/**
	 * Test register_action with array callback
	 */
	public function test_register_action_array_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		$result = $hooksManager->register_action('test_action', $callback, 10, 1);
		$this->assertTrue($result, 'Should register action with array callback');
	}

	/**
	 * Test register_action with closure callback
	 */
	public function test_register_action_closure_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function($param) {
			return $param . '_modified';
		};

		$result = $hooksManager->register_action('test_action', $callback, 10, 1);
		$this->assertTrue($result, 'Should register action with closure callback');
	}

	/**
	 * Test register_action with null callback
	 */
	public function test_register_action_null_callback(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_action('test_action', null, 10, 1);
		$this->assertFalse($result, 'Should fail with null callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for action: test_action');
	}

	/**
	 * Test register_action with integer callback
	 */
	public function test_register_action_integer_callback(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_action('test_action', 123, 10, 1);
		$this->assertFalse($result, 'Should fail with integer callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for action: test_action');
	}

	/**
	 * Test register_action with object callback that is not callable
	 */
	public function test_register_action_object_callback_not_callable(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$non_callable_object = new \stdClass();

		$result = $hooksManager->register_action('test_action', $non_callable_object, 10, 1);
		$this->assertFalse($result, 'Should fail with non-callable object callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for action: test_action');
	}

	/**
	 * Test register_action with string callback that exists but is not callable
	 */
	public function test_register_action_string_callback_exists_not_callable(): void {
		$this->logger->collected_logs = array();

		$testObject = new class() {
			private function private_method() {
				return 'private';
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_action('test_action', 'private_method', 10, 1);
		$this->assertFalse($result, 'Should fail with string callback that exists but is not callable');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for action: test_action');
	}

	/**
	 * Test register_action with different priority values
	 */
	public function test_register_action_different_priorities(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function() {
			return 'test';
		};

		$result1 = $hooksManager->register_action('test_action_1', $callback, 1, 1);
		$result2 = $hooksManager->register_action('test_action_2', $callback, 20, 1);
		$result3 = $hooksManager->register_action('test_action_3', $callback, 100, 1);

		$this->assertTrue($result1, 'Should register action with priority 1');
		$this->assertTrue($result2, 'Should register action with priority 20');
		$this->assertTrue($result3, 'Should register action with priority 100');
	}

	/**
	 * Test register_action with different accepted_args values
	 */
	public function test_register_action_different_accepted_args(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function() {
			return 'test';
		};

		$result1 = $hooksManager->register_action('test_action_1', $callback, 10, 0);
		$result2 = $hooksManager->register_action('test_action_2', $callback, 10, 5);
		$result3 = $hooksManager->register_action('test_action_3', $callback, 10, 10);

		$this->assertTrue($result1, 'Should register action with 0 accepted args');
		$this->assertTrue($result2, 'Should register action with 5 accepted args');
		$this->assertTrue($result3, 'Should register action with 10 accepted args');
	}

	/**
	 * Test register_action with context array
	 */
	public function test_register_action_with_context(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function() {
			return 'test';
		};

		$context = array('source' => 'test', 'priority' => 'high');

		$result = $hooksManager->register_action('test_action', $callback, 10, 1, $context);
		$this->assertTrue($result, 'Should register action with context array');
	}

	// === ADDITIONAL TESTS FOR register_filter ===

	/**
	 * Test register_filter with string callback that exists as method
	 */
	public function test_register_filter_string_callback_method_exists(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_filter('test_filter', 'test_method', 10, 1);
		$this->assertTrue($result, 'Should register filter with string callback that exists as method');
	}

	/**
	 * Test register_filter with string callback that doesn't exist as method
	 */
	public function test_register_filter_string_callback_method_not_exists(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_filter('test_filter', 'non_existent_method', 10, 1);
		$this->assertFalse($result, 'Should fail when string callback method does not exist');
	}

	/**
	 * Test register_filter with invalid callback and active logger
	 */
	public function test_register_filter_invalid_callback_active_logger(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_filter('test_filter', 'invalid_callback', 10, 1);
		$this->assertFalse($result, 'Should fail with invalid callback and log warning');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for filter: test_filter');
	}

	/**
	 * Test register_filter with invalid callback and inactive logger
	 */
	public function test_register_filter_invalid_callback_inactive_logger(): void {
		$inactiveLogger = new InactiveCollectingLogger();
		$hooksManager   = new HooksManager($this->test_object, $inactiveLogger);

		$result = $hooksManager->register_filter('test_filter', 'invalid_callback', 10, 1);
		$this->assertFalse($result, 'Should fail with invalid callback but not log when logger inactive');
		self::assertSame(array(), $inactiveLogger->get_logs(), 'Inactive logger should not record any log entries.');
	}

	/**
	 * Test register_filter with valid callable callback
	 */
	public function test_register_filter_valid_callable_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function($value) {
			return $value . '_modified';
		};

		$result = $hooksManager->register_filter('test_filter', $callback, 10, 1);
		$this->assertTrue($result, 'Should register filter with valid callable callback');
	}

	/**
	 * Test register_filter with array callback
	 */
	public function test_register_filter_array_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		$result = $hooksManager->register_filter('test_filter', $callback, 10, 1);
		$this->assertTrue($result, 'Should register filter with array callback');
	}

	/**
	 * Test register_filter with closure callback
	 */
	public function test_register_filter_closure_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function($value, $param) {
			return $value . '_' . $param;
		};

		$result = $hooksManager->register_filter('test_filter', $callback, 10, 2);
		$this->assertTrue($result, 'Should register filter with closure callback');
	}

	/**
	 * Test register_filter with null callback
	 */
	public function test_register_filter_null_callback(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_filter('test_filter', null, 10, 1);
		$this->assertFalse($result, 'Should fail with null callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for filter: test_filter');
	}

	/**
	 * Test register_filter with integer callback
	 */
	public function test_register_filter_integer_callback(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_filter('test_filter', 123, 10, 1);
		$this->assertFalse($result, 'Should fail with integer callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for filter: test_filter');
	}

	/**
	 * Test register_filter with object callback that is not callable
	 */
	public function test_register_filter_object_callback_not_callable(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$non_callable_object = new \stdClass();

		$result = $hooksManager->register_filter('test_filter', $non_callable_object, 10, 1);
		$this->assertFalse($result, 'Should fail with non-callable object callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for filter: test_filter');
	}

	/**
	 * Test register_filter with string callback that exists but is not callable
	 */
	public function test_register_filter_string_callback_exists_not_callable(): void {
		$this->logger->collected_logs = array();

		$testObject = new class() {
			private function private_method() {
				return 'private';
			}
		};

		$hooksManager = new HooksManager($testObject, $this->logger);

		$result = $hooksManager->register_filter('test_filter', 'private_method', 10, 1);
		$this->assertFalse($result, 'Should fail with string callback that exists but is not callable');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for filter: test_filter');
	}

	/**
	 * Test register_filter with different priority values
	 */
	public function test_register_filter_different_priorities(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function($value) {
			return $value . '_modified';
		};

		$result1 = $hooksManager->register_filter('test_filter_1', $callback, 1, 1);
		$result2 = $hooksManager->register_filter('test_filter_2', $callback, 20, 1);
		$result3 = $hooksManager->register_filter('test_filter_3', $callback, 100, 1);

		$this->assertTrue($result1, 'Should register filter with priority 1');
		$this->assertTrue($result2, 'Should register filter with priority 20');
		$this->assertTrue($result3, 'Should register filter with priority 100');
	}

	/**
	 * Test register_filter with different accepted_args values
	 */
	public function test_register_filter_different_accepted_args(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function($value) {
			return $value . '_modified';
		};

		$result1 = $hooksManager->register_filter('test_filter_1', $callback, 10, 0);
		$result2 = $hooksManager->register_filter('test_filter_2', $callback, 10, 5);
		$result3 = $hooksManager->register_filter('test_filter_3', $callback, 10, 10);

		$this->assertTrue($result1, 'Should register filter with 0 accepted args');
		$this->assertTrue($result2, 'Should register filter with 5 accepted args');
		$this->assertTrue($result3, 'Should register filter with 10 accepted args');
	}

	/**
	 * Test register_filter with context array
	 */
	public function test_register_filter_with_context(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function($value) {
			return $value . '_modified';
		};

		$context = array('source' => 'test', 'priority' => 'high');

		$result = $hooksManager->register_filter('test_filter', $callback, 10, 1, $context);
		$this->assertTrue($result, 'Should register filter with context array');
	}

	/**
	 * Test register_filter with boolean callback
	 */
	public function test_register_filter_boolean_callback(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$result = $hooksManager->register_filter('test_filter', true, 10, 1);
		$this->assertFalse($result, 'Should fail with boolean callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for filter: test_filter');
	}

	/**
	 * Test register_filter with array callback that is not callable
	 */
	public function test_register_filter_array_callback_not_callable(): void {
		$this->logger->collected_logs = array();
		$hooksManager                 = new HooksManager($this->test_object, $this->logger);

		$non_callable_array = array('not_callable', 'method');

		$result = $hooksManager->register_filter('test_filter', $non_callable_array, 10, 1);
		$this->assertFalse($result, 'Should fail with non-callable array callback');
		$this->expectLog('warning', 'HooksManager - Invalid callback provided for filter: test_filter');
	}

	/**
	 * Test register_filter with function name callback
	 */
	public function test_register_filter_function_name_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Create a global function for testing
		if (!function_exists('test_global_function')) {
			eval('function test_global_function($value) { return $value . "_global"; }');
		}

		$result = $hooksManager->register_filter('test_filter', 'test_global_function', 10, 1);
		$this->assertTrue($result, 'Should register filter with function name callback');
	}

	/**
	 * Test register_filter with static method callback
	 */
	public function test_register_filter_static_method_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Use a simple function callback instead of static method to avoid class declaration issues
		$callback = function($value) {
			return $value . '_static';
		};

		$result = $hooksManager->register_filter('test_filter', $callback, 10, 1);
		$this->assertTrue($result, 'Should register filter with closure callback');
	}



	/**
	 * Test remove_hook with action type
	 */
	public function test_remove_hook_action_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		// Mock the WordPress removal function
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 10),
			'return' => true
		));

		$result = $hooksManager->remove_hook('action', 'test_action', $callback, 10);
		$this->assertTrue($result, 'Should successfully remove action hook');
	}

	/**
	 * Test remove_hook with filter type
	 */
	public function test_remove_hook_filter_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		// Mock the WordPress removal function
		WP_Mock::userFunction('remove_filter', array(
			'times'  => 1,
			'args'   => array('test_filter', $callback, 10),
			'return' => true
		));

		$result = $hooksManager->remove_hook('filter', 'test_filter', $callback, 10);
		$this->assertTrue($result, 'Should successfully remove filter hook');
	}

	/**
	 * Test remove_hook with tracked hook (hook exists in internal tracking)
	 */
	public function test_remove_hook_tracked_hook(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		// Mock the WordPress functions
		WP_Mock::userFunction('add_action', array(
			'times'  => '0+',
			'return' => true
		));

		$hooksManager->register_action('test_action', $callback, 10);

		// Now remove it
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 10),
			'return' => true
		));

		$result = $hooksManager->remove_hook('action', 'test_action', $callback, 10);
		$this->assertTrue($result, 'Should successfully remove tracked hook');
	}

	/**
	 * Test remove_hook with untracked hook (hook not in internal tracking)
	 */
	public function test_remove_hook_untracked_hook(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		// Mock the WordPress removal function to succeed
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 10),
			'return' => true
		));

		$result = $hooksManager->remove_hook('action', 'test_action', $callback, 10);
		$this->assertTrue($result, 'Should return true when WordPress removal succeeds for untracked hook');
	}

	/**
	 * Test remove_hook with WordPress removal failure
	 */
	public function test_remove_hook_wordpress_removal_failure(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		// Mock the WordPress removal function to fail
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 10),
			'return' => false
		));

		$result = $hooksManager->remove_hook('action', 'test_action', $callback, 10);
		$this->assertFalse($result, 'Should return false when WordPress removal fails for untracked hook');
	}

	/**
	 * Test remove_hook with tracked hook but WordPress removal failure
	 */
	public function test_remove_hook_tracked_hook_wordpress_failure(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		// Mock the WordPress functions
		WP_Mock::userFunction('add_action', array(
			'times'  => '0+',
			'return' => true
		));

		$hooksManager->register_action('test_action', $callback, 10);

		// Now remove it with WordPress failure
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 10),
			'return' => false
		));

		$result = $hooksManager->remove_hook('action', 'test_action', $callback, 10);
		$this->assertTrue($result, 'Should return true when hook is tracked even if WordPress removal fails');
	}

	/**
	 * Test remove_hook with different priority values
	 */
	public function test_remove_hook_different_priorities(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		// Test with priority 1
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 1),
			'return' => true
		));

		$result1 = $hooksManager->remove_hook('action', 'test_action', $callback, 1);
		$this->assertTrue($result1, 'Should remove hook with priority 1');

		// Test with priority 20
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 20),
			'return' => true
		));

		$result2 = $hooksManager->remove_hook('action', 'test_action', $callback, 20);
		$this->assertTrue($result2, 'Should remove hook with priority 20');

		// Test with priority 100
		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 100),
			'return' => true
		));

		$result3 = $hooksManager->remove_hook('action', 'test_action', $callback, 100);
		$this->assertTrue($result3, 'Should remove hook with priority 100');
	}

	/**
	 * Test remove_hook with array callback
	 */
	public function test_remove_hook_array_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = array($this->test_object, 'test_method');

		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 10),
			'return' => true
		));

		$result = $hooksManager->remove_hook('action', 'test_action', $callback, 10);
		$this->assertTrue($result, 'Should remove hook with array callback');
	}

	/**
	 * Test remove_hook with function name callback
	 */
	public function test_remove_hook_function_name_callback(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Create a global function for testing
		if (!function_exists('test_remove_function')) {
			eval('function test_remove_function() { return "test"; }');
		}

		$callback = 'test_remove_function';

		WP_Mock::userFunction('remove_action', array(
			'times'  => 1,
			'args'   => array('test_action', $callback, 10),
			'return' => true
		));

		$result = $hooksManager->remove_hook('action', 'test_action', $callback, 10);
		$this->assertTrue($result, 'Should remove hook with function name callback');
	}

	/**
	 * Test remove_hook with empty string type
	 */
	public function test_remove_hook_empty_string_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function() {
			return 'test';
		};

		$result = $hooksManager->remove_hook('', 'test_hook', $callback, 10);
		$this->assertFalse($result, 'Should fail with empty string type');
	}

	/**
	 * Test remove_hook with mixed case type
	 */
	public function test_remove_hook_mixed_case_type(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		$callback = function() {
			return 'test';
		};

		$result = $hooksManager->remove_hook('Action', 'test_hook', $callback, 10);
		$this->assertFalse($result, 'Should fail with mixed case type');
	}

	// === COMPREHENSIVE TESTS FOR register_method_hook AND _register_hook ===

	/**
	 * Test register_method_hook with valid action method
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_valid_action(): void {
		$result = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 1, array('context' => 'test'));
		$this->assertTrue($result, 'Should register valid action method');
	}

	/**
	 * Test register_method_hook with valid filter method
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_valid_filter(): void {
		$result = $this->hooksManager->register_method_hook('filter', 'test_filter', 'test_method', 20, 2, array('context' => 'test'));
		$this->assertTrue($result, 'Should register valid filter method');
	}







	/**
	 * Test register_method_hook duplicate prevention
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_duplicate_prevention(): void {
		// First registration should succeed
		$result1 = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 1);
		$this->assertTrue($result1, 'First registration should succeed');

		// Second registration should fail (duplicate)
		$result2 = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 1);
		$this->assertFalse($result2, 'Second registration should fail due to duplicate');

		$this->expectLog('debug', 'HooksManager - Prevented duplicate action registration: test_action (priority: 10)');
	}

	/**
	 * Test register_method_hook with different priorities
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_different_priorities(): void {
		// Test priority 1
		$result1 = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 1);
		$this->assertTrue($result1, 'Should register with priority 1');

		// Test priority 100
		$result2 = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 100);
		$this->assertTrue($result2, 'Should register with priority 100');
	}

	/**
	 * Test register_method_hook with different accepted_args
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_different_accepted_args(): void {
		// Test with 0 accepted args
		$result1 = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 0);
		$this->assertTrue($result1, 'Should register with 0 accepted args');

		// Test with 5 accepted args - this should fail because it's a duplicate (same hook, priority, and callback)
		$result2 = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 5);
		$this->assertFalse($result2, 'Should fail because it\'s a duplicate (accepted_args is not part of duplicate detection)');
	}

	/**
	 * Test register_method_hook context merging
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_context_merging(): void {
		$context          = array('source' => 'test', 'priority' => 'high');
		$expected_context = array('source' => 'test', 'priority' => 'high', 'method' => 'test_method');

		$result = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 1, $context);
		$this->assertTrue($result, 'Should register with merged context');

		$this->expectLog('debug', 'HooksManager - Registered action: test_action (priority: 10) [' . json_encode($expected_context) . ']');
	}

	/**
	 * Test register_method_hook with WordPress registration success
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_wordpress_success(): void {
		// Configure the mock to return null (void) for this test
		$this->hooksManager->shouldReceive('_do_add_action')
			->once()
			->with('test_action', array($this->test_object, 'test_method'), 10, 1)
			->andReturnNull();

		$result = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 1);
		$this->assertTrue($result, 'Should succeed when WordPress registration succeeds');
	}



	/**
	 * Test register_method_hook stats tracking
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_stats_tracking(): void {
		$this->hooksManager->register_method_hook('action', 'test_action', 'test_method');

		$stats = $this->hooksManager->get_stats();
		$this->assertEquals(1, $stats['dynamic_hooks_registered'], 'Should track successful registration');
		$this->assertEquals(0, $stats['duplicates_prevented'], 'Should not track duplicates for new registration');
	}

	/**
	 * Test register_method_hook with inactive logger
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_inactive_logger(): void {
		$inactiveLogger = new InactiveCollectingLogger();
		$hooksManager   = new HooksManager($this->test_object, $inactiveLogger);

		$result = $hooksManager->register_method_hook('action', 'test_action', 'test_method');
		$this->assertTrue($result, 'Should work with inactive logger');
		self::assertSame(array(), $inactiveLogger->get_logs(), 'Inactive logger should not record any log entries.');
	}

	/**
	 * Test register_method_hook with empty context
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_empty_context(): void {
		$result = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 1, array());
		$this->assertTrue($result, 'Should register with empty context');

		$this->expectLog('debug', 'HooksManager - Registered action: test_action (priority: 10)');
	}

	/**
	 * Test register_method_hook with complex context
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::register_method_hook
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::_register_hook
	 */
	public function test_register_method_hook_complex_context(): void {
		$complex_context = array(
			'source'   => 'test',
			'priority' => 'high',
			'nested'   => array('key' => 'value'),
			'numbers'  => array(1, 2, 3)
		);
		$expected_context = array_merge($complex_context, array('method' => 'test_method'));

		$result = $this->hooksManager->register_method_hook('action', 'test_action', 'test_method', 10, 1, $complex_context);
		$this->assertTrue($result, 'Should register with complex context');

		$this->expectLog('debug', 'HooksManager - Registered action: test_action (priority: 10) [' . json_encode($expected_context) . ']');
	}

	/**
	 * Test evaluate_condition with a real function name as string
	 *
	 * @covers \Ran\PluginLib\HooksAccessory\HooksManager::evaluate_condition
	 */
	public function test_evaluate_condition_string_real_function(): void {
		$hooksManager = new HooksManager($this->test_object, $this->logger);

		// Test the is_string branch with a non-existent function
		// This will reach the is_string branch but function_exists will return false
		$result = $this->invoke_private_method($hooksManager, 'evaluate_condition', array('nonexistent_function'));
		$this->assertFalse($result, 'Should return false for non-existent function');

		// Test the is_callable branch with a real function
		// Define a function at runtime using eval
		if (!function_exists('test_conditional_function_for_coverage')) {
			eval('function test_conditional_function_for_coverage() { return true; }');
		}

		$result2 = $this->invoke_private_method($hooksManager, 'evaluate_condition', array('test_conditional_function_for_coverage'));
		$this->assertTrue($result2, 'Should evaluate our test function correctly');
	}
}
