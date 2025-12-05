<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Functions;
use WP_Mock;
use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\Components\Fields\Input\Builder as InputBuilder;
use Ran\PluginLib\Forms\Component\Validate\ValidatorInterface;
use Ran\PluginLib\Forms\Component\ComponentType;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;

final class UserSettingsBehaviorTest_AutoValidator implements ValidatorInterface {
	public static array $calls = array();

	public function __construct(private Logger $logger) {
	}

	public static function reset(): void {
		self::$calls = array();
	}

	public function validate(mixed $value, array $context, callable $emitWarning): bool {
		self::$calls[] = $value;
		$emitWarning('User auto validator failed for value: ' . (string) $value);
		return false;
	}
}

/**
 * @covers \Ran\PluginLib\Settings\UserSettings
 */
final class UserSettingsBehaviorTest extends PluginLibTestCase {
	use ExpectLogTrait;
	private RegisterOptions $options;
	private ComponentManifest $manifest;
	private CollectingLogger $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->logger_mock instanceof CollectingLogger
		    ? $this->logger_mock
		    : new CollectingLogger(array());
		$this->logger->collected_logs = array();

		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_user_meta')->andReturn(true);
		WP_Mock::userFunction('update_user_meta')->andReturn(true);
		WP_Mock::userFunction('update_user_option')->andReturn(true);
		WP_Mock::userFunction('delete_user_option')->andReturn(true);
		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
		WP_Mock::userFunction('wp_kses_post')->andReturnArg(0);
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		$loader = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger);

		$this->manifest = new ComponentManifest(
			$loader,
			$this->logger
		);
		$this->registerTemplateStubs();
		$this->options = new RegisterOptions(
			'behavior_user_options',
			StorageContext::forUser(123),
			false,
			$this->logger
		);

		$this->options->register_schema(array(
		    'profile_name' => array(
		        'default'  => 'example',
		        'validate' => static function ($value, callable $emitWarning): bool {
		        	if (!is_string($value)) {
		        		$emitWarning('profile_name must be a string');
		        		return false;
		        	}
		        	return true;
		        },
		    ),
		    'profile_age' => array(
		        'default'  => 18,
		        'sanitize' => static fn ($value): int => max(0, (int) $value),
		        'validate' => static function ($value, callable $emitWarning): bool {
		        	if (!is_int($value) || $value < 18) {
		        		$emitWarning('profile_age must be >= 18');
		        		return false;
		        	}
		        	return true;
		        },
            ),
		));
	}

	public function test_constructor_rejects_non_user_scope(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('UserSettings requires user context');

		new UserSettings(
			new RegisterOptions('invalid_scope', StorageContext::forSite(), true, $this->logger),
			$this->manifest,
			null,
			$this->logger
		);
	}

	public function test_boot_registers_profile_hooks(): void {
		$user_settings = $this->createUserSettings();
		$expectedHooks = array('show_user_profile', 'edit_user_profile', 'personal_options_update', 'edit_user_profile_update');

		$user_settings->collection('profile')->section('basic', 'Basic Info');

		$logStart = \count($this->logger->collected_logs);
		$user_settings->boot();

		$logEntries = \array_slice($this->logger->collected_logs, $logStart);
		$actionLogs = \array_values(\array_filter($logEntries, static function (array $entry) use ($expectedHooks): bool {
			return $entry['message'] === 'wp_wrappers.add_action' && \in_array($entry['context']['hook'] ?? '', $expectedHooks, true);
		}));
		self::assertCount(4, $actionLogs);
		self::assertSame($expectedHooks, \array_map(static fn ($entry) => $entry['context']['hook'], $actionLogs));
		// Patchwork shims add_action during tests, so the wrapper falls back to native calls.
		self::assertSame(
			array_fill(0, \count($actionLogs), 'native'),
			\array_map(static fn ($entry) => $entry['context']['via'], $actionLogs)
		);
	}

	public function test_save_settings_aborts_when_capability_fails(): void {
		$user_settings = $this->createUserSettings();

		WP_Mock::userFunction('current_user_can')->with('edit_user', 456)->andReturn(false);

		$user_settings->_save_settings(array('profile_name' => 'John'), array('user_id' => 456));

		self::assertSame(array(), $user_settings->take_messages());
	}

	public function test_save_settings_persists_when_validation_passes(): void {
		$user_settings = $this->createUserSettings();
		$logStart      = \count($this->logger->collected_logs);

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);

		$user_settings->_save_settings(array('profile_name' => 'Jane', 'profile_age' => 30), array('user_id' => 123));

		$messages = $user_settings->take_messages();
		$this->assertSame(array(), $messages);

		$logEntries   = \array_slice($this->logger->collected_logs, $logStart);
		$defaultsLogs = \array_values(\array_filter($logEntries, static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'RegisterOptions: register_schema defaults';
		}));
		self::assertNotEmpty($defaultsLogs, 'Expected defaults telemetry log.');
		$latestDefaults = \array_pop($defaultsLogs);
		self::assertSame('info', strtolower((string) ($latestDefaults['level'] ?? '')));
		$context = $latestDefaults['context'] ?? array();
		self::assertSame(2, $context['submitted_count'] ?? null);
		self::assertSame(array('profile_name', 'profile_age'), $context['submitted_keys'] ?? null);
		self::assertSame(2, $context['unchanged_count'] ?? null);
		self::assertSame(array('profile_name', 'profile_age'), $context['unchanged_keys'] ?? null);
		self::assertSame(0, $context['changed_count'] ?? null);
		self::assertSame(array(), $context['changed_keys'] ?? null);
		self::assertSame(2, $context['missing_options_count'] ?? null);
		self::assertSame(array('profile_name', 'profile_age'), $context['missing_option_keys'] ?? null);
	}

	public function test_schema_bundle_helper_caches_and_logs_hit_for_user_save_context(): void {
		$user_settings = $this->createUserSettings();
		$refOptions    = new \ReflectionProperty($user_settings, 'base_options');
		$refOptions->setAccessible(true);
		$baseOptions = $refOptions->getValue($user_settings);

		$method = new \ReflectionMethod($user_settings, '_resolve_schema_bundle');
		$method->setAccessible(true);

		$this->logger->collected_logs = array();
		$method->invoke($user_settings, $baseOptions, array('intent' => 'save', 'user_id' => 123));

		$cachedLogs = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cached';
		});
		self::assertCount(1, $cachedLogs, 'Expected schema bundle to be cached on first resolve.');
		self::assertCount(0, $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cache_hit';
		}), 'Cache hit should not occur on first resolve.');

		$this->logger->collected_logs = array();
		$method->invoke($user_settings, $baseOptions, array('intent' => 'save', 'user_id' => 123));
		$cacheHits = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cache_hit';
		});
		self::assertCount(1, $cacheHits, 'Expected schema bundle cache hit on second resolve.');
		self::assertArrayHasKey('key', $cacheHits[0]['context'] ?? array());
		self::assertCount(0, $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cached';
		}), 'Bundle should not be recomputed after cache hit.');
	}

	public function test_save_settings_retains_previous_values_on_validation_failure(): void {
		$user_settings = $this->createUserSettings();

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array('profile_name' => 'Jane', 'profile_age' => 30));

		$user_settings->_save_settings(array('profile_age' => 10), array('user_id' => 123));

		$messages = $user_settings->take_messages();
		$this->assertArrayHasKey('profile_age', $messages);
		$this->assertNotEmpty($messages['profile_age']['warnings'] ?? array());
	}

	public function test_save_settings_auto_schema_attaches_component_validator(): void {
		$alias = 'fields.auto-validator-user';
		$this->registerComponentValidator($alias);

		$user_settings = $this->createUserSettings();
		$user_settings->collection('profile')
			->section('auto', 'Auto Section')
				->field('auto_field', 'Auto Field', $alias)
			->end_section()
		->end_collection();

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array('auto_field' => 'existing'));
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('update_user_option')->andReturn(true);

		$user_settings->_save_settings(array('auto_field' => 'invalid'), array('user_id' => 123));

		$messages = $user_settings->take_messages();
		self::assertArrayHasKey('auto_field', $messages);
		$warnings = (array) ($messages['auto_field']['warnings'] ?? array());
		self::assertNotEmpty($warnings);
		self::assertTrue(
			(array_reduce($warnings, static function (bool $memo, string $message): bool {
				return $memo || str_contains($message, 'User auto validator failed');
			}, false)),
			'Expected user auto component validator warning.'
		);

		self::assertSame(array('invalid'), UserSettingsBehaviorTest_AutoValidator::$calls);

		$matchedLogs = $this->logger->find_logs(static function (array $entry): bool {
			if (($entry['message'] ?? null) !== UserSettings::class . ': Component validator queue matched schema key') {
				return false;
			}
			$context = $entry['context'] ?? array();
			return ($context['normalized_key'] ?? null) === 'auto_field'
				&& ($context['validator_count'] ?? null)   === 1;
		});
		self::assertNotEmpty($matchedLogs, 'Expected validator queue matched log for auto_field.');

		$consumedLogs = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? null) === UserSettings::class . ': Component validator queue consumed';
		});
		self::assertNotEmpty($consumedLogs, 'Expected validator queue consumed log.');
	}

	public function test_render_payload_includes_structured_messages_after_validation_failure(): void {
		$capturedPayload = null;
		$callback        = static function (array $payload) use (&$capturedPayload): void {
			$capturedPayload = $payload;
		};

		$user_settings = $this->createUserSettings();
		$collection    = $user_settings->collection('profile');
		$collection->template($callback);
		$collection->section('basic', 'Basic Info')
			->field('profile_name', 'Profile Name', 'fields.input')
		->end_section();
		$collection->end_collection();

		// Ensure existing values are returned when render resolves stored options.
		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array('profile_name' => 'Jane', 'profile_age' => 30));
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());

		$user_settings->_save_settings(array('profile_age' => 10), array('user_id' => 123));

		$this->captureOutput(function () use ($user_settings): void {
			$user_settings->_render('profile', array('user_id' => 123));
		});

		self::assertIsArray($capturedPayload, 'Expected collection template callback to capture payload.');
		self::assertArrayHasKey('messages_by_field', $capturedPayload);
		self::assertArrayHasKey('profile_age', $capturedPayload['messages_by_field']);

		$fieldMessages = $capturedPayload['messages_by_field']['profile_age'];
		self::assertArrayHasKey('warnings', $fieldMessages);
		self::assertArrayHasKey('notices', $fieldMessages);
		self::assertNotEmpty($fieldMessages['warnings'], 'Expected validation warning for profile_age.');
		self::assertContains('profile_age must be >= 18', $fieldMessages['warnings']);
		self::assertSame(array(), $fieldMessages['notices'], 'Expected notices array to be empty.');

		$logs       = $this->logger->get_logs();
		$schemaLogs = array_values(array_filter($logs, static function (array $entry): bool {
			return $entry['message'] === 'user_settings.render.schema_trace';
		}));
		self::assertNotEmpty($schemaLogs, 'Expected schema trace log from render.');
		$latestSchemaLog = array_pop($schemaLogs);
		self::assertArrayHasKey('fields', $latestSchemaLog['context']);
		self::assertArrayHasKey('profile_name', $latestSchemaLog['context']['fields']);
		self::assertArrayHasKey('profile_age', $latestSchemaLog['context']['fields']);
		self::assertGreaterThanOrEqual(0, $latestSchemaLog['context']['fields']['profile_name']['validate_schema_count'] ?? -1);
	}

	public function test_schema_bundle_helper_caches_and_logs_hit_for_user_render_context(): void {
		$user_settings = $this->createUserSettings();
		$refOptions    = new \ReflectionProperty($user_settings, 'base_options');
		$refOptions->setAccessible(true);
		$baseOptions = $refOptions->getValue($user_settings);

		$method = new \ReflectionMethod($user_settings, '_resolve_schema_bundle');
		$method->setAccessible(true);

		$this->logger->collected_logs = array();
		$method->invoke($user_settings, $baseOptions, array('intent' => 'render', 'collection' => 'profile'));

		$cachedLogs = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cached';
		});
		self::assertCount(1, $cachedLogs, 'Expected schema bundle to be cached on first resolve.');
		self::assertCount(0, $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cache_hit';
		}), 'Cache hit should not occur on first resolve.');

		$this->logger->collected_logs = array();
		$method->invoke($user_settings, $baseOptions, array('intent' => 'render', 'collection' => 'profile'));

		$cacheHits = $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cache_hit';
		});
		self::assertCount(1, $cacheHits, 'Expected schema bundle cache hit on second resolve.');
		self::assertArrayHasKey('key', $cacheHits[0]['context'] ?? array());
		self::assertCount(0, $this->logger->find_logs(static function (array $entry): bool {
			return ($entry['message'] ?? '') === 'forms.schema_bundle.cached';
		}), 'Bundle should not be recomputed after cache hit.');
	}

	public function test_save_settings_merges_manifest_defaults_and_propagates_messages(): void {
		$executionOrder  = array();
		$capturedContext = null;

		$this->manifest->register('fields.merge-user', static function (array $context) use (&$capturedContext): ComponentRenderResult {
			$capturedContext = $context;
			return new ComponentRenderResult('<input type="text" />');
		});

		$this->injectBuilderFactory('fields.merge-user');
		$this->injectManifestDefaults('fields.merge-user', array(
			'sanitize' => static function (mixed $value) use (&$executionOrder): string {
				$executionOrder[] = 'manifest_sanitize';
				return (string) $value;
			},
			'validate' => static function (mixed $value, callable $emitWarning) use (&$executionOrder): bool {
				$executionOrder[] = 'manifest_validate';
				return true;
			},
			'context' => array('manifest_flag' => true),
		));

		$this->options->register_schema(array(
			'merge_field_user' => array(
				'sanitize' => static function (mixed $value) use (&$executionOrder): string {
					$executionOrder[] = 'schema_sanitize';
					return trim((string) $value);
				},
				'validate' => static function (mixed $value, callable $emitWarning) use (&$executionOrder): bool {
					$executionOrder[] = 'schema_validate';
					$emitWarning('User schema validator failed.');
					return false;
				},
			),
		));

		$user_settings = $this->createUserSettings();
		$user_settings->collection('profile')
			->section('merge', 'Merge Section')
				->field('merge_field_user', 'Merge Field', 'fields.merge-user')
			->end_section()
		->end_collection();

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array('merge_field_user' => 'stored'));
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());

		$user_settings->_save_settings(array('merge_field_user' => '  example  '), array('user_id' => 123));

		self::assertNotEmpty($executionOrder, 'Expected sanitizers/validators to execute.');
		$firstManifestSanitize = array_search('manifest_sanitize', $executionOrder, true);
		self::assertNotFalse($firstManifestSanitize, 'Expected manifest sanitizer to run.');
		$firstSchemaSanitize = array_search('schema_sanitize', $executionOrder, true);
		self::assertNotFalse($firstSchemaSanitize, 'Expected schema sanitizer to run.');
		self::assertLessThan(
			$firstSchemaSanitize,
			$firstManifestSanitize,
			'Manifest sanitizer must precede schema sanitizer. Execution order: ' . implode(', ', $executionOrder)
		);

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
		self::assertNotEmpty($manifestValidateBeforeFailure, 'Expected manifest validator before failing schema validator.');

		$this->captureOutput(function () use ($user_settings): void {
			$user_settings->_render('profile', array('user_id' => 123));
		});

		self::assertIsArray($capturedContext);
		$renderWarnings = $capturedContext['validation_warnings'] ?? array();
		if (is_string($renderWarnings)) {
			$renderWarnings = array($renderWarnings);
		}
		self::assertTrue(
			(array_reduce((array) $renderWarnings, static function (bool $found, string $message): bool {
				return $found || str_contains($message, 'User schema validator failed');
			}, false)),
			'Expected user schema validator warning to surface during render.'
		);

		$messages = $user_settings->take_messages();
		self::assertArrayHasKey('merge_field_user', $messages);
		$warnings = $messages['merge_field_user']['warnings'] ?? array();
		self::assertNotEmpty($warnings, 'Expected warnings for merge_field_user.');
		self::assertTrue(
			(array_reduce((array) $warnings, static function (bool $found, string $message): bool {
				return $found || str_contains($message, 'User schema validator failed');
			}, false)),
			'Expected user schema validator failure warning.'
		);
	}

	public function test_render_outputs_error_when_collection_missing(): void {
		$user_settings = $this->createUserSettings();

		$output = $this->captureOutput(function () use ($user_settings): void {
			$user_settings->_render('unknown');
		});

		$this->assertStringContainsString('notice-error', $output);
		$this->assertStringContainsString('Unknown settings collection', $output);
	}

	public function test_render_uses_custom_collection_template(): void {
		$user_settings = $this->createUserSettings();
		$called        = false;

		$user_settings->collection('profile')
			->template(static function (array $payload) use (&$called): void {
				$called = true;
				echo '<div class="profile-collection">' . htmlspecialchars((string) ($payload['id_slug'] ?? ''), ENT_QUOTES) . '</div>';
			})
			->section('basic', 'Basic')->end_section();

		$output = $this->captureOutput(function () use ($user_settings): void {
			$user_settings->_render('profile', array('user_id' => 123));
		});

		$this->assertTrue($called);
		$this->assertStringContainsString('profile-collection', $output);
	}

	public function test_render_field_with_assets_requires_shared_session_to_enqueue(): void {
		$this->manifest->register('fields.profile-asset', function (array $context): ComponentRenderResult {
			$this->logger->debug('user_settings.test.profile_asset.render', array(
				'field_id'     => $context['field_id'] ?? null,
				'label'        => $context['label']    ?? null,
				'context_keys' => array_keys($context),
			));
			$script = ScriptDefinition::from_array(array(
				'handle' => 'profile-asset-script',
				'src'    => 'https://example.com/profile.js',
			));

			return new ComponentRenderResult(
				'<div class="profile-asset">' . htmlspecialchars((string) ($context['field_id'] ?? ''), ENT_QUOTES) . '</div>',
				script: $script,
				component_type: 'input'
			);
		});
		$this->injectBuilderFactory('fields.profile-asset');

		// Ensure root templates are registered for the render pipeline used in this test.
		$this->manifest->register('root-wrapper', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				'<div class="root-wrapper">' . ($context['inner_html'] ?? '') . '</div>',
				component_type: 'layout_wrapper'
			);
		});
		$this->manifest->register('user.root-wrapper', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				'<div class="user-root-wrapper">' . ($context['inner_html'] ?? '') . '</div>',
				component_type: 'layout_wrapper'
			);
		});

		$user_settings = $this->createUserSettings();
		$user_settings->collection('profile')->section('basic', 'Basic Info')
			->field('profile_asset', 'Profile Asset', 'fields.profile-asset')
		->end_section();

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('get_user_option')->andReturn(array());
		WP_Mock::userFunction('update_user_option')->andReturn(true);

		WP_Mock::userFunction('wp_register_script')
			->times(2)
			->with(
				'profile-asset-script',
				'https://example.com/profile.js',
				array(),
				false,
				true
			)
			->andReturn(true);

		$output = $this->captureOutput(function () use ($user_settings): void {
			$user_settings->_render('profile', array('user_id' => 123));
		});

		$session = $user_settings->get_form_session();
		self::assertInstanceOf(FormsServiceSession::class, $session, 'Expected UserSettings to have an active form session.');
		$this->logger->debug('user_settings.test.session_assets', array(
			'scripts' => array_keys($session->assets()->scripts()),
			'styles'  => array_keys($session->assets()->styles()),
		));
		$fieldRenderLogs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message'] === 'forms.default_field.render' && ($entry['context']['field_id'] ?? null) === 'profile_asset';
		});
		$this->logger->debug('user_settings.test.field_render_logs', array('count' => count($fieldRenderLogs)));
		self::assertNotEmpty($fieldRenderLogs, 'Expected profile_asset field render log.');
		$componentRenderLogs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message'] === 'user_settings.test.profile_asset.render';
		});
		self::assertNotEmpty($componentRenderLogs, 'Expected profile asset component render log.');
		self::assertArrayHasKey(
			'profile-asset-script',
			$session->assets()->scripts(),
			'Expected script asset to be captured by shared session.'
		);

		$session->enqueue_assets();
	}

	public function test_handle_custom_update_delegates_to_collection_handler(): void {
		$user_settings = $this->createUserSettings();

		$collectionBuilder = $user_settings->collection('profile');
		$collectionBuilder->heading('Profile Heading');
		$collectionBuilder->end_collection();

		$logs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message']                                 === 'settings.builder.collection.updated'
				&& ($entry['context']['container_id'] ?? null)          === 'profile'
				&& ($entry['context']['collection']['heading'] ?? null) === 'Profile Heading';
		});
		self::assertNotEmpty($logs, 'Expected collection updated log for profile heading.');

		$commitLogs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message']                        === 'settings.builder.collection.committed'
				&& ($entry['context']['container_id'] ?? null) === 'profile';
		});
		self::assertNotEmpty($commitLogs, 'Expected collection committed log for profile.');
	}

	public function test_resolve_context_throws_when_user_id_missing(): void {
		$user_settings = $this->createUserSettings();

		unset($GLOBALS['profileuser']);
		WP_Mock::userFunction('get_current_user_id')->andReturn(0);

		try {
			$user_settings->resolve_options(array('user_id' => 0));
			self::fail('Expected InvalidArgumentException when user_id cannot be resolved.');
		} catch (\InvalidArgumentException $exception) {
			self::assertSame('UserSettings::resolve_options requires a valid user_id.', $exception->getMessage());
		}

		$warningLogs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message'] === 'UserSettings::resolve_options requires a valid user_id.';
		});
		self::assertNotEmpty($warningLogs, 'Expected warning log when user_id resolution fails.');
	}

	public function test_resolve_context_accepts_explicit_option_storage(): void {
		$options  = new RegisterOptions('behavior_user_options', StorageContext::forUser(123, 'option', true), false, $this->logger);
		$settings = new UserSettings($options, $this->manifest, null, $this->logger);

		$settings->resolve_options(array('user_id' => 123, 'storage' => 'option', 'global' => true));

		$logs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message']                        === 'settings.builder.context.resolved'
				&& ($entry['context']['user_id'] ?? null)      === 123
				&& ($entry['context']['storage_kind'] ?? null) === 'option'
				&& ($entry['context']['global'] ?? null)       === true;
		});
		self::assertNotEmpty($logs, 'Expected context resolved log for explicit option storage.');
	}

	public function test_save_settings_uses_commit_merge_for_option_storage(): void {
		$options = new RegisterOptions('behavior_user_options', StorageContext::forUser(123, 'option', true), false, $this->logger);
		$options->register_schema(array(
		    'profile_toggle' => array(
		        'default'  => false,
		        'sanitize' => static fn ($value): bool => (bool) $value,
				'validate'       => static fn ($value): bool => (bool) $value,
		    ),
		));

		$user_settings = new UserSettings($options, $this->manifest, null, $this->logger);
		$user_settings->resolve_options(array('user_id' => 123, 'storage' => 'option', 'global' => true));

		$scopeContext = null;
		$scopeInvoked = false;
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist/scope/user')
			->with(Functions::type('bool'), Functions::type('array'))
			->reply(static function ($allowed, $ctx) use (&$scopeInvoked, &$scopeContext) {
				$scopeInvoked = true;
				$scopeContext = $ctx;
				return $allowed;
			});

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('update_user_option')->andReturn(true);

		$user_settings->_save_settings(array('profile_toggle' => true), array('user_id' => 123, 'storage' => 'option', 'global' => true));

		$baseApplyLogs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message']                 === 'wp_wrappers.apply_filters'
			    && ($entry['context']['hook'] ?? '') === 'ran/plugin_lib/options/allow_persist';
		});
		self::assertNotEmpty($baseApplyLogs, 'Expected general allow-persist filter to be logged.');
		$finalBaseLog = \array_pop($baseApplyLogs);
		self::assertSame('save_all', $finalBaseLog['context']['args'][0]['op'] ?? null);
		self::assertSame('user', $finalBaseLog['context']['args'][0]['scope'] ?? null);
		if ($scopeInvoked) {
			self::assertSame('save_all', $scopeContext['op'] ?? null);
		}

		$applyLogs = $this->logger->find_logs(static function (array $entry): bool {
			return $entry['message']                 === 'wp_wrappers.apply_filters'
			    && ($entry['context']['hook'] ?? '') === 'ran/plugin_lib/options/allow_persist/scope/user';
		});
		self::assertNotEmpty($applyLogs, 'Expected allow-persist scoped filter application to be logged.');
		$finalApplyLog = \array_pop($applyLogs);
		self::assertSame('save_all', $finalApplyLog['context']['args'][0]['op'] ?? null);
		self::assertTrue($finalApplyLog['context']['result']);

		$messages = $user_settings->take_messages();
		$this->assertSame(array(), $messages);
	}

	private function createUserSettings(): UserSettings {
		$this->registerTemplateStubs();
		return new UserSettings($this->options, $this->manifest, null, $this->logger);
	}

	private function registerTemplateStubs(): void {
		$loader = $this->manifest->get_component_loader();
		$loader->register('layout.zone.section-wrapper', 'admin/sections/test-section.php');
		$loader->register('section-wrapper', 'admin/sections/test-section.php');
		$loader->register('field-wrapper', 'admin/field-wrapper-simple.php');
		$loader->register('shared.field-wrapper', 'admin/field-wrapper-simple.php');
		$this->manifest->register('field-wrapper', static function (array $context): ComponentRenderResult {
			$componentHtml = (string) ($context['inner_html'] ?? '');
			return new ComponentRenderResult(
				'<div class="test-field-wrapper">' . $componentHtml . '</div>',
				component_type: ComponentType::LayoutWrapper
			);
		});
		$loader->register('fields.input', 'admin/fields/test-field.php');
		$loader->register('fields.merge-user', 'admin/fields/test-field.php');
		$loader->register('user.root-wrapper', 'admin/pages/default-page.php');
		$loader->register('root-wrapper', 'admin/pages/default-page.php');
		$this->manifest->register('user.root-wrapper', static function (array $context): ComponentRenderResult {
			$content = (string) ($context['inner_html'] ?? '');
			return new ComponentRenderResult(
				'<div class="user-root-wrapper">' . $content . '</div>',
				component_type: ComponentType::LayoutWrapper
			);
		});
		$this->manifest->register('root-wrapper', static function (array $context): ComponentRenderResult {
			$content = (string) ($context['inner_html'] ?? '');
			return new ComponentRenderResult(
				'<div class="root-wrapper">' . $content . '</div>',
				component_type: ComponentType::LayoutWrapper
			);
		});
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
		UserSettingsBehaviorTest_AutoValidator::reset();

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

		$this->setManifestValidatorClass($alias, UserSettingsBehaviorTest_AutoValidator::class);
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
	 * Uses ComponentBuilderInputBase as a generic builder.
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
}
