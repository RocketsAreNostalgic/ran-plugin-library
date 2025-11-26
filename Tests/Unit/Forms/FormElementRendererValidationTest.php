<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use WP_Mock;
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
use Mockery;
use InvalidArgumentException;

/**
 * @covers \Ran\PluginLib\Forms\Renderer\FormElementRenderer
 */
class FormElementRendererValidationTest extends PluginLibTestCase {
	use ExpectLogTrait;
	private CollectingLogger $logger;
	private ComponentLoader $loader;

	public function setUp(): void {
		parent::setUp();
		$this->logger      = new CollectingLogger();
		$this->logger_mock = $this->logger;
		$this->loader      = new ComponentLoader(__DIR__ . '/../../fixtures/templates');

		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('add_option')->andReturn(true);
		WP_Mock::userFunction('update_option')->andReturn(true);
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	private function createRenderer(?ComponentManifest $manifest = null, ?FormsService $service = null, ?CollectingLogger $logger = null): FormElementRenderer {
		$logger   = $logger   ?? $this->logger;
		$manifest = $manifest ?? new ComponentManifest($this->loader, $logger);
		$service  = $service  ?? new FormsService($manifest, $logger);

		$renderer = new FormElementRenderer($manifest, $service, $this->loader, $logger);
		$renderer->set_message_handler(new FormMessageHandler($logger));
		return $renderer;
	}

	public function test_validate_field_config_reports_all_failures(): void {
		$renderer = $this->createRenderer();

		$errors = $renderer->validate_field_config(array(
			'component_context' => 'not-an-array',
			'label'             => 42,
		));

		$this->assertSame(
			array(
				'Field configuration must have a non-empty string field_id',
				'Field configuration must have a non-empty string component',
				'Field component_context must be an array if provided',
				'Field label must be a string if provided',
			),
			$errors
		);
	}

	public function test_validate_field_config_allows_minimal_valid_payload(): void {
		$renderer = $this->createRenderer();

		$errors = $renderer->validate_field_config(array(
			'field_id'  => 'field',
			'component' => 'fields.text',
		));

		$this->assertSame(array(), $errors);
	}

	public function test_prepare_field_context_merges_additional_data_and_logs(): void {
		$renderer = $this->createRenderer();

		$field = array(
			'field_id'          => 'demo_field',
			'component'         => 'fields.text',
			'label'             => 'Label',
			'component_context' => array('mode' => 'demo'),
			'description'       => 'desc',
			'required'          => true,
			'custom'            => 'value',
		);

		$values   = array('demo_field' => 'stored');
		$messages = array(
			'demo_field' => array(
				'warnings' => array('warn'),
				'notices'  => array('note'),
			),
		);

		$message_handler = new FormMessageHandler($this->logger);
		$message_handler->set_messages($messages);
		$renderer->set_message_handler($message_handler);

		$context = $renderer->prepare_field_context($field, $values, array());

		$this->assertSame('stored', $context['value']);
		$this->assertSame(array('warn'), $context['validation_warnings']);
		$this->assertSame(array('note'), $context['display_notices']);
		$this->assertSame('value', $context['custom']);
		$this->expectLog('debug', 'FormElementRenderer: Context prepared');
	}

	public function test_prepare_field_context_uses_pending_values_and_surfaces_messages_once(): void {
		$renderer = $this->createRenderer();

		$field = array(
			'field_id'  => 'pending_field',
			'component' => 'fields.text',
			'label'     => 'Pending Field',
		);

		$stored_values  = array('pending_field' => 'stored-value');
		$pending_values = array('pending_field' => 'pending-value');

		$message_handler = new FormMessageHandler($this->logger);
		$message_handler->set_messages(array(
			'pending_field' => array(
				'warnings' => array('warning-one'),
				'notices'  => array('notice-one'),
			),
		));
		$message_handler->set_pending_values($pending_values);
		$renderer->set_message_handler($message_handler);

		$context = $renderer->prepare_field_context($field, $stored_values, array('before' => '<span>before</span>'));

		$this->assertSame('pending-value', $context['value'], 'Expected pending values to override stored values');
		$this->assertSame(array('warning-one'), $context['validation_warnings']);
		$this->assertSame(array('notice-one'), $context['display_notices']);
		$this->assertSame('<span>before</span>', $context['before']);
		$this->expectLog('debug', 'FormMessageHandler: Using pending values due to validation failure');
		$this->expectLog('debug', 'FormElementRenderer: Context prepared');
	}

	public function test_prepare_field_context_rejects_invalid_config(): void {
		$renderer = $this->createRenderer();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid field configuration');

		$renderer->prepare_field_context(array(), array(), array());
	}

	public function test_render_component_with_assets_wraps_exceptions(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andThrow(new \RuntimeException('render failed'));

		$renderer = $this->createRenderer($manifest);
		$session  = (new FormsService($manifest, $this->logger))->start_session();

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to render component 'demo'");

		$renderer->render_component_with_assets('demo', array('field_id' => 'demo'), $session);
		$this->expectLog('error', 'FormElementRenderer: Component rendering with assets failed');
	}

	public function test_render_field_component_applies_wrapper_and_handles_override(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn(new ComponentRenderResult('<input />', null, StyleDefinition::from_array(array(
				'handle' => 'style',
				'src'    => 'style.css',
			))));

		$loader = Mockery::mock(ComponentLoader::class);
		$loader->shouldReceive('render')
			->with('field-wrapper', Mockery::type('array'))
			->andReturnUsing(static function(string $template, array $context) {
				return new ComponentRenderResult('<div class="wrapper">' . ($context['component_html'] ?? '') . '</div>');
			});

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $loader, $this->logger);
		$renderer->set_message_handler(new FormMessageHandler($this->logger));
		$session = $service->start_session();

		$field = array(
			'field_id'  => 'field',
			'component' => 'fields.text',
			'label'     => 'Label',
		);

		$context = $renderer->prepare_field_context($field, array(), array());

		$session->set_individual_element_override('field', 'field', array(
			'field-wrapper' => 'field-wrapper'
		));

		$html = $renderer->render_field_component(
			'fields.text',
			'field',
			'Label',
			$context,
			'layout.field.field-wrapper',
			'field-wrapper',
			$session
		);

		$this->assertStringContainsString('wrapper', $html);
		$this->assertStringContainsString('<input />', $html);
		$this->expectLog('debug', 'FormsServiceSession: Assets ingested successfully');
		$this->expectLog('debug', 'FormElementRenderer: Component rendered with assets');
	}

