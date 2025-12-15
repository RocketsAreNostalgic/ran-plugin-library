<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
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
		$form_service = new FormsService($mock_manifest, $this->logger_mock);

		// Create FormElementRenderer
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$logger,
			new FormMessageHandler($logger)
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
	 */
	public function test_asset_collection_error_handling_continues_rendering(): void {
		$this->logger_mock = new CollectingLogger();
		$this->expectException(\InvalidArgumentException::class);

		// Mock ComponentManifest
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andThrow(new \RuntimeException('Component render failed'));

		// Mock ComponentLoader
		$mock_loader = Mockery::mock(ComponentLoader::class);

		$form_service = new FormsService($mock_manifest, $this->logger_mock);
		$session      = $form_service->start_session();

		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$this->logger_mock,
			new FormMessageHandler($this->logger_mock)
		);

		$renderer->render_component_with_assets('test-component', array(), $session);
	}

	/**
	 * Test that enhanced field rendering includes asset collection.
	 *
	 * @covers ::render_field_component
	 */
	public function test_render_field_component_enhanced_with_asset_collection(): void {
		$this->logger_mock = new CollectingLogger();

		$render_result = new ComponentRenderResult('<input type="text" name="test_field" />');

		// Mock ComponentManifest
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
			$this->logger_mock,
			new FormMessageHandler($this->logger_mock)
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
			'direct-output',
			'field-wrapper',
			$session
		);

		// Verify HTML is returned
		$this->assertEquals('<input type="text" name="test_field" />', $html);
		$this->assertSame(array('fields.text'), $session->get_used_component_aliases());
	}
}
