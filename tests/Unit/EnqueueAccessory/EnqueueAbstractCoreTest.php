<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\Logger;
use Mockery;
use ReflectionProperty;
use WP_Mock;
use Ran\PluginLib\EnqueueAccessory\ScriptsEnqueueTrait;
use Ran\PluginLib\EnqueueAccessory\StylesEnqueueTrait;
use Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait;

if (!class_exists('Ran\PluginLib\Tests\Unit\EnqueueAccessory\ConcreteEnqueueForCoreTesting')) {
	/**
	 * Concrete implementation of EnqueueAbstract for testing core methods.
	 */
	class ConcreteEnqueueForCoreTesting extends AssetEnqueueBaseAbstract {
		use ScriptsEnqueueTrait, StylesEnqueueTrait, MediaEnqueueTrait;
		public function load(): void {
			// Mock implementation, does nothing for these tests.
		}

		// Expose protected properties for easier testing in this suite
		public function set_scripts_for_testing(array $scripts): void {
			$this->scripts = $scripts;
		}
		public function set_styles_for_testing(array $styles): void {
			$this->styles = $styles;
		}
		public function set_media_tool_configs_for_testing(array $media_configs): void {
			$this->media_tool_configs = $media_configs;
		}
		public function set_inline_scripts_for_testing(array $inline_scripts): void {
			$this->inline_scripts = $inline_scripts;
		}
		public function set_head_callbacks_for_testing(array $callbacks): void {
			$this->head_callbacks = $callbacks;
		}
		public function set_footer_callbacks_for_testing(array $callbacks): void {
			$this->footer_callbacks = $callbacks;
		}
	}
}

/**
 * Class EnqueueAbstractCoreTest
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::enqueue
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_head
 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_footer
 */
class EnqueueAbstractCoreTest extends PluginLibTestCase {
	// Properties $config_mock (ConcreteConfigForTesting) and $logger_mock (Mockery\MockInterface)
	// are now effectively inherited or will be correctly typed and populated.
	// We will ensure $this->logger_mock is a Mockery mock for setting expectations.
	protected ConcreteEnqueueForCoreTesting $sut;
	protected Mockery\MockInterface $logger_mock; // Explicitly define for clarity, will be Mockery mock.

