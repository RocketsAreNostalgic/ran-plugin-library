<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Mockery;

/**
 * Minimal test for FormElementRenderer asset collection functionality.
 *
 * @coversDefaultClass \Ran\PluginLib\Forms\Renderer\FormElementRenderer
 */
class FormElementRendererAssetCollectionMinimalTest extends PluginLibTestCase {
	use ExpectLogTrait;
	public function setUp(): void {
		parent::setUp();

		// Mock all WordPress functions that might be called
		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('add_option')->andReturn(true);
		\WP_Mock::userFunction('update_option')->andReturn(true);
		\WP_Mock::userFunction('wp_register_style')->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_style')->andReturn(true);
		\WP_Mock::userFunction('wp_register_script')->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_script')->andReturn(true);
		\WP_Mock::userFunction('wp_localize_script')->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_media')->andReturn(true);
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Test that the new render_component_with_assets method exists and works.
	 *
	 * @covers ::render_component_with_assets
	 */
	public function test_render_component_with_assets_method_exists(): void {
		$logger = $this->logger_mock = new CollectingLogger();

		// Create a simple render result
		$render_result = new ComponentRenderResult('<div>Test</div>');

		// Mock ComponentManifest
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andReturn($render_result);

		// Mock ComponentLoader
		$mock_loader = Mockery::mock(ComponentLoader::class);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest);

		// Create FormElementRenderer
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$logger
		);

		// Create FormsServiceSession
		$session = $form_service->start_session();

		// Test that the method exists and can be called
		$this->assertTrue(method_exists($renderer, 'render_component_with_assets'));

		// Call the method
		$html = $renderer->render_component_with_assets('test-component', array(), $session);

		// Verify HTML is returned
		$this->assertEquals('<div>Test</div>', $html);
	}

	/**
	 * Test that asset collection error handling works.
	 *
	 * @covers ::render_component_with_assets
	 * @covers ::_collect_component_assets
	 */
	public function test_asset_collection_error_handling_continues_rendering(): void {
		$this->logger_mock = new CollectingLogger();

		// Create a render result with assets
		$script_definition = ScriptDefinition::from_array(array(
			'handle'  => 'test-script',
			'src'     => 'test-script.js',
			'deps'    => array(),
			'version' => '1.0.0'
		));

		$render_result = new ComponentRenderResult(
			'<div>Test Component</div>',
			$script_definition
		);

		// Mock ComponentManifest
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andReturn($render_result);

		// Mock ComponentLoader
		$mock_loader = Mockery::mock(ComponentLoader::class);

		// Create a mock FormsServiceSession that throws during asset ingestion
		$mock_session = Mockery::mock(FormsServiceSession::class);
		$mock_assets  = Mockery::mock(FormsAssets::class);
		$mock_assets->shouldReceive('ingest')
			->with($render_result)
			->andThrow(new \RuntimeException('Asset collection failed'));
		$mock_session->shouldReceive('assets')->andReturn($mock_assets);

		// Create FormElementRenderer
		$renderer = new FormElementRenderer(
			$mock_manifest,
			Mockery::mock(FormsService::class), // Not used in this test
			$mock_loader,
			$this->logger_mock
		);

		// Call render_component_with_assets - should not throw exception
		$html = $renderer->render_component_with_assets('test-component', array(), $mock_session);

		// Verify HTML is still returned despite asset collection failure
		$this->assertEquals('<div>Test Component</div>', $html);

		// Verify warning was logged
		$this->expectLog('warning', 'FormElementRenderer: Asset collection failed, continuing with rendering');
	}

	/**
	 * Test that enhanced field rendering includes asset collection.
	 *
	 * @covers ::render_field_component
	 * @covers ::_collect_component_assets
	 */
	public function test_render_field_component_enhanced_with_asset_collection(): void {
		$this->logger_mock = new CollectingLogger();

		// Create a render result with style asset
		$style_definition = StyleDefinition::from_array(array(
			'handle'  => 'field-style',
			'src'     => 'field-style.css',
			'deps'    => array(),
			'version' => '1.0.0'
		));

		$render_result = new ComponentRenderResult(
			'<input type="text" name="test_field" />',
			null,
			$style_definition
		);

		// Mock ComponentManifest
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn($render_result);

		// Mock ComponentLoader
		$mock_loader = Mockery::mock(ComponentLoader::class);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest);

		// Create FormElementRenderer
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$this->logger_mock
		);

		// Prepare field context
		$field = array(
			'field_id'  => 'test_field',
			'component' => 'fields.text',
			'label'     => 'Test Field'
		);

		$context = $renderer->prepare_field_context($field, array(), array());

		// Render field component
		$html = $renderer->render_field_component(
			'fields.text',
			'test_field',
			'Test Field',
			$context,
			array()
		);

		// Verify HTML is returned
		$this->assertEquals('<input type="text" name="test_field" />', $html);

		// Verify enhanced logging occurred
		$this->expectLog('debug', 'FormElementRenderer: Component rendered successfully');
		$logs       = $this->logger_mock->get_logs();
		$asset_logs = array_filter($logs, static function($log) {
			return isset($log['context']['has_assets']) && $log['context']['has_assets'] === true;
		});
		$this->assertNotEmpty($asset_logs, 'Expected log entry with asset information');
	}
}
