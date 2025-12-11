<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Functions;
use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\Components\Fields\Input\Builder as InputBuilder;
use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Mockery;
use InvalidArgumentException;

final class AdminSettingsBehaviorTest_AutoValidator implements ValidatorInterface {
	public static array $calls = array();

	public function __construct(private Logger $logger) {
	}

	public static function reset(): void {
		self::$calls = array();
	}

	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		self::$calls[] = $value;
		$emitWarning('Auto component validator failed for value: ' . (string) $value);
		return false;
	}
}

final class AdminSettingsBehaviorTest_PassThroughValidator implements ValidatorInterface {
	public function __construct(private Logger $logger) {
	}

	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		return true;
	}
}

/**
 * @covers \Ran\PluginLib\Settings\AdminSettings
 */
final class AdminSettingsBehaviorTest extends PluginLibTestCase {
	use ExpectLogTrait;
	private AdminSettings $settings;
	private RegisterOptions $options;
	private ComponentManifest $manifest;
	private CollectingLogger $logger;
	/** @var array<string,mixed> */
	private array $optionValues = array();

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->logger_mock instanceof CollectingLogger
		    ? $this->logger_mock
		    : new CollectingLogger(array());

		$this->optionValues = array();
		$self               = $this;

		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
		WP_Mock::userFunction('wp_kses_post')->andReturnArg(0);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);
		WP_Mock::userFunction('current_user_can')->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturnUsing(static function (string $option, mixed $default = false) use ($self) {
			return array_key_exists($option, $self->optionValues) ? $self->optionValues[$option] : $default;
		});
		WP_Mock::userFunction('settings_fields')->andReturnNull();
		WP_Mock::userFunction('submit_button')->andReturnNull();
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/site')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/network')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		$loader      = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger);
		$fixturesDir = __DIR__ . '/../../fixtures/templates';
		$loader->register_absolute('layout.zone.section-wrapper', $fixturesDir . '/admin/sections/test-section.php');
		$loader->register_absolute('field-wrapper', $fixturesDir . '/admin/fields/example-field-wrapper.php');
		$loader->register_absolute('layout.field.field-wrapper', $fixturesDir . '/admin/fields/example-field-wrapper.php');
		$loader->register_absolute('shared.field-wrapper', $fixturesDir . '/admin/fields/example-field-wrapper.php');
		$loader->register_absolute('section-wrapper', $fixturesDir . '/admin/section-wrapper.php');
		$loader->register_absolute('layout.zone.submit-controls-wrapper', $fixturesDir . '/admin/submit-controls-wrapper.php');
		$loader->register_absolute('admin.pages.behavior-page', $fixturesDir . '/admin/pages/test-page.php');
		$loader->register_absolute('admin.root-wrapper', $fixturesDir . '/admin/pages/default-page.php');
		$loader->register_absolute('root-wrapper', $fixturesDir . '/admin/pages/default-page.php');

		$this->manifest = new ComponentManifest(
			$loader,
			$this->logger
		);
		$this->registerTemplateStubs();
		$this->options = new RegisterOptions(
			'behavior_admin_options',
			StorageContext::forSite(),
			true,
			$this->logger
		);

		$this->options->register_schema(array(
			'valid_field' => array(
				'default'  => '',
				'validate' => static function ($value, callable $emitWarning): bool {
					if (!is_string($value)) {
						$emitWarning('valid_field must be a string');
						return false;
					}
					return true;
				},
			),
			'integer_field' => array(
				'default'  => 0,
				'sanitize' => static fn ($value): int => (int) $value,
				'validate' => static function ($value, callable $emitWarning): bool {
					if (!is_int($value)) {
						$emitWarning('integer_field must be an integer');
						return false;
					}
					return true;
				},
			),
		));

		$this->settings = new AdminSettings($this->options, $this->manifest, null, $this->logger);
	}

	public function test_register_setting_uses_settings_api(): void {
		$logStart = \count($this->logger->collected_logs);

		$this->settings->__register_setting();

		$logEntries   = \array_slice($this->logger->collected_logs, $logStart);
		$registerLogs = \array_values(\array_filter($logEntries, static function (array $entry): bool {
			return $entry['message'] === 'admin_settings.register_setting.after';
		}));
		self::assertNotEmpty($registerLogs);
		$log = \array_pop($registerLogs);
		self::assertSame('behavior_admin_options_group', $log['context']['group'] ?? null);
		self::assertSame('behavior_admin_options', $log['context']['option'] ?? null);
	}

	public function test_sanitize_returns_previous_payload_on_validation_failure(): void {
		$this->setOptionValues(array('valid_field' => 'existing', 'integer_field' => 0));

		$result = $this->settings->__sanitize(array('valid_field' => array('not a string')));

		$this->assertSame(array('valid_field' => 'existing', 'integer_field' => 0), $result);
		$messages = $this->settings->take_messages();
		$this->assertArrayHasKey('valid_field', $messages);
		$this->assertNotEmpty($messages['valid_field']['warnings'] ?? array());
	}

	public function test_sanitize_returns_sanitized_payload_on_success(): void {
		$this->setOptionValues(array());

		$result = $this->settings->__sanitize(array('integer_field' => '5'));

		$this->assertSame(array('valid_field' => '', 'integer_field' => 5), $result);
		$messages = $this->settings->take_messages();
		$this->assertSame(array(), $messages);
	}

	public function test_menu_group_commit_populates_reference_only_pages_map(): void {
		$menu_group = $this->settings->menu_group('reference-group')
			->heading('Reference Heading')
			->menu_label('Reference Menu')
			->page('reference-page', null, array(
				'heading'    => 'Reference Page',
				'menu_title' => 'Reference Menu Item',
				'capability' => 'manage_options',
			))
				->section('reference-section', 'Reference Section')
					->field('reference_field', 'Reference Field', 'fields.input')
				->end_field()->end_section()
			->end_page()
		->end_menu();

		$reflection = new \ReflectionClass($this->settings);
		$pagesProp  = $reflection->getProperty('pages');
		$pagesProp->setAccessible(true);
		$pages = $pagesProp->getValue($this->settings);

		self::assertSame(
			array('reference-page' => array('group' => 'reference-group', 'page' => 'reference-page')),
			$pages,
			'Pages map should retain only identifiers after menu_group_commit.'
		);

		$resolveMethod = $reflection->getMethod('_resolve_page_reference');
		$resolveMethod->setAccessible(true);
		$ref = $resolveMethod->invoke($this->settings, 'reference-page');

		self::assertSame('reference-group', $ref['group']);
		self::assertSame('reference-page', $ref['page']);
		self::assertSame('Reference Page', $ref['meta']['heading']);
		self::assertSame('Reference Menu Item', $ref['meta']['menu_title']);
		self::assertSame('manage_options', $ref['meta']['capability']);
	}

	public function test_schema_bundle_helper_caches_and_logs_hit_for_admin_context(): void {
		$refOptions = new \ReflectionProperty($this->settings, 'base_options');
		$refOptions->setAccessible(true);
		$baseOptions = $refOptions->getValue($this->settings);

		$method = new \ReflectionMethod($this->settings, '_resolve_schema_bundle');
		$method->setAccessible(true);

		$this->logger->collected_logs = array();
		$method->invoke($this->settings, $baseOptions, array('intent' => 'cache-test', 'page_slug' => 'cache-page'));

		$cachedLogs = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cached';
		});
		self::assertCount(1, $cachedLogs, 'Schema bundle should be cached on first resolve.');
		self::assertCount(0, $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cache_hit';
		}), 'Cache hit should not be logged on first resolve.');

		$this->logger->collected_logs = array();
		$method->invoke($this->settings, $baseOptions, array('intent' => 'cache-test', 'page_slug' => 'cache-page'));

		$cacheHits = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cache_hit';
		});
		self::assertCount(1, $cacheHits, 'Cache hit should be logged on second resolve.');
		self::assertArrayHasKey('key', $cacheHits[0]['context'] ?? array());
		self::assertCount(0, $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cached';
		}), 'Bundle should not be recomputed after cache hit.');
	}

	public function test_sanitize_auto_schema_attaches_component_validator(): void {
		$alias = 'fields.auto-validator';
		$this->registerComponentValidator($alias);

		$this->settings->menu_group('auto-group')
		    ->page('auto-page')
		        ->section('auto-section', 'Auto Section')
		            ->field('auto_field', 'Auto Field', $alias)
		        ->end_field()->end_section()
		    ->end_page()
		->end_menu();

		$this->setOptionValues(array(
			'valid_field'   => '',
			'integer_field' => 0,
			'auto_field'    => 'previous-value',
		));

		$result = $this->settings->__sanitize(array('auto_field' => 'invalid'));

		self::assertArrayHasKey('auto_field', $result);
		self::assertSame('previous-value', $result['auto_field']);

		$messages = $this->settings->take_messages();
		self::assertArrayHasKey('auto_field', $messages);
		$warnings = (array) ($messages['auto_field']['warnings'] ?? array());
		self::assertNotEmpty($warnings);
		self::assertTrue(
			(array_reduce($warnings, static function (bool $memo, string $message): bool {
				return $memo || str_contains($message, 'Auto component validator failed');
			}, false)),
			'Expected auto component validator warning.'
		);

		self::assertSame(array('invalid'), AdminSettingsBehaviorTest_AutoValidator::$calls);

		$matchedLogs = $this->logger->find_logs(static function (array $entry): bool {
			if (($entry['message'] ?? null) !== AdminSettings::class . ': Component validator queue matched schema key') {
				return false;
			}
			$context = $entry['context'] ?? array();
			return ($context['normalized_key'] ?? null) === 'auto_field'
				&& ($context['validator_count'] ?? null)   === 1;
		});
		self::assertNotEmpty($matchedLogs, 'Expected validator queue matched log for auto_field.');

		$consumedLogs = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? null) === AdminSettings::class . ': Component validator queue consumed';
		});
		self::assertNotEmpty($consumedLogs, 'Expected validator queue consumed log.');
	}

	public function test_render_unknown_page_falls_back_to_notice(): void {
		$output = $this->captureOutput(function (): void {
			$this->settings->__render('missing-page');
		});

		// In production mode (WP_DEBUG=false), shows generic error message
		$this->assertStringContainsString('Settings Error', $output);
	}

	/* issue */
	public function test_render_happy_path_enqueues_assets(): void {
		$this->settings->menu_group('behavior-group')
		    ->page('behavior-page')
		        ->heading('Behavior Page')
		        ->section('behavior-section', 'Behavior Section')
		            ->field('valid_field', 'Valid Field', 'fields.input')
		        ->end_field()->end_section()
		    ->end_page()
		->end_menu();

		$this->setOptionValues(array('valid_field' => 'value'));

		$output = $this->captureOutput(function (): void {
			$this->settings->__render('behavior-page');
		});

		$this->assertStringContainsString('<section', $output);
	}

	public function test_render_outputs_before_after_hooks_for_sections_groups_and_fields(): void {
		$this->enable_console_logging = false;
		$this->settings->menu_group('hooks-group')
		    ->page('hooks-page')
		        ->heading('Hooks Page')
		        ->section('hooks-section', 'Hooks Section', null, array(
		            'before' => static fn (): string => '<div class="section-before">section-before</div>',
		            'after'  => static fn (): string => '<div class="section-after">section-after</div>',
		        ))
		            ->field('valid_field', 'Standalone Field', 'fields.input')
		                ->before(static fn (): string => '<span class="field-before">field-before</span>')
		                ->after(static fn (): string => '<span class="field-after">field-after</span>')
		            ->end_field()
		            ->group('hooks-group-inner', 'Hooks Group', null, array(
		                'before' => static fn (): string => '<div class="group-before">group-before</div>',
		                'after'  => static fn (): string => '<div class="group-after">group-after</div>',
		            ))
		                ->field('integer_field', 'Group Field', 'fields.input', array(
		                    'before' => static fn (): string => '<span class="group-field-before">group-field-before</span>',
		                    'after'  => static fn (): string => '<span class="group-field-after">group-field-after</span>',
		                ))->end_field()
		            ->end_group()
		        ->end_section()
		    ->end_page()
		->end_menu();

		$this->setOptionValues(array(
			'valid_field'   => 'value-one',
			'integer_field' => 5,
		));

		$output = $this->captureOutput(function (): void {
			$this->settings->__render('hooks-page');
		});

		self::assertStringContainsString('<div class="section-before">section-before</div>', $output);
		self::assertStringContainsString('<div class="section-after">section-after</div>', $output);
		self::assertStringContainsString('<div class="group-before">group-before</div>', $output);
		self::assertStringContainsString('<div class="group-after">group-after</div>', $output);
		self::assertStringContainsString('<span class="field-before">field-before</span>', $output);
		self::assertStringContainsString('<span class="field-after">field-after</span>', $output);
		self::assertStringContainsString('<span class="group-field-before">group-field-before</span>', $output);
		self::assertStringContainsString('<span class="group-field-after">group-field-after</span>', $output);
	}

	public function test_render_uses_custom_template_when_callable_provided(): void {
		$callbackCalled = false;

		$renderOverride = static function (array $payload) use (&$callbackCalled): void {
			$callbackCalled = true;
			echo '<div class="custom-template">' . htmlspecialchars($payload['page_meta']['heading'], ENT_QUOTES) . '</div>';
		};

		$this->settings->menu_group('behavior-group')
		    ->page('behavior-page')
		        ->heading('Behavior Page')
		        ->template($renderOverride)
		    ->end_page()
		->end_menu();

		$this->expectOptionReturn(array());

		$output = $this->captureOutput(function (): void {
			$this->settings->__render('behavior-page');
		});

		$this->assertStringContainsString('custom-template', $output);
		$this->assertTrue($callbackCalled);
	}

	public function test_page_convenience_method_populates_group(): void {
		$this->settings->settings_page('tools-page')
		    ->heading('Tools Heading')
		    ->menu_label('Tools Label')
		    ->section('tools-section', 'Tools Section')
		    ->end_section()
		->end_page();

		$this->expectOptionReturn(array());

		$output = $this->captureOutput(function (): void {
			$this->settings->__render('tools-page');
		});

		$this->assertStringContainsString('Tools Heading', $output);
		$messages = $this->settings->take_messages();
		$this->assertSame(array(), $messages);
	}

	public function test_sanitize_network_scope_uses_network_context(): void {
		WP_Mock::userFunction('is_network_admin')->andReturn(true);
		WP_Mock::userFunction('get_site_option')
		    ->with('behavior_admin_options', array())
		    ->andReturn(array('valid_field' => '', 'integer_field' => 0));

		$result = $this->settings->__sanitize(array('integer_field' => '10'));

		$this->assertSame(array('valid_field' => '', 'integer_field' => 10), $result);
	}

	public function test_handle_menu_group_update_persists_metadata(): void {
		$logStart = \count($this->logger->collected_logs);

		$this->invokeSettingsMethod('_handle_custom_update', array('menu_group', array(
		    'container_id' => 'group-one',
		    'group_data'   => array(
		        'heading'    => 'Group One',
		        'menu_title' => 'Group One Title',
		        'capability' => 'manage_options',
		        'parent'     => null,
		    ),
		)));

		$this->invokeSettingsMethod('_handle_custom_update', array('menu_group_commit', array(
		    'container_id' => 'group-one',
		)));

		// Verify state was updated correctly (logging reduced to commit only)
		$menuGroups = $this->getSettingsProperty('menu_groups');
		self::assertArrayHasKey('group-one', $menuGroups);
		self::assertSame('Group One Title', $menuGroups['group-one']['meta']['menu_title'] ?? null);
		self::assertSame('manage_options', $menuGroups['group-one']['meta']['capability'] ?? null);

		// Verify commit log was emitted
		$logEntries = \array_slice($this->logger->collected_logs, $logStart);
		$commitLogs = \array_values(\array_filter($logEntries, static function (array $entry): bool {
			return $entry['message'] === 'settings.builder.menu_group.committed';
		}));
		self::assertNotEmpty($commitLogs);
		$commit = \array_pop($commitLogs);
		self::assertSame('group-one', $commit['context']['container_id'] ?? null);
	}

	public function test_handle_context_update_records_page_lookup(): void {
		$logStart = \count($this->logger->collected_logs);

		$this->invokeSettingsMethod('_handle_custom_update', array('menu_group', array(
		    'container_id' => 'context-group',
		    'group_data'   => array('heading' => 'Context Group Title', 'menu_title' => 'Context Group Title', 'capability' => 'manage_options'),
		)));

		$this->invokeSettingsMethod('_handle_custom_update', array('page', array(
		    'container_id' => 'context-page',
		    'group_id'     => 'context-group',
		    'page_data'    => array('meta' => array('heading' => 'Context Page')),
		)));

		// Verify state was updated correctly (logging reduced to commit only)
		$pages = $this->getSettingsProperty('pages');
		self::assertArrayHasKey('context-page', $pages);
		self::assertSame('context-group', $pages['context-page']['group'] ?? null);
		self::assertSame('context-page', $pages['context-page']['page'] ?? null);
	}

	/* issue **/
	public function test_sanitize_merges_manifest_defaults_and_propagates_messages(): void {
		$this->enable_console_logging = false;
		$executionOrder               = array();
		$capturedComponent            = null;

		$this->manifest->register('fields.merge', static function (array $context) use (&$capturedComponent): ComponentRenderResult {
			$capturedComponent = $context;
			return new ComponentRenderResult('<input type="text" />');
		});

		$this->injectBuilderFactory('fields.merge');
		$this->injectManifestDefaults('fields.merge', array(
			'sanitize' => array(function (mixed $value) use (&$executionOrder) {
				$executionOrder[] = 'manifest_sanitize';
				return (string) $value;
			}),
			'validate' => array(function (mixed $value, callable $emitWarning) use (&$executionOrder) {
				$executionOrder[] = 'manifest_validate';
				return true;
			}),
			'context' => array('manifest_flag' => true),
		));

		$this->options->register_schema(array(
			'merge_field' => array(
				'sanitize' => static function (mixed $value) use (&$executionOrder): string {
					$executionOrder[] = 'schema_sanitize';
					return trim((string) $value);
				},
				'validate' => static function (mixed $value, callable $emitWarning) use (&$executionOrder): bool {
					$executionOrder[] = 'schema_validate';
					$emitWarning('Schema validator failed.');
					return false;
				},
			),
		));

		$this->settings->menu_group('merge-group')
		    ->page('merge-page')
		        ->section('merge-section', 'Merge Section')
		            ->field('merge_field', 'Merge Field', 'fields.merge')
		        ->end_field()->end_section()
		    ->end_page()
		->end_menu();

		$this->setOptionValues(array('merge_field' => 'previous'));

		$result = $this->settings->__sanitize(array('merge_field' => ' test '));

		self::assertSame('previous', $result['merge_field']);

		self::assertNotEmpty($executionOrder);

		$firstManifestSanitize = array_search('manifest_sanitize', $executionOrder, true);
		self::assertNotFalse($firstManifestSanitize, 'Expected manifest sanitizer to run.');
		$schemaAfterManifest = array_filter(
			$executionOrder,
			static function (string $label, int $index) use ($firstManifestSanitize): bool {
				return $label === 'schema_sanitize' && $index > $firstManifestSanitize;
			},
			ARRAY_FILTER_USE_BOTH
		);
		self::assertNotEmpty($schemaAfterManifest, 'Expected schema sanitizer to run after manifest sanitizer.');
		$schemaBeforeManifest = array_filter(
			$executionOrder,
			static function (string $label, int $index) use ($firstManifestSanitize): bool {
				return $label === 'schema_sanitize' && $index < $firstManifestSanitize;
			},
			ARRAY_FILTER_USE_BOTH
		);
		self::assertSame(array(), array_values($schemaBeforeManifest), 'Schema sanitizer should not run before manifest sanitizer.');

		$lastSchemaValidate = null;
		foreach ($executionOrder as $idx => $label) {
			if ($label === 'schema_validate') {
				$lastSchemaValidate = $idx;
			}
		}
		self::assertNotNull($lastSchemaValidate, 'Expected schema validator executions.');

		$manifestValidateBeforeFailure = array_filter(
			$executionOrder,
			static function (string $label, int $index) use ($lastSchemaValidate): bool {
				return $label === 'manifest_validate' && $index < $lastSchemaValidate;
			},
			ARRAY_FILTER_USE_BOTH
		);
		self::assertNotEmpty($manifestValidateBeforeFailure, 'Expected manifest validator to run before failing schema validator.');

		$this->captureOutput(function () use (&$capturedComponent): void {
			$this->settings->__render('merge-page');
		});

		self::assertIsArray($capturedComponent);
		$renderWarnings = $capturedComponent['validation_warnings'] ?? array();
		if (is_string($renderWarnings)) {
			$renderWarnings = array($renderWarnings);
		}
		self::assertTrue(
			(array_reduce((array) $renderWarnings, static function (bool $found, string $message): bool {
				return $found || str_contains($message, 'Schema validator failed');
			}, false)),
			'Expected schema validator warning to surface during render.'
		);

		$messages = $this->settings->take_messages();
		self::assertArrayHasKey('merge_field', $messages);
		$warnings = $messages['merge_field']['warnings'] ?? array();
		self::assertNotEmpty($warnings, 'Expected warnings for merge_field.');
		self::assertTrue(
			(array_reduce($warnings, static function (bool $found, string $message): bool {
				return $found || str_contains($message, 'Schema validator failed');
			}, false)),
			'Expected schema validator failure warning.'
		);
	}

	public function test_render_and_sanitize_emit_schema_trace_logs(): void {
		$alias = 'fields.trace';

		$this->manifest->register($alias, static function (array $context): ComponentRenderResult {
			$fieldId = htmlspecialchars((string) ($context['field_id'] ?? ''), ENT_QUOTES);
			$value   = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input name="' . $fieldId . '" value="' . $value . '" />',
				component_type: 'input'
			);
		});

		$this->injectBuilderFactory($alias);
		$this->injectManifestDefaults($alias, array(
			'sanitize' => array(static fn (mixed $value): string => is_string($value) ? trim($value) : ''),
			'validate' => array(static function (mixed $value, callable $emitWarning): bool {
				if (!is_string($value) || $value === '') {
					$emitWarning('trace_field must be a non-empty string');
					return false;
				}
				return true;
			}),
			'context' => array('component_type' => 'input'),
		));
		$this->setManifestValidatorClass($alias, AdminSettingsBehaviorTest_PassThroughValidator::class);

		$this->options->register_schema(array(
			'trace_field' => array(
				'default' => 'seed-value',
			),
		));

		$this->settings->menu_group('trace-group')
		    ->page('trace-page')
		        ->section('trace-section', 'Trace Section')
		            ->field('trace_field', 'Trace Field', $alias)
		        ->end_field()->end_section()
		    ->end_page()
		->end_menu();

		$this->setOptionValues(array(
			'valid_field'   => 'existing',
			'integer_field' => 5,
			'trace_field'   => 'previous',
		));

		$this->logger->collected_logs = array();

		$this->captureOutput(function (): void {
			$this->settings->__render('trace-page');
		});

		$result = $this->settings->__sanitize(array(
			'valid_field'   => 'updated value',
			'integer_field' => '11',
			'trace_field'   => '  updated  ',
		));

		self::assertSame(array(
			'valid_field'   => 'updated value',
			'integer_field' => 11,
			'trace_field'   => 'updated',
		), $result);

		$logs = $this->logger->get_logs();

		$mergeLogs = array_values(array_filter($logs, static function (array $entry): bool {
			return $entry['level'] === 'debug'
				&& $entry['message']  === 'forms.schema.merge';
		}));
		self::assertNotEmpty(
			$mergeLogs,
			'Expected forms.schema.merge logs. Saw messages: ' . implode(', ', array_unique(array_map(static fn (array $entry): string => $entry['message'], $logs)))
		);
		self::assertNotEmpty(
			array_filter($mergeLogs, static function (array $entry) use ($alias): bool {
				return ($entry['context']['alias'] ?? null) === $alias;
			}),
			'Expected forms.schema.merge log for alias ' . $alias . '. Contexts: ' . json_encode(array_map(static fn (array $entry): array => $entry['context'], $mergeLogs))
		);

		$processingLogs = array_values(array_filter($logs, static function (array $entry): bool {
			return $entry['level'] === 'debug'
				&& $entry['message']  === 'RegisterOptions: _register_internal_schema processing entry';
		}));
		self::assertNotEmpty($processingLogs, 'Expected processing logs from _register_internal_schema.');
		self::assertNotEmpty(array_filter($processingLogs, static function (array $entry): bool {
			return ($entry['context']['key'] ?? null) === 'trace_field';
		}), 'Expected processing log for trace_field.');

		$mergedLogs = array_values(array_filter($logs, static function (array $entry): bool {
			return $entry['level'] === 'debug'
				&& $entry['message']  === 'RegisterOptions: _register_internal_schema merged entry';
		}));
		self::assertNotEmpty($mergedLogs, 'Expected merged-entry logs from _register_internal_schema.');
		self::assertNotEmpty(array_filter($mergedLogs, static function (array $entry): bool {
			return ($entry['context']['key'] ?? null) === 'trace_field';
		}), 'Expected merged-entry log for trace_field.');

		// Ensure manifest validators merge into component bucket while schema bucket remains available.
		$traceMerged = array_values(array_filter($mergedLogs, static function (array $entry): bool {
			return ($entry['context']['key'] ?? null) === 'trace_field';
		}));
		self::assertGreaterThanOrEqual(1, count($traceMerged), 'Expected merged-entry log for trace_field.');
		$traceWithComponentValidators = array_values(array_filter($traceMerged, static function (array $entry): bool {
			return (int) ($entry['context']['validate_component_count'] ?? 0) >= 1;
		}));
		self::assertNotEmpty($traceWithComponentValidators, 'Expected component validators to be merged for trace_field.');
		$representative = $traceWithComponentValidators[0];
		self::assertArrayHasKey('validate_schema_count', $representative['context'], 'Expected schema validator count present for trace_field.');
		self::assertGreaterThanOrEqual(0, (int) $representative['context']['validate_schema_count'], 'Expected schema validator count present for trace_field.');
	}

	/**
	 * @param array<string,mixed> $defaults
	 */
	private function injectManifestDefaults(string $alias, array $defaults): void {
		$reflection = new \ReflectionObject($this->manifest);
		$property   = $reflection->getProperty('componentMetadata');
		$property->setAccessible(true);
		$metadata = $property->getValue($this->manifest);
		if (!is_array($metadata)) {
			$metadata = array();
		}
		$current             = $metadata[$alias] ?? array();
		$current['defaults'] = $defaults;
		$metadata[$alias]    = $current;
		$property->setValue($this->manifest, $metadata);
	}

	private function registerComponentValidator(string $alias): void {
		AdminSettingsBehaviorTest_AutoValidator::reset();

		$this->manifest->register($alias, static function (array $context): ComponentRenderResult {
			$fieldId = htmlspecialchars((string) ($context['field_id'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input name="' . $fieldId . '" />',
				component_type: 'input'
			);
		});

		$this->injectBuilderFactory($alias);
		$this->injectManifestDefaults($alias, array(
			'context'  => array('component_type' => 'input'),
			'validate' => array(static fn ($value, callable $emitWarning): bool => true),
		));

		$this->setManifestValidatorClass($alias, AdminSettingsBehaviorTest_AutoValidator::class);
	}

	private function setManifestValidatorClass(string $alias, string $validatorClass): void {
		$reflection = new \ReflectionObject($this->manifest);
		$property   = $reflection->getProperty('componentMetadata');
		$property->setAccessible(true);
		$metadata = $property->getValue($this->manifest);
		if (!is_array($metadata)) {
			$metadata = array();
		}
		$current              = $metadata[$alias] ?? array();
		$current['validator'] = $validatorClass;
		$metadata[$alias]     = $current;
		$property->setValue($this->manifest, $metadata);
	}

	/**
	 * Inject a builder factory for a fake component alias.
	 */
	private function injectBuilderFactory(string $alias): void {
		$reflection = new \ReflectionObject($this->manifest);
		$property   = $reflection->getProperty('componentMetadata');
		$property->setAccessible(true);
		$metadata = $property->getValue($this->manifest);
		if (!is_array($metadata)) {
			$metadata = array();
		}
		$current            = $metadata[$alias] ?? array();
		$current['builder'] = static fn (string $id, string $label): InputBuilder => new InputBuilder($id, $label);
		$metadata[$alias]   = $current;
		$property->setValue($this->manifest, $metadata);
	}

	private function getSettingsProperty(string $property): mixed {
		$reflection = new \ReflectionObject($this->settings);
		$prop       = $reflection->getProperty($property);
		$prop->setAccessible(true);

		return $prop->getValue($this->settings);
	}

	private function setSettingsProperty(string $property, mixed $value): void {
		$reflection = new \ReflectionObject($this->settings);
		$prop       = $reflection->getProperty($property);
		$prop->setAccessible(true);
		$prop->setValue($this->settings, $value);
	}

	private function invokeSettingsMethod(string $method, array $args = array()): mixed {
		$reflection = new \ReflectionObject($this->settings);
		$refMethod  = $reflection->getMethod($method);
		$refMethod->setAccessible(true);

		return $refMethod->invokeArgs($this->settings, $args);
	}

	private function expectOptionReturn(array $value): void {
		$this->optionValues['behavior_admin_options'] = $value;
	}

	private function setOptionValues(array $value): void {
		$this->expectOptionReturn($value);
	}

	private function registerTemplateStubs(): void {
		$this->manifest->register('layout.zone.section-wrapper', static function (array $context): ComponentRenderResult {
			$renderer = $context['field_renderer'] ?? null;
			$markup   = '<div class="admin-sections">';
			foreach ($context['sections'] ?? array() as $section) {
				$markup .= '<section class="admin-section">';
				$markup .= '<h2>' . htmlspecialchars($section['title'] ?? '', ENT_QUOTES) . '</h2>';
				foreach ($section['items'] ?? array() as $item) {
					if (($item['type'] ?? '') === 'field' && is_callable($renderer)) {
						$markup .= $renderer($item['field']);
					}
				}
				$markup .= '</section>';
			}
			$markup .= '</div>';

			return new ComponentRenderResult(
				$markup,
				component_type: 'layout_wrapper'
			);
		});

		$this->manifest->register('field-wrapper', static function (array $context): ComponentRenderResult {
			$componentHtml = (string) ($context['inner_html'] ?? '');
			return new ComponentRenderResult(
				'<div class="test-field-wrapper">' . $componentHtml . '</div>',
				component_type: ComponentType::LayoutWrapper
			);
		});

		$this->manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			$fieldId = htmlspecialchars($context['field_id'] ?? 'field', ENT_QUOTES);
			$value   = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input name="' . $fieldId . '" value="' . $value . '" />',
				component_type: 'input'
			);
		});

		// Inject callable defaults for fields.input to satisfy ValidatorPipelineService
		$this->injectManifestDefaults('fields.input', array(
			'sanitize' => array(static fn (mixed $value): string => is_string($value) ? trim($value) : ''),
			'validate' => array(static fn (mixed $value, callable $emitWarning): bool => true),
			'context'  => array('component_type' => 'input'),
		));

		$this->manifest->register('admin.pages.behavior-page', static function (array $context): ComponentRenderResult {
			$content = $context['inner_html'] ?? '';
			return new ComponentRenderResult(
				'<div class="admin-page">' . $content . '</div>',
				component_type: 'layout_wrapper'
			);
		});

		$this->manifest->register('submit-controls-wrapper', static function (array $context): ComponentRenderResult {
			$zone    = htmlspecialchars((string) ($context['zone_id'] ?? ''), ENT_QUOTES);
			$content = $context['inner_html'] ?? '';
			return new ComponentRenderResult(
				'<div class="submit-wrapper" data-zone="' . $zone . '">' . $content . '</div>',
				component_type: 'layout_wrapper'
			);
		});

		$this->manifest->register('components.button', static function (array $context): ComponentRenderResult {
			$type  = htmlspecialchars((string) ($context['type'] ?? 'button'), ENT_QUOTES);
			$label = htmlspecialchars((string) ($context['label'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<button type="' . $type . '">' . $label . '</button>',
				component_type: 'input'
			);
		});
	}

	public function skip_test__render_default_submit_controls_are_seeded(): void {
		$this->settings->menu_group('submit-group')
		    ->page('submit-page')
		        ->heading('Submit Page')
		        ->section('submit-section', 'Submit Section')
		            ->field('valid_field', 'Valid Field', 'fields.input')
		        ->end_field()->end_section()
		    ->end_page()
		->end_menu();

		$this->expectOptionReturn(array('valid_field' => 'seed'));

		$output = $this->captureOutput(function (): void {
			$this->settings->__render('submit-page');
		});

		self::assertStringContainsString('submit-wrapper', $output, 'Expected submit controls wrapper.');
		self::assertStringContainsString('Save Changes', $output, 'Expected default submit button label.');
	}

	public function skip_test_render_custom_submit_controls_override_default(): void {
		$this->settings->menu_group('custom-submit-group')
			->page('custom-submit-page')
				->section('custom-submit-section', 'Custom Submit Section')
					->field('valid_field', 'Valid Field', 'fields.input')
				->end_field()->end_section()
				->submit_controls()
					->button('primary', 'Publish Settings');

		$this->expectOptionReturn(array('valid_field' => 'value'));

		$output = $this->captureOutput(function (): void {
			$this->settings->__render('custom-submit-page');
		});

		self::assertStringContainsString('submit-wrapper', $output, 'Expected submit controls wrapper.');
		self::assertStringContainsString('Publish Settings', $output, 'Expected custom submit button label.');
		self::assertStringNotContainsString('Save Changes', $output, 'Default submit label should be cleared once custom controls provided.');
	}

	public function skip_test_render_payload_includes_structured_messages_after_validation_failure(): void {
		$capturedPayload = null;
		$renderOverride  = static function (array $payload) use (&$capturedPayload): void {
			$capturedPayload = $payload;
			echo '<div class="payload-callback"></div>';
		};

		$this->settings->menu_group('messages-group')
		    ->page('messages-page')
		        ->heading('Messages Page')
		        ->template($renderOverride)
		        ->section('messages-section', 'Messages Section')
		            ->field('valid_field', 'Valid Field', 'fields.input')
		        ->end_field()->end_section()
		    ->end_page()
		->end_menu();

		$this->setOptionValues(array(
			'valid_field'   => 'existing value',
			'integer_field' => 0,
		));

		/** @var \Ran\PluginLib\Forms\Renderer\FormMessageHandler $messageHandler */
		$messageHandler = $this->getSettingsProperty('message_handler');
		$messageHandler->set_messages(array(
			'valid_field' => array(
				'warnings' => array('valid_field must be a string'),
				'notices'  => array(),
			),
		));
		$messageHandler->set_pending_values(array(
			'valid_field'   => array('not-a-string'),
			'integer_field' => 0,
		));

		$this->captureOutput(function (): void {
			$this->settings->__render('messages-page');
		});

		self::assertIsArray($capturedPayload, 'Expected root template callback to capture payload.');
		self::assertArrayHasKey('messages_by_field', $capturedPayload);
		self::assertArrayHasKey('valid_field', $capturedPayload['messages_by_field']);

		$fieldMessages = $capturedPayload['messages_by_field']['valid_field'];
		self::assertArrayHasKey('warnings', $fieldMessages);
		self::assertArrayHasKey('notices', $fieldMessages);
		self::assertNotEmpty($fieldMessages['warnings'], 'Expected validation warning for valid_field.');
		self::assertContains('valid_field must be a string', $fieldMessages['warnings']);
		self::assertSame(array(), $fieldMessages['notices'], 'Expected notices array to be empty.');
	}

	public function skip_test_render_field_with_assets_requires_shared_session_to_enqueue(): void {
		$this->manifest->register('fields.asset-field', function (array $context): ComponentRenderResult {
			$this->logger->debug('admin_settings.test.asset_field.render', array(
				'field_id'     => $context['field_id'] ?? null,
				'label'        => $context['label']    ?? null,
				'context_keys' => array_keys($context),
			));
			$script = ScriptDefinition::from_array(array(
				'handle' => 'test-asset-script',
				'src'    => 'https://example.com/test.js',
			));

			return new ComponentRenderResult(
				'<div data-asset-field="' . htmlspecialchars((string) ($context['field_id'] ?? ''), ENT_QUOTES) . '">Asset Field</div>',
				script: $script,
				component_type: 'input'
			);
		});

		$menuBuilder = $this->settings->menu_group('asset-group');
		$pageBuilder = $menuBuilder->page('asset-page');

		$pageBuilder
			->section('asset-section', 'Asset Section')
				->field('asset_field', 'Asset Field', 'fields.asset-field')
			->end_field()->end_section();

		$pageBuilder
			->submit_controls()
				->field('asset_submit', 'Save Changes', 'fields.asset-field')
			->end_submit_controls();

		$pageBuilder->end_page();
		$menuBuilder->end_menu();

		$this->expectOptionReturn(array('asset_field' => 'value'));

		// Expect shared session enqueue to register and enqueue the script once the refactor is complete.
		WP_Mock::userFunction('wp_register_script')
			->times(2)
			->with(
				'test-asset-script',
				'https://example.com/test.js',
				array(),
				false,
				true
			)
			->andReturn(true);

		$this->captureOutput(function (): void {
			$this->settings->__render('asset-page');
		});

		$logs       = $this->logger->get_logs();
		$submitLogs = array_filter($logs, static function (array $entry): bool {
			return $entry['level'] === 'debug' && strpos($entry['message'], 'forms.submit_controls.controls.updated') !== false;
		});
		self::assertNotEmpty($submitLogs, 'Expected submit controls update log to be recorded. Found ' . count($submitLogs));
		$this->expectLog('debug', 'FormElementRenderer: Component rendered with assets', 2);

		$session = $this->settings->get_form_session();
		self::assertInstanceOf(FormsServiceSession::class, $session, 'Expected AdminSettings to have an active form session.');
		$handles = array_keys($session->assets()->scripts());
		$this->logger->debug('admin_settings.test.asset_handles', array('handles' => $handles));

		$handlesLog = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message'] === 'admin_settings.test.asset_handles';
		});
		self::assertNotEmpty($handlesLog, 'Expected asset handles log to be recorded.');
		self::assertContains('test-asset-script', $handles, 'Expected script asset to be captured by shared session. Handles: ' . implode(', ', $handles));

		$session->enqueue_assets();
	}

	public function skip_test_render_field_component_wraps_render_exceptions(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andThrow(new \RuntimeException('render failed'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $this->manifest->get_component_loader(), $this->logger);

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to render field 'field' with component 'fields.text'");

		$renderer->render_field_with_wrapper('fields.text', 'field', 'Label', $context, 'wrappers/simple-wrapper', 'field-wrapper', $this->settings->get_form_session());
		$this->expectLog('error', 'FormElementRenderer: Field with wrapper rendering failed');
	}
}
