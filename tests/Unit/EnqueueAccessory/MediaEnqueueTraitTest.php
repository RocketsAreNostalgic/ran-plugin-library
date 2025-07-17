<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\EnqueueAccessory;

use Mockery;
use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait;
use Ran\PluginLib\EnqueueAccessory\AssetEnqueueBaseAbstract;

/**
 * Concrete implementation of MediaEnqueueTrait for testing media-related methods.
 */
class ConcreteEnqueueForMediaTesting extends AssetEnqueueBaseAbstract {
	use MediaEnqueueTrait;

	public function __construct(ConfigInterface $config) {
		parent::__construct($config);
	}

	public function load(): void {
		// Implementation not needed for testing
	}
}

/**
 * Class MediaEnqueueTraitTest
 *
 * @package Ran\PluginLib\Tests\Unit\EnqueueAccessory
 *
 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait
 */
class MediaEnqueueTraitTest extends PluginLibTestCase {
	use ExpectLogTrait;

	/** @var ConcreteEnqueueForMediaTesting */
	protected $instance;

	/** @var Mockery\MockInterface|ConfigInterface */
	protected $config_mock;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$logger = new CollectingLogger();

		$this->config_mock = Mockery::mock(ConfigInterface::class);
		$this->config_mock->shouldReceive('get_is_dev_callback')->andReturn(null)->byDefault();
		$this->config_mock->shouldReceive('is_dev_environment')->andReturn(false)->byDefault();
		$this->config_mock->shouldReceive('get_logger')->andReturn($logger)->byDefault();

		$this->instance = new ConcreteEnqueueForMediaTesting($this->config_mock);

		// Mock WordPress functions
		WP_Mock::userFunction('wp_enqueue_media')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('has_action')->withAnyArgs()->andReturn(false)->byDefault();
		WP_Mock::userFunction('add_action')->withAnyArgs()->andReturnNull()->byDefault();
		WP_Mock::userFunction('current_action')->withAnyArgs()->andReturn('admin_enqueue_scripts')->byDefault();
		WP_Mock::userFunction('wp_json_encode')->withAnyArgs()->andReturnUsing(function($data) {
			return json_encode($data);
		})->byDefault();
	}

	/**
	 * Clean up test environment.
	 */
	public function tearDown(): void {
		parent::tearDown();
		Mockery::close();
	}

	// ------------------------------------------------------------------------
	// Trait Specific Capability Tests
	// ------------------------------------------------------------------------

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::add
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::get
	 */
	public function test_add_and_get_media_configurations(): void {
		// Arrange
		$media_configs = array(
			array(
				'args'      => array('post' => 123),
				'condition' => function() {
					return is_admin();
				},
				'hook' => 'admin_enqueue_scripts'
			),
			array(
				'args'      => array('post' => 456),
				'condition' => function() {
					return current_user_can('upload_files');
				},
				'hook' => 'wp_enqueue_scripts'
			)
		);

		// Act
		$this->instance->add($media_configs);
		$result = $this->instance->get();

		// Assert
		$this->assertArrayHasKey('general', $result);
		$this->assertArrayHasKey('deferred', $result);
		$this->assertSame($media_configs, $result['general']);
		$this->assertEmpty($result['deferred']); // Should be empty until stage_media() is called
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::stage_media
	 */
	public function test_stage_media_organizes_by_hook(): void {
		// Arrange
		$media_configs = array(
			array(
				'args'      => array('post' => 123),
				'condition' => function() {
					return true;
				},
				'hook' => 'admin_enqueue_scripts'
			),
			array(
				'args'      => array('post' => 456),
				'condition' => function() {
					return true;
				},
				'hook' => 'wp_enqueue_scripts'
			),
			array(
				'args'      => array('post' => 789),
				'condition' => function() {
					return true;
				}
				// No hook specified - should default to admin_enqueue_scripts
			)
		);

		// Act
		$this->instance->stage_media($media_configs);
		$result = $this->instance->get();

		// Assert
		$this->assertArrayHasKey('deferred', $result);
		$this->assertArrayHasKey('admin_enqueue_scripts', $result['deferred']);
		$this->assertArrayHasKey('wp_enqueue_scripts', $result['deferred']);

		// Check that configs are organized by hook
		$this->assertCount(2, $result['deferred']['admin_enqueue_scripts']); // First and third (default)
		$this->assertCount(1, $result['deferred']['wp_enqueue_scripts']); // Second

		// Verify the actual configurations
		$this->assertEquals(123, $result['deferred']['admin_enqueue_scripts'][0]['args']['post']);
		$this->assertEquals(789, $result['deferred']['admin_enqueue_scripts'][2]['args']['post']);
		$this->assertEquals(456, $result['deferred']['wp_enqueue_scripts'][1]['args']['post']);
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::_enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_calls_wp_enqueue_media(): void {
		// Arrange
		$media_configs = array(
			array(
				'args'      => array('post' => 123),
				'condition' => function() {
					return true;
				}, // Always true
				'hook' => 'admin_enqueue_scripts'
			),
			array(
				'args'      => array('post' => 456),
				'condition' => function() {
					return false;
				}, // Always false
				'hook' => 'admin_enqueue_scripts'
			)
		);

		// Stage the media configs
		$this->instance->stage_media($media_configs);

		// Set up expectations - wp_enqueue_media should be called once (for the first config only)
		WP_Mock::userFunction('wp_enqueue_media')
			->with(array('post' => 123))
			->once();

		// Act
		$this->instance->_enqueue_deferred_media_tools('admin_enqueue_scripts');

		// Assert: WP_Mock will verify the expectations
		$this->assertTrue(true); // Prevents risky test warning
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::_enqueue_deferred_media_tools
	 */
	public function test_enqueue_deferred_media_tools_handles_missing_hook(): void {
		// Arrange - no media configs staged

		// Set up expectations - wp_enqueue_media should NOT be called
		WP_Mock::userFunction('wp_enqueue_media')->never();

		// Act
		$this->instance->_enqueue_deferred_media_tools('nonexistent_hook');

		// Assert: WP_Mock will verify the expectations
		$this->assertTrue(true); // Prevents risky test warning
	}

	/**
	 * @test
	 * @covers \Ran\PluginLib\EnqueueAccessory\MediaEnqueueTrait::add
	 */
	public function test_add_overwrites_existing_configurations(): void {
		// Arrange
		$first_configs = array(
			array('args' => array('post' => 123))
		);
		$second_configs = array(
			array('args' => array('post' => 456)),
			array('args' => array('post' => 789))
		);

		// Act
		$this->instance->add($first_configs);
		$this->instance->add($second_configs);
		$result = $this->instance->get();

		// Assert - should only have the second configs
		$this->assertSame($second_configs, $result['general']);
		$this->assertCount(2, $result['general']);
	}
}
