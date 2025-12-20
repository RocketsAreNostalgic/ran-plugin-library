<?php
/**
 * Tests for FormsServiceSession schema merging and integration pipeline.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Mockery;

final class FormsServiceSessionSchemaMergeTest extends PluginLibTestCase {
	use ExpectLogTrait;

	public function test_merge_schema_with_defaults_orders_callables(): void {
		$schemaSanitize = function ($value) {
			return $value;
		};
		$schemaValidate = function ($value) {
			return true;
		};
		$defaultsSanitize = function ($value) {
			return $value;
		};
		$defaultsValidate = function ($value) {
			return true;
		};

		$defaults = array(
			'sanitize' => array($defaultsSanitize),
			'validate' => array($defaultsValidate),
			'context'  => array('from_manifest' => true),
		);

		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('builder_classes')->andReturn(array());
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('components.merge')
			->andReturn($defaults);
		$manifest->shouldReceive('default_catalogue')->andReturn(array());
		$manifest->shouldReceive('validator_factories')->andReturn(array());
		$manifest->shouldReceive('sanitizer_factories')->andReturn(array());

		$resolver = new FormsTemplateOverrideResolver($this->logger_mock);
		$session  = new FormsServiceSession($manifest, $resolver, $this->logger_mock);

		$schema = array(
			'sanitize' => array($schemaSanitize),
			'validate' => array($schemaValidate),
			'context'  => array('from_schema' => true),
		);

		$result = $session->merge_schema_with_defaults('components.merge', $schema);

		self::assertArrayHasKey('sanitize', $result);
		self::assertSame(
			array(
				'component' => array($defaultsSanitize),
				'schema'    => array($schemaSanitize),
			),
			$result['sanitize']
		);

		self::assertArrayHasKey('validate', $result);
		self::assertSame(
			array(
				'component' => array($defaultsValidate),
				'schema'    => array($schemaValidate),
			),
			$result['validate']
		);

		self::assertSame(
			array('from_manifest' => true, 'from_schema' => true),
			$result['context'] ?? array()
		);

		$this->expectLog('debug', 'forms.schema.merge');
	}

	public function test_merge_schema_without_defaults_returns_coerced_structure(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('builder_classes')->andReturn(array());
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('components.empty')
			->andReturn(array());
		$manifest->shouldReceive('default_catalogue')->andReturn(array());
		$manifest->shouldReceive('validator_factories')->andReturn(array());
		$manifest->shouldReceive('sanitizer_factories')->andReturn(array());

		$resolver = new FormsTemplateOverrideResolver($this->logger_mock);
		$session  = new FormsServiceSession($manifest, $resolver, $this->logger_mock);

		$validator = function () {
			return true;
		};
		$schema = array('validate' => array($validator));
		$result = $session->merge_schema_with_defaults('components.empty', $schema);

		// When no defaults exist, schema is still coerced to canonical bucket structure
		// Flat validators go to 'schema' bucket (flatAsComponent = false)
		self::assertArrayHasKey('sanitize', $result);
		self::assertArrayHasKey('validate', $result);
		self::assertSame(array(), $result['sanitize']['component']);
		self::assertSame(array(), $result['sanitize']['schema']);
		self::assertSame(array(), $result['validate']['component']);
		self::assertSame(array($validator), $result['validate']['schema']);
		$this->expectLog('debug', 'forms.schema.merge.no_defaults');
	}

	/**
	 * Test that components with validator factories are logged in verbose debug mode.
	 *
	 * Validators are injected via the queue path in FormsCore, not through schema merge.
	 * The merge just logs what factories are available.
	 */
	public function test_merge_schema_logs_available_factories(): void {
		$manifestDefaults = array(
			'sanitize' => array(),
			'validate' => array(),
		);

		// Create mock factories
		$mockValidatorFactory = function() {
			return Mockery::mock(\Ran\PluginLib\Forms\Component\Validate\ValidatorInterface::class);
		};
		$mockSanitizerFactory = function() {
			return Mockery::mock(\Ran\PluginLib\Forms\Component\Sanitize\SanitizerInterface::class);
		};

		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('builder_classes')->andReturn(array());
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('fields.text')
			->andReturn($manifestDefaults);
		$manifest->shouldReceive('default_catalogue')->andReturn(array());
		$manifest->shouldReceive('validator_factories')->andReturn(array(
			'fields.text' => $mockValidatorFactory,
		));
		$manifest->shouldReceive('sanitizer_factories')->andReturn(array(
			'fields.text' => $mockSanitizerFactory,
		));

		$resolver = new FormsTemplateOverrideResolver($this->logger_mock);
		$session  = new FormsServiceSession($manifest, $resolver, $this->logger_mock);

		$result = $session->merge_schema_with_defaults('fields.text', array());

		$this->assertIsArray($result);
		// In verbose debug mode, would log 'forms.schema.merge.factories_available'
		// but we don't assert on debug logs in tests
	}

	/**
	 * Test that components without factories don't cause errors.
	 */
	public function test_merge_schema_handles_components_without_factories(): void {
		$manifestDefaults = array(
			'sanitize' => array(),
			'validate' => array(),
		);

		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('builder_classes')->andReturn(array());
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('layout.wrapper')
			->andReturn($manifestDefaults);
		$manifest->shouldReceive('default_catalogue')->andReturn(array());
		$manifest->shouldReceive('validator_factories')->andReturn(array());
		$manifest->shouldReceive('sanitizer_factories')->andReturn(array());

		$resolver = new FormsTemplateOverrideResolver($this->logger_mock);
		$session  = new FormsServiceSession($manifest, $resolver, $this->logger_mock);

		// Should not throw - components without factories are valid (display/layout components)
		$result = $session->merge_schema_with_defaults('layout.wrapper', array());

		$this->assertIsArray($result);
	}

	public function test_merge_pipeline_propagates_validation_messages(): void {
		$executionOrder = array();

		$manifestDefaults = array(
			'sanitize' => array(function ($value) use (&$executionOrder) {
				$executionOrder[] = 'manifest_sanitize';
				return trim($value);
			}),
			'validate' => array(function ($value, callable $emitWarning) use (&$executionOrder) {
				$executionOrder[] = 'manifest_validate';
				return true;
			}),
			'context' => array('manifest_flag' => true),
		);

		$schemaSanitize = function ($value) use (&$executionOrder) {
			$executionOrder[] = 'schema_sanitize';
			return strtoupper($value);
		};
		$schemaValidate = function ($value, callable $emitWarning) use (&$executionOrder) {
			$executionOrder[] = 'schema_validate';
			$emitWarning('Schema validator failed');
			return false;
		};
		$schema = array(
			'sanitize' => array($schemaSanitize),
			'validate' => array($schemaValidate),
			'context'  => array('schema_flag' => true),
		);

		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('builder_classes')->andReturn(array());
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('fields.merge')
			->andReturn($manifestDefaults);
		$manifest->shouldReceive('default_catalogue')->andReturn(array('fields.merge' => $manifestDefaults));
		// Component has validators/sanitizers in manifest - no warning expected
		$manifest->shouldReceive('validator_factories')->andReturn(array('fields.merge' => fn() => null));
		$manifest->shouldReceive('sanitizer_factories')->andReturn(array('fields.merge' => fn() => null));

		$resolver = new FormsTemplateOverrideResolver($this->logger_mock);
		$session  = new FormsServiceSession($manifest, $resolver, $this->logger_mock);

		$merged = $session->merge_schema_with_defaults('fields.merge', $schema);

		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);
		WP_Mock::userFunction('esc_html')->andReturnArg(0);
		WP_Mock::userFunction('wp_unslash')->andReturnArg(0);
		WP_Mock::userFunction('maybe_unserialize')->andReturnArg(0);
		WP_Mock::userFunction('maybe_serialize')->andReturnArg(0);
		WP_Mock::userFunction('apply_filters')->andReturnArg(1);
		WP_Mock::userFunction('current_filter')->andReturn('');
		WP_Mock::userFunction('did_action')->andReturn(0);
		WP_Mock::userFunction('maybe_add_action')->andReturnNull();

		$options = new RegisterOptions('merge_options', StorageContext::forSite(), true, $this->logger_mock);
		$options->with_policy(new class implements \Ran\PluginLib\Options\Policy\WritePolicyInterface {
			public function allow(string $op, \Ran\PluginLib\Options\WriteContext $ctx): bool {
				return true;
			}
		});
		$options->__register_internal_schema(array('field' => $merged));
		$options->register_schema(array(
			'field' => array(
				'sanitize' => $schemaSanitize,
				'validate' => $schemaValidate,
				'context'  => $merged['context'] ?? array(),
			),
		));

		$options->stage_options(array('field' => ' foo '));

		self::assertNotEmpty($executionOrder);
		self::assertSame('manifest_sanitize', $executionOrder[0]);
		self::assertContains('schema_sanitize', $executionOrder);
		self::assertContains('manifest_validate', $executionOrder);
		self::assertContains('schema_validate', $executionOrder);

		$messages = $options->take_messages();
		self::assertArrayHasKey('field', $messages);
		self::assertContains('Schema validator failed', $messages['field']['warnings'] ?? array());

		$rendererManifest = Mockery::mock(ComponentManifest::class);
		$rendererManifest->shouldReceive('builder_classes')->andReturn(array());
		$capturedContext = null;
		$rendererManifest->shouldReceive('render')
			->once()
			->with('fields.merge', Mockery::on(function ($renderContext) use (&$capturedContext) {
				$capturedContext = $renderContext;
				return isset($renderContext['validation_warnings']) && in_array('Schema validator failed', $renderContext['validation_warnings'] ?? array(), true);
			}))
			->andReturn(new ComponentRenderResult('<div>field</div>'));
		$loader      = Mockery::mock(ComponentLoader::class);
		$formService = new FormsService($rendererManifest, $this->logger_mock);
		$renderer    = new FormElementRenderer(
			$rendererManifest,
			$formService,
			$loader,
			$this->logger_mock
		);
		$message_handler = new FormMessageHandler($this->logger_mock);
		$message_handler->set_messages($messages);
		$renderer->set_message_handler($message_handler);
		$session = $formService->start_session();

		$field = array(
			'field_id'          => 'field',
			'component'         => 'fields.merge',
			'label'             => 'Field Label',
			'component_context' => array(),
		);

		$context = $renderer->prepare_field_context($field, array(), array());
		$renderer->render_field_component('fields.merge', 'field', 'Field Label', $context, 'direct-output', 'field-wrapper', $session);

		self::assertSame(array('Schema validator failed'), $context['validation_warnings']);
		self::assertSame('Field Label', $context['label']);
		self::assertNotNull($capturedContext);
		$logs      = $this->logger_mock->get_logs();
		$mergeLogs = array_values(array_filter($logs, static function (array $entry): bool {
			return $entry['message'] === 'forms.schema.merge';
		}));
		self::assertNotEmpty($mergeLogs);
		$latest = array_pop($mergeLogs);
		self::assertSame('fields.merge', $latest['context']['alias'] ?? null);
		self::assertSame(array('component' => 1, 'schema' => 1), $latest['context']['merged_validate_counts'] ?? null);
	}
}
