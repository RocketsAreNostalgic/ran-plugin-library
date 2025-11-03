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
 * Test FormElementRenderer asset collection integration with FormsServiceSession.
 *
 * Verifies Requirements 6.1, 6.2, 6.7, 10.1, 10.2 for proper asset collection
 * and error handling during component rendering.
 *
 * @coversDefaultClass \Ran\PluginLib\Forms\Renderer\FormElementRenderer
 */
class FormElementRendererAssetCollectionTest extends PluginLibTestCase {
	use ExpectLogTrait;
	private ComponentManifest $component_manifest;
	private FormsService $form_service;
	private FormElementRenderer $renderer;
	private CollectingLogger $logger;

	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions that ComponentManifest might use
		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('add_option')->andReturn(true);
		\WP_Mock::userFunction('update_option')->andReturn(true);

		$this->logger      = new CollectingLogger();
		$this->logger_mock = $this->logger;

		// Create ComponentLoader with test templates
		$component_loader         = new ComponentLoader(__DIR__ . '/../../fixtures/templates');
		$this->component_manifest = new ComponentManifest($component_loader, $this->logger);

		// Create FormsService
		$this->form_service = new FormsService($this->component_manifest);

		// Create FormElementRenderer
		$this->renderer = new FormElementRenderer(
			$this->component_manifest,
			$this->form_service,
			$component_loader,
			$this->logger
		);
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Test that FormElementRenderer properly collects assets from ComponentRenderResult.
	 *
	 * @covers ::render_component_with_assets
	 * @covers ::_collect_component_assets
	 */
	public function test_render_component_with_assets_collects_assets_successfully(): void {
		// Mock WordPress functions
		\WP_Mock::userFunction('wp_register_style')->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_style')->andReturn(true);
		\WP_Mock::userFunction('wp_register_script')->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_script')->andReturn(true);
		\WP_Mock::userFunction('wp_localize_script')->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_media')->andReturn(true);

		// Create a mock ComponentRenderResult with assets
		$script_definition = ScriptDefinition::from_array(array(
			'handle'  => 'test-script',
			'src'     => 'test-script.js',
			'deps'    => array('jquery'),
			'version' => '1.0.0'
		));

		$style_definition = StyleDefinition::from_array(array(
			'handle'  => 'test-style',
			'src'     => 'test-style.css',
			'deps'    => array(),
			'version' => '1.0.0'
		));

		$render_result = new ComponentRenderResult(
			'<div>Test Component</div>',
			$script_definition,
			$style_definition,
			true // requires_media
		);

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andReturn($render_result);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest);

		// Create renderer with mocked manifest
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger
		);

		// Create FormsServiceSession to test asset collection
		$session = $form_service->start_session();

		// Render component with assets
		$context = array('field_id' => 'test_field');
		$html    = $renderer->render_component_with_assets('test-component', $context, $session);

		// Verify HTML is returned
		$this->assertEquals('<div>Test Component</div>', $html);

		// Verify assets were collected in the session
		$assets = $session->assets();
		$this->assertTrue($assets->has_assets());
		$this->assertTrue($assets->requires_media());

		// Verify script was collected
		$scripts = $assets->scripts();
		$this->assertArrayHasKey('test-script', $scripts);
		$this->assertEquals('test-script', $scripts['test-script']->handle);

		// Verify style was collected
		$styles = $assets->styles();
		$this->assertArrayHasKey('test-style', $styles);
		$this->assertEquals('test-style', $styles['test-style']->handle);

		$this->expectLog('debug', 'FormElementRenderer: Component rendered with assets');
	}

	/**
	 * Test that asset collection errors are handled gracefully.
	 *
	 * @covers ::render_component_with_assets
	 * @covers ::_collect_component_assets
	 */
	public function test_asset_collection_error_handling(): void {
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

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andReturn($render_result);

		// Create a mock FormsServiceSession that throws an exception during asset ingestion
		$mock_session = Mockery::mock(FormsServiceSession::class);
		$mock_assets  = Mockery::mock(FormsAssets::class);
		$mock_assets->shouldReceive('ingest')
			->with($render_result)
			->andThrow(new \RuntimeException('Asset collection failed'));
		$mock_session->shouldReceive('assets')->andReturn($mock_assets);

		// Create renderer with mocked manifest
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$this->form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger
		);

		// Render component - should not throw exception despite asset collection failure
		$context = array('field_id' => 'test_field');
		$html    = $renderer->render_component_with_assets('test-component', $context, $mock_session);

		// Verify HTML is still returned despite asset collection failure
		$this->assertEquals('<div>Test Component</div>', $html);

		$this->expectLog('warning', 'FormElementRenderer: Asset collection failed, continuing with rendering');
	}

	/**
	 * Test that render_field_component properly integrates with asset collection.
	 *
	 * @covers ::render_field_component
	 * @covers ::_collect_component_assets
	 */
	public function test_render_field_component_collects_assets(): void {
		// Mock WordPress functions
		\WP_Mock::userFunction('wp_register_style')->andReturn(true);
		\WP_Mock::userFunction('wp_enqueue_style')->andReturn(true);

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

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn($render_result);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest);

		// Create renderer with mocked manifest
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger
		);

		// Prepare field context
		$field = array(
			'field_id'  => 'test_field',
			'component' => 'fields.text',
			'label'     => 'Test Field'
		);

		$context = $renderer->prepare_field_context($field, array(), array());

		$session = $form_service->start_session();

		// Render field component
		$html = $renderer->render_field_component(
			'fields.text',
			'test_field',
			'Test Field',
			$context,
			array(),
			'direct-output',
			$session
		);

		// Verify HTML is returned
		$this->assertEquals('<input type="text" name="test_field" />', $html);

		$this->expectLog('debug', 'FormElementRenderer: Component rendered successfully');

		$assets = $session->assets();
		$this->assertTrue($assets->has_assets(), 'Expected session assets to record the style dependency.');
		$this->assertArrayHasKey('field-style', $assets->styles(), 'Expected style handle to be captured.');
	}

	/**
	 * Test that components without assets are handled correctly.
	 *
	 * @covers ::render_component_with_assets
	 * @covers ::_collect_component_assets
	 */
	public function test_render_component_without_assets(): void {
		// Create a render result without assets
		$render_result = new ComponentRenderResult('<div>No Assets Component</div>');

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('simple-component', Mockery::type('array'))
			->andReturn($render_result);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest);

		// Create renderer with mocked manifest
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger
		);

		// Create FormsServiceSession
		$session = $form_service->start_session();

		// Render component without assets
		$context = array('field_id' => 'simple_field');
		$html    = $renderer->render_component_with_assets('simple-component', $context, $session);

		// Verify HTML is returned
		$this->assertEquals('<div>No Assets Component</div>', $html);

		// Verify no assets were collected
		$assets = $session->assets();
		$this->assertFalse($assets->has_assets());

		$this->expectLog('debug', 'FormElementRenderer: Component rendered with assets');

		$logs       = $this->logger_mock->get_logs();
		$asset_logs = array_filter($logs, static function($log) {
			return isset($log['context']['has_assets']) && $log['context']['has_assets'] === false;
		});
		$this->assertNotEmpty($asset_logs);
	}
}
