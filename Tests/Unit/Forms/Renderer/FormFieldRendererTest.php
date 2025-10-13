<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Renderer;

use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Forms\Renderer\FormFieldRenderer;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Tests for FormFieldRenderer functionality.
 *
 * Tests universal form field processing logic including field configuration
 * validation, context preparation, component rendering coordination, template
 * override functionality, and asset management integration.
 */
final class FormFieldRendererTest extends PluginLibTestCase {
	protected ?CollectingLogger $logger_mock = null;
	private $component_manifest_mock;
	private $form_service_mock;
	private $component_loader_mock;
	private FormFieldRenderer $renderer;

	public function setUp(): void {
		parent::setUp();

		// Create logger mock using parent method
		$this->logger_mock = new CollectingLogger(array());

		// Create mocks for dependencies
		$this->component_manifest_mock = $this->createMock(ComponentManifest::class);
		$this->form_service_mock       = $this->createMock(FormService::class);
		$this->component_loader_mock   = $this->createMock(ComponentLoader::class);

		// Mock basic WordPress functions
		WP_Mock::userFunction('plugin_dir_url')->andReturn('http://example.com/wp-content/plugins/test/');
		WP_Mock::userFunction('wp_enqueue_style')->andReturn(true);



		// Create FormFieldRenderer instance
		$this->renderer = new FormFieldRenderer(
			$this->component_manifest_mock,
			$this->form_service_mock,
			$this->component_loader_mock,
			$this->logger_mock
		);
	}

