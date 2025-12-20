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
		$component_loader         = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger);
		$this->component_manifest = new ComponentManifest($component_loader, $this->logger);

		// Create FormsService
		$this->form_service = new FormsService($this->component_manifest, $this->logger);

		// Create FormElementRenderer
		$this->renderer = new FormElementRenderer(
			$this->component_manifest,
			$this->form_service,
			$component_loader,
			$this->logger,
			new FormMessageHandler($this->logger)
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
	 */
	public function test_render_component_with_assets_collects_assets_successfully(): void {
		$render_result = new ComponentRenderResult('<div>Test Component</div>');

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('builder_classes')->andReturn(array());
		$mock_manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andReturn($render_result);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest, $this->logger);

		// Create renderer with mocked manifest
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger,
			new FormMessageHandler($this->logger)
		);

		// Create FormsServiceSession to test asset collection
		$session = $form_service->start_session();

		// Render component with assets
		$context = array('field_id' => 'test_field');
		$html    = $renderer->render_component_with_assets('test-component', $context, $session);

		// Verify HTML is returned
		$this->assertEquals('<div>Test Component</div>', $html);

		$this->assertSame(array('test-component'), $session->get_used_component_aliases());
	}

	/**
	 * Test that asset collection errors are handled gracefully.
	 *
	 * @covers ::render_component_with_assets
	 */
	public function test_asset_collection_error_handling(): void {
		$render_result = new ComponentRenderResult('<div>Test Component</div>');

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('builder_classes')->andReturn(array());
		$mock_manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andReturn($render_result);

		$form_service = new FormsService($mock_manifest, $this->logger);
		$session      = $form_service->start_session();

		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger,
			new FormMessageHandler($this->logger)
		);

		$context = array('field_id' => 'test_field');
		$html    = $renderer->render_component_with_assets('test-component', $context, $session);

		$this->assertEquals('<div>Test Component</div>', $html);

		$this->assertSame(array('test-component'), $session->get_used_component_aliases());
	}

	/**
	 * Test that render_field_component properly integrates with asset collection.
	 *
	 * @covers ::render_field_component
	 */
	public function test_render_field_component_collects_assets(): void {
		$render_result = new ComponentRenderResult('<input type="text" name="test_field" />');

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('builder_classes')->andReturn(array());
		$mock_manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn($render_result);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest, $this->logger);

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
			'direct-output',
			'field-wrapper',
			$session
		);

		// Verify HTML is returned
		$this->assertEquals('<input type="text" name="test_field" />', $html);

		$this->assertSame(array('fields.text'), $session->get_used_component_aliases());
	}

	/**
	 * Test that components without assets are handled correctly.
	 *
	 * @covers ::render_component_with_assets
	 */
	public function test_render_component_without_assets(): void {
		// Create a render result without assets
		$render_result = new ComponentRenderResult('<div>No Assets Component</div>');

		// Mock ComponentManifest to return our test result
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('builder_classes')->andReturn(array());
		$mock_manifest->shouldReceive('render')
			->with('simple-component', Mockery::type('array'))
			->andReturn($render_result);

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest, $this->logger);

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

		$this->assertSame(array('simple-component'), $session->get_used_component_aliases());
	}
}
