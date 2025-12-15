<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Mockery;

/**
 * Test complete form rendering pipeline integration.
 *
 * Validates Requirements 3.4, 3.5, 3.6 for end-to-end form rendering pipeline:
 * - FormsServiceSession orchestrates template resolution → rendering → assets
 * - Component rendering produces expected markup output
 * - Asset collection works throughout the pipeline
 *
 * @coversDefaultClass \Ran\PluginLib\Forms\Renderer\FormElementRenderer
 * @covers \Ran\PluginLib\Forms\FormsService
 * @covers \Ran\PluginLib\Forms\FormsServiceSession
 * @covers \Ran\PluginLib\Forms\FormsAssets
 * @covers \Ran\PluginLib\Forms\FormsCore
 */
class FormRenderingPipelineTest extends PluginLibTestCase {
	private CollectingLogger $logger;
	private ComponentManifest $component_manifest;
	private FormsService $form_service;
	private FormElementRenderer $renderer;
	private FormMessageHandler $message_handler;

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

		$this->logger = new CollectingLogger();

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
			$this->logger
		);
		$this->message_handler = new FormMessageHandler($this->logger);
		$this->renderer->set_message_handler($this->message_handler);
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Test complete end-to-end form rendering pipeline with asset collection.
	 *
	 * Validates that FormsServiceSession orchestrates:
	 * 1. Template resolution through ComponentManifest
	 * 2. Component rendering through FormElementRenderer
	 * 3. Asset collection and management
	 *
	 * @covers ::render_component_with_assets
	 * @covers ::render_field_component
	 * @covers ::_collect_component_assets
	 * @covers \Ran\PluginLib\Forms\FormsService::start_session
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::render_element
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::render_component
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::assets
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::manifest
	 */
	public function test_complete_form_rendering_pipeline_with_assets(): void {
		$text_result = new ComponentRenderResult(
			'<input type="text" name="text_field" class="fields.input" />'
		);

		$select_result = new ComponentRenderResult(
			'<select name="select_field" class="select-input"><option value="">Choose...</option></select>'
		);

		$media_result = new ComponentRenderResult(
			'<div class="media-field"><input type="hidden" name="media_field" /><button type="button">Choose Media</button></div>'
		);

		// Mock ComponentManifest to return different results for different components
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn($text_result);
		$mock_manifest->shouldReceive('render')
			->with('fields.select', Mockery::type('array'))
			->andReturn($select_result);
		$mock_manifest->shouldReceive('render')
			->with('fields.media', Mockery::type('array'))
			->andReturn($media_result);
		$mock_manifest->shouldReceive('enqueue_assets_for_aliases')
			->once()
			->with(array('fields.media', 'fields.select', 'fields.text'));

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest, $this->logger);

		// Create FormElementRenderer with mocked manifest
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger
		);
		$message_handler = new FormMessageHandler($this->logger);
		$renderer->set_message_handler($message_handler);
		// Start FormsServiceSession to track the complete pipeline
		$session = $form_service->start_session();

		$this->assertSame(array(), $session->get_used_component_aliases());

		// Step 1: Render text field component
		$text_context = array(
			'field_id'  => 'text_field',
			'component' => 'fields.text',
			'label'     => 'Text Field',
			'value'     => 'test value'
		);

		$text_html = $renderer->render_component_with_assets('fields.text', $text_context, $session);

		// Verify text field HTML
		$this->assertEquals('<input type="text" name="text_field" class="fields.input" />', $text_html);

		$this->assertSame(array('fields.text'), $session->get_used_component_aliases());

		// Step 2: Render select field component
		$select_context = array(
			'field_id'  => 'select_field',
			'component' => 'fields.select',
			'label'     => 'Select Field',
			'options'   => array('option1' => 'Option 1', 'option2' => 'Option 2')
		);

		$select_html = $renderer->render_component_with_assets('fields.select', $select_context, $session);

		// Verify select field HTML
		$this->assertEquals('<select name="select_field" class="select-input"><option value="">Choose...</option></select>', $select_html);

		$this->assertSame(array('fields.select', 'fields.text'), $session->get_used_component_aliases());

		// Step 3: Render media field component
		$media_context = array(
			'field_id'  => 'media_field',
			'component' => 'fields.media',
			'label'     => 'Media Field'
		);

		$media_html = $renderer->render_component_with_assets('fields.media', $media_context, $session);

		// Verify media field HTML
		$this->assertEquals('<div class="media-field"><input type="hidden" name="media_field" /><button type="button">Choose Media</button></div>', $media_html);

		$this->assertSame(array('fields.media', 'fields.select', 'fields.text'), $session->get_used_component_aliases());
		$session->enqueue_assets();
	}

	/**
	 * Test FormsServiceSession orchestrates template resolution → rendering → assets.
	 *
	 * @covers ::render_field_component
	 * @covers ::prepare_field_context
	 */
	public function test_form_service_session_orchestrates_complete_pipeline(): void {
		$render_result = new ComponentRenderResult(
			'<div class="complex-field"><input type="text" name="complex_field" /><div class="field-controls">Controls</div></div>'
		);

		// Mock ComponentManifest for template resolution
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('fields.complex', Mockery::on(function($context) {
				// Verify context contains expected field data
				return isset($context['field_id']) && $context['field_id'] === 'complex_field' && isset($context['label']) && $context['label'] === 'Complex Field' && isset($context['value']) && $context['value'] === 'test value' && isset($context['validation_warnings']) && is_array($context['validation_warnings']) && isset($context['display_notices']) && is_array($context['display_notices']);
			}))
			->andReturn($render_result);
		$mock_manifest->shouldReceive('enqueue_assets_for_aliases')
			->once()
			->with(array('fields.complex'));

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest, $this->logger);

		// Create FormElementRenderer
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$this->component_manifest->get_component_loader(),
			$this->logger
		);
		$message_handler = new FormMessageHandler($this->logger);
		$renderer->set_message_handler($message_handler);

		// Start session to track orchestration
		$session = $form_service->start_session();

		// Prepare field configuration
		$field = array(
			'field_id'          => 'complex_field',
			'component'         => 'fields.complex',
			'label'             => 'Complex Field',
			'description'       => 'A complex field with multiple assets',
			'required'          => true,
			'component_context' => array(
				'placeholder' => 'Enter complex value...',
				'max_length'  => 255
			)
		);

		$values   = array('complex_field' => 'test value');
		$messages = array(
			'complex_field' => array(
				'warnings' => array('This field needs attention'),
				'notices'  => array('Field updated successfully')
			)
		);

		$message_handler->set_messages($messages);

		// Step 1: Template Resolution - Prepare field context
		$context = $renderer->prepare_field_context($field, $values, array());

		// Verify context preparation (template resolution step)
		$this->assertEquals('complex_field', $context['field_id']);
		$this->assertEquals('fields.complex', $context['component']);
		$this->assertEquals('Complex Field', $context['label']);
		$this->assertEquals('test value', $context['value']);
		$this->assertEquals(array('This field needs attention'), $context['validation_warnings']);
		$this->assertEquals(array('Field updated successfully'), $context['display_notices']);
		$this->assertEquals('A complex field with multiple assets', $context['description']);
		$this->assertTrue($context['required']);
		// component_context is now flattened into top-level context
		$this->assertSame('Enter complex value...', $context['placeholder'] ?? null);
		$this->assertSame(255, $context['max_length'] ?? null);

		// Step 2: Component Rendering - Render field component
		$html = $renderer->render_field_component(
			'fields.complex',
			'complex_field',
			'Complex Field',
			$context,
			'direct-output',
			'field-wrapper',
			$session
		);

		// Verify rendering step
		$expected_html = '<div class="complex-field"><input type="text" name="complex_field" /><div class="field-controls">Controls</div></div>';
		$this->assertEquals($expected_html, $html);
		$this->assertSame(array('fields.complex'), $session->get_used_component_aliases());
		$session->enqueue_assets();
	}

	/**
	 * Test that component rendering produces expected markup output.
	 *
	 * @covers ::render_field_with_wrapper
	 * @covers ::_apply_template_wrapper
	 */
	public function test_component_rendering_produces_expected_markup_output(): void {
		// Create a component with specific markup structure
		$render_result = new ComponentRenderResult(
			'<div class="custom-field-wrapper"><label for="custom_field">Custom Label</label><input type="text" id="custom_field" name="custom_field" class="custom-input" /></div>'
		);

		// Mock ComponentManifest
		$mock_manifest = Mockery::mock(ComponentManifest::class);
		$mock_manifest->shouldReceive('render')
			->with('fields.custom', Mockery::type('array'))
			->andReturn($render_result);

		// Mock ComponentLoader for wrapper template
		$mock_loader = Mockery::mock(ComponentLoader::class);
		$mock_loader->shouldReceive('render')
			->with('layout.field.field-wrapper', Mockery::on(function($context) {
				// Verify wrapper template receives correct context
				return isset($context['field_id']) && $context['field_id'] === 'custom_field' && isset($context['label']) && $context['label'] === 'Custom Field' && isset($context['inner_html']) && strpos($context['inner_html'], 'custom-field-wrapper') !== false && isset($context['validation_warnings']) && is_array($context['validation_warnings']) && isset($context['display_notices']) && is_array($context['display_notices']);
			}))
			->andReturn(new ComponentRenderResult(
				'<div class="field-container"><div class="field-label">Custom Field</div><div class="field-input"><div class="custom-field-wrapper"><label for="custom_field">Custom Label</label><input type="text" id="custom_field" name="custom_field" class="custom-input" /></div></div></div>'
			));

		// Create FormsService with mocked manifest
		$form_service = new FormsService($mock_manifest, $this->logger);

		// Create FormElementRenderer
		$renderer = new FormElementRenderer(
			$mock_manifest,
			$form_service,
			$mock_loader,
			$this->logger
		);

		$session = $form_service->start_session();

		// Prepare field context
		$field = array(
			'field_id'    => 'custom_field',
			'component'   => 'fields.custom',
			'label'       => 'Custom Field',
			'description' => 'A custom field for testing markup output'
		);

		$context = $renderer->prepare_field_context($field, array(), array());

		// Test direct component rendering (no wrapper)
		$direct_html = $renderer->render_field_component(
			'fields.custom',
			'custom_field',
			'Custom Field',
			$context,
			'direct-output',
			'field-wrapper',
			$session
		);

		// Verify direct component markup
		$expected_direct = '<div class="custom-field-wrapper"><label for="custom_field">Custom Label</label><input type="text" id="custom_field" name="custom_field" class="custom-input" /></div>';
		$this->assertEquals($expected_direct, $direct_html);

		// Test component rendering with wrapper
		$wrapped_html = $renderer->render_field_with_wrapper(
			'fields.custom',
			'custom_field',
			'Custom Field',
			$context,
			'layout.field.field-wrapper',
			'field-wrapper',
			$session
		);

		// Verify wrapped component markup falls back gracefully with warning comment
		$expected_direct = '<div class="custom-field-wrapper"><label for="custom_field">Custom Label</label><input type="text" id="custom_field" name="custom_field" class="custom-input" /></div>';
		$this->assertStringContainsString($expected_direct, $wrapped_html);
		$this->assertStringContainsString('<!-- kepler-template-fallback: custom_field -->', $wrapped_html);
		$this->assertStringContainsString('Template failure while rendering "layout.field.field-wrapper". Check logs for details.', $wrapped_html);
		$this->assertSame(array('fields.custom'), $session->get_used_component_aliases());
	}
}
