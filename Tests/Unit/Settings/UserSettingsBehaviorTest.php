<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Functions;
use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Forms\FormsServiceSession;

/**
 * @covers \Ran\PluginLib\Settings\UserSettings
 */
final class UserSettingsBehaviorTest extends PluginLibTestCase {
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
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);

		$this->manifest = new ComponentManifest(
			new ComponentLoader(__DIR__ . '/../../fixtures/templates'),
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
		        'validate' => array(static function ($value, callable $emitWarning): bool {
		        	if (!is_string($value)) {
		        		$emitWarning('profile_name must be a string');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'profile_age' => array(
		        'default'  => 18,
		        'sanitize' => array(static fn ($value): int => max(0, (int) $value)),
		        'validate' => array(static function ($value, callable $emitWarning): bool {
		        	if (!is_int($value) || $value < 18) {
		        		$emitWarning('profile_age must be >= 18');
		        		return false;
		        	}
		        	return true;
		        }),
            ),
		));
	}

	public function test_constructor_rejects_non_user_scope(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('UserSettings requires user context');

		new UserSettings(
			new RegisterOptions('invalid_scope', StorageContext::forSite(), true, $this->logger),
			$this->manifest,
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

		$user_settings->save_settings(array('profile_name' => 'John'), array('user_id' => 456));

		self::assertSame(array(), $user_settings->take_messages());
	}

	public function test_save_settings_persists_when_validation_passes(): void {
		$user_settings = $this->createUserSettings();

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('update_option')->andReturn(true);

		$user_settings->save_settings(array('profile_name' => 'Jane', 'profile_age' => 30), array('user_id' => 123));

		$messages = $user_settings->take_messages();
		$this->assertSame(array(), $messages);
	}

	public function test_save_settings_retains_previous_values_on_validation_failure(): void {
		$user_settings = $this->createUserSettings();

		WP_Mock::userFunction('current_user_can')->withAnyArgs()->andReturn(true);
		WP_Mock::userFunction('get_option')->andReturn(array('profile_name' => 'Jane', 'profile_age' => 30));

		$user_settings->save_settings(array('profile_age' => 10), array('user_id' => 123));

		$messages = $user_settings->take_messages();
		$this->assertArrayHasKey('profile_age', $messages);
		$this->assertNotEmpty($messages['profile_age']['warnings'] ?? array());
	}

	public function test_render_outputs_default_template_when_collection_missing(): void {
		$user_settings = $this->createUserSettings();

		ob_start();
		$user_settings->render('unknown');
		$output = ob_get_clean();

		$this->assertSame('', $output);
	}

	public function test_render_uses_custom_collection_template(): void {
		$user_settings = $this->createUserSettings();
		$called        = false;

		$user_settings->collection('profile', static function (array $payload) use (&$called): void {
			$called = true;
			echo '<div class="profile-collection">' . htmlspecialchars((string) ($payload['id_slug'] ?? ''), ENT_QUOTES) . '</div>';
		})->section('basic', 'Basic')->end_section();

		ob_start();
		$user_settings->render('profile', array('user_id' => 123));
		$output = ob_get_clean();

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
				component_type: 'form_field'
			);
		});

		// Ensure root templates are registered for the render pipeline used in this test.
		$this->manifest->register('root-wrapper', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				'<div class="root-wrapper">' . ($context['content'] ?? '') . '</div>',
				component_type: 'layout_wrapper'
			);
		});
		$this->manifest->register('user.root-wrapper', static function (array $context): ComponentRenderResult {
			return new ComponentRenderResult(
				'<div class="user-root-wrapper">' . ($context['content'] ?? '') . '</div>',
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

		ob_start();
		$user_settings->render('profile', array('user_id' => 123));
		ob_end_clean();

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
		$settings = new UserSettings($options, $this->manifest, $this->logger);

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

		$user_settings = new UserSettings($options, $this->manifest, $this->logger);
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

		$user_settings->save_settings(array('profile_toggle' => true), array('user_id' => 123, 'storage' => 'option', 'global' => true));

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
		return new UserSettings($this->options, $this->manifest, $this->logger);
	}

	private function registerTemplateStubs(): void {
		$loader = $this->manifest->get_component_loader();
		$loader->register('section', 'admin/sections/test-section.php');
		$loader->register('section-wrapper', 'admin/sections/test-section.php');
		$loader->register('field-wrapper', 'admin/field-wrapper.php');
		$loader->register('fields.input', 'admin/fields/test-field.php');
		$loader->register('user.root-wrapper', 'admin/pages/default-page.php');
		$loader->register('root-wrapper', 'admin/pages/default-page.php');
	}
}
