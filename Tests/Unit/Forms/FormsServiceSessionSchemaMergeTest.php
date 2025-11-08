<?php
/**
 * Tests for FormsServiceSession schema merging and integration pipeline.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms;

use Mockery;
use Ran\PluginLib\Forms\FormsAssets;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use WP_Mock;

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
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('components.merge')
			->andReturn($defaults);
		$manifest->shouldReceive('default_catalogue')->andReturn(array());

		$session = new FormsServiceSession($manifest, new FormsAssets(), $this->logger_mock);

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

	public function test_merge_schema_without_defaults_is_noop(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('components.empty')
			->andReturn(array());
		$manifest->shouldReceive('default_catalogue')->andReturn(array());

		$session = new FormsServiceSession($manifest, new FormsAssets(), $this->logger_mock);

		$schema = array('validate' => array(function () {
			return true;
		}));
		$result = $session->merge_schema_with_defaults('components.empty', $schema);

		self::assertSame($schema, $result);
		$this->expectLog('debug', 'forms.schema.merge.no_defaults');
	}

	public function test_merge_schema_with_defaults_throws_when_no_validators(): void {
		$manifestDefaults = array(
			'sanitize' => array(function($value) {
				return $value;
			}),
			'validate' => array(),
		);

		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('components.requires-validator')
			->andReturn($manifestDefaults);
		$manifest->shouldReceive('default_catalogue')->andReturn(array());

		$session = new FormsServiceSession($manifest, new FormsAssets(), $this->logger_mock);

		$this->expectException(\InvalidArgumentException::class);
		$session->merge_schema_with_defaults('components.requires-validator', array());
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

		$schema = array(
			'sanitize' => array(function ($value) use (&$executionOrder) {
				$executionOrder[] = 'schema_sanitize';
				return strtoupper($value);
			}),
			'validate' => array(function ($value, callable $emitWarning) use (&$executionOrder) {
				$executionOrder[] = 'schema_validate';
				$emitWarning('Schema validator failed');
				return false;
			}),
			'context' => array('schema_flag' => true),
		);

		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('get_defaults_for')
			->once()
			->with('fields.merge')
			->andReturn($manifestDefaults);
		$manifest->shouldReceive('default_catalogue')->andReturn(array('fields.merge' => $manifestDefaults));

		$session = new FormsServiceSession($manifest, new FormsAssets(), $this->logger_mock);

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
		$options->register_schema(array('field' => $merged));

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
		$capturedContext  = null;
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
		$session = $formService->start_session();

		$field = array(
			'field_id'          => 'field',
			'component'         => 'fields.merge',
			'label'             => 'Field Label',
			'component_context' => array(),
		);

		$context = $renderer->prepare_field_context(
			$field,
			array(),
			array('field' => array('warnings' => $messages['field']['warnings'] ?? array(), 'notices' => array()))
		);
		$renderer->render_field_component('fields.merge', 'field', 'Field Label', $context, array(), 'direct-output', $session);

		self::assertSame(array('Schema validator failed'), $context['validation_warnings']);
		self::assertSame('Field Label', $context['label']);
		self::assertNotNull($capturedContext);
		$this->expectLog('debug', 'forms.schema.merge');
	}
}
