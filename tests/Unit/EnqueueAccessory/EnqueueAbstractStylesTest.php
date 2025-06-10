<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\EnqueueAbstract;
use Ran\PluginLib\Util\Logger;
use WP_Mock;
use Mockery;
use Mockery\MockInterface;

/**
 * Concrete implementation of EnqueueAbstract for testing style-related methods.
 */
class ConcreteEnqueueForStylesTesting extends EnqueueAbstract {
	public function load(): void {
		// Minimal implementation for testing purposes.
		// This might involve calling $this->enqueue() or specific hook registrations
		// if your tests need to simulate the load lifecycle.
	}
}

/**
 * Class EnqueueAbstractStylesTest
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract
 */
class EnqueueAbstractStylesTest extends PluginLibTestCase {
	private static int $hasActionCallCount = 0;
	/** @var ConfigInterface&MockInterface */
	protected MockInterface $config_instance_mock;

	/** @var Logger&MockInterface */
	protected MockInterface $logger_mock;

	/** @var ConcreteEnqueueForStylesTesting&MockInterface */
	protected $instance; // Mockery will handle the type

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		self::$hasActionCallCount = 0;

		$this->config_instance_mock = Mockery::mock(ConfigInterface::class);
		$this->logger_mock          = Mockery::mock(Logger::class);
		$this->config_instance_mock->shouldReceive('get')->with('logger')->byDefault()->andReturn($this->logger_mock);

		// Default behavior for logger
		$this->logger_mock->shouldReceive('is_active')->byDefault()->andReturn(true);
		$this->logger_mock->shouldReceive('is_verbose')->byDefault()->andReturn(true);
		// $this->logger_mock->shouldReceive('debug')->withAnyArgs()->byDefault()->andReturnNull(); // Commented out to ensure add_action is hit
		$this->logger_mock->shouldReceive('error')->withAnyArgs()->byDefault()->andReturnNull();
		$this->logger_mock->shouldReceive('info')->withAnyArgs()->byDefault()->andReturnNull();

		// Create a partial mock of ConcreteEnqueueForStylesTesting
		$this->instance = Mockery::mock(
			ConcreteEnqueueForStylesTesting::class,
			array($this->config_instance_mock) // Constructor arguments
		)->makePartial();
		$this->instance->shouldAllowMockingProtectedMethods();

		// Mock get_logger() to return our logger mock
		$this->instance->shouldReceive('get_logger')->byDefault()->andReturn($this->logger_mock);

		// Default WP_Mock function mocks for style functions
		WP_Mock::userFunction('wp_register_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('wp_enqueue_style')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('wp_add_inline_style')->withAnyArgs()->andReturn(true)->byDefault();
		WP_Mock::userFunction('did_action')->withAnyArgs()->andReturn(0)->byDefault(); // 0 means false
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn(null)->byDefault();
		WP_Mock::userFunction('is_admin')->andReturn(false)->byDefault(); // Default to not admin context
		WP_Mock::userFunction('wp_doing_ajax')->andReturn(false)->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	// ------------------------------------------------------------------------
	// Test Methods for Style Functionalities
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_styles
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

		// Logger expectations for add_styles()
		$expected_log_message = 'EnqueueAbstract: Adding ' . count($styles_to_add) . ' styles to the queue.';
		$this->logger_mock->shouldReceive('debug')
		    ->with($expected_log_message)
		    ->once();

		// Call the method under test
		$result = $this->instance->add_styles($styles_to_add);

		// Assert chainability
		$this->assertSame($this->instance, $result, 'add_styles() should be chainable.');

		// Retrieve and check stored styles
		$retrieved_styles_array = $this->instance->get_styles();
		$this->assertArrayHasKey('general', $retrieved_styles_array);
		$this->assertArrayHasKey('deferred', $retrieved_styles_array);
		$this->assertArrayHasKey('inline', $retrieved_styles_array);

		$general_styles = $retrieved_styles_array['general'];
		$this->assertCount(count($styles_to_add), $general_styles);

		// Check first style
		$this->assertEquals($styles_to_add[0]['handle'], $general_styles[0]['handle']);
		$this->assertEquals($styles_to_add[0]['src'], $general_styles[0]['src']);
		$this->assertEquals($styles_to_add[0]['deps'], $general_styles[0]['deps']);
		$this->assertEquals($styles_to_add[0]['version'], $general_styles[0]['version']);
		$this->assertEquals($styles_to_add[0]['media'], $general_styles[0]['media']);
		$this->assertTrue(is_callable($general_styles[0]['condition']));
		$this->assertTrue(($general_styles[0]['condition'])());

		// Check second style (and default condition handling)
		$this->assertEquals($styles_to_add[1]['handle'], $general_styles[1]['handle']);
		$this->assertEquals($styles_to_add[1]['src'], $general_styles[1]['src']);
		$this->assertArrayNotHasKey('condition', $styles_to_add[1]); // Original doesn't have it
		$this->assertArrayNotHasKey('condition', $general_styles[1]); // Stored shouldn't magically get it if not processed yet
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_should_process_and_enqueue_direct_styles(): void {
		$styles_to_enqueue = array(
		    array( // Style 1: All params, condition true
		        'handle'    => 'direct-style-1',
		        'src'       => 'path/to/direct-style-1.css',
		        'deps'      => array('dependency-1'),
		        'version'   => '1.1.0',
		        'media'     => 'screen',
		        'condition' => static fn() => true,
		        // 'hook' is omitted for direct enqueue
		    ),
		    array( // Style 2: Minimal params (handle, src), no condition
		        'handle' => 'direct-style-2',
		        'src'    => 'path/to/direct-style-2.css',
		        // 'deps', 'version', 'media', 'condition', 'hook' omitted
		    ),
		);

		$expected_style_count = count($styles_to_enqueue);

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Entered. Processing {$expected_style_count} style definition(s).")->once();

		// Style 1: direct-style-1
		// Log from enqueue_styles() - just the initial processing log for the style
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Processing style: "direct-style-1", original index: 0.')->once();
		// Logs from _process_single_style() for 'direct-style-1'
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Processing style \'direct-style-1\' in context \'enqueue_styles\'.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Registering style \'direct-style-1\'.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Enqueuing style \'direct-style-1\'.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Checking for inline styles for \'direct-style-1\'.')->once();
		// Logs from _process_inline_styles() for 'direct-style-1'
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle 'direct-style-1'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - No inline styles found or processed for 'direct-style-1'.")->once();
		// Log from _process_single_style() finishing 'direct-style-1'
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Finished processing style \'direct-style-1\'.')->once();

