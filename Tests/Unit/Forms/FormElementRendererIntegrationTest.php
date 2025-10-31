<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Mockery;

/**
 * Integration test for FormElementRenderer with FormsServiceSession asset management.
 *
 * Verifies that FormElementRenderer properly collects and returns assets to FormsServiceSession
 * as required by task 4.1.
 *
 * @coversDefaultClass \Ran\PluginLib\Forms\Renderer\FormElementRenderer
 */
class FormElementRendererIntegrationTest extends PluginLibTestCase {
	use ExpectLogTrait;
	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions
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
	 * Test complete integration: FormElementRenderer → FormsServiceSession → Asset Collection.
	 *
	 * This test verifies Requirements 6.1, 6.2, 6.7, 10.1, 10.2:
	 * - FormElementRenderer collects assets from ComponentRenderResult during rendering
	 * - Asset collection error handling with appropriate warnings
	 * - Integration with FormsServiceSession for asset management
	 * - Assets are properly collected and returned to FormsServiceSession
	 *
	 * @covers ::render_field_component
	 * @covers ::render_component_with_assets
	 * @covers ::_collect_component_assets
	 */
	public function test_complete_asset_collection_integration(): void {
		$this->logger_mock = new CollectingLogger();

		// Create a component with assets
		$style_definition = StyleDefinition::from_array(array(
			'handle'  => 'integration-test-style',
			'src'     => 'integration-test.css',
			'deps'    => array(),
			'version' => '1.0.0'
		));

		$render_result = new ComponentRenderResult(
			'<input type="text" name="integration_field" />',
			null,
			$style_definition
		);

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn($render_result);

		// Mock ComponentLoader
		$mock_loader = Mockery::mock(ComponentLoader::class);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest, $this->logger_mock);

		// Create FormElementRenderer
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$this->logger_mock
		);

		// Start a session to track assets
		$session = $form_service->start_session();

		// Verify session starts with no assets
		$this->assertFalse($session->assets()->has_assets());

		// Render field component using FormElementRenderer
		$field = array(
			'field_id'  => 'integration_field',
			'component' => 'fields.text',
			'label'     => 'Integration Test Field'
		);

		$context = $renderer->prepare_field_context($field, array(), array());

		$html = $renderer->render_field_component(
			'fields.text',
			'integration_field',
			'Integration Test Field',
			$context,
			array(),
			'direct-output',
			$session  // Pass the session for asset collection
		);

		// Verify HTML is returned
		$this->assertEquals('<input type="text" name="integration_field" />', $html);

		// Verify assets were collected in the session
		$assets = $session->assets();
		$this->assertTrue($assets->has_assets(), 'Assets should have been collected');

		// Verify the specific style was collected
		$styles = $assets->styles();
		$this->assertArrayHasKey('integration-test-style', $styles);
		$this->assertEquals('integration-test-style', $styles['integration-test-style']->handle);

		$this->expectLog('debug', 'FormElementRenderer: Component rendered successfully');
	}

	/**
	 * Test that FormElementRenderer handles components without assets correctly.
	 *
	 * @covers ::render_field_component
	 * @covers ::_collect_component_assets
	 */
	public function test_handles_components_without_assets(): void {
		$this->logger_mock = new CollectingLogger();

		// Create a component without assets
		$render_result = new ComponentRenderResult('<div>No assets component</div>');

		// Mock ComponentManifest
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('simple.component', Mockery::type('array'))
			->andReturn($render_result);

		// Mock ComponentLoader
		$mock_loader = Mockery::mock(ComponentLoader::class);

		// Create FormsService and FormElementRenderer
		$form_service = new FormsService($mock_manifest, $this->logger_mock);
		$renderer     = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$this->logger_mock
		);

		// Start session and render component
		$session = $form_service->start_session();

		$field = array(
			'field_id'  => 'simple_field',
			'component' => 'simple.component',
			'label'     => 'Simple Field'
		);

		$context = $renderer->prepare_field_context($field, array(), array());

		$html = $renderer->render_field_component(
			'simple.component',
			'simple_field',
			'Simple Field',
			$context,
			array(),
			'direct-output',
			$session  // Pass the session for asset collection
		);

		// Verify HTML is returned
		$this->assertEquals('<div>No assets component</div>', $html);

		// Verify no assets were collected
		$this->assertFalse($session->assets()->has_assets());

		$this->expectLog('debug', 'FormElementRenderer: Component rendered successfully');
		$logs           = $this->logger_mock->get_logs();
		$no_assets_logs = array_filter($logs, static function($log) {
			return isset($log['context']['has_assets']) && $log['context']['has_assets'] === false;
		});
		$this->assertNotEmpty($no_assets_logs, 'Should log when component has no assets');
	}

	/**
	 * Test that multiple components accumulate assets correctly.
	 *
	 * @covers ::render_field_component
	 * @covers ::_collect_component_assets
	 */
	public function test_multiple_components_accumulate_assets(): void {
		$logger = new CollectingLogger();

		// Create two different components with different assets
		$style1 = StyleDefinition::from_array(array(
			'handle'  => 'component-1-style',
			'src'     => 'component1.css',
			'deps'    => array(),
			'version' => '1.0.0'
		));

		$style2 = StyleDefinition::from_array(array(
			'handle'  => 'component-2-style',
			'src'     => 'component2.css',
			'deps'    => array(),
			'version' => '1.0.0'
		));

		$result1 = new ComponentRenderResult('<input type="text" />', null, $style1);
		$result2 = new ComponentRenderResult('<textarea></textarea>', null, $style2);

		// Mock ComponentManifest
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn($result1);
		$mock_manifest->shouldReceive('render')
			->with('fields.textarea', Mockery::type('array'))
			->andReturn($result2);

		// Mock ComponentLoader
		$mock_loader = Mockery::mock(ComponentLoader::class);

		// Create FormsService and FormElementRenderer
		$form_service = new FormsService($mock_manifest, $logger);
		$renderer     = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$logger
		);

		// Start session
		$session = $form_service->start_session();

		// Render first component
		$field1 = array(
			'field_id'  => 'field1',
			'component' => 'fields.text',
			'label'     => 'Field 1'
		);
		$context1 = $renderer->prepare_field_context($field1, array(), array());
		$renderer->render_field_component('fields.text', 'field1', 'Field 1', $context1, array(), 'direct-output', $session);

		// Render second component
		$field2 = array(
			'field_id'  => 'field2',
			'component' => 'fields.textarea',
			'label'     => 'Field 2'
		);
		$context2 = $renderer->prepare_field_context($field2, array(), array());
		$renderer->render_field_component('fields.textarea', 'field2', 'Field 2', $context2, array(), 'direct-output', $session);

		// Verify both assets were collected
		$assets = $session->assets();
		$this->assertTrue($assets->has_assets());

		$styles = $assets->styles();
		$this->assertArrayHasKey('component-1-style', $styles);
		$this->assertArrayHasKey('component-2-style', $styles);
		$this->assertCount(2, $styles);
	}
}