	public function test_render_field_component_falls_back_when_wrapper_fails(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')
			->with('fields.text', Mockery::type('array'))
			->andReturn(new ComponentRenderResult('<input />'));
		$manifest->shouldReceive('render')
			->with('custom.wrapper', Mockery::type('array'))
			->andThrow(new \RuntimeException('wrapper render failed'));

		$loader = Mockery::mock(ComponentLoader::class);
		$loader->shouldReceive('render')->andThrow(new \RuntimeException('wrapper failed'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $loader, $this->logger);
		$session  = $service->start_session();

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$html = $renderer->render_field_component(
			'fields.text',
			'field',
			'Label',
			$context,
			'custom.wrapper',
			'field-wrapper',
			$session
		);

		$this->assertStringContainsString('<input />', $html);
		$this->assertStringContainsString('<!-- kepler-template-fallback: field -->', $html);
		$this->assertStringContainsString('Template failure while rendering "layout.field.field-wrapper"', $html);
		$this->expectLog('warning', 'FormsServiceSession: Template render failed; returning fallback markup');
	}

	public function test_render_component_with_assets_logs_successfully(): void {
		WP_Mock::userFunction('wp_register_style')->andReturn(true);
		WP_Mock::userFunction('wp_enqueue_style')->andReturn(true);

		$style_definition = StyleDefinition::from_array(array(
			'handle'  => 'logged-style',
			'src'     => 'logged-style.css',
			'deps'    => array(),
			'VErsion' => '1.0.0',
		));

		$render_result = new ComponentRenderResult('<div>Rendered</div>', null, $style_definition);

		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')
			->with('test-component', Mockery::type('array'))
			->andReturn($render_result);

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $this->loader, $this->logger);
		$renderer->set_message_handler(new FormMessageHandler($this->logger));
		$session = $service->start_session();

		$html = $renderer->render_component_with_assets('test-component', array('field_id' => 'field'), $session);

		$this->assertSame('<div>Rendered</div>', $html);
		$this->assertTrue($session->assets()->has_assets());
		$this->expectLog('debug', 'FormsServiceSession: Assets ingested successfully');
		$this->expectLog('debug', 'FormElementRenderer: Component rendered with assets');
	}

	public function test_render_field_with_wrapper_uses_default_session_when_missing(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andReturn(new ComponentRenderResult('<div>component</div>'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = $this->createRenderer($manifest, $service);

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$session = $service->start_session();

		$html = $renderer->render_field_with_wrapper(
			'fields.text',
			'field',
			'Label',
			$context,
			'wrappers/simple-wrapper',
			'field-wrapper',
			$session
		);

		$this->assertStringContainsString('component', $html);
	}

	public function test_render_field_with_wrapper_wraps_exceptions(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andThrow(new \RuntimeException('render failed'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $this->loader, $this->logger);
		$renderer->set_message_handler(new FormMessageHandler($this->logger));

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to render field 'field' with component 'fields.text'");

		$renderer->render_field_with_wrapper(
			'fields.text',
			'field',
			'Label',
			$context,
			'wrappers/simple-wrapper',
			'field-wrapper',
			$service->start_session()
		);
		$this->expectLog('error', 'FormElementRenderer: Field with wrapper rendering failed');
	}

	public function test_render_field_component_wraps_render_exceptions(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andThrow(new \RuntimeException('render failed'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $this->loader, $this->logger);

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to render component 'fields.text' for field 'field'");

		$session = $service->start_session();
		$renderer->render_field_component(
			'fields.text',
			'field',
			'Label',
			$context,
			'direct-output',
			'field-wrapper',
			$session
		);
		$this->expectLog('error', 'FormElementRenderer: Component rendering failed');
	}
}