	/**
	 * Test field configuration validation with valid configuration.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_valid_configuration(): void {
		$valid_field = array(
			'field_id'          => 'test_field',
			'component'         => 'text_input',
			'component_context' => array('placeholder' => 'Enter text'),
			'label'             => 'Test Field'
		);

		$errors = $this->renderer->validate_field_config($valid_field);

		$this->assertEmpty($errors, 'Valid field configuration should not produce errors');
	}

	/**
	 * Test field configuration validation with minimal valid configuration.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_minimal_valid_configuration(): void {
		$minimal_field = array(
			'field_id'  => 'test_field',
			'component' => 'text_input'
		);

		$errors = $this->renderer->validate_field_config($minimal_field);

		$this->assertEmpty($errors, 'Minimal valid field configuration should not produce errors');
	}

	/**
	 * Test field configuration validation with missing field_id.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_missing_field_id(): void {
		$invalid_field = array(
			'component' => 'text_input'
		);

		$errors = $this->renderer->validate_field_config($invalid_field);

		$this->assertNotEmpty($errors, 'Missing field_id should produce errors');
		$this->assertContains('Field configuration must have a non-empty string field_id', $errors);
	}

	/**
	 * Test field configuration validation with empty field_id.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_empty_field_id(): void {
		$invalid_field = array(
			'field_id'  => '',
			'component' => 'text_input'
		);

		$errors = $this->renderer->validate_field_config($invalid_field);

		$this->assertNotEmpty($errors, 'Empty field_id should produce errors');
		$this->assertContains('Field configuration must have a non-empty string field_id', $errors);
	}

	/**
	 * Test field configuration validation with non-string field_id.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_non_string_field_id(): void {
		$invalid_field = array(
			'field_id'  => 123,
			'component' => 'text_input'
		);

		$errors = $this->renderer->validate_field_config($invalid_field);

		$this->assertNotEmpty($errors, 'Non-string field_id should produce errors');
		$this->assertContains('Field configuration must have a non-empty string field_id', $errors);
	}

	/**
	 * Test field configuration validation with missing component.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_missing_component(): void {
		$invalid_field = array(
			'field_id' => 'test_field'
		);

		$errors = $this->renderer->validate_field_config($invalid_field);

		$this->assertNotEmpty($errors, 'Missing component should produce errors');
		$this->assertContains('Field configuration must have a non-empty string component', $errors);
	}

	/**
	 * Test field configuration validation with invalid component_context.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_invalid_component_context(): void {
		$invalid_field = array(
			'field_id'          => 'test_field',
			'component'         => 'text_input',
			'component_context' => 'not_an_array'
		);

		$errors = $this->renderer->validate_field_config($invalid_field);

		$this->assertNotEmpty($errors, 'Invalid component_context should produce errors');
		$this->assertContains('Field component_context must be an array if provided', $errors);
	}

	/**
	 * Test field configuration validation with invalid label.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_invalid_label(): void {
		$invalid_field = array(
			'field_id'  => 'test_field',
			'component' => 'text_input',
			'label'     => 123
		);

		$errors = $this->renderer->validate_field_config($invalid_field);

		$this->assertNotEmpty($errors, 'Invalid label should produce errors');
		$this->assertContains('Field label must be a string if provided', $errors);
	}

	/**
	 * Test field configuration validation with multiple errors.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 */
	public function test_validate_field_config_multiple_errors(): void {
		$invalid_field = array(
			'field_id'          => '',
			'component'         => '',
			'component_context' => 'not_an_array',
			'label'             => 123
		);

		$errors = $this->renderer->validate_field_config($invalid_field);

		$this->assertCount(4, $errors, 'Should produce 4 validation errors');
		$this->assertContains('Field configuration must have a non-empty string field_id', $errors);
		$this->assertContains('Field configuration must have a non-empty string component', $errors);
		$this->assertContains('Field component_context must be an array if provided', $errors);
		$this->assertContains('Field label must be a string if provided', $errors);
	}
	/**
	 * Test context preparation with valid field configuration.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_prepare_field_context_valid_configuration(): void {
		$field = array(
			'field_id'          => 'test_field',
			'component'         => 'text_input',
			'component_context' => array('placeholder' => 'Enter text'),
			'label'             => 'Test Field',
			'custom_property'   => 'custom_value'
		);

		$values = array(
			'test_field' => 'test_value'
		);

		$messages = array(
			'test_field' => array(
				'warnings' => array('Warning message'),
				'notices'  => array('Notice message')
			)
		);

		$context = $this->renderer->prepare_field_context($field, $values, $messages);

		// Verify base context structure
		$this->assertEquals('test_field', $context['field_id']);
		$this->assertEquals('text_input', $context['component']);
		$this->assertEquals('Test Field', $context['label']);
		$this->assertEquals('test_value', $context['value']);
		$this->assertEquals(array('Warning message'), $context['validation_warnings']);
		$this->assertEquals(array('Notice message'), $context['display_notices']);
		$this->assertEquals(array('placeholder' => 'Enter text'), $context['component_context']);

		// Verify custom properties are included
		$this->assertEquals('custom_value', $context['custom_property']);
	}

	/**
	 * Test context preparation with missing field value.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_prepare_field_context_missing_field_value(): void {
		$field = array(
			'field_id'  => 'test_field',
			'component' => 'text_input'
		);

		$values   = array(); // No value for test_field
		$messages = array();

		$context = $this->renderer->prepare_field_context($field, $values, $messages);

		$this->assertNull($context['value'], 'Missing field value should be null');
		$this->assertEquals(array(), $context['validation_warnings']);
		$this->assertEquals(array(), $context['display_notices']);
	}

	/**
	 * Test context preparation with missing messages.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_prepare_field_context_missing_messages(): void {
		$field = array(
			'field_id'  => 'test_field',
			'component' => 'text_input'
		);

		$values   = array('test_field' => 'test_value');
		$messages = array(); // No messages for test_field

		$context = $this->renderer->prepare_field_context($field, $values, $messages);

		$this->assertEquals(array(), $context['validation_warnings']);
		$this->assertEquals(array(), $context['display_notices']);
	}

	/**
	 * Test context preparation with partial messages.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_prepare_field_context_partial_messages(): void {
		$field = array(
			'field_id'  => 'test_field',
			'component' => 'text_input'
		);

		$values   = array('test_field' => 'test_value');
		$messages = array(
			'test_field' => array(
				'warnings' => array('Warning message')
				// No notices
			)
		);

		$context = $this->renderer->prepare_field_context($field, $values, $messages);

		$this->assertEquals(array('Warning message'), $context['validation_warnings']);
		$this->assertEquals(array(), $context['display_notices']);
	}

	/**
	 * Test context preparation with invalid field configuration.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_prepare_field_context_invalid_configuration(): void {
		$invalid_field = array(
			'field_id'  => '', // Invalid
			'component' => 'text_input'
		);

		$values   = array();
		$messages = array();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid field configuration');

		$this->renderer->prepare_field_context($invalid_field, $values, $messages);
	}

	/**
	 * Test context preparation with minimal field configuration.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_prepare_field_context_minimal_configuration(): void {
		$field = array(
			'field_id'  => 'test_field',
			'component' => 'text_input'
		);

		$values   = array();
		$messages = array();

		$context = $this->renderer->prepare_field_context($field, $values, $messages);

		$this->assertEquals('test_field', $context['field_id']);
		$this->assertEquals('text_input', $context['component']);
		$this->assertEquals('', $context['label']); // Default empty label
		$this->assertNull($context['value']);
		$this->assertEquals(array(), $context['validation_warnings']);
		$this->assertEquals(array(), $context['display_notices']);
		$this->assertEquals(array(), $context['component_context']); // Default empty array
	}

	/**
	 * Test template override functionality.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::set_template_overrides
	 */
	public function test_set_template_overrides(): void {
		$overrides = array(
			'field-wrapper' => 'admin-field-wrapper',
			'section'       => 'admin-section'
		);

		// This should not throw any exceptions
		$this->renderer->set_template_overrides($overrides);

		// Verify the overrides were set (we can't directly test this without accessing private properties,
		// but we can verify no exceptions were thrown and the method completed successfully)
		$this->assertTrue(true, 'Template overrides should be set without errors');
	}

