<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;
use Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait;
use Ran\PluginLib\Util\Logger;
use WP_Mock;
use Mockery;
use Mockery\MockInterface;

/**
 * Concrete implementation of StylesEnqueueTrait for testing style-related methods.
 */
class ConcreteEnqueueForStylesTesting extends AssetEnqueueBaseAbstract {
	use StylesEnqueueTrait;

	// Config is inherited from AssetEnqueueBaseAbstract and set in constructor.

	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	/**
	 * Explicitly define get_logger to aid in diagnosing trait method resolution.
	 *
	 * @return Logger The logger instance.
	 */
	public function get_logger(): Logger {
		return parent::get_logger();
	}

	public function load(): void {
		// Minimal implementation for testing purposes.
	}

	// Expose protected property for testing
	public function get_internal_inline_styles_array(): array {
		return $this->inline_styles;
	}
}

/**
 * Class StylesEnqueueTraitTest
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait
 * @property ConcreteEnqueueForStylesTesting&MockInterface $instance
 */
class StylesEnqueueTraitTest extends PluginLibTestCase {
	private static int $hasActionCallCount = 0;

	/** @var ConcreteEnqueueForStylesTesting&MockInterface */
	protected $instance; // Mockery will handle the type

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp(); // This sets up config_mock and logger_mock

		// Ensure the logger is considered active for all tests in this class.
		$this->logger_mock->shouldReceive('is_active')->andReturn(true)->byDefault();
		$this->logger_mock->shouldReceive('is_verbose')->andReturn(true)->byDefault();

		// Set up default, permissive expectations for all log levels.
		// Individual tests can override these with more specific expectations.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('info')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('warning')->withAnyArgs()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('error')->withAnyArgs()->andReturnNull()->byDefault();


		// Default WP_Mock function mocks for style functions
		WP_Mock::userFunction('wp_register_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_style')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_add_inline_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault(); // 0 means false
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault(); // Default to not admin context
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
		WP_Mock::userFunction('_doing_it_wrong')->withAnyArgs()->andReturnNull()->byDefault();