	public function setUp(): void {
		parent::setUp(); // Handles WP_Mock setup via RanTestCase
		WP_Mock::userFunction( '_doing_it_wrong' )->andReturnNull();

		// Get the concrete config instance set up by PluginLibTestCase's helpers
		// This assigns to the inherited $this->config_mock of type ConcreteConfigForTesting
		$this->config_mock = $this->get_and_register_concrete_config_instance();

		// Create a new Mockery mock for the Logger.
		$this->logger_mock = Mockery::mock(Logger::class);

		// Use reflection to set the private 'logger' property in ConfigAbstract
		// to our $this->logger_mock instance.
		$configAbstractReflection = new \ReflectionClass(\Ran\PluginLib\Config\ConfigAbstract::class);
		$loggerProperty           = $configAbstractReflection->getProperty('logger');
		$loggerProperty->setAccessible(true);
		// Set the value on $this->config_mock, which is an instance of a child of ConfigAbstract
		$loggerProperty->setValue($this->config_mock, $this->logger_mock);

		// Verify that the config mock now returns our injected logger mock.
		$this->assertSame($this->logger_mock, $this->config_mock->get_logger(), 'Config mock should return the injected logger mock.');

		// Set up default permissive logging expectations on our $this->logger_mock
		// These should be specific in tests where specific log messages are expected.
		$this->logger_mock->shouldReceive('debug')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('info')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('warning')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('error')->withAnyArgs()->zeroOrMoreTimes()->andReturnNull()->byDefault();
		$this->logger_mock->shouldReceive('is_active')->withNoArgs()->zeroOrMoreTimes()->andReturn(true)->byDefault();

		// Instantiate the System Under Test (SUT)
		// It will use $this->config_mock and, through it, our $this->logger_mock.
		$this->sut = new ConcreteEnqueueForCoreTesting($this->config_mock);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::enqueue
	 */
	public function test_enqueue_processes_all_asset_types_when_present(): void {
		// Create a dedicated ConfigInterface mock for this test.
		$local_config_mock = Mockery::mock(\Ran\PluginLib\Config\ConfigInterface::class);
		// Ensure this dedicated config mock returns the logger_mock we've set expectations on.
		$local_config_mock->shouldReceive('get_logger')->andReturn($this->logger_mock)->times(2);

		// Create SUT using PHPUnit's getMockBuilder to ensure methods are properly stubbed.
		$sut = $this->getMockBuilder(ConcreteEnqueueForCoreTesting::class)
		    ->setConstructorArgs(array($local_config_mock))
		    ->onlyMethods(array('enqueue_scripts', 'enqueue_styles', 'enqueue_media', 'enqueue_inline_scripts'))
		    ->getMock();

		// Populate assets on the SUT mock.
		// These setters are public methods on ConcreteEnqueueForCoreTesting and not stubbed.
		$sut->set_scripts_for_testing(array(array('handle' => 'test-script')));
		$sut->set_styles_for_testing(array(array('handle' => 'test-style')));
		$sut->set_media_tool_configs_for_testing(array(array('args' => array('post' => 1))));
		$sut->set_inline_scripts_for_testing(array(array('handle' => 'test-script', 'data' => 'var a = 1;')));

		// Logger expectations (Mockery)
		// Expect 2 debug calls: enqueue_started and enqueue_finished (constructor log bypassed).
		$this->logger_mock->shouldReceive('debug')->with(Mockery::any())->times(2);

		// SUT method expectations (PHPUnit)
		$sut->expects($this->once())
		    ->method('enqueue_scripts')
		    ->with(array(array('handle' => 'test-script')))
		    ->willReturn($sut);
		$sut->expects($this->once())
		    ->method('enqueue_styles')
		    ->with(array(array('handle' => 'test-style')))
		    ->willReturn($sut);
		$sut->expects($this->once())
		    ->method('enqueue_media')
		    ->with(array(array('args' => array('post' => 1))))
		    ->willReturn($sut);
		$sut->expects($this->once())
		    ->method('enqueue_inline_scripts')
		    ->willReturn($sut);

		// Diagnostic: Verify SUT's internal config and logger before enqueue call
		$config_in_sut = $this->get_protected_property_value($sut, 'config');
		$this->assertSame($local_config_mock, $config_in_sut, "SUT's config property should be the local_config_mock for this test.");
		$logger_from_sut_config = $config_in_sut->get_logger(); // This will call the mock config's get_logger
		$this->assertSame($this->logger_mock, $logger_from_sut_config, "Logger from SUT's config should be the main logger_mock.");

		$sut->enqueue();
		// Assertions are handled by Mockery's expectation verification upon teardown.
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::enqueue
	 */
	public function test_enqueue_skips_processing_for_absent_asset_types(): void {
		$sut_partial_mock = Mockery::mock(ConcreteEnqueueForCoreTesting::class, array($this->config_mock))->makePartial();
		$sut_partial_mock->shouldAllowMockingProtectedMethods();

		// Ensure all asset arrays are empty
		$sut_partial_mock->set_scripts_for_testing(array());
		$sut_partial_mock->set_styles_for_testing(array());
		$sut_partial_mock->set_media_tool_configs_for_testing(array());
		$sut_partial_mock->set_inline_scripts_for_testing(array()); // enqueue_inline_scripts is always called

		$this->logger_mock->shouldReceive('debug')->with(Mockery::pattern('/^AssetEnqueueBaseAbstract::enqueue - Main enqueue process started\. Scripts: 0, Styles: 0, Media: 0, Inline Scripts: 0\.$/'))->once()->ordered();
		// Logging for empty assets is now handled within the respective trait methods.
		// The enqueue method in the base class now always calls these handlers.
		$sut_partial_mock->shouldReceive('enqueue_scripts')->once()->ordered()->andReturnSelf();
		$sut_partial_mock->shouldReceive('enqueue_styles')->once()->ordered()->andReturnSelf();
		$sut_partial_mock->shouldReceive('enqueue_media')->once()->ordered()->andReturnSelf();
		// enqueue_inline_scripts is always called, it handles its own empty state logging.
		$sut_partial_mock->shouldReceive('enqueue_inline_scripts')->once()->ordered()->andReturnSelf();

		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::enqueue - Main enqueue process finished.')->once()->ordered();

		$sut_partial_mock->enqueue();
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_head
	 */
	public function test_render_head_executes_simple_callbacks(): void {
		$callbacks = array(
		    array('callback' => function() {
		    	echo 'head_test_output_1';
		    }, 'condition' => null),
		    array('callback' => function() {
		    	echo 'head_test_output_2';
		    }, 'condition' => null),
		);
		$this->sut->set_head_callbacks_for_testing($callbacks);

		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_head - Executing head callback 0.')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_head - Executing head callback 1.')->once()->ordered();

		ob_start();
		$this->sut->render_head();
		$output = ob_get_clean();

		$this->assertEquals('head_test_output_1head_test_output_2', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_head
	 */
	public function test_render_head_executes_conditional_callbacks_when_condition_true(): void {
		$callbacks = array(
		    array('callback' => function() {
		    	echo 'conditional_head_true';
		    }, 'condition' => function() {
		    	return true;
		    })
		);
		$this->sut->set_head_callbacks_for_testing($callbacks);
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_head - Executing head callback 0.')->once();

		ob_start();
		$this->sut->render_head();
		$output = ob_get_clean();
		$this->assertEquals('conditional_head_true', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_head
	 */
	public function test_render_head_skips_conditional_callbacks_when_condition_false(): void {
		$callbacks = array(
		    array('callback' => function() {
		    	echo 'conditional_head_false';
		    }, 'condition' => function() {
		    	return false;
		    })
		);
		$this->sut->set_head_callbacks_for_testing($callbacks);
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_head - Skipping head callback 0 due to false condition.')->once();

		ob_start();
		$this->sut->render_head();
		$output = ob_get_clean();
		$this->assertEquals('', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_head
	 */
	public function test_render_head_handles_no_callbacks_gracefully(): void {
		$this->sut->set_head_callbacks_for_testing(array());
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_head - No head callbacks to execute.')->once();

		ob_start();
		$this->sut->render_head();
		$output = ob_get_clean();
		$this->assertEquals('', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_footer
	 */
	public function test_render_footer_executes_simple_callbacks(): void {
		$callbacks = array(
		    array('callback' => function() {
		    	echo 'footer_test_output_1';
		    }, 'condition' => null),
		    array('callback' => function() {
		    	echo 'footer_test_output_2';
		    }, 'condition' => null),
		);
		$this->sut->set_footer_callbacks_for_testing($callbacks);

		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_footer - Executing footer callback 0.')->once()->ordered();
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_footer - Executing footer callback 1.')->once()->ordered();

		ob_start();
		$this->sut->render_footer();
		$output = ob_get_clean();

		$this->assertEquals('footer_test_output_1footer_test_output_2', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_footer
	 */
	public function test_render_footer_executes_conditional_callbacks_when_condition_true(): void {
		$callbacks = array(
		    array('callback' => function() {
		    	echo 'conditional_footer_true';
		    }, 'condition' => function() {
		    	return true;
		    })
		);
		$this->sut->set_footer_callbacks_for_testing($callbacks);
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_footer - Executing footer callback 0.')->once();

		ob_start();
		$this->sut->render_footer();
		$output = ob_get_clean();
		$this->assertEquals('conditional_footer_true', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_footer
	 */
	public function test_render_footer_skips_conditional_callbacks_when_condition_false(): void {
		$callbacks = array(
		    array('callback' => function() {
		    	echo 'conditional_footer_false';
		    }, 'condition' => function() {
		    	return false;
		    })
		);
		$this->sut->set_footer_callbacks_for_testing($callbacks);
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_footer - Skipping footer callback 0 due to false condition.')->once();

		ob_start();
		$this->sut->render_footer();
		$output = ob_get_clean();
		$this->assertEquals('', $output);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract::render_footer
	 */
	public function test_render_footer_handles_no_callbacks_gracefully(): void {
		$this->sut->set_footer_callbacks_for_testing(array());
		$this->logger_mock->shouldReceive('debug')->with('AssetEnqueueBaseAbstract::render_footer - No footer callbacks to execute.')->once();

		ob_start();
		$this->sut->render_footer();
		$output = ob_get_clean();
		$this->assertEquals('', $output);
	}
}