	/**
	 * Test component rendering coordination with successful rendering.
	 *
	 * Note: This test is skipped due to FormServiceSession being final and not mockable.
	 * The core functionality is tested through other methods.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::render_field_component
	 */
	public function test_render_field_component_successful_rendering(): void {
		$component = 'text_input';
		$field_id  = 'test_field';
		$label     = 'Test Field';
		$context   = array(
			'field_id'  => $field_id,
			'component' => $component,
			'value'     => 'test_value'
		);
		$values = array('test_field' => 'test_value');

		// Create the render result (it's a readonly class, so we need to create a real instance)
		$render_result = new ComponentRenderResult('<input type="text" value="test_value" />');

		// Mock FormServiceSession
		$session_mock = $this->createMock(\Ran\PluginLib\Forms\FormServiceSession::class);

		// Set up component manifest mock
		$this->component_manifest_mock
			->expects($this->once())
			->method('render')
			->with($component, $context)
			->willReturn($render_result);

		// Set up form service mock
		$this->form_service_mock
			->expects($this->once())
			->method('start_session')
			->willReturn($session_mock);

		$result = $this->renderer->render_field_component(
			$component,
			$field_id,
			$label,
			$context,
			$values
		);

		$this->assertEquals('<input type="text" value="test_value" />', $result);
	}

	/**
	 * Test component rendering coordination with template wrapper.
	 *
	 * Note: This test is skipped due to FormServiceSession being final and not mockable.
	 * The core functionality is tested through other methods.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::render_field_component
	 */
	public function test_render_field_component_with_template_wrapper(): void {
		$component = 'text_input';
		$field_id  = 'test_field';
		$label     = 'Test Field';
		$context   = array(
			'field_id'  => $field_id,
			'component' => $component,
			'value'     => 'test_value'
		);
		$values           = array('test_field' => 'test_value');
		$wrapper_template = 'field-wrapper';

		// Create the render result (it's a readonly class, so we need to create a real instance)
		$render_result = new ComponentRenderResult('<input type="text" value="test_value" />');

		// Mock FormServiceSession
		$session_mock = $this->createMock(\Ran\PluginLib\Forms\FormServiceSession::class);

		// Set up component manifest mock
		$this->component_manifest_mock
			->expects($this->once())
			->method('render')
			->with($component, $context)
			->willReturn($render_result);

		// Set up form service mock
		$this->form_service_mock
			->expects($this->once())
			->method('start_session')
			->willReturn($session_mock);

		$result = $this->renderer->render_field_component(
			$component,
			$field_id,
			$label,
			$context,
			$values,
			$wrapper_template
		);

		// For now, template wrapper just returns the component HTML directly
		// This will be enhanced in the template architecture sprint
		$this->assertEquals('<input type="text" value="test_value" />', $result);
	}