		// Create a partial mock of ConcreteEnqueueForStylesTesting
		$this->instance = Mockery::mock(
			ConcreteEnqueueForStylesTesting::class,
			array($this->config_mock) // Pass only the config_mock from parent
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

		// Reset internal state before each test to prevent leakage
		$properties_to_reset = array(
			'assets',
			'inline_styles',
			'deferred_assets',
		);
		foreach ($properties_to_reset as $prop_name) {
			try {
				$this->set_protected_property_value($this->instance, $prop_name, array());
			} catch (\ReflectionException $e) {
				// Property might not exist in all versions/contexts, ignore.
				$this->logger_mock->debug("StylesEnqueueTraitStylesTest::setUp - Could not reset property {$prop_name}: " . $e->getMessage());
			}
		}
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	// ------------------------------------------------------------------------
	// Test Methods for Style Functionalities
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::get_styles
	 */
	public function test_add_styles_should_store_styles_correctly(): void {
		$styles_to_add = array(
			array(
				'handle'    => 'my-style-1',
				'src'       => 'path/to/my-style-1.css',
				'deps'      => array('jquery-ui-style'),
				'version'   => '1.0.0',
				'media'     => 'screen',
				'condition' => static fn() => true,
			),
			array(
				'handle'  => 'my-style-2',
				'src'     => 'path/to/my-style-2.css',
				'deps'    => array(),
				'version' => false, // Use plugin version
				'media'   => 'all',
				// No condition, should default to true
			),
		);

		// Logger expectations for StylesEnqueueTrait::add_styles()
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Entered. Current style count: 0. Adding 2 new style(s).')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Adding style. Key: 0, Handle: my-style-1, Src: path/to/my-style-1.css')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Adding style. Key: 1, Handle: my-style-2, Src: path/to/my-style-2.css')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Adding 2 style definition(s). Current total: 0')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Exiting. New total style count: 2')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - All current style handles: my-style-1, my-style-2')->once()->ordered();

		// Call the method under test
		$result = $this->instance->add_styles($styles_to_add);

		// Assert chainability
		$this->assertSame($this->instance, $result, 'add_styles() should be chainable.');

		// Retrieve and check stored styles
		$retrieved_styles_array = $this->instance->get_styles();
		$this->assertArrayHasKey('general', $retrieved_styles_array);
		// print the retrieved styles array for debugging
		// fwrite(STDERR, var_export($retrieved_styles_array['general'], true) . "\n");

		$general_styles = $retrieved_styles_array['general'];
		$this->assertCount(count($styles_to_add), $general_styles, 'Should have the same number of styles added.');

		// Check first style (my-style-1)
		if (isset($general_styles[0])) {
			$this->assertEquals('my-style-1', $general_styles[0]['handle']);
			$this->assertEquals('path/to/my-style-1.css', $general_styles[0]['src']);
			$this->assertEquals(array('jquery-ui-style'), $general_styles[0]['deps']);
			$this->assertEquals('1.0.0', $general_styles[0]['version']);
			$this->assertEquals('screen', $general_styles[0]['media']);
			$this->assertTrue(is_callable($general_styles[0]['condition']));
			$this->assertTrue(($general_styles[0]['condition'])());
		}

		// Check second style (my-style-2)
		if (isset($general_styles[1])) {
			$this->assertEquals('my-style-2', $general_styles[1]['handle']);
			$this->assertEquals('path/to/my-style-2.css', $general_styles[1]['src']);
			$this->assertEquals(array(), $general_styles[1]['deps']);
			$this->assertEquals(false, $general_styles[1]['version']); // As per input
			$this->assertEquals('all', $general_styles[1]['media']);
			// Check that 'condition' is not present if not provided and not yet processed to default
			$this->assertArrayNotHasKey('condition', $styles_to_add[1]);
			$this->assertArrayNotHasKey('condition', $general_styles[1]);
		}

		//Check style properties
		$this->assertEquals('path/to/my-style-1.css', $retrieved_styles_array['general'][0]['src']);
		$this->assertEquals('path/to/my-style-2.css', $retrieved_styles_array['general'][1]['src']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_styles
	 */
	public function test_add_styles_handles_single_style_definition_correctly(): void {
		$style_to_add = array(
			'handle' => 'single-style',
			'src'    => 'path/to/single.css',
			'deps'   => array(),
		);

		// Logger expectations for StylesEnqueueTrait::add_styles()
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Entered. Current style count: 0. Adding 1 new style(s).')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Adding style. Key: 0, Handle: single-style, Src: path/to/single.css')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Adding 1 style definition(s). Current total: 0')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Exiting. New total style count: 1')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - All current style handles: single-style')->once()->ordered();

		// Call the method under test
		$this->instance->add_styles($style_to_add);

		// Retrieve and check stored styles
		$retrieved_styles = $this->instance->get_styles()['general'];
		$this->assertCount(1, $retrieved_styles);
		$this->assertEquals('single-style', $retrieved_styles[0]['handle']);
		$this->assertEquals('path/to/single.css', $retrieved_styles[0]['src']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::add_styles
	 */
	public function test_add_styles_handles_empty_input_gracefully(): void {
		// Logger expectations for StylesEnqueueTrait::add_styles() with an empty array.
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Entered with empty array. No styles to add.')->once();

		// Call the method under test
		$this->instance->add_styles(array());

		// Retrieve and check stored styles
		$retrieved_styles = $this->instance->get_styles()['general'];
		$this->assertCount(0, $retrieved_styles, 'The styles queue should be empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::register_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 */
	public function test_register_styles_registers_non_hooked_style_correctly(): void {
		$style_to_add = array(
			'handle'  => 'my-style',
			'src'     => 'path/to/my-style.css',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
		);

		// --- Logger Mocks for StylesEnqueueTrait::add_styles() ---
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Entered. Current style count: 0. Adding 1 new style(s).')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Adding style. Key: 0, Handle: my-style, Src: path/to/my-style.css')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Adding 1 style definition(s). Current total: 0')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - Exiting. New total style count: 1')->once()->ordered('add');
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::add_styles - All current style handles: my-style')->once()->ordered('add');

		$this->instance->add_styles(array($style_to_add));

		// --- WP_Mock and Logger Mocks for register_styles() ---
		$is_registered = false;

		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::register_styles - Entered. Processing 1 style definition(s) for registration.')->once()->ordered('register');
		$this->logger_mock->shouldReceive('debug')->with('StylesEnqueueTrait::register_styles - Processing style: "my-style", original index: 0.')->once()->ordered('register');
		$this->logger_mock->shouldReceive('debug')->with("StylesEnqueueTrait::_process_single_style - Processing style 'my-style' in context 'register_styles'.")->once()->ordered('register');

		// Mock wp_style_is to reflect the state change
		WP_Mock::userFunction('wp_style_is', array(
			'args'   => array('my-style', 'registered'),
			'return' => function () use ( &$is_registered ) {
				return $is_registered;
			},
		))->atLeast()->once();

		$this->logger_mock->shouldReceive('debug')->with("StylesEnqueueTrait::_process_single_style - Registering style 'my-style'.")->once()->ordered('register');

		// Mock wp_register_style to simulate the state change
		WP_Mock::userFunction('wp_register_style', array(
			'args'   => array('my-style', 'path/to/my-style.css', array(), '1.0', 'all'),
			'times'  => 1,
			'return' => function () use ( &$is_registered ) {
				$is_registered = true;
				return true;
			},
		));

		// Mocks for inline style processing
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: register_styles) - Checking for inline styles for parent handle 'my-style'.")->once()->ordered('register');
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: register_styles) - No inline styles found or processed for 'my-style'.")->once()->ordered('register');

		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Finished processing style 'my-style'.")->once()->ordered('register');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Exited. Remaining immediate styles: 1. Deferred styles: 0.')->once()->ordered('register');

		// Call the method under test
		$this->instance->register_styles();

		// Assert that the style is still in the queue for enqueuing later
		$retrieved_styles = $this->instance->get_styles();
		$this->assertCount(1, $retrieved_styles['general']);
		$this->assertEquals('my-style', $retrieved_styles['general'][0]['handle']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::register_styles
	 */
	public function test_register_styles_defers_hooked_style_correctly(): void {
		$style_to_add = array(
			'handle' => 'my-deferred-style',
			'src'    => 'path/to/deferred.css',
			'hook'   => 'admin_enqueue_scripts',
		);

		// --- Logger Mocks for add_styles() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Entered. Current style count: 0. Adding 1 new style(s).');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Adding style. Key: 0, Handle: my-deferred-style, Src: path/to/deferred.css');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Adding 1 style definition(s). Current total: 0');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Exiting. New total style count: 1');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - All current style handles: my-deferred-style');

		$this->instance->add_styles(array($style_to_add));

		// --- WP_Mock and Logger Mocks for register_styles() ---
		WP_Mock::userFunction('has_action')
			->with('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_styles'))
			->andReturn(false); // Mock that the action hasn't been added yet

		WP_Mock::expectActionAdded('admin_enqueue_scripts', array($this->instance, 'enqueue_deferred_styles'), 10, 1);

		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Entered. Processing 1 style definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Processing style: "my-deferred-style", original index: 0.');
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::register_styles - Deferring registration of style 'my-deferred-style' (original index 0) to hook: admin_enqueue_scripts.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::register_styles - Added action for 'enqueue_deferred_styles' on hook: admin_enqueue_scripts.");
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Exited. Remaining immediate styles: 0. Deferred styles: 1.');

		// Call the method under test
		$this->instance->register_styles();

		// Assert that the style is in the deferred queue
		// Access protected property $deferred_styles for assertion
		$reflection           = new \ReflectionClass($this->instance);
		$deferred_styles_prop = $reflection->getProperty('deferred_styles');
		$deferred_styles_prop->setAccessible(true);
		$deferred_styles = $deferred_styles_prop->getValue($this->instance);

		$this->assertArrayHasKey('admin_enqueue_scripts', $deferred_styles);
		$this->assertCount(1, $deferred_styles['admin_enqueue_scripts']);
		// The key for the style definition within the hook's array is its original index.
		// In this test, we add a single style, so its original index is 0.
		$this->assertArrayHasKey(0, $deferred_styles['admin_enqueue_scripts']);
		$this->assertEquals('my-deferred-style', $deferred_styles['admin_enqueue_scripts'][0]['handle']);

		// Assert that the main styles queue is empty as the style was deferred
		// Access protected property $styles for assertion
		$styles_prop = $reflection->getProperty('styles');
		$styles_prop->setAccessible(true);
		$styles_array = $styles_prop->getValue($this->instance);
		$this->assertEmpty($styles_array);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::register_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 */
	public function test_register_styles_skips_style_if_condition_is_false(): void {
		$style_to_add = array(
			'handle'    => 'my-conditional-style',
			'src'       => 'path/to/conditional.css',
			'condition' => function () {
				return false;
			},
		);

		// --- Logger Mocks for add_styles() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Entered. Current style count: 0. Adding 1 new style(s).');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Adding style. Key: 0, Handle: my-conditional-style, Src: path/to/conditional.css');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Adding 1 style definition(s). Current total: 0');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Exiting. New total style count: 1');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - All current style handles: my-conditional-style');

		$this->instance->add_styles(array($style_to_add));

		// --- WP_Mock and Logger Mocks for register_styles() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Entered. Processing 1 style definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Processing style: "my-conditional-style", original index: 0.');
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Processing style 'my-conditional-style' in context 'register_styles'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Condition not met for style 'my-conditional-style'. Skipping.");

		// Assert that wp_register_style is never called
		WP_Mock::userFunction('wp_register_style')->never();
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Exited. Remaining immediate styles: 0. Deferred styles: 0.');

		// Call the method under test
		$this->instance->register_styles();

		// Assert that the style was processed and removed from the immediate queue
		$this->assertEmpty($this->instance->get_styles()['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function test_enqueue_styles_enqueues_registered_style(): void {
		$style_to_add = array(
			'handle'  => 'my-basic-style',
			'src'     => 'path/to/basic.css',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
		);

		// --- Mocks for add_styles() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Entered. Current style count: 0. Adding 1 new style(s).');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Adding style. Key: 0, Handle: my-basic-style, Src: path/to/basic.css');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Adding 1 style definition(s). Current total: 0');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - Exiting. New total style count: 1');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::add_styles - All current style handles: my-basic-style');
		$this->instance->add_styles(array($style_to_add));

		// --- Consolidated WP_Mock::userFunction('wp_style_is', ...) calls for 'my-basic-style' ---
		// For 'registered' status - 4 calls expected:
		// 1. In register_styles->_process_single_style (trait L551): before wp_register_style -> returns false
		// 2. In register_styles->_process_single_style->_process_inline_styles (trait L440): after wp_register_style -> returns true
		// 3. In enqueue_styles->_process_single_style (trait L551): style already registered -> returns true
		// 4. In enqueue_styles->_process_single_style->_process_inline_styles (trait L440): style still registered -> returns true (for short-circuiting)
		WP_Mock::userFunction('wp_style_is')
		    ->with('my-basic-style', 'registered')
		    ->times(4)
		    ->andReturnValues(array(false, true, true, true));

		// For 'enqueued' status - 1 call expected:
		// 1. In enqueue_styles->_process_single_style (trait L570): returns false (before wp_enqueue_style call).
		// The call at _process_inline_styles (trait L440) for 'enqueued' status is NOT expected if short-circuiting works correctly.
		WP_Mock::userFunction('wp_style_is')
		    ->with('my-basic-style', 'enqueued')
		    ->times(1)
		    ->andReturnValues(array(false));

		// --- Mocks for register_styles() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Entered. Processing 1 style definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Processing style: "my-basic-style", original index: 0.');
		// _process_single_style (called by register_styles)
		// Call to wp_style_is('my-basic-style', 'registered') handled by consolidated mock (1st call, returns false)
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Processing style 'my-basic-style' in context 'register_styles'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Registering style 'my-basic-style'.");
		WP_Mock::userFunction('wp_register_style', array('args' => array('my-basic-style', 'path/to/basic.css', array(), '1.0', 'all'), 'times' => 1, 'return' => true));
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Finished processing style 'my-basic-style'.");
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::register_styles - Exited. Remaining immediate styles: 1. Deferred styles: 0.');
		$this->instance->register_styles();

		// --- Mocks for enqueue_styles() ---
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::enqueue_styles - Entered. Processing 1 style definition(s) from internal queue.');
		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::enqueue_styles - Processing style: "my-basic-style", original index: 0.');

		// _process_single_style (called by enqueue_styles)
		// The call to wp_style_is('my-basic-style', 'registered') at trait line 551 is handled by the
		// second call to the consolidated mock (defined earlier), which returns true.
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Processing style 'my-basic-style' in context 'enqueue_styles'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Style 'my-basic-style' already registered. Skipping wp_register_style."); // Log from line 553
		// Enqueue check within _process_single_style (do_enqueue=true)
		// The call to wp_style_is('my-basic-style', 'enqueued') at trait line 570 is handled by the
		// consolidated mock for 'enqueued' status (defined earlier), which returns false.
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Enqueuing style 'my-basic-style'.");
		WP_Mock::userFunction('wp_enqueue_style', array('args' => array('my-basic-style'), 'times' => 1));
		// _process_inline_styles (called by _process_single_style from enqueue_styles)
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle 'my-basic-style'.");
		// Parent style 'my-basic-style' will have been enqueued by wp_enqueue_style just before _process_inline_styles is called.
		// Parent check in _process_inline_styles (trait line 440): `if ( ! wp_style_is( $parent_handle, 'registered' ) && ! wp_style_is( $parent_handle, 'enqueued' ) )`
		// The call to wp_style_is('my-basic-style', 'registered') at trait line 440 is handled by the		// third call to the consolidated mock for 'registered' status (defined earlier), which returns true.
		// Because `!wp_style_is(..., 'registered')` evaluates to `!true` (which is `false`),
		// the `&&` condition short-circuits.
		// Therefore, the `wp_style_is('my-basic-style', 'enqueued')` part of the condition at trait line 440 is NOT executed.
		// This prevents the "No matching handler found" error for that specific call.
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_styles) - No inline styles found or processed for 'my-basic-style'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_single_style - Finished processing style 'my-basic-style'.");

		$this->logger_mock->shouldReceive('debug')->once()->with('StylesEnqueueTrait::enqueue_styles - Exited. Deferred styles count: 0.');

		$this->instance->enqueue_styles();

		$this->assertEmpty($this->instance->get_styles()['general']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_deferred_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function test_enqueue_deferred_styles_processes_and_enqueues_style_on_hook(): void {
		// Arrange
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$style_handle = 'my-deferred-style';
		$hook_name    = 'wp_footer';

		$deferred_style = array(
			'handle'  => $style_handle,
			'src'     => 'path/to/deferred.css',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
			'hook'    => $hook_name,
		);

		// --- Act 1: Add the style ---
		$add_styles_prefix = 'StylesEnqueueTrait::add_styles';
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_styles_prefix} - Entered. Current style count: 0. Adding 1 new style(s).");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_styles_prefix} - Adding style. Key: 0, Handle: {$style_handle}, Src: path/to/deferred.css");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_styles_prefix} - Adding 1 style definition(s). Current total: 0");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_styles_prefix} - Exiting. New total style count: 1");
		$this->logger_mock->shouldReceive('debug')->once()->with("{$add_styles_prefix} - All current style handles: {$style_handle}");
		$this->instance->add_styles(array($deferred_style));

		// --- Act 2: Register the style (which defers it) ---
		$register_styles_prefix = 'StylesEnqueueTrait::register_styles - ';
		$this->logger_mock->shouldReceive('debug')->once()->with($register_styles_prefix . 'Entered. Processing 1 style definition(s) for registration.');
		$this->logger_mock->shouldReceive('debug')->once()->with($register_styles_prefix . "Processing style: \"{$style_handle}\", original index: 0.");
		$this->logger_mock->shouldReceive('debug')->once()->with($register_styles_prefix . "Deferring registration of style '{$style_handle}' (original index 0) to hook: {$hook_name}.");
		WP_Mock::userFunction('has_action', array(
			'args'   => array($hook_name, array($this->instance, 'enqueue_deferred_styles')),
			'times'  => 1,
			'return' => false
		));
		WP_Mock::expectActionAdded($hook_name, array($this->instance, 'enqueue_deferred_styles'), 10, 1);
		$this->logger_mock->shouldReceive('debug')->once()->with($register_styles_prefix . "Added action for 'enqueue_deferred_styles' on hook: {$hook_name}.");
		$this->logger_mock->shouldReceive('debug')
			->once()
			->withArgs(function ($message) {
				return str_starts_with($message, 'StylesEnqueueTrait::register_styles - Exited. Remaining immediate styles: 0') && str_contains($message, '. Deferred styles:');
			});
		$this->instance->register_styles();

		// --- Assert state after registration ---
		$deferred_styles_prop = new \ReflectionProperty($this->instance, 'deferred_styles');
		$deferred_styles_prop->setAccessible(true);
		$current_deferred = $deferred_styles_prop->getValue($this->instance);
		$this->assertArrayHasKey($hook_name, $current_deferred);
		$this->assertEquals($style_handle, $current_deferred[$hook_name][0]['handle']);

		// --- Act 3: Trigger the deferred enqueue ---
		$enqueue_deferred_prefix = 'StylesEnqueueTrait::enqueue_deferred_styles - ';
		$process_single_prefix   = 'StylesEnqueueTrait::_process_single_style - ';

		// This is the key fix: "Entered hook" not "Entered for hook"
		$this->logger_mock->shouldReceive('debug')->once()->with($enqueue_deferred_prefix . "Entered hook: \"{$hook_name}\".");
		$this->logger_mock->shouldReceive('debug')->once()->with($enqueue_deferred_prefix . "Processing deferred style: \"{$style_handle}\" (original index 0) for hook: \"{$hook_name}\".");

		// _process_single_style mocks
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Processing style '{$style_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.");
		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'registered')->times(2)->andReturnValues(array(false, true));
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Registering style '{$style_handle}' on hook '{$hook_name}'.");
		WP_Mock::userFunction('wp_register_style', array('args' => array($style_handle, 'path/to/deferred.css', array(), '1.0', 'all'), 'times' => 1, 'return' => true));
		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'enqueued')->once()->andReturn(false);
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Enqueuing style '{$style_handle}' on hook '{$hook_name}'.");
		WP_Mock::userFunction('wp_enqueue_style', array('args' => array($style_handle), 'times' => 1));
		$this->logger_mock->shouldReceive('debug')->once()->with($process_single_prefix . "Finished processing style '{$style_handle}' on hook '{$hook_name}'.");

		// Final log in enqueue_deferred_styles
		$this->logger_mock->shouldReceive('debug')->once()->with($enqueue_deferred_prefix . "Exited for hook: \"{$hook_name}\".");

		// Execute
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assert final state ---
		$current_deferred_after = $deferred_styles_prop->getValue($this->instance);
		$this->assertArrayNotHasKey($hook_name, $current_deferred_after, 'Deferred styles for the hook should be cleared after processing.');
	} // End of test_enqueue_deferred_styles_processes_and_enqueues_style_on_hook

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_skips_if_hook_not_set(): void {
		// Arrange
		$hook_name  = 'non_existent_hook';
		$log_prefix = 'StylesEnqueueTrait::enqueue_deferred_styles - ';
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Entered hook: "' . $hook_name . '".');

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Hook "' . $hook_name . '" not found in deferred styles. Nothing to process.');

		// Act
		$this->instance->enqueue_deferred_styles($hook_name);

		// Assert
		// Mockery handles the primary assertions. This is a final state check for robustness.
		$deferred_styles_prop = new \ReflectionProperty($this->instance, 'deferred_styles');
		$deferred_styles_prop->setAccessible(true);
		$current_deferred = $deferred_styles_prop->getValue($this->instance);
		$this->assertArrayNotHasKey($hook_name, $current_deferred, 'Hook should not have been added to deferred_styles.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_skips_if_hook_set_but_empty(): void {
		// Arrange
		$hook_name  = 'empty_hook_for_styles';
		$log_prefix = 'StylesEnqueueTrait::enqueue_deferred_styles - ';

		// Set the deferred_styles property to have the hook, but with an empty array of styles.
		$deferred_styles_prop = new \ReflectionProperty($this->instance, 'deferred_styles');
		$deferred_styles_prop->setAccessible(true);
		$deferred_styles_prop->setValue($this->instance, array($hook_name => array()));

		// Ensure the logger is considered active for the conditional log statements.
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Entered hook: "' . $hook_name . '".');

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with($log_prefix . 'Hook "' . $hook_name . '" was set but had no styles. It has now been cleared.');

		// Act
		$this->instance->enqueue_deferred_styles($hook_name);

		// Assert
		$current_deferred = $deferred_styles_prop->getValue($this->instance);
		$this->assertArrayNotHasKey($hook_name, $current_deferred, 'The hook should be cleared from deferred styles.');
	}

	/**
	 * Tests basic addition of an inline style when the logger is inactive.
	 */
	public function test_add_inline_styles_basic_no_logger() {
		// Arrange: Configure logger to be inactive for this test
		$this->logger_mock->shouldReceive('is_active')->andReturn(false);

		$handle            = 'test-style-handle';
		$content           = '.test-class { color: blue; }';
		$expected_position = 'after'; // Default

		// Act: Call the method under test
		$this->instance->add_inline_styles( $handle, $content );

		// Assert: Check the internal $inline_styles property
		$inline_styles = $this->get_protected_property_value( $this->instance, 'inline_styles' );

		$this->assertCount( 1, $inline_styles, 'Expected one inline style to be added.' );
		$added_style = $inline_styles[0];

		$this->assertEquals( $handle, $added_style['handle'], 'Handle does not match.' );
		$this->assertEquals( $content, $added_style['content'], 'Content does not match.' );
		$this->assertEquals( $expected_position, $added_style['position'], 'Position does not match default.' );
		$this->assertNull( $added_style['condition'], 'Condition should be null by default.' );
		$this->assertNull( $added_style['parent_hook'], 'Parent hook should be null by default.' );
	} // End of test_add_inline_styles_basic_no_logger

	/**
	 * Tests addition of an inline style with an active logger and ensures correct log messages.
	 */
	public function test_add_inline_styles_with_active_logger() {
		// Arrange: Configure logger to be active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$handle  = 'test-log-handle';
		$content = '.log-class { font-weight: bold; }';
		// inline_styles is reset to [] in setUp, so initial count is 0.
		$initial_inline_styles_count = 0;

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::add_inline_styles - Entered. Current inline style count: {$initial_inline_styles_count}. Adding new inline style for handle: " . \esc_html($handle));

		// No parent hook finding log expected in this basic case as $this->styles is empty.

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('StylesEnqueueTrait::add_inline_styles - Exiting. New total inline style count: ' . ($initial_inline_styles_count + 1));

		// Act: Call the method under test
		$this->instance->add_inline_styles($handle, $content);

		// Assert: Check the internal $inline_styles property (basic check, primary assertion is logger)
		$inline_styles = $this->get_protected_property_value( $this->instance, 'inline_styles' );
		$this->assertCount($initial_inline_styles_count + 1, $inline_styles, 'Expected one inline style to be added.');
		// Get the last added style (which will be the first if initial_inline_styles_count is 0)
		$added_style = $inline_styles[$initial_inline_styles_count];

		$this->assertEquals($handle, $added_style['handle']);
		$this->assertEquals($content, $added_style['content']);
	} // End of test_add_inline_styles_with_active_logger

	/**
	 * Tests that add_inline_styles correctly associates a parent_hook
	 * from an existing registered style if not explicitly provided.
	 */
	public function test_add_inline_styles_associates_parent_hook_from_registered_styles() {
		// Arrange: Configure logger to be active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$parent_handle               = 'parent-for-inline';
		$parent_hook_name            = 'my_custom_parent_hook';
		$inline_content              = '.inline-for-parent { display: none; }';
		$initial_inline_styles_count = 0; // As it's reset in setUp

		// Pre-populate the $styles property with a parent style
		$parent_style_definition = array(
			'handle'    => $parent_handle,
			'src'       => 'path/to/parent.css',
			'deps'      => array(),
			'ver'       => '1.0',
			'media'     => 'all',
			'hook'      => $parent_hook_name, // This is key
			'condition' => null,
			'extra'     => array(),
		);
		$styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'styles');
		$styles_property->setAccessible(true);
		$styles_property->setValue($this->instance, array($parent_style_definition));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::add_inline_styles - Entered. Current inline style count: {$initial_inline_styles_count}. Adding new inline style for handle: " . \esc_html($parent_handle));

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::add_inline_styles - Inline style for '{$parent_handle}' associated with parent hook: '{$parent_hook_name}'. Original parent style hook: '{$parent_hook_name}'.");

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('StylesEnqueueTrait::add_inline_styles - Exiting. New total inline style count: ' . ($initial_inline_styles_count + 1));

		// Act: Call the method under test, $parent_hook is null by default
		$this->instance->add_inline_styles($parent_handle, $inline_content);

		// Assert: Check the internal $inline_styles property
		$inline_styles_array = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertCount($initial_inline_styles_count + 1, $inline_styles_array, 'Expected one inline style to be added.');

		$added_inline_style = $inline_styles_array[$initial_inline_styles_count];
		$this->assertEquals($parent_handle, $added_inline_style['handle']);
		$this->assertEquals($inline_content, $added_inline_style['content']);
		$this->assertEquals($parent_hook_name, $added_inline_style['parent_hook'], 'Parent hook was not correctly associated from the registered style.');
	} // End of test_add_inline_styles_associates_parent_hook_from_registered_styles

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function test_process_inline_styles_logs_and_exits_if_inline_styles_globally_empty() {
		// Arrange
		$style_handle       = 'test-parent-style';
		$processing_context = 'direct_call'; // Context for this direct test
		$style_definition   = array(
		    'handle'  => $style_handle,
		    'src'     => 'path/to/style.css',
		    'deps'    => array(),
		    'version' => false,
		    'media'   => 'all',
		);

		// Ensure $this->inline_styles is globally empty
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array());

		// Logger active for debug messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Ensure wp_style_is returns false so registration/enqueueing is attempted
		\WP_Mock::userFunction('wp_style_is')
		    ->with($style_handle, 'registered')
		    ->andReturn(false);
		\WP_Mock::userFunction('wp_style_is')
		    ->with($style_handle, 'enqueued')
		    ->andReturn(false);

		// --- Ordered Logger and WP_Mock expectations ---

		// 1. Log from _process_single_style entry
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("StylesEnqueueTrait::_process_single_style - Processing style '{$style_handle}' in context '{$processing_context}'.");

		// 2. Log before wp_register_style
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("StylesEnqueueTrait::_process_single_style - Registering style '{$style_handle}'.");

		// 3. Mock wp_register_style call, ensuring it returns true
		\WP_Mock::userFunction('wp_register_style')->once()
		    ->with($style_handle, $style_definition['src'], $style_definition['deps'], $style_definition['version'], $style_definition['media'])
		    ->andReturn(true);

		// 4. Log before wp_enqueue_style
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("StylesEnqueueTrait::_process_single_style - Enqueuing style '{$style_handle}'.");

		// 5. Mock wp_enqueue_style call
		\WP_Mock::userFunction('wp_enqueue_style')->once()->with($style_handle);

		// 6. Log before _process_inline_styles
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("StylesEnqueueTrait::_process_single_style - Checking for inline styles for '{$style_handle}'.");

		// 7. Target Log from _process_inline_styles (Gap 1)
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("StylesEnqueueTrait::_process_inline_styles (context: _{$processing_context}) - No inline styles defined globally. Nothing to process for handle '{$style_handle}'.");

		// 8. Log from _process_single_style completion
		$this->logger_mock->shouldReceive('debug')->ordered()
		    ->with("StylesEnqueueTrait::_process_single_style - Finished processing style '{$style_handle}'.");

		// Act: Call _process_single_style directly using reflection
		$method = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_single_style');
		$method->setAccessible(true);
		$method->invoke($this->instance, $style_definition, $processing_context, null, true, true); // do_register=true, do_enqueue=true

		// Assert: Mockery will assert its expectations automatically.
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function test_process_inline_styles_skips_if_condition_is_false() {
		// Arrange
		$parent_handle      = 'test-parent-style';
		$processing_context = 'direct_call';
		$style_definition   = array(
			'handle'  => $parent_handle,
			'src'     => 'path/to/parent.css',
			'deps'    => array(),
			'version' => false,
			'media'   => 'all',
		);

		// Define an inline style with a condition that returns false
		$inline_style_with_condition = array(
			'parent_handle' => $parent_handle,
			'content'       => '.conditional-style { display: none; }',
			'condition'     => function () {
				return false;
			},
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($inline_style_with_condition));

		// Logger active for debug messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Mocks for parent style processing
		\WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'registered')->andReturn(false);
		\WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'enqueued')->andReturn(false);
		\WP_Mock::userFunction('wp_register_style')->once()->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_style')->once();

		// Crucially, wp_add_inline_style should NOT be called
		\WP_Mock::userFunction('wp_add_inline_style')->never();

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Processing style '{$parent_handle}' in context '{$processing_context}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Registering style '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Enqueuing style '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Checking for inline styles for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: _{$processing_context}) - Processing inline styles for parent handle '{$parent_handle}'.");

		// Target Log for this test
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: _{$processing_context}) - Condition not met for inline style with parent '{$parent_handle}'. Skipping.");

		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: _{$processing_context}) - Finished processing inline styles for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Finished processing style '{$parent_handle}'.");

		// Act
		$method = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_single_style');
		$method->setAccessible(true);
		$method->invoke($this->instance, $style_definition, $processing_context, null, true, true);

		// Assert
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function test_process_inline_styles_skips_if_content_is_empty() {
		// Arrange
		$parent_handle      = 'test-parent-style';
		$processing_context = 'direct_call';
		$style_definition   = array(
			'handle'  => $parent_handle,
			'src'     => 'path/to/parent.css',
			'deps'    => array(),
			'version' => false,
			'media'   => 'all',
		);

		// Define an inline style with empty content
		$inline_style_empty_content = array(
			'parent_handle' => $parent_handle,
			'content'       => '', // Empty content
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($inline_style_empty_content));

		// Logger active for debug/warning messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Mocks for parent style processing
		\WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'registered')->andReturn(false);
		\WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'enqueued')->andReturn(false);
		\WP_Mock::userFunction('wp_register_style')->once()->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_style')->once();

		// Crucially, wp_add_inline_style should NOT be called
		\WP_Mock::userFunction('wp_add_inline_style')->never();

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Processing style '{$parent_handle}' in context '{$processing_context}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Registering style '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Enqueuing style '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Checking for inline styles for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: _{$processing_context}) - Processing inline styles for parent handle '{$parent_handle}'.");

		// Target Log for this test
		$this->logger_mock->shouldReceive('warning')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: _{$processing_context}) - Invalid inline style definition for parent '{$parent_handle}'. Missing content. Skipping.");

		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: _{$processing_context}) - Finished processing inline styles for '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_single_style - Finished processing style '{$parent_handle}'.");

		// Act
		$method = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_single_style');
		$method->setAccessible(true);
		$method->invoke($this->instance, $style_definition, $processing_context, null, true, true);

		// Assert
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function test_process_inline_styles_adds_style_successfully() {
		// Arrange
		$parent_handle      = 'test-parent-style';
		$processing_context = 'direct_call';
		$style_definition   = array(
			'handle'  => $parent_handle,
			'src'     => 'path/to/parent.css',
			'deps'    => array(),
			'version' => false,
			'media'   => 'all',
		);

		$inline_content  = '.successful-style { color: green; }';
		$inline_position = 'after';

		// Define a valid inline style
		$valid_inline_style = array(
			'handle'   => $parent_handle,
			'content'  => $inline_content,
			'position' => $inline_position,
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($valid_inline_style));

		// Logger active for debug messages
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Mocks for parent style processing. We assume the style is already registered and enqueued.
		\WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'registered')->andReturn(true);
		\WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'enqueued')->andReturn(true);

		// Because the style is already registered/enqueued, these should not be called.
		\WP_Mock::userFunction('wp_register_style')->never();
		\WP_Mock::userFunction('wp_enqueue_style')->never();

		// Crucially, wp_add_inline_style SHOULD be called and return true
		\WP_Mock::userFunction('wp_add_inline_style')->once()
			->with($parent_handle, $inline_content, $inline_position)
			->andReturn(true);


		// --- Ordered Logger expectations ---
		// For public enqueue_inline_styles()
		$this->logger_mock->shouldReceive('debug')->ordered()->with('StylesEnqueueTrait::enqueue_inline_styles - Entered method.');
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::enqueue_inline_styles - Found 1 unique parent handle(s) with immediate inline styles to process: {$parent_handle}");

		// For protected _process_inline_styles()
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Checking for inline styles for parent handle '{$parent_handle}'.");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Adding inline style for '{$parent_handle}' (key: 0, position: {$inline_position}).");
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Removed processed inline style with key '0' for handle '{$parent_handle}'.");

		// For public enqueue_inline_styles() - exit
		$this->logger_mock->shouldReceive('debug')->ordered()->with('StylesEnqueueTrait::enqueue_inline_styles - Exited method.');

		// Act
		$this->instance->enqueue_inline_styles();

		// Assert: WP_Mock and Mockery will verify expectations.
		$this->assertTrue(true); // Placeholder if other assertions are covered by mocks
	}

	// Expose protected method for testing
	public function call_enqueue_deferred_styles(string $hook_name): void {
		$this->_enqueue_deferred_styles($hook_name);
	}

	// Expose protected method for testing
	public function call_enqueue_inline_styles(?string $hook_name = null): void {
		$this->_enqueue_inline_styles($hook_name);
	}

	// Expose protected property for testing
	public function get_internal_styles_array(): array {
		return $this->styles;
	}




	/**
	 * Tests enqueue_inline_styles when only deferred inline styles are present.
	 * (i.e., all inline styles have a parent_hook set)
	 */
	public function test_enqueue_inline_styles_only_deferred_styles_present() {
		// Arrange: Logger is active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		// Pre-populate $this->inline_styles with a deferred style
		$deferred_inline_style = array(
			'handle'      => 'deferred-handle',
			'content'     => '.deferred { color: red; }',
			'position'    => 'after',
			'condition'   => null,
			'parent_hook' => 'some_action_hook', // Key: this makes it deferred
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($deferred_inline_style));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('StylesEnqueueTrait::enqueue_inline_styles - Entered method.');

		// This is the crucial log for this case
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with('StylesEnqueueTrait::enqueue_inline_styles - No immediate inline styles found needing processing.');



		// Act: Call the method under test
		$result = $this->instance->enqueue_inline_styles();

		// Assert: Method returns $this for chaining
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		// Mockery will assert that all expected log calls were made.
	} // End of test_enqueue_inline_styles_only_deferred_styles_present

	/**
	 * Tests enqueue_inline_styles processes a single immediate inline style,
	 * including logging and call to wp_add_inline_style.
	 */
	public function test_enqueue_inline_styles_processes_one_immediate_style() {
		// Arrange: Logger is active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$handle   = 'test-immediate-handle';
		$content  = '.immediate { border: 1px solid green; }';
		$position = 'after'; // This is logged by _process_inline_styles

		$immediate_style = array(
			'handle'      => $handle,
			'content'     => $content,
			'position'    => $position,
			'condition'   => null,
			'parent_hook' => null, // Key: makes it an "immediate" style for enqueue_inline_styles
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($immediate_style));

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with('StylesEnqueueTrait::enqueue_inline_styles - Entered method.');
		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with('StylesEnqueueTrait::enqueue_inline_styles - Found 1 unique parent handle(s) with immediate inline styles to process: ' . \esc_html($handle));

		// Logs from the call to _process_inline_styles($handle, null, 'enqueue_inline_styles')
		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Checking for inline styles for parent handle '{$handle}'.");

		// WP_Mock expectation for wp_style_is (called within _process_inline_styles)
		// This call happens between the "Processing item" log and "Successfully added" log.
		\WP_Mock::userFunction('wp_style_is', array(
			'args'   => array($handle, 'registered'),
			'times'  => 1,
			'return' => true,
		));

		// Note: If wp_style_is($handle, 'registered') was false, it would also check 'enqueued'.
		// For this test, we assume 'registered' is true, so the second check is skipped.

		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Processing inline style item for handle '{$handle}'. Content: {$content}. Position: {$position}.");

		\WP_Mock::userFunction('wp_add_inline_style', array(
			'args'   => array($handle, $content, 'after'), // Added 'after' for default position
			'times'  => 1,
			'return' => true, // Simulate successful addition
		));

		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Successfully added inline style for '{$handle}' with wp_add_inline_style.");

		$this->logger_mock->shouldReceive('debug')
			->ordered()
			->with('StylesEnqueueTrait::enqueue_inline_styles - Exited method.');

		// Act: Call the method under test
		$result = $this->instance->enqueue_inline_styles();

		// Assert: Method returns $this for chaining
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		// Mockery will assert all logger expectations.
		// WP_Mock will assert its expectations during tearDown.
	} // End of test_enqueue_inline_styles_processes_one_immediate_style

	/**
	 * Tests that enqueue_inline_styles skips invalid (non-array) inline style data
	 * and processes any valid immediate styles that might also be present.
	 */
	public function test_enqueue_inline_styles_skips_invalid_non_array_inline_style_data() {
		// Arrange: Logger is active
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);

		$valid_handle   = 'valid-handle-amidst-invalid';
		$valid_content  = '.valid-content { background: white; }';
		$valid_position = 'before';

		$valid_immediate_style = array(
			'handle'      => $valid_handle,
			'content'     => $valid_content,
			'position'    => $valid_position,
			'condition'   => null,
			'parent_hook' => null, // Immediate
		);
		$invalid_style_item = 'this is definitely not an array'; // The invalid item

		// Set $inline_styles with the invalid item first, then the valid one.
		// Keys will be 0 (invalid) and 1 (valid).
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($invalid_style_item, $valid_immediate_style));

		// --- Ordered Logger expectations ---
		$this->logger_mock->shouldReceive('debug')->ordered()->with('StylesEnqueueTrait::enqueue_inline_styles - Entered method.');

		// Expect warning for the invalid item at key '0' from the outer loop in enqueue_inline_styles
		$this->logger_mock->shouldReceive('warning')->ordered()->with("StylesEnqueueTrait::enqueue_inline_styles - Invalid inline style data at key '0'. Skipping.");

		// Then, normal processing for the valid item
		$this->logger_mock->shouldReceive('debug')->ordered()->with('StylesEnqueueTrait::enqueue_inline_styles - Found 1 unique parent handle(s) with immediate inline styles to process: ' . \esc_html($valid_handle));

		// Logs from _process_inline_styles for the valid_handle
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Checking for inline styles for parent handle '{$valid_handle}'.");

		// Mocks for wp_style_is check inside _process_inline_styles
		// We need the first check to be false to force the evaluation of the second check.
		\WP_Mock::userFunction('wp_style_is', array(
			'args'   => array($valid_handle, 'registered'),
			'times'  => 1,
			'return' => false, // Mock wp_style_is($valid_handle, 'registered') to return false.
			// In the typical condition `!wp_style_is(..., 'registered') && !wp_style_is(..., 'enqueued')`,
			// this `!false` part evaluates to `true`.
		));
		// We need the second check to be true so the overall condition is false and the method continues.
		\WP_Mock::userFunction('wp_style_is', array(
			'args'   => array($valid_handle, 'enqueued'),
			'times'  => 1,
			'return' => true,  // Mock wp_style_is($valid_handle, 'enqueued') to return true.
			// In the typical condition `!wp_style_is(..., 'registered') && !wp_style_is(..., 'enqueued')`,
			// this `!true` part evaluates to `false`.
			// Thus, the overall condition `(true && false)` becomes `false`,
			// allowing inline script processing to proceed.
		));

		// This warning is logged by _process_inline_styles when it encounters the non-array item at key 0
		$this->logger_mock->shouldReceive('warning')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Invalid inline style data at key '0'. Skipping.");

		// This is the log for the valid item
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Processing inline style item for handle '{$valid_handle}'. Content: {$valid_content}. Position: {$valid_position}.");

		// The actual inline style call
		\WP_Mock::userFunction('wp_add_inline_style', array(
			'args'   => array($valid_handle, $valid_content, $valid_position),
			'times'  => 1,
			'return' => true,
		));

		// The success log after the call
		$this->logger_mock->shouldReceive('debug')->ordered()->with("StylesEnqueueTrait::_process_inline_styles (context: enqueue_inline_styles) - Successfully added inline style for '{$valid_handle}' with wp_add_inline_style.");

		// The final exit log
		$this->logger_mock->shouldReceive('debug')->ordered()->with('StylesEnqueueTrait::enqueue_inline_styles - Exited method.');

		// Act
		$result = $this->instance->enqueue_inline_styles();

		// Assert
		$this->assertSame($this->instance, $result, 'Method should return $this for chaining.');
		// Mockery and WP_Mock will assert their expectations.
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::register_styles
	 * Tests that the correct log message is emitted when attempting to add a deferred style action
	 * for a hook that already has that action registered.
	 */
	public function test_register_styles_logs_when_deferred_action_already_exists() {
		// Arrange
		$this->logger_mock->shouldReceive('is_active')->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldIgnoreMissing();

		$hook_name     = 'my_custom_hook';
		$style1_handle = 'deferred-style-1';
		$style2_handle = 'deferred-style-2';

		$styles_data = array(
			$style1_handle => array(
				'handle' => $style1_handle,
				'src'    => 'path/to/style1.css',
				'deps'   => array(),
				'ver'    => false,
				'media'  => 'all',
				'hook'   => $hook_name,
			),
			$style2_handle => array(
				'handle' => $style2_handle,
				'src'    => 'path/to/style2.css',
				'hook'   => $hook_name, // Same hook
			),
		);

		$this->instance->add_styles($styles_data);

		$testInstance    = $this->instance; // Capture $this->instance for the closure
		$callbackMatcher = \Mockery::on(function ($actual_callback_arg) use ($testInstance) {
			if (!is_array($actual_callback_arg) || count($actual_callback_arg) !== 2) {
				return false;
			}
			// Exact instance check
			if ($actual_callback_arg[0] !== $testInstance) {
				return false;
			}
			if ($actual_callback_arg[1] !== 'enqueue_deferred_styles') {
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

		\WP_Mock::expectActionAdded($hook_name, array( $this->instance, 'enqueue_deferred_styles' ), 10, 1);

		// Logger expectations (Mockery ordering removed to avoid conflict with WP_Mock ordering)
		$this->logger_mock->shouldReceive('debug')
			->with("StylesEnqueueTrait::register_styles - Added action for 'enqueue_deferred_styles' on hook: {$hook_name}.") // LOG A1
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("StylesEnqueueTrait::register_styles - Action for 'enqueue_deferred_styles' on hook '{$hook_name}' already exists.") // LOG B
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("StylesEnqueueTrait::register_styles - Deferred action for hook '{$hook_name}' was already added by this instance.") // LOG C
			->never();

		// Act
		$this->instance->register_styles();

		// Assert (WP_Mock and Mockery will verify expectations)
		$this->assertTrue(true);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * Tests that _process_single_style logs and skips registration if a style is already registered.
	 */
	public function test_process_single_style_logs_when_style_already_registered_and_skips_reregistration(): void {
		$style_handle = 'my-already-registered-style';
		$style_src    = 'path/to/style.css';
		$styles_data  = array(
			$style_handle => array(
				'handle' => $style_handle,
				'src'    => $style_src,
				'hook'   => null, // Process immediately
			),
		);

		// Set up logger expectations for add_styles
		$this->logger_mock->shouldReceive('debug')
			->with('StylesEnqueueTrait::add_styles - Entered. Current style count: 0. Adding 1 new style(s).')
			->ordered();
		$this->logger_mock->shouldReceive('debug')
			->with("StylesEnqueueTrait::add_styles - Adding style. Key: {$style_handle}, Handle: {$style_handle}, Src: {$style_src}")
			->ordered();
		$this->logger_mock->shouldReceive('debug')
			->with('StylesEnqueueTrait::add_styles - Exiting. New total style count: 1')
			->ordered();

		$this->instance->add_styles($styles_data);

		// Assert that inline_styles array is empty after add_styles and before register_styles is called
		$this->assertEmpty($this->instance->get_internal_inline_styles_array(), 'Inline styles array should be empty before register_styles call.');

		// Mock wp_style_is to indicate the style is already registered
		// Called once in _process_single_style, once in _process_inline_styles
		\WP_Mock::userFunction('wp_style_is')
			->times(2)
			->with($style_handle, 'registered')
			->andReturn(true);

		// Mock wp_style_is for 'enqueued' check within _process_inline_styles
		// This should NOT be called if 'registered' is true due to short-circuiting in the IF condition.
		\WP_Mock::userFunction('wp_style_is')
			->never()
			->with($style_handle, 'enqueued');

		// Expect wp_register_style NOT to be called
		\WP_Mock::userFunction('wp_register_style')
			->never();

		// Expect the specific debug log messages in order
		// From _process_single_style
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::_process_single_style - Processing style '{$style_handle}' in context 'register_styles'.")
			->ordered();

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::_process_single_style - Style '{$style_handle}' already registered. Skipping wp_register_style.")
			->ordered();

		// From _process_inline_styles (called by _process_single_style)
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::_process_inline_styles (context: register_styles) - Checking for inline styles for parent handle '{$style_handle}'.")
			->ordered();

		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::_process_inline_styles (context: register_styles) - No inline styles found or processed for '{$style_handle}'.")
			->ordered();

		// From _process_single_style (finish)
		$this->logger_mock->shouldReceive('debug')
			->once()
			->with("StylesEnqueueTrait::_process_single_style - Finished processing style '{$style_handle}'.")
			->ordered();

		// Act: register_styles will call _process_single_style with $do_register = true
		$this->instance->register_styles();

		// Assert: Mockery and WP_Mock will verify expectations.
		$this->assertTrue(true); // Placeholder assertion
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * Tests that _process_single_style logs and skips enqueueing if a style is already enqueued.
	 */
	public function test_process_single_style_logs_when_style_already_enqueued_and_skips_re_enqueue(): void {
		// Arrange
		$sut = new ConcreteEnqueueForStylesTesting($this->config_mock);

		$style_handle       = 'my-already-enqueued-style';
		$hook_name          = 'my_custom_hook';
		$processing_context = 'test_context';
		$style_definition   = array(
			'handle' => $style_handle,
			'src'    => 'path/to/style.css',
		);

		// Mock WordPress functions
		\WP_Mock::userFunction('wp_style_is', array(
			'args'   => array($style_handle, 'registered'),
			'return' => true,
		));
		\WP_Mock::userFunction('wp_style_is', array(
			'args'   => array($style_handle, 'enqueued'),
			'return' => true,
		));

		// Ordered expectations ONLY for is_active calls (when hook_name is NOT null, inline processing in _process_single_style is skipped)
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 1. Entry log (_process_single_style)
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 2. "already registered" log (_process_single_style)
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 3. "already enqueued" log (_process_single_style)
		// Final log from _process_single_style
		$this->logger_mock->shouldReceive('is_active')->ordered()->andReturn(true); // 4. Exit log (_process_single_style)

		// Non-ordered expectations for debug calls that WILL occur
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"StylesEnqueueTrait::_process_single_style - Processing style '{$style_handle}' on hook '{$hook_name}' in context '{$processing_context}'."
		);
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"StylesEnqueueTrait::_process_single_style - Style '{$style_handle}' on hook '{$hook_name}' already registered. Skipping wp_register_style."
		);
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"StylesEnqueueTrait::_process_single_style - Style '{$style_handle}' on hook '{$hook_name}' already enqueued. Skipping wp_enqueue_style."
		);
		// Logs for inline style processing are NOT expected here because $hook_name is not null
		$this->logger_mock->shouldReceive('debug')->once()->with(
			"StylesEnqueueTrait::_process_single_style - Finished processing style '{$style_handle}' on hook '{$hook_name}'."
		);

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_single_style');
		$reflection->setAccessible(true);
		$result = $reflection->invoke(
			$sut,
			$style_definition,
			$processing_context,
			$hook_name,
			true, // do_register
			true  // do_enqueue
		);

		// Assert
		$this->assertSame($style_handle, $result, 'Method should return the handle on success.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * Tests that _process_single_style returns false for an invalid style definition.
	 */
	public function test_process_single_style_returns_false_for_invalid_definition(): void {
		// Arrange
		$sut              = new ConcreteEnqueueForStylesTesting($this->config_mock);
		$style_definition = array(
			'handle' => 'my-invalid-style',
			'src'    => '', // Invalid src makes it invalid when do_register is true
		);
		$hook_name          = 'test_hook';
		$processing_context = 'test_context';

		$this->logger_mock->shouldReceive('debug')
			->once()
			->withArgs(function ($message) use ($style_definition, $hook_name, $processing_context) {
				return str_contains($message, "Processing style '{$style_definition['handle']}'") && str_contains($message, "on hook '{$hook_name}'") && str_contains($message, "in context '{$processing_context}'");
			})
			->ordered();

		$this->logger_mock->shouldReceive('warning')
			->once()
			->with("StylesEnqueueTrait::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: '{$style_definition['handle']}' on hook '{$hook_name}'.")
			->ordered();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_single_style');
		$reflection->setAccessible(true);
		$result = $reflection->invoke(
			$sut,
			$style_definition,
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_single_style
	 * Tests that _process_single_style returns false when wp_register_style fails.
	 */
	public function test_process_single_style_returns_false_on_registration_failure(): void {
		// Arrange
		$sut                = new ConcreteEnqueueForStylesTesting($this->config_mock);
		$style_handle       = 'my-failing-style';
		$hook_name          = 'test_hook';
		$processing_context = 'test_context';
		$style_definition   = array(
			'handle' => $style_handle,
			'src'    => 'path/to/style.css',
		);

		\WP_Mock::userFunction('wp_style_is', array(
			'args'   => array($style_handle, 'registered'),
			'return' => false,
			'times'  => 1,
		));

		\WP_Mock::userFunction('wp_register_style', array(
			'args' => array(
				$style_handle,
				$style_definition['src'],
				array(),      // deps
				false,   // version
				'all'    // media
			),
			'return' => false, // Simulate failure
			'times'  => 1,
		));

		$this->logger_mock->shouldReceive('debug')->once()->with(Mockery::pattern("/Processing style '{$style_handle}'/"))->ordered();
		$this->logger_mock->shouldReceive('debug')->once()->with(Mockery::pattern("/Registering style '{$style_handle}'/"))->ordered();
		$this->logger_mock->shouldReceive('warning')->once()->with(Mockery::pattern("/wp_register_style\\(\\) failed for handle '{$style_handle}'/"))->ordered();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_single_style');
		$reflection->setAccessible(true);
		$result = $reflection->invoke(
			$sut,
			$style_definition,
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
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function test_process_inline_styles_handles_deferred_style_with_matching_hook(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);
		$parent_handle = 'parent-style';
		$hook_name     = 'a_custom_hook';
		$inline_style  = array(
			'handle'      => $parent_handle,
			'content'     => '.my-class { color: red; }',
			'parent_hook' => $hook_name,
			'position'    => 'after',
		);
		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('inline_styles');
		$property->setAccessible(true);
		$property->setValue($sut, array($inline_style));

		WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'registered')->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: deferred) - Checking for inline styles for parent handle 'parent-style' on hook 'a_custom_hook'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: deferred) - Adding inline style for 'parent-style' (key: 0, position: after) on hook 'a_custom_hook'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: deferred) - Removed processed inline style with key '0' for handle 'parent-style' on hook 'a_custom_hook'.");

		WP_Mock::userFunction('wp_add_inline_style')->with('parent-style', '.my-class { color: red; }', 'after')->once();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_inline_styles');
		$reflection->setAccessible(true);
		$reflection->invoke($sut, $parent_handle, $hook_name, 'deferred');

		WP_Mock::assertActionsCalled();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function testProcessInlineStylesSkipsStyleWhenConditionIsFalse(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$this->logger_mock->shouldReceive('is_active')->andReturn(true);
		$parent_handle = 'parent-style';
		$inline_style  = array(
			'handle'    => $parent_handle,
			'content'   => '.my-class { color: blue; }',
			'condition' => fn() => false,
		);
		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('inline_styles');
		$property->setAccessible(true);
		$property->setValue($sut, array($inline_style));

		WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'registered')->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: immediate) - Checking for inline styles for parent handle 'parent-style'.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: immediate) - Condition false for inline style targeting 'parent-style' (key: 0).");
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_inline_styles');
		$reflection->setAccessible(true);
		$reflection->invoke($sut, $parent_handle, null, 'immediate');

		WP_Mock::assertActionsCalled();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::_process_inline_styles
	 */
	public function testProcessInlineStylesSkipsStyleWithEmptyContent(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();
		$parent_handle = 'parent-style';
		$inline_style  = array(
			'handle'  => $parent_handle,
			'content' => '', // Empty content
		);
		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('inline_styles');
		$property->setAccessible(true);
		$property->setValue($sut, array($inline_style));

		WP_Mock::userFunction('wp_style_is')->with($parent_handle, 'registered')->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: immediate) - Checking for inline styles for parent handle 'parent-style'.");
		$this->logger_mock->shouldReceive('warning')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: immediate) - Empty content for inline style targeting 'parent-style' (key: 0). Skipping addition.");
		$this->logger_mock->shouldReceive('debug')->once()->with("StylesEnqueueTrait::_process_inline_styles (context: immediate) - Removed processed inline style with key '0' for handle 'parent-style'.");
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// Act
		$reflection = new \ReflectionMethod(ConcreteEnqueueForStylesTesting::class, '_process_inline_styles');
		$reflection->setAccessible(true);
		$reflection->invoke($sut, $parent_handle, null, 'immediate');

		WP_Mock::assertActionsCalled();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait::enqueue_styles
	 */
	public function testEnqueueStylesThrowsExceptionForDeferredStyleInQueue(): void {
		// Arrange
		$sut = Mockery::mock(ConcreteEnqueueForStylesTesting::class, array($this->config_mock))->makePartial();
		$sut->shouldAllowMockingProtectedMethods();
		$sut->shouldReceive('get_logger')->andReturn($this->logger_mock)->byDefault();

		$deferred_style = array(
			'handle' => 'deferred-style',
			'src'    => 'path/to/style.css',
			'hook'   => 'wp_footer',
		);

		$reflection = new \ReflectionObject($sut);
		$property   = $reflection->getProperty('styles');
		$property->setAccessible(true);
		$property->setValue($sut, array($deferred_style));

		// Expect
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage(
			"StylesEnqueueTrait::enqueue_styles - Found a deferred style ('deferred-style') in the immediate queue. " .
			'The `register_styles()` method must be called before `enqueue_styles()` to correctly process deferred styles.'
		);

		// Act
		$sut->enqueue_styles();
	}
}
