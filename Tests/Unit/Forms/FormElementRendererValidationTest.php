<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use InvalidArgumentException;
use Mockery;
use Ran\PluginLib\EnqueueAccessory\StyleDefinition;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Util\ExpectLogTrait;
use WP_Mock;

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

		return new FormElementRenderer($manifest, $service, $this->loader, $logger);
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

	public function test_set_and_get_template_overrides_and_logger_fallback(): void {
		$renderer = new FormElementRenderer(
			new ComponentManifest($this->loader, $this->logger),
			new FormsService(new ComponentManifest($this->loader, $this->logger)),
			$this->loader,
			null // force fallback logger
		);

		$renderer->set_template_overrides(array('wrapper' => 'custom-wrapper'));

		$this->assertSame(array('wrapper' => 'custom-wrapper'), $renderer->get_template_overrides());

		$reflection = new \ReflectionClass($renderer);
		$property   = $reflection->getProperty('logger');
		$property->setAccessible(true);

		$this->assertNotNull($property->getValue($renderer));
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

		$context = $renderer->prepare_field_context($field, $values, $messages);

		$this->assertSame('stored', $context['value']);
		$this->assertSame(array('warn'), $context['validation_warnings']);
		$this->assertSame(array('note'), $context['display_notices']);
		$this->assertSame('value', $context['custom']);
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

		$renderer->set_template_overrides(array('shared.field-wrapper' => 'field-wrapper'));

		$field = array(
			'field_id'  => 'field',
			'component' => 'fields.text',
			'label'     => 'Label',
		);

		$context = $renderer->prepare_field_context($field, array(), array());
		$html    = $renderer->render_field_component('fields.text', 'field', 'Label', $context, array(), 'shared.field-wrapper');

		$this->assertStringContainsString('wrapper', $html);
		$this->assertStringContainsString('<input />', $html);
		$this->expectLog('debug', 'FormElementRenderer: Component rendered successfully');
		$this->expectLog('debug', 'FormElementRenderer: Assets collected successfully');
	}

	public function test_render_field_component_falls_back_when_wrapper_fails(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andReturn(new ComponentRenderResult('<input />'));

		$loader = Mockery::mock(ComponentLoader::class);
		$loader->shouldReceive('render')->andThrow(new \RuntimeException('wrapper failed'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $loader, $this->logger);

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$html = $renderer->render_field_component('fields.text', 'field', 'Label', $context, array(), 'custom.wrapper');

		$this->assertSame('<input />', $html);
		$this->expectLog('error', 'FormElementRenderer: Template wrapper failed');
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

		$session = $service->start_session();

		$html = $renderer->render_component_with_assets('test-component', array('field_id' => 'field'), $session);

		$this->assertSame('<div>Rendered</div>', $html);
		$this->assertTrue($session->assets()->has_assets());
		$this->expectLog('debug', 'FormElementRenderer: Component rendered with assets for FormsServiceSession');
		$this->expectLog('debug', 'FormElementRenderer: Assets collected successfully');
	}

	public function test_render_field_with_wrapper_uses_default_session_when_missing(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andReturn(new ComponentRenderResult('<div>component</div>'));

		$renderer = $this->createRenderer($manifest);

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$html = $renderer->render_field_with_wrapper('fields.text', 'field', 'Label', $context, array(), 'wrappers/simple-wrapper');

		$this->assertStringContainsString('component', $html);
	}

	public function test_render_field_with_wrapper_wraps_exceptions(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andThrow(new \RuntimeException('render failed'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $this->loader, $this->logger);

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to render field 'field' with component 'fields.text'");

		$renderer->render_field_with_wrapper('fields.text', 'field', 'Label', $context, array(), 'wrappers/simple-wrapper');
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

		$renderer->render_field_component('fields.text', 'field', 'Label', $context, array());
		$this->expectLog('error', 'FormElementRenderer: Component rendering failed');
	}
}