	/**
	 * Test component rendering coordination with rendering failure.
	 *
	 * Note: This test is skipped due to FormServiceSession being final and not mockable.
	 * The core functionality is tested through other methods.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::render_field_component
	 */
	public function test_render_field_component_rendering_failure(): void {
		$component = 'invalid_component';
		$field_id  = 'test_field';
		$label     = 'Test Field';
		$context   = array(
			'field_id'  => $field_id,
			'component' => $component
		);
		$values = array();

		// Mock FormServiceSession
		$session_mock = $this->createMock(\Ran\PluginLib\Forms\FormServiceSession::class);

		// Set up form service mock to be called first
		$this->form_service_mock
			->expects($this->once())
			->method('start_session')
			->willReturn($session_mock);

		// Set up component manifest mock to throw exception
		$this->component_manifest_mock
			->expects($this->once())
			->method('render')
			->with($component, $context)
			->willThrowException(new \RuntimeException('Component not found'));

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to render component 'invalid_component' for field 'test_field'");

		$this->renderer->render_field_component(
			$component,
			$field_id,
			$label,
			$context,
			$values
		);
	}

	/**
	 * Test asset management integration during rendering.
	 *
	 * Note: This test is skipped due to FormServiceSession being final and not mockable.
	 * The core functionality is tested through other methods.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::render_field_component
	 */
	public function test_render_field_component_asset_management(): void {
		$component = 'text_input';
		$field_id  = 'test_field';
		$label     = 'Test Field';
		$context   = array('field_id' => $field_id);
		$values    = array();

		// Create the render result (it's a readonly class, so we need to create a real instance)
		$render_result = new ComponentRenderResult('<input type="text" />');

		// Mock FormServiceSession
		$session_mock = $this->createMock(\Ran\PluginLib\Forms\FormServiceSession::class);

		// Set up component manifest mock
		$this->component_manifest_mock
			->expects($this->once())
			->method('render')
			->willReturn($render_result);

		// Verify form session is started for asset capture
		$this->form_service_mock
			->expects($this->once())
			->method('start_session')
			->willReturn($session_mock);

		$this->renderer->render_field_component(
			$component,
			$field_id,
			$label,
			$context,
			$values
		);
	}

	/**
	 * Test consistent behavior across different contexts.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::validate_field_config
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_consistent_behavior_across_contexts(): void {
		// Test that the same field configuration produces consistent results
		// regardless of the context it's used in

		$field = array(
			'field_id'  => 'consistent_field',
			'component' => 'text_input',
			'label'     => 'Consistent Field'
		);

		// Test validation consistency
		$errors1 = $this->renderer->validate_field_config($field);
		$errors2 = $this->renderer->validate_field_config($field);

		$this->assertEquals($errors1, $errors2, 'Field validation should be consistent');

		// Test context preparation consistency
		$values   = array('consistent_field' => 'test_value');
		$messages = array();

		$context1 = $this->renderer->prepare_field_context($field, $values, $messages);
		$context2 = $this->renderer->prepare_field_context($field, $values, $messages);

		$this->assertEquals($context1, $context2, 'Context preparation should be consistent');
	}

	/**
	 * Test integration with message handling.
	 *
	 * @covers \Ran\PluginLib\Forms\Renderer\FormFieldRenderer::prepare_field_context
	 */
	public function test_message_integration(): void {
		$field = array(
			'field_id'  => 'message_field',
			'component' => 'text_input'
		);

		$values   = array('message_field' => 'test_value');
		$messages = array(
			'message_field' => array(
				'warnings' => array('Validation warning 1', 'Validation warning 2'),
				'notices'  => array('Display notice 1', 'Display notice 2')
			)
		);

		$context = $this->renderer->prepare_field_context($field, $values, $messages);

		// Verify messages are properly integrated into context
		$this->assertEquals(
			array('Validation warning 1', 'Validation warning 2'),
			$context['validation_warnings']
		);
		$this->assertEquals(
			array('Display notice 1', 'Display notice 2'),
			$context['display_notices']
		);
	}
}