		// Style 2: direct-style-2
		// Log from enqueue_styles() - just the initial processing log for the style
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Processing style: "direct-style-2", original index: 1.')->once();
		// Logs from _process_single_style() for 'direct-style-2'
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Processing style \'direct-style-2\' in context \'enqueue_styles\'.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Registering style \'direct-style-2\'.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Enqueuing style \'direct-style-2\'.')->once();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Checking for inline styles for \'direct-style-2\'.')->once();
		// Logs from _process_inline_styles() for 'direct-style-2'
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle 'direct-style-2'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - No inline styles found or processed for 'direct-style-2'.")->once();
		// Log from _process_single_style() finishing 'direct-style-2'
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::_process_single_style - Finished processing style \'direct-style-2\'.')->once();

		// Final log from enqueue_styles()
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once();

		// --- WordPress Function Mocks ---
		// Style 1
		WP_Mock::userFunction('wp_register_style')
		    ->with('direct-style-1', 'path/to/direct-style-1.css', array('dependency-1'), '1.1.0', 'screen')
		    ->once()
		    ->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')
		    ->with('direct-style-1')
		    ->once();

		// Style 2 (defaults for deps, version, media)
		WP_Mock::userFunction('wp_register_style')
		    ->with('direct-style-2', 'path/to/direct-style-2.css', array(), false, 'all') // Note: default version is false, default media 'all'
		    ->once()
		    ->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')
		    ->with('direct-style-2')
		    ->once();

		// Call the method under test
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert chainability
		$this->assertSame($this->instance, $result, 'enqueue_styles() should be chainable.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_should_skip_styles_with_failing_condition(): void {
		$styles_to_enqueue = array(
		    array( // Style 1: Condition fails
		        'handle'    => 'style-fail',
		        'src'       => 'path/to/style-fail.css',
		        'condition' => static fn() => false,
		    ),
		    array( // Style 2: Condition passes
		        'handle'    => 'style-pass',
		        'src'       => 'path/to/style-pass.css',
		        'condition' => static fn() => true,
		    ),
		);

		$expected_style_count = count($styles_to_enqueue);

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Entered. Processing {$expected_style_count} style definition(s).")->once();

		// Style 1 (fail) expectations
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Processing style: "style-fail", original index: 0.')->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style 'style-fail' in context 'enqueue_styles'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Condition not met for style 'style-fail'. Skipping.")->once();
		// No "Finished processing style 'style-fail'" log as _process_single_style exits early.
		// No registration, enqueueing, or inline style processing logs for style-fail.

		// Style 2 (pass) expectations
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Processing style: "style-pass", original index: 1.')->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style 'style-pass' in context 'enqueue_styles'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style 'style-pass'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style 'style-pass'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for 'style-pass'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle 'style-pass'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - No inline styles found or processed for 'style-pass'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style 'style-pass'.")->once();

		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once();

		// --- WordPress Function Mocks ---
		// Style 1 (fail) - should NOT be called
		WP_Mock::userFunction('wp_register_style')
		    ->with('style-fail', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())
		    ->never(); // Crucial expectation
		WP_Mock::userFunction('wp_enqueue_style')
		    ->with('style-fail')
		    ->never(); // Crucial expectation

		// Style 2 (pass) - should be called
		WP_Mock::userFunction('wp_register_style')
		    ->with('style-pass', 'path/to/style-pass.css', array(), false, 'all')
		    ->once()
		    ->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')
		    ->with('style-pass')
		    ->once();

		// Call the method under test
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert chainability
		$this->assertSame($this->instance, $result, 'enqueue_styles() should be chainable.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_should_warn_and_skip_invalid_direct_styles(): void {
		$styles_to_enqueue = array(
		    array( // Style 1: Missing handle
		        // 'handle' => 'missing-handle-style',
		        'src' => 'path/to/style1.css',
		    ),
		    array( // Style 2: Missing src
		        'handle' => 'missing-src-style',
		        // 'src'    => 'path/to/style2.css',
		    ),
		    array( // Style 3: Valid style
		        'handle' => 'valid-style',
		        'src'    => 'path/to/valid-style.css',
		    ),
		);

		$expected_style_count = count($styles_to_enqueue);

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::enqueue_styles - Entered. Processing {$expected_style_count} style definition(s).")
		    ->once();

		// Style 1 (missing handle)
		$this->logger_mock->shouldReceive('debug')
		    ->with('EnqueueAbstract::enqueue_styles - Processing style: "N/A", original index: 0.') // handle_for_log is 'N/A'
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_single_style - Processing style 'N/A' in context 'enqueue_styles'.")
		    ->once();
		$this->logger_mock->shouldReceive('warning')
		    ->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: 'N/A'.")
		    ->once();

		// Style 2 (missing src)
		$this->logger_mock->shouldReceive('debug')
		    ->with('EnqueueAbstract::enqueue_styles - Processing style: "missing-src-style", original index: 1.')
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_single_style - Processing style 'missing-src-style' in context 'enqueue_styles'.")
		    ->once();
		$this->logger_mock->shouldReceive('warning')
		    ->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: 'missing-src-style'.")
		    ->once();

		// Style 3 (valid)
		$this->logger_mock->shouldReceive('debug')
		    ->with('EnqueueAbstract::enqueue_styles - Processing style: "valid-style", original index: 2.')
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_single_style - Processing style 'valid-style' in context 'enqueue_styles'.")
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_single_style - Registering style 'valid-style'.")
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_single_style - Enqueuing style 'valid-style'.")
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_single_style - Checking for inline styles for 'valid-style'.")
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle 'valid-style'.")
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - No inline styles found or processed for 'valid-style'.")
		    ->once();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_single_style - Finished processing style 'valid-style'.")
		    ->once();

		$this->logger_mock->shouldReceive('debug')
		    ->with('EnqueueAbstract::enqueue_styles - Exited.')
		    ->once();

		// --- WordPress Function Mocks ---
		// Invalid styles should NOT lead to WP function calls
		WP_Mock::userFunction('wp_register_style')->never()->with('missing-handle-style', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());
		WP_Mock::userFunction('wp_enqueue_style')->never()->with('missing-handle-style');

		WP_Mock::userFunction('wp_register_style')->never()->with('missing-src-style', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());
		WP_Mock::userFunction('wp_enqueue_style')->never()->with('missing-src-style');

		// Valid style
		WP_Mock::userFunction('wp_register_style')
		    ->with('valid-style', 'path/to/valid-style.css', array(), false, 'all')
		    ->once()
		    ->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')
		    ->with('valid-style')
		    ->once();

		// Call the method under test
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert chainability
		$this->assertSame($this->instance, $result, 'enqueue_styles() should be chainable.');
	}



	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_processes_immediate_inline_styles_correctly(): void {
		$main_style_handle    = 'immediate-main-style';
		$main_style_src       = 'path/to/immediate-main.css';
		$inline_style_content = '.immediate-inline { color: blue; }';
		$inline_style_key     = 'test_inline_immediate_1';

		$styles_to_enqueue = array(
			array(
				'handle'    => $main_style_handle,
				'src'       => $main_style_src,
				'deps'      => array(),
				'version'   => '1.0',
				'media'     => 'all',
				'condition' => null, // No condition for main style
				'hook'      => null, // Immediate
			),
		);

		// Set up the inline style directly for predictable key and to focus test on enqueue_styles
		$initial_inline_styles_array = array(
		    $inline_style_key => array(
		        'handle'      => $main_style_handle,
		        'content'     => $inline_style_content,
		        'position'    => 'after',
		        'condition'   => null,
		        'parent_hook' => null, // Crucial: no parent_hook for immediate processing
		    )
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, $initial_inline_styles_array);


		// --- Logger Expectations ---
		// Logs from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$main_style_handle}\", original index: 0.")->once();

		// Logs from _process_single_style (context: 'enqueue_styles')
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$main_style_handle}' in context 'enqueue_styles'.")->once()->ordered();
		// REMOVED: Style status log, as it no longer exists
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$main_style_handle}'.")->once()->ordered();
		// REMOVED: Registered style log, as it no longer exists
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$main_style_handle}'.")->once()->ordered();
		// REMOVED: Enqueued style log, as it no longer exists
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$main_style_handle}'.")->once()->ordered();

		// Logs from _process_inline_styles (context: 'enqueue_styles', parent_handle: $main_style_handle)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Adding inline style for '{$main_style_handle}' (key: {$inline_style_key}, position: after).")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Removed processed inline style with key '{$inline_style_key}' for handle '{$main_style_handle}'.")->once()->ordered();

		// Log from _process_single_style after inline styles
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$main_style_handle}'.")->once()->ordered();

		// Log from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once()->ordered();


		// --- WP_Mock Expectations ---
		WP_Mock::userFunction('wp_register_style')
			->with($main_style_handle, $main_style_src, array(), '1.0', 'all')
			->once()
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')
			->with($main_style_handle)
			->once()
			->andReturnNull();
		WP_Mock::userFunction('wp_add_inline_style')
			->with($main_style_handle, $inline_style_content, 'after')
			->once()
			->andReturn(true);

		// --- Call Method ---
		$this->instance->enqueue_styles($styles_to_enqueue);

		// --- Assertions ---
		// Assert that the inline style was removed from the internal array
		$final_inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertArrayNotHasKey($inline_style_key, $final_inline_styles, "Processed inline style with key '{$inline_style_key}' should have been removed.");
		$this->assertEmpty($final_inline_styles, 'Inline styles array should be empty after processing the only one.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_respects_condition_for_immediate_inline_styles(): void {
		$main_style_handle      = 'immediate-conditional-style';
		$main_style_src         = 'path/to/immediate-conditional.css';
		$inline_style_content   = '.immediate-conditional-inline { font-weight: bold; }';
		$inline_style_key_true  = 'inline_cond_true';
		$inline_style_key_false = 'inline_cond_false';

		$styles_to_enqueue = array(
			array(
				'handle' => $main_style_handle,
				'src'    => $main_style_src,
				'hook'   => null, // Immediate
			),
		);

		// --- Scenario 1: Condition returns true ---_MOCK_RESET_NEEDED_HERE_MAYBE
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->byDefault()->andReturnNull(); // Reset general debug expectations for clarity
		$this->logger_mock->shouldReceive('is_active')->byDefault()->andReturn(true);

		$initial_inline_styles_true = array(
			$inline_style_key_true => array(
				'handle'      => $main_style_handle,
				'content'     => $inline_style_content,
				'condition'   => static fn() => true,
				'parent_hook' => null,
			)
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, $initial_inline_styles_true);

		// --- Logger Expectations for Scenario 1 (Condition True) ---
		// Logs from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$main_style_handle}\", original index: 0.")->once()->ordered();

		// Logs from _process_single_style (context: 'enqueue_styles')
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$main_style_handle}' in context 'enqueue_styles'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$main_style_handle}'.")->once()->ordered();

		// Logs from _process_inline_styles (context: 'enqueue_styles', parent_handle: $main_style_handle)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle '{$main_style_handle}'.")->once()->ordered();
		// REMOVED: Condition ... is TRUE log, as it doesn't exist.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Adding inline style for '{$main_style_handle}' (key: {$inline_style_key_true}, position: after).")->once()->ordered(); // Position 'after' is from the inline style definition
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Removed processed inline style with key '{$inline_style_key_true}' for handle '{$main_style_handle}'.")->once()->ordered();

		// Log from _process_single_style after inline styles
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$main_style_handle}'.")->once()->ordered();

		// Log from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once()->ordered();

		// --- WP_Mock Expectations for Scenario 1 ---
		WP_Mock::userFunction('wp_register_style')->with($main_style_handle, $main_style_src, Mockery::any(), Mockery::any(), Mockery::any())->once()->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')->with($main_style_handle)->once()->andReturnNull();
		WP_Mock::userFunction('wp_add_inline_style')->with($main_style_handle, $inline_style_content, 'after')->once()->andReturn(true);

		$this->instance->enqueue_styles($styles_to_enqueue);
		$final_inline_styles_true = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertArrayNotHasKey($inline_style_key_true, $final_inline_styles_true, 'Inline style (condition true) should have been removed.');

		// --- Scenario 2: Condition returns false ---
		// Reset mocks for the second scenario (important for Mockery expectation counts)
		Mockery::close(); // Close previous Mockery session
		$this->setUp(); // Re-run setUp to get fresh mocks

		$initial_inline_styles_false = array(
			$inline_style_key_false => array(
				'handle'      => $main_style_handle,
				'content'     => $inline_style_content,
				'condition'   => static fn() => false,
				'parent_hook' => null,
			)
		);
		$inline_styles_property->setValue($this->instance, $initial_inline_styles_false); // Use the same property reflection

		// --- Logger Expectations for Scenario 2 (Condition False) ---
		$this->logger_mock->shouldReceive('is_active')->andReturn(true); // Explicitly set for Scenario 2
		// Logs from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$main_style_handle}\", original index: 0.")->once()->ordered();

		// Logs from _process_single_style (context: 'enqueue_styles', handle: $main_style_handle)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$main_style_handle}' in context 'enqueue_styles'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$main_style_handle}'.")->once()->ordered();

		// Logs from _process_inline_styles (context: 'enqueue_styles', parent_handle: $main_style_handle)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Condition false for inline style targeting '{$main_style_handle}' (key: {$inline_style_key_false}).")->once()->ordered();
		// The inline style is still removed from the internal array even if skipped
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Removed processed inline style with key '{$inline_style_key_false}' for handle '{$main_style_handle}'.")->once()->ordered();

		// Log from _process_single_style after inline styles
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$main_style_handle}'.")->once()->ordered();

		// Log from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once()->ordered();

		// --- WP_Mock Expectations for Scenario 2 ---
		WP_Mock::userFunction('wp_register_style')->with($main_style_handle, $main_style_src, Mockery::any(), Mockery::any(), Mockery::any())->once()->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')->with($main_style_handle)->once()->andReturnNull();
		WP_Mock::userFunction('wp_add_inline_style')->with($main_style_handle, $inline_style_content, 'after')->never(); // Should NOT be called

		$this->instance->enqueue_styles($styles_to_enqueue);
		$final_inline_styles_false = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertArrayNotHasKey($inline_style_key_false, $final_inline_styles_false, 'Inline style (condition false) should have been removed because conditions are checked and items removed from the array regardless of outcome.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_processes_valid_style_on_hook(): void {
		$hook_name = 'test_deferred_hook';
		$style_def = array(
			'handle'    => 'deferred-style-1',
			'src'       => 'path/to/deferred-style-1.css',
			'deps'      => array( 'jquery' ),
			'version'   => '1.1.0',
			'media'     => 'print',
			'condition' => null,
		);

		// Manually set the deferred_styles property
		$deferred_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, array($hook_name => array(0 => $style_def))); // Store with original index 0

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style_def['handle']}\" (original index 0) for hook: \"{$hook_name}\".")->once()->ordered();

		// (wp_style_is calls will return false for 'registered' and 'enqueued' as per WP_Mock setup below)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style_def['handle']}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		// No 'Condition not met' log as condition is null for $style_def.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$style_def['handle']}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$style_def['handle']}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$style_def['handle']}' on hook '{$hook_name}'.")->once()->ordered();

		// Logs from _process_inline_styles (called by _process_single_style)
		// This test doesn't set up inline styles for $style_def, so it should log that none were found.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$style_def['handle']}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$style_def['handle']}' on hook '{$hook_name}'.")->once()->ordered();

		// Final log from _process_single_style
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$style_def['handle']}' on hook '{$hook_name}'.")->once()->ordered();

		// Final log from enqueue_deferred_styles itself
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once()->ordered();

		// --- WP_Mock Expectations ---
		WP_Mock::userFunction('wp_style_is')
			->with($style_def['handle'], 'registered')
			->once()
			->andReturn(false);
		WP_Mock::userFunction('wp_style_is')
			->with($style_def['handle'], 'enqueued')
			->once()
			->andReturn(false);

		WP_Mock::userFunction('wp_register_style')
			->with($style_def['handle'], $style_def['src'], $style_def['deps'], $style_def['version'], $style_def['media'])
			->once()
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')
			->with($style_def['handle'])
			->once()
			->andReturnNull();

		// --- Execute Method ---
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assertions ---
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Hook '{$hook_name}' should be cleared from deferred_styles after processing.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_handles_no_styles_for_hook_gracefully(): void {
		$hook_name = 'empty_hook';

		// Ensure deferred_styles is empty or doesn't contain $hook_name
		$deferred_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, array('other_hook' => array(array('handle' => 'some-style')))); // Ensure it's not this hook

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - No styles found deferred for hook: \"{$hook_name}\". Exiting.")
			->once();

		// --- WP_Mock Expectations (ensure no WP functions are called) ---
		WP_Mock::userFunction('wp_style_is')->never();
		WP_Mock::userFunction('wp_register_style')->never();
		WP_Mock::userFunction('wp_enqueue_style')->never();
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// --- Execute Method ---
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assertions ---
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Hook '{$hook_name}' should not exist or be added to deferred_styles.");
		$this->assertArrayHasKey('other_hook', $final_deferred_styles, "'other_hook' should remain untouched in deferred_styles.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_skips_style_if_condition_is_false(): void {
		$hook_name = 'conditional_deferred_hook';
		$style_def = array(
			'handle'    => 'conditional-deferred-style',
			'src'       => 'path/to/conditional-style.css',
			'deps'      => array(),
			'version'   => '1.0',
			'media'     => 'all',
			'condition' => static fn() => false, // Condition returns false
		);

		// Manually set the deferred_styles property
		$deferred_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, array($hook_name => array(0 => $style_def)));

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style_def['handle']}\" (original index 0) for hook: \"{$hook_name}\".")
			->once();
		$this->logger_mock->shouldReceive('debug') // Log from _process_single_style before condition check
			->with("EnqueueAbstract::_process_single_style - Processing style '{$style_def['handle']}' on hook '{$hook_name}' in context 'enqueue_deferred'.")
			->once();
		$this->logger_mock->shouldReceive('debug') // Corrected log from _process_single_style for condition not met
			->with("EnqueueAbstract::_process_single_style - Condition not met for style '{$style_def['handle']}' on hook '{$hook_name}'. Skipping.")
			->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once();

		// --- WP_Mock Expectations (ensure no WP functions are called for the style itself) ---
		WP_Mock::userFunction('wp_style_is')->never(); // Not called if condition is false before status check
		WP_Mock::userFunction('wp_register_style')->never();
		WP_Mock::userFunction('wp_enqueue_style')->never();
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// --- Execute Method ---
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assertions ---
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Hook '{$hook_name}' should be cleared from deferred_styles even if its styles are skipped.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_skips_invalid_style_definition(): void {
		$hook_name            = 'invalid_deferred_hook';
		$style_missing_handle = array(
			// 'handle' is missing
			'src' => 'path/to/style-no-handle.css',
		);
		$style_missing_src = array(
			'handle' => 'style-no-src',
			// 'src' is missing
		);

		$deferred_styles_array = array(
			$hook_name => array(
				0 => $style_missing_handle,
				1 => $style_missing_src,
			)
		);

		// Manually set the deferred_styles property
		$deferred_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, $deferred_styles_array);

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")
			->once();

		// For style_missing_handle
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"N/A_at_original_index_0\" (original index 0) for hook: \"{$hook_name}\".")
			->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style 'N/A' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once();
		$this->logger_mock->shouldReceive('warning')
			->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: 'N/A' on hook '{$hook_name}'.")
			->once();

		// For style_missing_src
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style_missing_src['handle']}\" (original index 1) for hook: \"{$hook_name}\".")
			->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style_missing_src['handle']}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once();
		$this->logger_mock->shouldReceive('warning')
			->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: '{$style_missing_src['handle']}' on hook '{$hook_name}'.")
			->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once(); // Added this line

		// --- WP_Mock Expectations (ensure no WP functions are called) ---
		WP_Mock::userFunction('wp_style_is')->never();
		WP_Mock::userFunction('wp_register_style')->never();
		WP_Mock::userFunction('wp_enqueue_style')->never();
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// --- Execute Method ---
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assertions ---
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Hook '{$hook_name}' should be cleared from deferred_styles even if its styles are invalid.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_skips_inline_style_if_its_condition_is_false(): void {
		$hook_name        = 'deferred_inline_cond_false_hook';
		$style_handle     = 'deferred-main-for-inline-cond';
		$inline_style_key = 'inline_cond_false_deferred';
		$inline_content   = '.deferred-inline-cond-false { display: none; }';

		$main_style_def = array(
			'handle'    => $style_handle,
			'src'       => 'path/to/deferred-main.css',
			'deps'      => array(),
			'version'   => '1.0',
			'media'     => 'all',
			'condition' => null,
		);

		$inline_style_def = array(
			'handle'      => $style_handle,
			'content'     => $inline_content,
			'position'    => 'after',
			'condition'   => static fn() => false, // Condition for inline style is false
			'parent_hook' => $hook_name,
		);

		// Manually set properties
		$deferred_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, array($hook_name => array(0 => $main_style_def)));

		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($inline_style_key => $inline_style_def));

		// --- Logger Expectations ---
		// Logs from enqueue_deferred_styles itself
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style_handle}\" (original index 0) for hook: \"{$hook_name}\".")->once();

		// Logs from _process_single_style (context: 'enqueue_deferred', hook: $hook_name, handle: $style_handle)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$style_handle}' on hook '{$hook_name}'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$style_handle}' on hook '{$hook_name}'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$style_handle}' on hook '{$hook_name}'.")->once();

		// Ensure that the 'Failed to register style' warning is NOT logged for the parent style
		$regex_failed_to_register = "/^EnqueueAbstract::_process_single_style - Failed to register style '{$style_handle}' on hook '{$hook_name}'. WP_Styles registration status: .*$/";
		$this->logger_mock->shouldNotReceive('warning')->with(Mockery::pattern($regex_failed_to_register));

		// Logs from _process_inline_styles (context: 'enqueue_deferred', parent_handle: $style_handle, hook: $hook_name)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$style_handle}' on hook '{$hook_name}'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Condition false for inline style targeting '{$style_handle}' (key: {$inline_style_key}) on hook '{$hook_name}'.")->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Removed processed inline style with key '{$inline_style_key}' for handle '{$style_handle}' on hook '{$hook_name}'.")->once(); // Still removed from array

		// Log from _process_single_style after processing inline styles
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$style_handle}' on hook '{$hook_name}'.")->once();

		// Log from enqueue_deferred_styles itself
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once();

		// Ensure 'Adding inline style' log from _process_inline_styles is NOT called
		$this->logger_mock->shouldNotReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Adding inline style for parent '{$style_handle}' (position: after).");

		// --- WP_Mock Expectations ---
		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'registered')->once()->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'enqueued')->once()->andReturn(false);
		WP_Mock::userFunction('wp_register_style')->with($style_handle, $main_style_def['src'], $main_style_def['deps'], $main_style_def['version'], $main_style_def['media'])->once()->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')->with($style_handle)->once()->andReturnNull();
		WP_Mock::userFunction('wp_add_inline_style')->never(); // Inline style should not be added

		// --- Execute Method ---
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assertions ---
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Hook '{$hook_name}' should be cleared.");

		$final_inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertArrayNotHasKey($inline_style_key, $final_inline_styles, "Inline style '{$inline_style_key}' (condition false) SHOULD have been removed after processing.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_skips_inline_style_missing_content(): void {
		$hook_name        = 'deferred_inline_missing_content_hook';
		$style_handle     = 'deferred-main-for-missing-content-inline';
		$inline_style_key = 'inline_missing_content_deferred';

		$main_style_def = array(
			'handle'    => $style_handle,
			'src'       => 'path/to/deferred-main-mc.css',
			'condition' => null,
		);

		$inline_style_def_missing_content = array(
			'handle' => $style_handle,
			// 'content' is missing
			'position'    => 'after',
			'condition'   => null,
			'parent_hook' => $hook_name,
		);

		// Manually set properties
		$deferred_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, array($hook_name => array(0 => $main_style_def)));

		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, array($inline_style_key => $inline_style_def_missing_content));

		// --- Logger Expectations ---
		// Logs from enqueue_deferred_styles itself
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style_handle}\" (original index 0) for hook: \"{$hook_name}\".")->once()->ordered(); // Added (original index 0)

		// Logs from _process_single_style (called by enqueue_deferred_styles for the parent style)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		// Parent style condition is null, so it passes.
		// wp_style_is(..., 'registered') returns false (mocked), so registration is attempted.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		// wp_style_is(..., 'enqueued') returns false (mocked), so enqueueing is attempted.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Logs from _process_inline_styles (called by _process_single_style)
		// The inline style has missing content.
		$this->logger_mock->shouldReceive('warning')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Empty content for inline style targeting '{$style_handle}' (key: {$inline_style_key}) on hook '{$hook_name}'. Skipping addition.")->once()->ordered();
		// Ensure 'Adding inline style' and 'Removed processed inline style' are NOT logged for this specific inline style
		$this->logger_mock->shouldNotReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Adding inline style for handle '{$style_handle}' (position 'after') on hook '{$hook_name}'.");
		// The 'Removed processed inline style' log WILL occur because the key is added to keys_to_unset even if content is empty.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Removed processed inline style with key '{$inline_style_key}' for handle '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Log from _process_single_style (finishing parent style)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Log from enqueue_deferred_styles itself (exiting)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once()->ordered();

		// --- WP_Mock Expectations ---
		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'registered')->once()->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'enqueued')->once()->andReturn(false);
		WP_Mock::userFunction('wp_register_style')->with($style_handle, $main_style_def['src'], array(), false, 'all')->once()->andReturn(true); // Assuming default deps, ver, media if not in def
		WP_Mock::userFunction('wp_enqueue_style')->with($style_handle)->once()->andReturnNull();
		WP_Mock::userFunction('wp_add_inline_style')->never(); // Malformed inline style should not be added

		// --- Execute Method ---
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assertions ---
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Hook '{$hook_name}' should be cleared.");

		$final_inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertArrayNotHasKey($inline_style_key, $final_inline_styles, "Inline style '{$inline_style_key}' (missing content) should have been removed from the processing array.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_ignores_inline_styles_with_parent_hook_for_immediate_styles(): void {
		$main_style_handle             = 'immediate-style-for-parent-hook-test';
		$main_style_src                = 'path/to/immediate-parent-hook.css';
		$inline_style_content_deferred = '.deferred-inline-should-be-ignored { display: none; }';
		$inline_style_key_deferred     = 'inline_deferred_ignore';
		$deferred_hook_name            = 'my_specific_deferred_hook';

		$styles_to_enqueue = array(
			array(
				'handle' => $main_style_handle,
				'src'    => $main_style_src,
				'hook'   => null, // Immediate
			),
		);

		$initial_inline_styles = array(
			$inline_style_key_deferred => array(
				'handle'      => $main_style_handle, // Targets the immediate style's handle
				'content'     => $inline_style_content_deferred,
				'condition'   => null,
				'parent_hook' => $deferred_hook_name, // But is intended for a deferred hook
			)
		);
		$inline_styles_property = new \ReflectionProperty(ConcreteEnqueueForStylesTesting::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, $initial_inline_styles);

		// --- Logger Expectations ---
		// Logs from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$main_style_handle}\", original index: 0.")->once()->ordered();

		// Logs from _process_single_style (context: 'enqueue_styles')
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$main_style_handle}' in context 'enqueue_styles'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$main_style_handle}'.")->once()->ordered();

		// Logs from _process_inline_styles (context: 'enqueue_styles', parent_handle: $main_style_handle)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Checking for inline styles for parent handle '{$main_style_handle}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - No inline styles found or processed for '{$main_style_handle}'.")->once()->ordered();

		// Log from _process_single_style (finishing parent style)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$main_style_handle}'.")->once()->ordered();

		// Crucially, no logs for adding or removing this specific inline style from _process_inline_styles
		$this->logger_mock->shouldNotReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Adding inline style for parent '{$main_style_handle}'.");
		$this->logger_mock->shouldNotReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_styles) - Removed processed inline style with key '{$inline_style_key_deferred}'.");

		// Log from enqueue_styles itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once()->ordered();

		// --- WP_Mock Expectations ---

		WP_Mock::userFunction('wp_register_style')->with($main_style_handle, $main_style_src, Mockery::any(), Mockery::any(), Mockery::any())->once()->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')->with($main_style_handle)->once()->andReturnNull();
		// wp_add_inline_style should NOT be called for the deferred inline style
		WP_Mock::userFunction('wp_add_inline_style')->with($main_style_handle, $inline_style_content_deferred, Mockery::any())->never();

		// --- Call Method ---
		$this->instance->enqueue_styles($styles_to_enqueue);

		// --- Assertions ---
		$final_inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertArrayHasKey($inline_style_key_deferred, $final_inline_styles, 'Inline style with parent_hook should NOT have been removed.');
		$this->assertEquals($initial_inline_styles[$inline_style_key_deferred], $final_inline_styles[$inline_style_key_deferred], 'Inline style with parent_hook data should remain unchanged.');
	}

	/**
	 * @test89.66%
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_basic_immediate_parent_not_in_sut_queue(): void {
		$parent_handle     = 'parent-immediate';
		$inline_content    = '.foo { color: red; }';
		$expected_position = 'after'; // Default

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertIsArray($inline_styles);
		$this->assertCount(1, $inline_styles);

		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content,
			'position'    => $expected_position,
			'condition'   => null,
			'parent_hook' => null
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_with_explicit_parent_hook(): void {
		$parent_handle     = 'parent-for-explicit-hook';
		$inline_content    = '.explicit-hook { border: 1px solid green; }';
		$explicit_hook     = 'my_custom_parent_hook';
		$expected_position = 'after'; // Default

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		// No warning or association log expected here, as parent_hook is explicit and parent is not in SUT's styles_to_enqueue

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content, $expected_position, null, $explicit_hook);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertIsArray($inline_styles);
		$this->assertCount(1, $inline_styles);

		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content,
			'position'    => $expected_position,
			'condition'   => null,
			'parent_hook' => $explicit_hook
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_derives_parent_hook_from_sut_styles(): void {
		$parent_handle     = 'parent-in-sut-styles';
		$inline_content    = '.derived-hook { font-style: italic; }';
		$derived_hook      = 'sut_deferred_hook';
		$expected_position = 'after'; // Default

		// Setup SUT's internal styles to include the parent with a hook
		$sut_styles_property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'styles');
		$sut_styles_property->setAccessible(true);
		$sut_styles_property->setValue($this->instance, array(
			array(
				'handle' => $parent_handle,
				'src'    => 'path/to/parent-in-sut.css',
				'hook'   => $derived_hook
			)
		));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Inline style for '{$parent_handle}' associated with parent hook: '{$derived_hook}'. Original parent style hook: '{$derived_hook}'.")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act: Call add_inline_styles with null for parent_hook to trigger derivation
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content, $expected_position, null, null);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertIsArray($inline_styles);
		$this->assertCount(1, $inline_styles);

		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content,
			'position'    => $expected_position,
			'condition'   => null,
			'parent_hook' => $derived_hook // Crucial assertion: hook should be derived
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_explicit_hook_takes_precedence_over_derived(): void {
		$parent_handle            = 'parent-with-sut-and-explicit-hook';
		$inline_content           = '.explicit-wins { background: yellow; }';
		$explicit_hook_for_inline = 'my_explicit_inline_hook';
		$derived_hook_from_sut    = 'hook_from_parent_style_in_sut';
		$expected_position        = 'after'; // Default

		// Setup SUT's internal styles to include the parent with a different hook
		$sut_styles_property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'styles');
		$sut_styles_property->setAccessible(true);
		$sut_styles_property->setValue($this->instance, array(
			array(
				'handle' => $parent_handle,
				'src'    => 'path/to/parent-for-precedence-test.css',
				'hook'   => $derived_hook_from_sut
			)
		));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		// This log confirms the SUT found the parent in $this->styles, but used the explicit hook for the inline_style_item itself.
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Inline style for '{$parent_handle}' associated with parent hook: '{$explicit_hook_for_inline}'. Original parent style hook: '{$derived_hook_from_sut}'.")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act: Call add_inline_styles with an explicit parent_hook
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content, $expected_position, null, $explicit_hook_for_inline);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertIsArray($inline_styles);
		$this->assertCount(1, $inline_styles);

		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content,
			'position'    => $expected_position,
			'condition'   => null,
			'parent_hook' => $explicit_hook_for_inline // Crucial: explicit hook should be stored
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_no_hook_derived_if_parent_in_sut_has_no_hook(): void {
		$parent_handle     = 'parent-in-sut-no-hook';
		$inline_content    = '.no-derived-hook { text-decoration: underline; }';
		$expected_position = 'after'; // Default

		// Setup SUT's internal styles to include the parent, but with a null hook
		$sut_styles_property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'styles');
		$sut_styles_property->setAccessible(true);
		$sut_styles_property->setValue($this->instance, array(
			array(
				'handle' => $parent_handle,
				'src'    => 'path/to/parent-in-sut-no-hook.css',
				'hook'   => null // Parent style has no hook defined
			)
		));

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		// Expect NO log about associating with a parent hook, as the parent in SUT has no hook.
		$this->logger_mock->shouldNotReceive('debug')
			->with(Mockery::pattern("/EnqueueAbstract::add_inline_styles - Inline style for '{$parent_handle}' associated with parent hook:/i"));

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act: Call add_inline_styles with null for parent_hook
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content, $expected_position, null, null);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertIsArray($inline_styles);
		$this->assertCount(1, $inline_styles);

		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content,
			'position'    => $expected_position,
			'condition'   => null,
			'parent_hook' => null // Crucial: hook should remain null
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_with_before_position(): void {
		$parent_handle     = 'parent-for-before-position';
		$inline_content    = '.before-style { color: red; }';
		$expected_position = 'before';

		// Logger expectations (standard entry/exit)
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content, $expected_position);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertCount(1, $inline_styles);
		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content,
			'position'    => $expected_position, // Crucial
			'condition'   => null,
			'parent_hook' => null
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_with_condition_callback(): void {
		$parent_handle      = 'parent-for-condition';
		$inline_content     = '.conditional-style { display: none; }';
		$condition_callback = static function () {
			return true;
		};

		// Logger expectations (standard entry/exit)
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content, 'after', $condition_callback);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertCount(1, $inline_styles);
		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content,
			'position'    => 'after',
			'condition'   => $condition_callback, // Crucial
			'parent_hook' => null
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
		$this->assertSame($condition_callback, $inline_styles[0]['condition'], 'The stored condition should be the exact same callable instance.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_with_empty_content(): void {
		$parent_handle  = 'parent-for-empty-content';
		$inline_content = ''; // Empty content

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: {$parent_handle}")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertCount(1, $inline_styles);
		$expected_inline_item = array(
			'handle'      => $parent_handle,
			'content'     => $inline_content, // Crucial: empty content stored
			'position'    => 'after',
			'condition'   => null,
			'parent_hook' => null
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_inline_styles
	 */
	public function test_add_inline_styles_with_empty_handle(): void {
		$parent_handle  = ''; // Empty handle
		$inline_content = '.empty-handle-style { border: 1px dashed; }';

		// Logger expectations (esc_html('') results in an empty string for the handle in the log)
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Entered. Current inline style count: 0. Adding new inline style for handle: ')
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::add_inline_styles - Exiting. New total inline style count: 1')
			->once();

		// Act
		$result = $this->instance->add_inline_styles($parent_handle, $inline_content);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertCount(1, $inline_styles);
		$expected_inline_item = array(
			'handle'      => $parent_handle, // Crucial: empty handle stored
			'content'     => $inline_content,
			'position'    => 'after',
			'condition'   => null,
			'parent_hook' => null
		);
		$this->assertEquals($expected_inline_item, $inline_styles[0]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_registers_and_enqueues_new_styles(): void {
		$hook_name    = 'test_deferred_hook';
		$style_handle = 'test-deferred-style';
		$style_def    = array(
			'handle'    => $style_handle,
			'src'       => 'path/to/test-deferred-style.css',
			'deps'      => array(),
			'version'   => '1.0',
			'media'     => 'all',
			'condition' => null,
		);

		// --- Get current deferred_styles state BEFORE modification ---
		// Set initial state for deferred_styles using reflection
		$reflection               = new \ReflectionClass($this->instance);
		$deferred_styles_property = $reflection->getProperty('deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, array($hook_name => array($style_def)));

		// --- Get current deferred_styles state AFTER modification ---
		// --- Mocks ---
		// Mock wp_style_is to return false for both 'registered' and 'enqueued' for our specific handle
		WP_Mock::userFunction('wp_style_is')
			->with($style_handle, 'registered')
			->once()
			->andReturn(false);
		WP_Mock::userFunction('wp_style_is')
			->with($style_handle, 'enqueued')
			->once()
			->andReturn(false);

		// Expect wp_register_style and wp_enqueue_style to be called for our style
		WP_Mock::userFunction('wp_register_style')
			->with($style_handle, $style_def['src'], $style_def['deps'], $style_def['version'], $style_def['media'])
			->once()
			->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')
			->with($style_handle)
			->once();

		// --- Logger Expectations ---
		// Log from enqueue_deferred_styles itself
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style_handle}\" (original index 0) for hook: \"{$hook_name}\".")->once()->ordered();

		// Logs from _process_single_style (context: 'enqueue_deferred', hook: $hook_name)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		// No 'Style status' log expected here.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		// No 'Registered style' (after WP call) log expected here.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		// No 'Enqueued style' (after WP call) log expected here.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Logs from _process_inline_styles (context: 'enqueue_deferred', parent_handle: $style_handle, hook: $hook_name)
		// This test doesn't set up inline styles, so it should log that none were found.
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Log from _process_single_style after processing inline styles
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Log from enqueue_deferred_styles itself
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once()->ordered();


		// --- Get current deferred_styles state JUST BEFORE invoking the method ---
		// --- Execute Protected Method ---
		$enqueue_deferred_styles_method = $reflection->getMethod('enqueue_deferred_styles');
		$enqueue_deferred_styles_method->setAccessible(true);
		$enqueue_deferred_styles_method->invoke($this->instance, $hook_name);

		// --- Assertions ---
		// Check that the processed hook is removed from deferred_styles
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Processed hook '{$hook_name}' should be removed from deferred_styles.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_processes_styles_for_hook(): void {
		$hook_name = 'my_custom_hook';

		$deferred_style_1_handle      = 'deferred-style-1-on-hook';
		$deferred_style_2_handle      = 'deferred-style-2-skipped-condition';
		$deferred_style_3_handle      = 'deferred-style-3-no-condition';
		$another_style_handle_literal = 'another-style-handle';

		$initial_deferred_styles = array(
		    $hook_name => array(
		        0 => array(
		            'handle'    => $deferred_style_1_handle,
		            'src'       => 'path/to/deferred-1.css',
		            'deps'      => array(),
		            'version'   => '1.0',
		            'media'     => 'all',
		            'condition' => fn() => true,
		        ),
		        1 => array(
		            'handle'    => $deferred_style_2_handle,
		            'src'       => 'path/to/deferred-2.css',
		            'deps'      => array(),
		            'version'   => '1.0',
		            'media'     => 'all',
		            'condition' => fn() => false, // This style should be skipped
		        ),
		        2 => array(
		            'handle'  => $deferred_style_3_handle,
		            'src'     => 'path/to/deferred-3.css',
		            'deps'    => array(),
		            'version' => '1.0',
		            'media'   => 'all',
		            // No condition
		        ),
		        3 => array( // Invalid style definition to test warning
		            'handle' => 'invalid-style-no-src',
		            // 'src'    => 'path/to/invalid.css', // Missing src
		            'version' => '1.0',
		        )
		    ),
		    'another_hook' => array(
		        0 => array('handle' => 'other-hook-style', 'src' => 'path/to/other.css')
		    )
		);

		$initial_inline_styles = array(
		    $deferred_style_1_handle => array(
		        'inline_key_1' => array('content' => '/* CSS for deferred_style_1_handle_inline_key_1 */', 'position' => 'after', 'condition' => fn() => true),
		    ),
		    $another_style_handle_literal => array( // Content for this doesn't appear in 'Adding inline style' logs as it's not processed
		        'inline_key_3' => array('content' => '.another-style-content { /* for another_style_handle_literal */ }', 'position' => 'after'),
		    ),
		    $deferred_style_3_handle => array(
		        'inline_key_4' => array('content' => '/* CSS for deferred_style_3_handle_inline_key_4_cond_false */', 'position' => 'after', 'condition' => fn() => false), // Will be logged with numeric key '2'
		        'inline_key_5' => array('content' => '/* CSS for deferred_style_3_handle_inline_key_5_no_cond */', 'position' => 'after'), // Will be logged with numeric key '3'
		    ),
		    $deferred_style_2_handle => array( // Content for this doesn't appear in 'Adding inline style' logs as parent style is skipped
		        'inline_key_6' => array('content' => '.deferred-style-2-content { /* for deferred_style_2_handle */ }', 'position' => 'after'),
		    )
		);

		$deferred_styles_property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'deferred_styles');
		$deferred_styles_property->setAccessible(true);
		$deferred_styles_property->setValue($this->instance, $initial_deferred_styles);

		$inline_styles_property = new \ReflectionProperty(\Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::class, 'inline_styles');
		$inline_styles_property->setAccessible(true);
		$inline_styles_property->setValue($this->instance, $initial_inline_styles);

		// --- Logger Expectations ---
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once()->ordered();

		// Style 1 (deferred-style-1-on-hook) - Processed (original index 0)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$deferred_style_1_handle}\" (original index 0) for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$deferred_style_1_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$deferred_style_1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$deferred_style_1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$deferred_style_1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		// Inline style 'inline_key_1' for deferred_style_1_handle (condition true)
		$this->logger_mock->shouldReceive('debug')
            ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$deferred_style_1_handle}' on hook '{$hook_name}'.")
            ->ordered();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Condition callback for inline style key: inline_key_1 for parent handle: {$deferred_style_1_handle} on hook: {$hook_name} evaluated to true.")
		    ->ordered();
		// wp_add_inline_style is NOT called for inline_key_1
		// Logger "Added inline style key: inline_key_1..." is NOT called
		// Based on assertEmpty failure, inline_key_1 is NOT unset, so "Removed processed..." log is NOT made.
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$deferred_style_1_handle}' on hook '{$hook_name}'.") // This matches previous errors
		    ->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$deferred_style_1_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Style 2 (deferred-style-2-skipped-condition) - Skipped by condition (original index 1)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$deferred_style_2_handle}\" (original index 1) for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$deferred_style_2_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Condition not met for style '{$deferred_style_2_handle}' on hook '{$hook_name}'. Skipping.")->once()->ordered();
		// No 'Finished processing' log for skipped style

		// Style 3 (deferred-style-3-no-condition) - Processed (original index 2)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$deferred_style_3_handle}\" (original index 2) for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$deferred_style_3_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$deferred_style_3_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$deferred_style_3_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$deferred_style_3_handle}' on hook '{$hook_name}'.")->once()->ordered();
		// Inline styles for deferred_style_3_handle
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$deferred_style_3_handle}' on hook '{$hook_name}'.")->once()->ordered();
		// -> Inline style 'inline_key_4' (numeric key 2) for deferred_style_3_handle (condition false)
		$this->logger_mock->shouldReceive('debug')
            ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Adding inline style for '{$deferred_style_3_handle}' (key: 2, position: after) on hook '{$hook_name}'.") // Logged before condition check
            ->ordered();
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Condition callback for inline style key: 2 for parent handle: {$deferred_style_3_handle} on hook: {$hook_name} evaluated to false.")
		    ->ordered();
		// wp_add_inline_style is NOT called for key 2
		// Logger "Added inline style key: 2..." is NOT called
		// Based on assertEmpty failure for deferred_style_1_handle, key 2 is NOT unset, so "Removed processed..." log is NOT made.

		// -> Inline style 'inline_key_5' (numeric key 3) for deferred_style_3_handle (no condition, so true)
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Adding inline style for '{$deferred_style_3_handle}' (key: 3, position: after) on hook '{$hook_name}'.") // Logged before wp_add_inline_style attempt
		    ->ordered();
		// Condition is implicitly true as it's not set for inline_key_5
		// WP_Mock::wp_add_inline_style is NOT called for key 3 (based on SUT error)
		// Logger "Added inline style key: 3..." is NOT called
		// Based on assertEmpty failure for deferred_style_1_handle, key 3 is NOT unset, so "Removed processed..." log is NOT made.
		$this->logger_mock->shouldReceive('debug')
		    ->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$deferred_style_3_handle}' on hook '{$hook_name}'.") // This matches the error
		    ->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$deferred_style_3_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Style 4 (invalid-style-no-src) - Warning (original index 3)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"invalid-style-no-src\" (original index 3) for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style 'invalid-style-no-src' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		$this->logger_mock->shouldReceive('warning')->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: 'invalid-style-no-src' on hook '{$hook_name}'.")->once()->ordered();
		// No 'Finished processing' log for invalid style

		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once()->ordered();

		// --- WP_Mock Expectations ---
		// Style 1
		WP_Mock::userFunction('wp_style_is')->with($deferred_style_1_handle, 'registered')->once()->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with($deferred_style_1_handle, 'enqueued')->once()->andReturn(false);
		WP_Mock::userFunction('wp_register_style')
		    ->with($deferred_style_1_handle, 'path/to/deferred-1.css', array(), '1.0', 'all')
		    ->once()->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')->with($deferred_style_1_handle)->once();
		WP_Mock::userFunction('wp_add_inline_style')
		    ->with($deferred_style_1_handle, '.style1 { color: blue; }')
		    ->never();

		// Style 2 - Not registered or enqueued due to condition
		WP_Mock::userFunction('wp_register_style')->with($deferred_style_2_handle, Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())->never();
		WP_Mock::userFunction('wp_enqueue_style')->with($deferred_style_2_handle)->never();

		// Style 3
		WP_Mock::userFunction('wp_style_is')->with($deferred_style_3_handle, 'registered')->once()->andReturn(false);
		WP_Mock::userFunction('wp_style_is')->with($deferred_style_3_handle, 'enqueued')->once()->andReturn(false);
		WP_Mock::userFunction('wp_register_style')
		    ->with($deferred_style_3_handle, 'path/to/deferred-3.css', array(), '1.0', 'all')
		    ->once()->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')->with($deferred_style_3_handle)->once();
		WP_Mock::userFunction('wp_add_inline_style') // For inline_key_5
		    ->with($deferred_style_3_handle, '.style3 { font-weight: bold; }')
		    ->never();
		WP_Mock::userFunction('wp_add_inline_style') // For inline_key_4 (should not be called due to condition)
		    ->with($deferred_style_3_handle, '.style3-fail-condition { color: purple; }')
		    ->never();

		// Invalid style - Not registered or enqueued
		WP_Mock::userFunction('wp_register_style')->with('invalid-style-no-src', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any())->never();

		// --- Call Method ---
		$this->instance->enqueue_deferred_styles($hook_name);

		// --- Assertions ---
		$final_deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $final_deferred_styles, "Processed hook '{$hook_name}' should be removed from deferred_styles.");
		$this->assertArrayHasKey('another_hook', $final_deferred_styles, "'another_hook' should remain in deferred_styles.");

		$final_inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertCount(4, $final_inline_styles, 'Four parent handles should remain in inline_styles.');

		// Check $deferred_style_1_handle (remained because its inline style was not "added", key not unset)
		$this->assertArrayHasKey($deferred_style_1_handle, $final_inline_styles, 'Parent handle ' . $deferred_style_1_handle . ' should remain as its inline style was not added.');
		$this->assertArrayHasKey('inline_key_1', $final_inline_styles[$deferred_style_1_handle], 'inline_key_1 for ' . $deferred_style_1_handle . ' should remain as wp_add_inline_style was not called.');
		$this->assertCount(1, $final_inline_styles[$deferred_style_1_handle], 'deferred_style_1_handle should have 1 inline style remaining.');
		$this->assertEquals($initial_inline_styles[$deferred_style_1_handle]['inline_key_1'], $final_inline_styles[$deferred_style_1_handle]['inline_key_1'], 'Content of inline_key_1 for ' . $deferred_style_1_handle . ' should be unchanged.');

		// Check $deferred_style_3_handle (remained because its inline styles were not "added", keys not unset)
		$this->assertArrayHasKey($deferred_style_3_handle, $final_inline_styles, 'Parent handle ' . $deferred_style_3_handle . ' should remain as its inline styles were not added.');
		$this->assertArrayHasKey('inline_key_4', $final_inline_styles[$deferred_style_3_handle], 'inline_key_4 for ' . $deferred_style_3_handle . ' should remain as wp_add_inline_style was not called.');
		$this->assertArrayHasKey('inline_key_5', $final_inline_styles[$deferred_style_3_handle], 'inline_key_5 for ' . $deferred_style_3_handle . ' should remain as wp_add_inline_style was not called.');
		$this->assertCount(2, $final_inline_styles[$deferred_style_3_handle], 'deferred_style_3_handle should have 2 inline styles remaining.');
		$this->assertEquals($initial_inline_styles[$deferred_style_3_handle]['inline_key_4'], $final_inline_styles[$deferred_style_3_handle]['inline_key_4'], 'Content of inline_key_4 for ' . $deferred_style_3_handle . ' should be unchanged.');
		$this->assertEquals($initial_inline_styles[$deferred_style_3_handle]['inline_key_5'], $final_inline_styles[$deferred_style_3_handle]['inline_key_5'], 'Content of inline_key_5 for ' . $deferred_style_3_handle . ' should be unchanged.');

		// Check 'another-style-handle' (not processed on this hook, untouched)
		$this->assertArrayHasKey($another_style_handle_literal, $final_inline_styles, 'Parent handle \'' . $another_style_handle_literal . '\' should remain as it was not for the processed hook.');
		$this->assertArrayHasKey('inline_key_3', $final_inline_styles[$another_style_handle_literal], 'inline_key_3 (under ' . $another_style_handle_literal . ') should remain.');

		// Check 'deferred_style_2_handle' (parent style was skipped by condition, untouched)
		$this->assertArrayHasKey($deferred_style_2_handle, $final_inline_styles, 'Parent handle ' . $deferred_style_2_handle . ' should remain as its parent style was skipped by condition.');
		$this->assertArrayHasKey('inline_key_6', $final_inline_styles[$deferred_style_2_handle], 'inline_key_6 (under ' . $deferred_style_2_handle . ') should remain.');
	}

	/**
	 * Tests that register_styles correctly registers a single, valid, non-hooked style.
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_styles
	 */
	public function test_register_styles_registers_non_hooked_style_correctly(): void {
		$style = array(
			'handle'  => 'test-style',
			'src'     => 'path/to/style.css',
			'deps'    => array('dependency-1'),
			'version' => '1.1.0',
			'media'   => 'screen',
		);

		$this->logger_mock->shouldReceive('is_active')->andReturn(true)->byDefault();

		// Expectations for register_styles method itself
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Entered.')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Styles directly passed. Adding them to internal collection via add_styles().')->once()->ordered();

		// Expectations for the internal call to add_styles
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract: Adding 1 styles to the queue.')->once()->ordered();

		// Back to register_styles logging
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Processing 1 style definition(s) for registration.')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Attempting to process style: "test-style", original index: 0.')->once()->ordered();
		// Logs from _process_single_style (context: 'register_styles', handle: 'test-style', hook_name: null)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style 'test-style' in context 'register_styles'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style 'test-style'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style 'test-style'.")->once()->ordered();

		WP_Mock::userFunction('wp_register_style', array(
			'times' => 1,
			'args'  => array(
				$style['handle'],
				$style['src'],
				$style['deps'],
				$style['version'],
				$style['media'],
			),
		))->andReturn(true);

		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Exited.')->once()->ordered();

		$this->instance->register_styles(array($style));
		// Mockery will assert expectations on tearDown
		$this->assertTrue(true); // Placeholder if no direct assertion on SUT state for this path.
	}

	/**
	 * Tests that enqueue_styles correctly defers to the same hook, calls add_action once, and skips if action exists.
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_defers_to_same_hook_calls_add_action_once_and_skips_if_action_exists(): void {
		$hook_name     = 'test_shared_hook_for_styles';
		$style1_handle = 'style1-on-shared-hook';
		$style2_handle = 'style2-on-shared-hook';

		$styles_to_enqueue = array(
			array(
				'handle' => $style1_handle,
				'src'    => 'path/to/style1.css',
				'hook'   => $hook_name,
			),
			array(
				'handle' => $style2_handle,
				'src'    => 'path/to/style2.css',
				'hook'   => $hook_name,
			),
		);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Entered. Processing 2 style definition(s).')
			->once();

		// Style 1 processing
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$style1_handle}\", original index: 0.")
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Deferring style \"{$style1_handle}\" (original index 0) to hook: \"{$hook_name}\".")
			->once();
		// Expect add_action log for the first style on this hook
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook_name}\".")
			->once();

		// Expect is_active to be called twice (once for each style's log path)
		$this->logger_mock->shouldReceive('is_active')->andReturn(true)->times(8);

		// Style 2 processing
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$style2_handle}\", original index: 1.")
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Deferring style \"{$style2_handle}\" (original index 1) to hook: \"{$hook_name}\".")
			->once();

		// Expect 'Action ... already exists.' log for the second style on the same hook.
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Action for \'enqueue_deferred_styles\' on hook \'test_shared_hook_for_styles\' already exists.')
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Exited.')
			->once();


		// Mock has_action: Use a closure with diagnostics to control sequential returns.
		$has_action_call_count = 0;
		WP_Mock::userFunction('has_action')
			->with($hook_name, Mockery::any()) // Test with Mockery::any() for callback
			->andReturnUsing(function() use (&$has_action_call_count) {
				$has_action_call_count++;
				$return_val = $has_action_call_count === 1 ? false : true;
				return $return_val;
			})
			->twice();

		// Mock the _do_add_action method on the SUT instance.
		// Expect it to be called once for the first style.
		$this->instance->shouldReceive('_do_add_action')
			->with(
				$hook_name,
				Mockery::on(function ($arg) { // Match the callback array flexibly
					return is_array($arg)
						&& isset($arg[0]) && $arg[0] instanceof \Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForStylesTesting
						&& isset($arg[1]) && $arg[1] === 'enqueue_deferred_styles';
				}),
				10, // priority
				1   // accepted_args
			)
			->once()
			->andReturnUsing(function(...$args) {
				return null; // Original intended return
			});

		// Act
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayHasKey($hook_name, $deferred_styles);
		$this->assertCount(2, $deferred_styles[$hook_name]);
		$this->assertEquals($styles_to_enqueue[0], $deferred_styles[$hook_name][0]);
		$this->assertEquals($styles_to_enqueue[1], $deferred_styles[$hook_name][1]);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_deferred_style_missing_handle_logs_na_and_stores(): void {
		$hook_name = 'test_hook_for_no_handle_style';
		$style_src = 'path/to/no-handle-style.css';

		$styles_to_enqueue = array(
			0 => array( // Original index will be 0
				// 'handle' key is intentionally missing
				'src'  => $style_src,
				'hook' => $hook_name,
			),
		);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')
			->once();

		// Style processing - expecting 'N/A' for handle
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Processing style: "N/A", original index: 0.')
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Deferring style \"N/A\" (original index 0) to hook: \"{$hook_name}\".")
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook_name}\".")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Exited.')
			->once();

		// Mock has_action: returns false
		WP_Mock::userFunction('has_action')
			->with($hook_name, array($this->instance, 'enqueue_deferred_styles'))
			->andReturn(false)
			->once();

		// Act
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayHasKey($hook_name, $deferred_styles, 'Hook key should exist in deferred_styles.');
		$this->assertCount(1, $deferred_styles[$hook_name], 'Should be one style under this hook.');
		// The key for the style within the hook's array will be its original index (0 in this case).
		$this->assertArrayHasKey(0, $deferred_styles[$hook_name], 'Style with original index 0 should exist.');
		$this->assertEquals($styles_to_enqueue[0], $deferred_styles[$hook_name][0], 'Stored style definition should match input.');
		$this->assertArrayNotHasKey('handle', $deferred_styles[$hook_name][0], 'Stored style definition should still be missing the handle.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 */
	public function test_enqueue_styles_with_empty_array_logs_and_exits_gracefully(): void {
		$styles_to_enqueue = array();

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Entered. Processing 0 style definition(s).')
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Exited.')
			->once();

		// Act
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertEmpty($deferred_styles, 'Deferred styles array should remain empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 */
	public function test_enqueue_styles_immediate_style_empty_handle_logs_warning_and_skips(): void {
		$style_src         = 'path/to/valid-for-empty-handle.css';
		$styles_to_enqueue = array(
			array(
				'handle' => '', // Empty handle
				'src'    => $style_src,
				'hook'   => null, // Immediate style
			),
		);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')
			->once();

		// enqueue_styles log for the specific style (handle is empty string here)
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Processing style: "", original index: 0.')
			->once();

		// _process_single_style logs (handle is also an empty string here)
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Processing style '' in context 'enqueue_styles'.")
			->once();
		$this->logger_mock->shouldReceive('warning')
			->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: ''.")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Exited.')
			->once();

		// Ensure no WP functions are called for this invalid style
		WP_Mock::userFunction('wp_register_style')->never();
		WP_Mock::userFunction('wp_enqueue_style')->never();
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// Act
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		// Assert internal states are not affected by the invalid style
		$internal_styles = $this->get_protected_property_value($this->instance, 'styles');
		$this->assertEmpty($internal_styles, 'Internal styles array should be empty.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertEmpty($inline_styles, 'Inline styles array should be empty.');

		$deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertEmpty($deferred_styles, 'Deferred styles array should be empty.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 */
	public function test_enqueue_styles_immediate_style_empty_src_logs_warning_and_skips(): void {
		$style_handle      = 'valid-handle-empty-src';
		$styles_to_enqueue = array(
			array(
				'handle' => $style_handle,
				'src'    => '', // Empty src
				'hook'   => null, // Immediate style
			),
		);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$style_handle}\", original index: 0.")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Processing style '{$style_handle}' in context 'enqueue_styles'.")
			->once();
		$this->logger_mock->shouldReceive('warning')
			->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: '{$style_handle}'.")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_styles - Exited.')
			->once();

		// Ensure no WP functions are called for this invalid style
		WP_Mock::userFunction('wp_register_style')->never();
		WP_Mock::userFunction('wp_enqueue_style')->never();
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// Act
		$result = $this->instance->enqueue_styles($styles_to_enqueue);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		$internal_styles = $this->get_protected_property_value($this->instance, 'styles');
		$this->assertEmpty($internal_styles, 'Internal styles array should be empty.');

		$inline_styles = $this->get_protected_property_value($this->instance, 'inline_styles');
		$this->assertEmpty($inline_styles, 'Inline styles array should be empty.');

		$deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertEmpty($deferred_styles, 'Deferred styles array should be empty.');
	}


	// endregion Immediate Styles

	// region Enqueue Deferred Styles

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 */
	public function test_enqueue_deferred_styles_no_styles_for_hook_logs_and_exits(): void {
		$hook_name = 'test_hook_no_styles';

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_deferred_styles - No styles found deferred for hook: \"{$hook_name}\". Exiting.")
			->once();

		// Note: The unset($this->deferred_styles[$hook_name]) inside the method doesn't produce a log.
		// The method returns early after the 'No styles found' log, so the final 'Exited for hook' log is not reached in this path.

		// Act
		$this->instance->enqueue_deferred_styles($hook_name);

		// Assert
		// Verify that the deferred_styles property remains empty or does not contain the hook key.
		$deferred_styles = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $deferred_styles, "Deferred styles array should not have key '{$hook_name}'.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 */
	public function test_enqueue_deferred_styles_processes_single_style_correctly(): void {
		$hook_name    = 'my_custom_deferred_hook';
		$style_handle = 'test-deferred-style';
		$style_src    = 'path/to/my-deferred-style.css';
		$original_idx = 0; // The key for the style in the $styles_to_enqueue array

		$styles_to_enqueue = array(
			$original_idx => array(
				'handle' => $style_handle,
				'src'    => $style_src,
				'hook'   => $hook_name,
			),
		);

		// --- Setup: Defer the style using enqueue_styles() ---
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Entered. Processing 1 style definition(s).')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$style_handle}\", original index: {$original_idx}.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Deferring style \"{$style_handle}\" (original index {$original_idx}) to hook: \"{$hook_name}\".")->once()->ordered();
		WP_Mock::userFunction('has_action')
			->with($hook_name, array($this->instance, 'enqueue_deferred_styles'))
			->andReturn(false)
			->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once()->ordered();

		$this->instance->enqueue_styles($styles_to_enqueue);

		// --- Act: Call enqueue_deferred_styles() ---

		// Logger expectations for enqueue_deferred_styles() itself
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style_handle}\" (original index {$original_idx}) for hook: \"{$hook_name}\".")->once()->ordered();

		// Logger expectations for _process_single_style() called from enqueue_deferred_styles()
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'registered')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_register_style')->with($style_handle, $style_src, array(), false, 'all')->once();

		WP_Mock::userFunction('wp_style_is')->with($style_handle, 'enqueued')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_enqueue_style')->with($style_handle)->once();

		// Logger expectations for _process_single_style() indicating it will check for inline styles
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Logger expectations for _process_inline_styles() itself
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$style_handle}' on hook '{$hook_name}'.")
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$style_handle}' on hook '{$hook_name}'.")
			->once();

		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Final log for enqueue_deferred_styles()
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once()->ordered();

		$this->instance->enqueue_deferred_styles($hook_name);

		// Assert: deferred_styles for the hook should be cleared
		$deferred_styles_after = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $deferred_styles_after, "Deferred styles for hook '{$hook_name}' should be cleared after processing.");
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 */
	public function test_enqueue_deferred_styles_processes_multiple_styles_for_same_hook(): void {
		$hook_name     = 'my_multi_style_hook';
		$style1_handle = 'test-deferred-style-1';
		$style1_src    = 'path/to/deferred-1.css';
		$original_idx1 = 'style_one'; // Using string keys for clarity

		$style2_handle = 'test-deferred-style-2';
		$style2_src    = 'path/to/deferred-2.css';
		$original_idx2 = 'style_two';

		$styles_to_enqueue = array(
			$original_idx1 => array(
				'handle' => $style1_handle,
				'src'    => $style1_src,
				'hook'   => $hook_name,
			),
			$original_idx2 => array(
				'handle' => $style2_handle,
				'src'    => $style2_src,
				'hook'   => $hook_name,
			),
		);

		// --- Setup: Defer styles using enqueue_styles() ---
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Entered. Processing 2 style definition(s).')->once()->ordered();
		// Style 1 deferral logs
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$style1_handle}\", original index: {$original_idx1}.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Deferring style \"{$style1_handle}\" (original index {$original_idx1}) to hook: \"{$hook_name}\".")->once()->ordered();

		// Mock has_action specifically for this hook and callback
		\WP_Mock::userFunction( 'has_action' )
			->with( $hook_name, array( $this->instance, 'enqueue_deferred_styles' ) )
			->andReturnValues( array( false, true ) ); // First call false, subsequent true for this specific hook/callback

		WP_Mock::expectActionAdded($hook_name, array($this->instance, 'enqueue_deferred_styles'), 10, 1);
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook_name}\".")->once(); // Not ordered due to known Mockery/WP_Mock conflict
		// Style 2 deferral logs (has_action not called again for same hook)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$style2_handle}\", original index: {$original_idx2}.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Deferring style \"{$style2_handle}\" (original index {$original_idx2}) to hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Action for 'enqueue_deferred_styles' on hook '{$hook_name}' already exists.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once()->ordered();

		$this->instance->enqueue_styles($styles_to_enqueue);

		// --- Act: Call enqueue_deferred_styles() ---
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once()->ordered();

		// Expectations for Style 1 processing
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style1_handle}\" (original index {$original_idx1}) for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style1_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		WP_Mock::userFunction('wp_style_is')->with($style1_handle, 'registered')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$style1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_register_style')->with($style1_handle, $style1_src, array(), false, 'all')->once();
		WP_Mock::userFunction('wp_style_is')->with($style1_handle, 'enqueued')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$style1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_enqueue_style')->with($style1_handle)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$style1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$style1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$style1_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$style1_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Expectations for Style 2 processing
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$style2_handle}\" (original index {$original_idx2}) for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$style2_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		WP_Mock::userFunction('wp_style_is')->with($style2_handle, 'registered')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$style2_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_register_style')->with($style2_handle, $style2_src, array(), false, 'all')->once();
		WP_Mock::userFunction('wp_style_is')->with($style2_handle, 'enqueued')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$style2_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_enqueue_style')->with($style2_handle)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$style2_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$style2_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$style2_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$style2_handle}' on hook '{$hook_name}'.")->once()->ordered();

		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once()->ordered();

		$this->instance->enqueue_deferred_styles($hook_name);

		// Assert: deferred_styles for the hook should be cleared
		$deferred_styles_after = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $deferred_styles_after, "Deferred styles for hook '{$hook_name}' should be cleared after processing.");
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_deferred_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 */
	public function test_enqueue_deferred_styles_handles_missing_handle_gracefully(): void {
		$hook_name            = 'my_deferred_hook_with_invalid';
		$valid_style_handle   = 'valid-style';
		$valid_style_src      = 'path/to/valid-style.css';
		$original_idx_valid   = 0;
		$original_idx_invalid = 1; // Original index in the $styles array

		$styles_to_enqueue = array(
			$original_idx_valid => array(
				'handle' => $valid_style_handle,
				'src'    => $valid_style_src,
				'deps'   => array(),
				'ver'    => false,
				'media'  => 'all',
				'hook'   => $hook_name,
			),
			$original_idx_invalid => array(
				'handle' => '', // Invalid: empty handle
				'src'    => 'path/to/invalid-style.css',
				'hook'   => $hook_name,
			),
		);

		// Expectations for enqueue_styles (deferring both)
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Entered. Processing 2 style definition(s).')->once()->ordered();

		// Valid style deferral
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"{$valid_style_handle}\", original index: {$original_idx_valid}.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Deferring style \"{$valid_style_handle}\" (original index {$original_idx_valid}) to hook: \"{$hook_name}\".")->once()->ordered();

		\WP_Mock::userFunction( 'has_action' )
			->with( $hook_name, array( $this->instance, 'enqueue_deferred_styles' ) )
			->andReturnValues( array( false, true ) ); // false for 1st check (valid style), true for 2nd check (invalid style)

		WP_Mock::expectActionAdded($hook_name, array($this->instance, 'enqueue_deferred_styles'), 10, 1);
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Added action for 'enqueue_deferred_styles' on hook: \"{$hook_name}\".")->once()->ordered(); // NOW ORDERED

		// Invalid style deferral
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Processing style: \"\", original index: {$original_idx_invalid}.")->once()->ordered(); // Handle is empty string
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Deferring style \"\" (original index {$original_idx_invalid}) to hook: \"{$hook_name}\".")->once()->ordered(); // Handle is empty string
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_styles - Action for 'enqueue_deferred_styles' on hook '{$hook_name}' already exists.")->once()->ordered();

		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::enqueue_styles - Exited.')->once()->ordered();

		$this->instance->enqueue_styles($styles_to_enqueue);

		// Expectations for enqueue_deferred_styles
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Entered for hook: \"{$hook_name}\".")->once()->ordered();

		// Processing for the valid style (original_idx_valid = 0)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"{$valid_style_handle}\" (original index {$original_idx_valid}) for hook: \"{$hook_name}\".")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$valid_style_handle}' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered();
		WP_Mock::userFunction('wp_style_is')->with($valid_style_handle, 'registered')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$valid_style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_register_style')->with($valid_style_handle, $valid_style_src, array(), false, 'all')->once();
		WP_Mock::userFunction('wp_style_is')->with($valid_style_handle, 'enqueued')->andReturn(false)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$valid_style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		WP_Mock::userFunction('wp_enqueue_style')->with($valid_style_handle)->once();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Checking for inline styles for '{$valid_style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - Checking for inline styles for parent handle '{$valid_style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_inline_styles (context: enqueue_deferred) - No inline styles found or processed for '{$valid_style_handle}' on hook '{$hook_name}'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$valid_style_handle}' on hook '{$hook_name}'.")->once()->ordered();

		// Processing for the invalid style (original_idx_invalid = 1)
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Processing deferred style: \"\" (original index {$original_idx_invalid}) for hook: \"{$hook_name}\".")->once()->ordered(); // Handle is empty string
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '' on hook '{$hook_name}' in context 'enqueue_deferred'.")->once()->ordered(); // Reverted: Expect '' as per error message
		$this->logger_mock->shouldReceive('warning')->with("EnqueueAbstract::_process_single_style - Invalid style definition. Missing handle or src. Skipping. Handle: '' on hook '{$hook_name}'.")->once()->ordered(); // Reverted: Expect '' as per error message logic

		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::enqueue_deferred_styles - Exited for hook: \"{$hook_name}\".")->once()->ordered();

		WP_Mock::userFunction('wp_register_style')->with('', 'path/to/invalid-style.css', \Mockery::any(), \Mockery::any(), \Mockery::any())->never();
		WP_Mock::userFunction('wp_enqueue_style')->with('')->never();

		$this->instance->enqueue_deferred_styles($hook_name);

		$deferred_styles_after = $this->get_protected_property_value($this->instance, 'deferred_styles');
		$this->assertArrayNotHasKey($hook_name, $deferred_styles_after, "Deferred styles for hook '{$hook_name}' should be cleared after processing, even with invalid items.");
	}


	// region Register Styles

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_styles
	 */
	public function test_register_styles_no_styles_passed_and_internal_queue_empty(): void {
		// Logger expectations
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Entered.')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Processing 0 style definition(s) for registration.')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Exited.')->once()->ordered();

		// Act
		$result = $this->instance->register_styles();

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::register_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::add_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 */
	public function test_register_styles_with_direct_styles_argument(): void {
		// Ensure logger is considered active and verbose for this test, overriding defaults if necessary.
		$this->logger_mock->shouldReceive('is_active')->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldReceive('is_verbose')->zeroOrMoreTimes()->andReturn(true);

		$direct_style_handle = 'direct-arg-style';
		$direct_style_src    = 'path/to/direct-arg-style.css';
		$hooked_style_handle = 'hooked-style';
		$hooked_style_src    = 'path/to/hooked-style.css';
		$hook_name           = 'test_hook';

		$styles_to_pass = array(
			array(
				'handle' => $hooked_style_handle,
				'src'    => $hooked_style_src,
				'hook'   => $hook_name,
			),
			array(
				'handle' => $direct_style_handle,
				'src'    => $direct_style_src,
				// No deps, version, media, condition for simplicity in this test
			)
		);

		// Initial logging from register_styles
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Entered.')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Styles directly passed. Adding them to internal collection via add_styles().')->once()->ordered();

		// Logging from add_styles (called internally by register_styles when styles are passed)
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract: Adding 2 styles to the queue.')->once()->ordered();

		// Back to register_styles logging - now processing 2 styles
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Processing 2 style definition(s) for registration.')->once()->ordered();

		// First style (hooked-style) - should be skipped
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::register_styles - Attempting to process style: \"{$hooked_style_handle}\", original index: 0.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::register_styles - Style \"{$hooked_style_handle}\" has a hook '{$hook_name}'. Skipping registration here (deferred handling).")->once()->ordered();

		// Second style (direct-arg-style) - should be processed
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::register_styles - Attempting to process style: \"{$direct_style_handle}\", original index: 1.")->once()->ordered();

		// _process_single_style logs for direct_style_handle
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Processing style '{$direct_style_handle}' in context 'register_styles'.")->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Registering style '{$direct_style_handle}'.")->once()->ordered();
		WP_Mock::userFunction('wp_register_style')->with($direct_style_handle, $direct_style_src, array(), false, 'all')->once()->ordered();
		// Enqueue and inline processing should be skipped for direct_style_handle in this context
		WP_Mock::userFunction('wp_style_is')->with($direct_style_handle, 'enqueued')->never();
		$this->logger_mock->shouldReceive('debug')->with("EnqueueAbstract::_process_single_style - Finished processing style '{$direct_style_handle}'.")->once()->ordered();

		// Final log from register_styles
		$this->logger_mock->shouldReceive('debug')->with('EnqueueAbstract::register_styles - Exited.')->once()->ordered();

		// Act
		$result = $this->instance->register_styles($styles_to_pass);

		// Assert
		$this->assertSame($this->instance, $result, 'Method should be chainable.');

		// Verify internal state ($this->styles should be populated by add_styles)
		$internal_styles = $this->get_protected_property_value($this->instance, 'styles');
		$this->assertCount(2, $internal_styles, 'Internal styles property should contain both passed styles.');
		// We need to check the content more carefully if order matters or if add_styles re-indexes.
		// For now, let's assume add_styles preserves the order and structure for what's stored.
		$this->assertEquals($styles_to_pass, $internal_styles, 'Internal styles property should match the passed styles.');
	}

	// endregion Register Styles

	// region Enqueue Inline Styles

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test_enqueue_inline_styles_no_immediate_styles(): void {
		// Create a partial mock for EnqueueAbstract to mock _process_inline_styles and get_logger
		$sut = $this->getMockBuilder(EnqueueAbstract::class)
			->setConstructorArgs(array($this->config_instance_mock))
			->onlyMethods(array('_process_inline_styles', 'get_logger'))
			->getMockForAbstractClass();

		// Ensure the SUT uses our logger mock
		$sut->method('get_logger')->willReturn($this->logger_mock);

		// Set $this->inline_styles to an empty array
		$this->set_protected_property_value($sut, 'inline_styles', array());

		$this->logger_mock->shouldReceive('is_active')->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldReceive('is_verbose')->zeroOrMoreTimes()->andReturn(true); // Though not directly used by enqueue_inline_styles, good for consistency

		// Expected log messages
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Entered method. Attempting to process any remaining immediate inline styles.')
			->once();
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - No immediate inline styles found needing processing.')
			->once();

		// _process_inline_styles should never be called
		$sut->expects($this->never())
			->method('_process_inline_styles');

		// Act
		$result = $sut->enqueue_inline_styles();

		// Assert
		$this->assertSame($sut, $result, 'Method should be chainable.');
	}


	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test_enqueue_inline_styles_with_one_immediate_style_unique_handle(): void {
		$sut = $this->getMockBuilder(EnqueueAbstract::class)
			->setConstructorArgs(array($this->config_instance_mock))
			->onlyMethods(array('_process_inline_styles', 'get_logger'))
			->getMockForAbstractClass();

		$sut->method('get_logger')->willReturn($this->logger_mock);

		$parent_handle       = 'parent-style-1';
		$inline_styles_array = array(
			array(
				'handle'      => $parent_handle,
				'content'     => '.my-class { color: red; }',
				'position'    => 'after',
				'condition'   => null,
				'parent_hook' => null, // Indicates an immediate style
			)
		);
		$this->set_protected_property_value($sut, 'inline_styles', $inline_styles_array);

		$this->logger_mock->shouldReceive('is_active')->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldReceive('is_verbose')->zeroOrMoreTimes()->andReturn(true);

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Entered method. Attempting to process any remaining immediate inline styles.')
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Found 1 unique parent handle(s) with immediate inline styles to process: ' . esc_html($parent_handle))
			->once();

		$sut->expects($this->once())
			->method('_process_inline_styles')
			->with(
				$this->equalTo($parent_handle),
				$this->isNull(),
				$this->equalTo('enqueue_inline_styles')
			);
		// Ensure it's called after the 'Found' log - ordering primarily handled by logger expectations

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Exited method.')
			->once(); // After _process_inline_styles is notionally called

		// Act
		$result = $sut->enqueue_inline_styles();

		// Assert
		$this->assertSame($sut, $result, 'Method should be chainable.');
	}

	/**
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test_enqueue_inline_styles_multiple_immediate_styles_various_handles(): void {
		$sut = $this->getMockBuilder(EnqueueAbstract::class)
			->setConstructorArgs(array($this->config_instance_mock))
			->onlyMethods(array('_process_inline_styles', 'get_logger'))
			->getMockForAbstractClass();

		$sut->method('get_logger')->willReturn($this->logger_mock);

		$handle_alpha = 'parent-style-alpha';
		$handle_beta  = 'parent-style-beta';
		$handle_gamma = 'parent-style-gamma'; // Hooked
		$handle_delta = 'parent-style-delta';

		$inline_styles_array = array(
			array('handle' => $handle_alpha, 'content' => 'css1', 'parent_hook' => null),      // Immediate
			array('handle' => $handle_beta,  'content' => 'css2', 'parent_hook' => null),      // Immediate
			array('handle' => $handle_alpha, 'content' => 'css3', 'parent_hook' => null),      // Immediate, duplicate parent
			array('handle' => $handle_gamma, 'content' => 'css4', 'parent_hook' => 'some_hook'), // Hooked
			array('handle' => $handle_delta, 'content' => 'css5', 'parent_hook' => null),      // Immediate
		);
		$this->set_protected_property_value($sut, 'inline_styles', $inline_styles_array);

		$expected_processed_handles = array($handle_alpha, $handle_beta, $handle_delta);

		$this->logger_mock->shouldReceive('is_active')->zeroOrMoreTimes()->andReturn(true);
		$this->logger_mock->shouldReceive('is_verbose')->zeroOrMoreTimes()->andReturn(true);

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Entered method. Attempting to process any remaining immediate inline styles.')
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Found ' . count($expected_processed_handles) . ' unique parent handle(s) with immediate inline styles to process: ' . implode(', ', array_map('esc_html', $expected_processed_handles)))
			->once();

		$sut->expects($this->exactly(count($expected_processed_handles)))
			->method('_process_inline_styles')
			->withConsecutive(
				array($this->equalTo($handle_alpha), $this->isNull(), $this->equalTo('enqueue_inline_styles')),
				array($this->equalTo($handle_beta),  $this->isNull(), $this->equalTo('enqueue_inline_styles')),
				array($this->equalTo($handle_delta), $this->isNull(), $this->equalTo('enqueue_inline_styles'))
			);
		// Note: Mockery's ordered() applies to all expectations on $this->logger_mock.
		// PHPUnit's withConsecutive asserts order for these specific calls to _process_inline_styles.

		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Exited method.')
			->once();

		// Act
		$result = $sut->enqueue_inline_styles();

		// Assert
		$this->assertSame($sut, $result, 'Method should be chainable.');
	}
	// endregion Enqueue Inline Styles
	// region Tests for _process_inline_styles error handling

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_skips_non_array_item_and_logs_warning(): void {
		$sut = $this->instance;

		$parent_handle      = 'parent-for-invalid-item';
		$valid_inline_style = array(
			'handle'      => $parent_handle,
			'content'     => 'p { color: green; }',
			'position'    => 'after',
			'condition'   => null,
			'parent_hook' => null,
		);
		$invalid_item = 'this-is-not-an-array'; // Malformed item

		// Set inline_styles with a mix of valid and invalid items
		$this->set_protected_property_value($sut, 'inline_styles', array(
			$valid_inline_style, // A valid one to ensure loop continues (key 0)
			$invalid_item,       // The invalid one (key 1)
		));
		$processing_context = 'test_context_invalid_item';
		$invalid_item_key   = 1; // The original key of the invalid item

		// Expect logs and WP_Mock calls in order
		$this->logger_mock->shouldReceive('debug') // 1. Initial check
			->with(Mockery::on(function ($message) use ($parent_handle, $processing_context) {
				return strpos($message, "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Checking for inline styles for parent handle '{$parent_handle}'.") !== false;
			}))
			->once()
	// endregion Tests for _process_inline_styles error handling
			->ordered();

		$this->logger_mock->shouldReceive('debug') // 2. Processing valid item (log "Adding")
			->with(Mockery::on(function ($message) use ($parent_handle) {
				return strpos($message, "Adding inline style for '{$parent_handle}' (key: 0, position: after)") !== false;
			}))
			->once();

		WP_Mock::userFunction('wp_add_inline_style') // 3. Actual style addition for valid item
			->with($parent_handle, $valid_inline_style['content'], $valid_inline_style['position'])
			->once();

		$this->logger_mock->shouldReceive('warning') // 4. Warning for invalid item
			->with("EnqueueAbstract::_process_inline_styles (context: test_context_invalid_item) -  Invalid inline style data at key '1'. Skipping.")
			->once();

		$this->logger_mock->shouldReceive('debug') // 5. Removal of processed valid item (log "Removed")
			->with(Mockery::on(function ($message) use ($parent_handle) {
				return strpos($message, "Removed processed inline style with key '0' for handle '{$parent_handle}'") !== false;
			}))
			->once();

		// Call the protected method
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, null, $processing_context);

		// Assert the state of inline_styles after processing
		// The valid item (original key 0) should be removed.
		// The invalid item (original key 1) was skipped and not added to keys_to_unset.
		// After unsetting key 0 and re-indexing, the invalid item should be at key 0.
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(1, $remaining_styles, 'Only the invalid item should remain after processing and re-indexing.');
		$this->assertSame($invalid_item, $remaining_styles[0], 'The remaining item should be the invalid one.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_processes_item_when_callable_condition_is_true(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-style-callable-condition-true';
		$hook_name          = 'test_hook_callable_condition_true';
		$processing_context = 'test_context_callable_condition_true';
		$style_key          = 0; // Since it's the only item
		$style_content      = '.my-class { color: blue; }';
		$style_position     = 'after';

		$style_with_callable_condition = array(
			'handle'      => $parent_handle, // Correct key for matching
			'parent_hook' => $hook_name,     // Correct key for matching
			'content'     => $style_content,
			'position'    => $style_position,
			'condition'   => function () {
				return true;
			},
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_with_callable_condition));

		// Mock logger expectations
		$this->logger_mock->shouldReceive('debug') // 1. Initial check
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $processing_context) {
				return strpos($message, "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Checking for inline styles for parent handle '{$parent_handle}' on hook '{$hook_name}'.") !== false;
			}))
			->once();

		// Condition is true, so no 'Condition false' log.

		$this->logger_mock->shouldReceive('debug') // 2. Adding inline style log
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $style_key, $style_position, $processing_context) {
				$expected_log_part   = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Adding inline style for '{$parent_handle}' (key: {$style_key}, position: {$style_position})";
				$expected_log_suffix = " on hook '{$hook_name}'.";
				return strpos($message, $expected_log_part) !== false && strpos($message, $expected_log_suffix) !== false;
			}))
			->once();

		WP_Mock::userFunction('wp_add_inline_style')
			->with($parent_handle, $style_content, $style_position)
			// ->andReturn(true) // Not strictly needed for this test flow if not checking return, but good practice.
			->once();

		$this->logger_mock->shouldReceive('debug') // 3. Removed processed style log
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $style_key, $processing_context) {
				$expected_log_part   = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Removed processed inline style with key '{$style_key}' for handle '{$parent_handle}'";
				$expected_log_suffix = " on hook '{$hook_name}'.";
				return strpos($message, $expected_log_part) !== false && strpos($message, $expected_log_suffix) !== false;
			}))
			->once();

		// Call the protected method
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $hook_name, $processing_context);

		// Assert that the style was processed and removed
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(0, $remaining_styles, 'Style with true condition should be processed and removed.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_skips_item_when_callable_condition_is_false(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-style-callable-condition-false';
		$hook_name          = 'test_hook_callable_condition_false';
		$processing_context = 'test_context_callable_condition_false';
		$style_key          = 0; // Since it's the only item

		$style_with_callable_condition = array(
			'handle'      => $parent_handle, // Correct key for matching
			'parent_hook' => $hook_name,     // Correct key for matching
			'content'     => '.another-class { display: none; }',
			'position'    => 'before',
			'condition'   => function () {
				return false;
			},
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_with_callable_condition));

		// Mock logger expectations
		$this->logger_mock->shouldReceive('debug') // 1. Initial check
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $processing_context) {
				return strpos($message, "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Checking for inline styles for parent handle '{$parent_handle}' on hook '{$hook_name}'.") !== false;
			}))
			->once();

		$this->logger_mock->shouldReceive('debug') // 2. Condition false log
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $style_key, $processing_context) {
				$expected_log_part   = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Condition false for inline style targeting '{$parent_handle}' (key: {$style_key})";
				$expected_log_suffix = " on hook '{$hook_name}'.";
				return strpos($message, $expected_log_part) !== false && strpos($message, $expected_log_suffix) !== false;
			}))
			->once();

		WP_Mock::userFunction('wp_add_inline_style')->never(); // Should not be called

		$this->logger_mock->shouldReceive('debug') // 3. Removed processed style log
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $style_key, $processing_context) {
				$expected_log_part   = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Removed processed inline style with key '{$style_key}' for handle '{$parent_handle}'";
				$expected_log_suffix = " on hook '{$hook_name}'.";
				return strpos($message, $expected_log_part) !== false && strpos($message, $expected_log_suffix) !== false;
			}))
			->once();

		// Call the protected method
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $hook_name, $processing_context);

		// Assert that the style was removed because its condition was false (and thus added to keys_to_unset)
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(0, $remaining_styles, 'Style with false condition should be processed (checked) and removed.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_skips_item_with_empty_content_and_logs_warning(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-style-empty-content';
		$hook_name          = 'test_hook_empty_content'; // Ensuring hook_name is present for full log message
		$processing_context = 'test_context_empty_content';
		$style_key          = 0; // Since it's the only item

		$style_with_empty_content = array(
			'handle'      => $parent_handle,
			'parent_hook' => $hook_name,
			'content'     => '', // Empty content
			'position'    => 'after',
			'condition'   => function () { // Condition that passes
				return true;
			},
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_with_empty_content));

		// Mock logger expectations
		$this->logger_mock->shouldReceive('debug') // 1. Initial check
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $processing_context) {
				$expected_message = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Checking for inline styles for parent handle '{$parent_handle}' on hook '{$hook_name}'.";
				return $message === $expected_message;
			}))
			->once();

		$this->logger_mock->shouldReceive('warning') // 2. Empty content warning
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $style_key, $processing_context) {
				$expected_message = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Empty content for inline style targeting '{$parent_handle}' (key: {$style_key}) on hook '{$hook_name}'. Skipping addition.";
				return $message === $expected_message;
			}))
			->once();

		WP_Mock::userFunction('wp_add_inline_style')->never(); // Should not be called

		$this->logger_mock->shouldReceive('debug') // 3. Removed processed style log
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $style_key, $processing_context) {
				$expected_message = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Removed processed inline style with key '{$style_key}' for handle '{$parent_handle}' on hook '{$hook_name}'.";
				return $message === $expected_message;
			}))
			->once();

		// Call the protected method
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $hook_name, $processing_context);

		// Assert that the style was removed because its content was empty (and thus added to keys_to_unset)
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(0, $remaining_styles, 'Style with empty content should be processed (checked) and removed.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_logs_when_no_matching_styles_found(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-no-match';
		$hook_name          = 'test_hook_no_match';
		$processing_context = 'test_context_no_match';

		// Ensure inline_styles is empty
		$this->set_protected_property_value($sut, 'inline_styles', array());

		// Mock logger expectations
		$this->logger_mock->shouldReceive('debug') // 1. Initial check
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $processing_context) {
				$expected_message = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - Checking for inline styles for parent handle '{$parent_handle}' on hook '{$hook_name}'.";
				return $message === $expected_message;
			}))
			->once();

		$this->logger_mock->shouldReceive('debug') // 2. No styles found/processed log
			->with(Mockery::on(function ($message) use ($parent_handle, $hook_name, $processing_context) {
				$expected_message = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - No inline styles found or processed for '{$parent_handle}' on hook '{$hook_name}'.";
				return $message === $expected_message;
			}))
			->once();

		$this->logger_mock->shouldNotReceive('warning'); // No warnings should occur
		WP_Mock::userFunction('wp_add_inline_style')->never(); // Should not be called

		// Call the protected method
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $hook_name, $processing_context);

		// Assert that inline_styles remains empty as no matching styles were processed
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertEmpty($remaining_styles, 'Inline styles should remain empty when no matches are found.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_skips_non_array_item_and_it_persists(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-mixed-items';
		$hook_name          = 'test_hook_mixed_items';
		$processing_context = 'test_context_mixed_items';

		$valid_style_before = array(
			'handle'      => $parent_handle,
			'parent_hook' => $hook_name,
			'content'     => '/* valid style before */',
			'position'    => 'after',
		);
		$non_array_item    = 'this is not an array';
		$valid_style_after = array(
			'handle'      => $parent_handle,
			'parent_hook' => $hook_name,
			'content'     => '/* valid style after */',
			'position'    => 'after',
		);

		// Key 0: valid, Key 1: invalid, Key 2: valid
		$this->set_protected_property_value($sut, 'inline_styles', array(
			0 => $valid_style_before,
			1 => $non_array_item,
			2 => $valid_style_after,
		));

		// Mock logger expectations
		$log_prefix = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - ";

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Checking for inline styles for parent handle '{$parent_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		// For $valid_style_before (key 0)
		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Adding inline style for '{$parent_handle}' (key: 0, position: {$valid_style_before['position']}) on hook '{$hook_name}'.")
			->once()->ordered();
		WP_Mock::userFunction('wp_add_inline_style')
			->with($parent_handle, $valid_style_before['content'], $valid_style_before['position'])
			->once()->andReturn(true)->ordered();

		// For $non_array_item (key 1)
		$this->logger_mock->shouldReceive('warning')
			->with($log_prefix . " Invalid inline style data at key '1'. Skipping.") // Added extra space after $log_prefix
			->once()->ordered();

		// For $valid_style_after (key 2)
		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Adding inline style for '{$parent_handle}' (key: 2, position: {$valid_style_after['position']}) on hook '{$hook_name}'.")
			->once()->ordered();
		WP_Mock::userFunction('wp_add_inline_style')
			->with($parent_handle, $valid_style_after['content'], $valid_style_after['position'])
			->once()->andReturn(true)->ordered();

		// Removal logs for processed styles (key 0 and key 2)
		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Removed processed inline style with key '0' for handle '{$parent_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Removed processed inline style with key '2' for handle '{$parent_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		// Call the protected method
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $hook_name, $processing_context);

		// Assert that the non-array item persists and valid items are removed
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(1, $remaining_styles, 'Only the non-array item should remain.');
		// After array_values, the remaining item will be at key 0.
		$this->assertSame($non_array_item, $remaining_styles[0], 'The remaining item should be the non-array item.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_skips_item_with_mismatched_hook_deferred_context(): void {
		$sut                       = $this->instance;
		$parent_handle             = 'test-parent-hook-mismatch';
		$correct_hook_name         = 'correct_hook';
		$wrong_hook_name_for_style = 'wrong_hook';
		$processing_context        = 'test_context_hook_mismatch';

		$style_with_wrong_hook = array(
			'handle'      => $parent_handle, // Matches parent_handle
			'parent_hook' => $wrong_hook_name_for_style, // Does NOT match correct_hook_name
			'content'     => '/* some content */',
			'position'    => 'after',
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_with_wrong_hook));

		// Mock logger expectations
		$log_prefix = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - ";

		$this->logger_mock->shouldReceive('debug') // 1. Initial check
			->with($log_prefix . "Checking for inline styles for parent handle '{$parent_handle}' on hook '{$correct_hook_name}'.")
			->once()->ordered();

		// No processing logs for the style itself (add, condition, empty, remove)

		$this->logger_mock->shouldReceive('debug') // 2. No styles found/processed log
			->with($log_prefix . "No inline styles found or processed for '{$parent_handle}' on hook '{$correct_hook_name}'.")
			->once()->ordered();

		$this->logger_mock->shouldNotReceive('warning');
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// Call the protected method, passing the correct_hook_name
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $correct_hook_name, $processing_context);

		// Assert that the style remains as it wasn't matched and processed
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(1, $remaining_styles, 'The style with mismatched hook should remain.');
		$this->assertSame($style_with_wrong_hook, $remaining_styles[0], 'The remaining style should be the one with the mismatched hook.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_skips_item_in_immediate_context_if_style_has_parent_hook(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-immediate-mismatch';
		$processing_context = 'test_context_immediate_mismatch';

		$style_with_parent_hook = array(
			'handle'      => $parent_handle, // Matches parent_handle
			'parent_hook' => 'some_specific_hook', // Non-empty, so should not match in immediate context
			'content'     => '/* some content */',
			'position'    => 'after',
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_with_parent_hook));

		// Mock logger expectations
		$log_prefix = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - ";

		$this->logger_mock->shouldReceive('debug') // 1. Initial check (ends with '.')
			->with($log_prefix . "Checking for inline styles for parent handle '{$parent_handle}'.")
			->once()->ordered();

		// No processing logs for the style itself (add, condition, empty, remove)

		$this->logger_mock->shouldReceive('debug') // 2. No styles found/processed log (ends with '.')
			->with($log_prefix . "No inline styles found or processed for '{$parent_handle}'.")
			->once()->ordered();

		$this->logger_mock->shouldNotReceive('warning');
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// Call the protected method with hook_name = null for immediate context
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, null, $processing_context);

		// Assert that the style remains as it wasn't matched and processed
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(1, $remaining_styles, 'The style with a parent_hook should remain in immediate context.');
		$this->assertSame($style_with_parent_hook, $remaining_styles[0], 'The remaining style should be the original one.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_skips_item_with_mismatched_handle(): void {
		$sut                      = $this->instance;
		$parent_handle_to_process = 'correct-parent-handle';
		$style_handle_in_item     = 'wrong-style-handle'; // This will not match
		$processing_context       = 'test_context_handle_mismatch';

		$style_with_wrong_handle = array(
			'handle'      => $style_handle_in_item, // Does NOT match parent_handle_to_process
			'parent_hook' => null, // Irrelevant as handle check fails first
			'content'     => '/* some content */',
			'position'    => 'after',
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_with_wrong_handle));

		// Mock logger expectations
		$log_prefix = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - ";

		$this->logger_mock->shouldReceive('debug') // 1. Initial check (immediate context, so ends with '.')
			->with($log_prefix . "Checking for inline styles for parent handle '{$parent_handle_to_process}'.")
			->once()->ordered();

		// No processing logs for the style itself (add, condition, empty, remove)

		$this->logger_mock->shouldReceive('debug') // 2. No styles found/processed log (immediate context, so ends with '.')
			->with($log_prefix . "No inline styles found or processed for '{$parent_handle_to_process}'.")
			->once()->ordered();

		$this->logger_mock->shouldNotReceive('warning');
		WP_Mock::userFunction('wp_add_inline_style')->never();

		// Call the protected method with hook_name = null for immediate context
		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle_to_process, null, $processing_context);

		// Assert that the style remains as it wasn't matched and processed
		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(1, $remaining_styles, 'The style with a mismatched handle should remain.');
		$this->assertSame($style_with_wrong_handle, $remaining_styles[0], 'The remaining style should be the original one with the wrong handle.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_uses_default_position_when_not_specified(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-default-pos';
		$hook_name          = 'test_hook_default_pos';
		$processing_context = 'test_context_default_pos';

		$style_data_no_position = array(
			'handle'  => $parent_handle,
			'content' => '/* some content for default position test */',
			// 'position' key is deliberately omitted to test default 'after'
			'parent_hook' => $hook_name,
			'condition'   => fn() => true,
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_data_no_position));

		$log_prefix = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - ";

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Checking for inline styles for parent handle '{$parent_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		// Condition is true (or not checked if not callable, but here it is)
		// Content is not empty

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Adding inline style for '{$parent_handle}' (key: 0, position: after) on hook '{$hook_name}'.") // Expect 'after' as default
			->once()->ordered();

		WP_Mock::userFunction('wp_add_inline_style')
			->with($parent_handle, $style_data_no_position['content'], 'after') // Assert 'after' is used
			->once()->andReturn(true)->ordered();

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Removed processed inline style with key '0' for handle '{$parent_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		$this->logger_mock->shouldNotReceive('warning');

		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $hook_name, $processing_context);

		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(0, $remaining_styles, 'Style should have been processed and removed.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_inline_styles_adds_style_when_condition_key_is_missing(): void {
		$sut                = $this->instance;
		$parent_handle      = 'test-parent-no-condition-key';
		$hook_name          = 'test_hook_no_condition_key';
		$processing_context = 'test_context_no_condition_key';

		$style_data_no_condition_key = array(
			'handle'      => $parent_handle,
			'content'     => '/* some content for missing condition key test */',
			'parent_hook' => $hook_name,
			// 'condition' key is deliberately omitted
			// 'position' key is also omitted to rely on default 'after'
		);

		$this->set_protected_property_value($sut, 'inline_styles', array($style_data_no_condition_key));

		$log_prefix = "EnqueueAbstract::_process_inline_styles (context: {$processing_context}) - ";

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Checking for inline styles for parent handle '{$parent_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		// Since 'condition' key is missing, $condition_inline becomes null, is_callable(null) is false.
		// The style should proceed to be added.

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Adding inline style for '{$parent_handle}' (key: 0, position: after) on hook '{$hook_name}'.") // Expect 'after' as default position
			->once()->ordered();

		WP_Mock::userFunction('wp_add_inline_style')
			->with($parent_handle, $style_data_no_condition_key['content'], 'after') // Assert 'after' is used for position
			->once()->andReturn(true)->ordered();

		$this->logger_mock->shouldReceive('debug')
			->with($log_prefix . "Removed processed inline style with key '0' for handle '{$parent_handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		$this->logger_mock->shouldNotReceive('warning');

		$reflection = new \ReflectionObject($sut);
		$method     = $reflection->getMethod('_process_inline_styles');
		$method->setAccessible(true);
		$method->invoke($sut, $parent_handle, $hook_name, $processing_context);

		$remaining_styles = $this->get_protected_property_value($sut, 'inline_styles');
		$this->assertCount(0, $remaining_styles, 'Style should have been processed and removed.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::enqueue_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_inline_styles
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test_enqueue_inline_styles_skips_non_array_item_and_logs_warning(): void {
		// Create a partial mock of EnqueueAbstract to mock _process_inline_styles and get_logger
		$sut = $this->getMockBuilder(EnqueueAbstract::class)
			->setConstructorArgs(array($this->config_mock, $this->logger_mock)) // Corrected order: config, then logger
			->onlyMethods(array('_process_inline_styles', 'get_logger'))
			->getMockForAbstractClass();

		// Ensure get_logger returns our specific logger_mock for expectation setting
		$sut->method('get_logger')->willReturn($this->logger_mock);

		$valid_immediate_handle = 'immediate-style-handle';
		$item1_valid_immediate  = array(
			'handle'  => $valid_immediate_handle,
			'content' => '/* Immediate style content */',
			// parent_hook is implicitly null/empty for immediate processing
		);
		$item2_non_array      = 'This is a string, not an array-based style configuration.';
		$item3_valid_deferred = array(
			'handle'      => 'deferred-style-handle',
			'content'     => '/* Deferred style content */',
			'parent_hook' => 'some_action_hook',
		);

		$inline_styles_with_mixed_types = array(
			$item1_valid_immediate, // key 0
			$item2_non_array,       // key 1
			$item3_valid_deferred,  // key 2
		);
		$this->set_protected_property_value($sut, 'inline_styles', $inline_styles_with_mixed_types);

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Entered method. Attempting to process any remaining immediate inline styles.')
			->once()->ordered();

		// Expect warning for the non-array item (key 1)
		$this->logger_mock->shouldReceive('warning')
			->with("EnqueueAbstract::enqueue_inline_styles - Invalid inline style data at key '1'. Skipping.")
			->once()->ordered();

		// Expect log for found unique parent handles
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::enqueue_inline_styles - Found 1 unique parent handle(s) with immediate inline styles to process: {$valid_immediate_handle}")
			->once()->ordered();

		// Expect _process_inline_styles to be called for the immediate style
		$sut->expects($this->once())
			->method('_process_inline_styles')
			->with(
				$this->equalTo($valid_immediate_handle),
				$this->isNull(),
				$this->equalTo('enqueue_inline_styles') // Corrected context string
			);

		// Expect final exit log
		$this->logger_mock->shouldReceive('debug')
			->with('EnqueueAbstract::enqueue_inline_styles - Exited method.')
			->once()->ordered();

		// Call the method under test
		$sut->enqueue_inline_styles();

		// Mockery and PHPUnit's mock expectations handle the assertions implicitly.
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_single_style_deferred_skips_register_if_already_registered(): void {
		$handle     = 'test-style-deferred-reg-skip';
		$style_data = array(
			'handle'  => $handle, // Added handle to style_data
			'src'     => 'path/to/style.css',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
		);
		$hook_name          = 'test_hook';
		$processing_context = 'enqueue_deferred';
		$do_register        = true;
		$do_enqueue         = true;

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Processing style '{$handle}' on hook '{$hook_name}' in context '{$processing_context}'.")
			->once()->ordered();

		// Mock wp_style_is for registration check
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->once()
			->andReturn(true); // Style is already registered

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Style '{$handle}' on hook '{$hook_name}' already registered. Skipping wp_register_style.")
			->once()->ordered();

		// wp_register_style should NOT be called
		WP_Mock::userFunction('wp_register_style')->never();

		// Mock wp_style_is for enqueue check (assume not enqueued for this part of the test)
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(false);

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Enqueuing style '{$handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		WP_Mock::userFunction('wp_enqueue_style')
			->with($handle)
			->once();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Finished processing style '{$handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		$result = $this->instance->_process_single_style($style_data, $processing_context, $hook_name, $do_register, $do_enqueue);
		$this->assertTrue($result, '_process_single_style should return true when successfully processing a style, even if registration is skipped.');
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::_process_single_style
	 * @covers \Ran\PluginLib\EnqueueAccessory\EnqueueAbstract::get_logger
	 */
	public function test__process_single_style_deferred_skips_enqueue_if_already_enqueued(): void {
		$handle     = 'test-style-deferred-enq-skip';
		$style_data = array(
			'handle'  => $handle, // Added handle to style_data
			'src'     => 'path/to/style.css',
			'deps'    => array(),
			'version' => '1.0',
			'media'   => 'all',
		);
		$hook_name          = 'test_hook';
		$processing_context = 'enqueue_deferred';
		$do_register        = true;
		$do_enqueue         = true;

		// Logger expectations
		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Processing style '{$handle}' on hook '{$hook_name}' in context '{$processing_context}'.")
			->once()->ordered();

		// Mock wp_style_is for registration check (assume not registered, so it proceeds)
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'registered')
			->once()
			->andReturn(false);

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Registering style '{$handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		WP_Mock::userFunction('wp_register_style')
			->with($handle, $style_data['src'], $style_data['deps'], $style_data['version'], $style_data['media'])
			->once();

		// Mock wp_style_is for enqueue check
		WP_Mock::userFunction('wp_style_is')
			->with($handle, 'enqueued')
			->once()
			->andReturn(true); // Style is already enqueued

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Style '{$handle}' on hook '{$hook_name}' already enqueued. Skipping wp_enqueue_style.")
			->once()->ordered();

		// wp_enqueue_style should NOT be called
		WP_Mock::userFunction('wp_enqueue_style')->never();

		$this->logger_mock->shouldReceive('debug')
			->with("EnqueueAbstract::_process_single_style - Finished processing style '{$handle}' on hook '{$hook_name}'.")
			->once()->ordered();

		$result = $this->instance->_process_single_style($style_data, $processing_context, $hook_name, $do_register, $do_enqueue);
		$this->assertTrue($result, '_process_single_style should return true when successfully processing a style, even if enqueueing is skipped.');
	}
}
