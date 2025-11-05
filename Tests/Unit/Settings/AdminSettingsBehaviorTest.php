<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Functions;
use WP_Mock;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\EnqueueAccessory\ScriptDefinition;
use Mockery;
use InvalidArgumentException;

/**
 * @covers \Ran\PluginLib\Settings\AdminSettings
 */
final class AdminSettingsBehaviorTest extends PluginLibTestCase {
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

		$loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates');
		$loader->register('section', 'admin/sections/test-section.php');
		$loader->register('field-wrapper', 'admin/field-wrapper.php');
		$loader->register('shared.field-wrapper', 'admin/field-wrapper.php');
		$loader->register('section-wrapper', 'admin/section-wrapper.php');
		$loader->register('fields.input', 'admin/fields/test-field.php');
		$loader->register('admin.pages.behavior-page', 'admin/pages/test-page.php');
		$loader->register('admin.root-wrapper', 'admin/pages/default-page.php');
		$loader->register('root-wrapper', 'admin/pages/default-page.php');

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

		$this->settings = new AdminSettings($this->options, $this->manifest, $this->logger);
	}

	public function test_register_setting_uses_settings_api(): void {
		$logStart = \count($this->logger->collected_logs);

		$this->settings->_register_setting();

		$logEntries   = \array_slice($this->logger->collected_logs, $logStart);
		$registerLogs = \array_values(\array_filter($logEntries, static function (array $entry): bool {
			return $entry['message'] === 'admin_settings.register_setting';
		}));
		self::assertNotEmpty($registerLogs);
		$log = \array_pop($registerLogs);
		self::assertSame('behavior_admin_options_group', $log['context']['group'] ?? null);
		self::assertSame('behavior_admin_options', $log['context']['option'] ?? null);
	}

	public function test_sanitize_returns_previous_payload_on_validation_failure(): void {
		$this->setOptionValues(array('valid_field' => 'existing', 'integer_field' => 0));

		$result = $this->settings->_sanitize(array('valid_field' => array('not a string')));

		$this->assertSame(array('valid_field' => 'existing', 'integer_field' => 0), $result);
		$messages = $this->settings->take_messages();
		$this->assertArrayHasKey('valid_field', $messages);
		$this->assertNotEmpty($messages['valid_field']['warnings'] ?? array());
	}

	public function test_sanitize_returns_sanitized_payload_on_success(): void {
		$this->setOptionValues(array());

		$result = $this->settings->_sanitize(array('integer_field' => '5'));

		$this->assertSame(array('valid_field' => '', 'integer_field' => 5), $result);
		$messages = $this->settings->take_messages();
		$this->assertSame(array(), $messages);
	}

	public function test_render_unknown_page_falls_back_to_notice(): void {
		ob_start();
		$this->settings->render('missing-page');
		$output = ob_get_clean();

		$this->assertStringContainsString('Unknown settings page', $output);
	}

	public function test_render_happy_path_enqueues_assets(): void {
		$this->settings->menu_group('behavior-group')
		    ->page('behavior-page')
		        ->heading('Behavior Page')
		        ->section('behavior-section', 'Behavior Section')
		            ->field('valid_field', 'Valid Field', 'fields.input')
		        ->end_section()
		    ->end_page()
		->end_menu_group();

		$this->setOptionValues(array('valid_field' => 'value'));

		ob_start();
		$this->settings->render('behavior-page');
		$output = ob_get_clean();

		$this->assertStringContainsString('<section', $output);
	}

	public function test_render_outputs_before_after_hooks_for_sections_groups_and_fields(): void {
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
		                ))
		            ->end_group()
		        ->end_section()
		    ->end_page()
		->end_menu_group();

		$this->setOptionValues(array(
			'valid_field'   => 'value-one',
			'integer_field' => 5,
		));

		ob_start();
		$this->settings->render('hooks-page');
		$output = ob_get_clean();

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
		->end_menu_group();

		$this->expectOptionReturn(array());

		ob_start();
		$this->settings->render('behavior-page');
		$output = ob_get_clean();

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

		ob_start();
		$this->settings->render('tools-page');
		$output = ob_get_clean();

		$this->assertStringContainsString('Tools Heading', $output);
		$messages = $this->settings->take_messages();
		$this->assertSame(array(), $messages);
	}

	public function test_sanitize_network_scope_uses_network_context(): void {
		WP_Mock::userFunction('is_network_admin')->andReturn(true);
		WP_Mock::userFunction('get_site_option')
		    ->with('behavior_admin_options', array())
		    ->andReturn(array('valid_field' => '', 'integer_field' => 0));

		$result = $this->settings->_sanitize(array('integer_field' => '10'));

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

		$logEntries = \array_slice($this->logger->collected_logs, $logStart);
		$updateLogs = \array_values(\array_filter($logEntries, static function (array $entry): bool {
			return $entry['message'] === 'settings.builder.menu_group.updated';
		}));
		$commitLogs = \array_values(\array_filter($logEntries, static function (array $entry): bool {
			return $entry['message'] === 'settings.builder.menu_group.committed';
		}));

		self::assertNotEmpty($updateLogs);
		self::assertNotEmpty($commitLogs);

		$update = \array_pop($updateLogs);
		self::assertSame('group-one', $update['context']['container_id'] ?? null);
		self::assertSame('Group One Title', $update['context']['menu_title'] ?? null);
		self::assertSame('manage_options', $update['context']['capability'] ?? null);

		$commit = \array_pop($commitLogs);
		self::assertSame('group-one', $commit['context']['container_id'] ?? null);
		self::assertIsArray($commit['context']['pages'] ?? null);
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

		$logEntries = \array_slice($this->logger->collected_logs, $logStart);
		$pageLogs   = \array_values(\array_filter($logEntries, static function (array $entry): bool {
			return $entry['message'] === 'settings.builder.page.updated';
		}));
		self::assertNotEmpty($pageLogs);
		$log = \array_pop($pageLogs);
		self::assertSame('context-page', $log['context']['page_slug'] ?? null);
		self::assertSame('context-group', $log['context']['group_id'] ?? null);
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
		$this->manifest->register('section', static function (array $context): ComponentRenderResult {
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
			$markup = '<div class="field-wrapper">' . ($context['component_html'] ?? '') . '</div>';
			return new ComponentRenderResult(
				$markup,
				component_type: 'field_wrapper'
			);
		});

		$this->manifest->register('fields.input', static function (array $context): ComponentRenderResult {
			$fieldId = htmlspecialchars($context['field_id'] ?? 'field', ENT_QUOTES);
			$value   = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES);
			return new ComponentRenderResult(
				'<input name="' . $fieldId . '" value="' . $value . '" />',
				component_type: 'form_field'
			);
		});

		$this->manifest->register('admin.pages.behavior-page', static function (array $context): ComponentRenderResult {
			$content = $context['content'] ?? '';
			return new ComponentRenderResult(
				'<div class="admin-page">' . $content . '</div>',
				component_type: 'layout_wrapper'
			);
		});

		$this->manifest->register('submit-controls-wrapper', static function (array $context): ComponentRenderResult {
			$zone    = htmlspecialchars((string) ($context['zone_id'] ?? ''), ENT_QUOTES);
			$content = $context['content'] ?? '';
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
				component_type: 'form_field'
			);
		});
	}

	public function test_render_default_submit_controls_are_seeded(): void {
		$this->settings->menu_group('submit-group')
		    ->page('submit-page')
		        ->heading('Submit Page')
		        ->section('submit-section', 'Submit Section')
		            ->field('valid_field', 'Valid Field', 'fields.input')
		        ->end_section()
		    ->end_page()
		->end_menu_group();

		$this->expectOptionReturn(array('valid_field' => 'seed'));

		ob_start();
		$this->settings->render('submit-page');
		$output = ob_get_clean();

		self::assertStringContainsString('submit-wrapper', $output, 'Expected submit controls wrapper.');
		self::assertStringContainsString('Save Changes', $output, 'Expected default submit button label.');
	}

	public function test_render_custom_submit_controls_override_default(): void {
		$this->settings->menu_group('custom-submit-group')
			->page('custom-submit-page')
				->section('custom-submit-section', 'Custom Submit Section')
					->field('valid_field', 'Valid Field', 'fields.input')
				->end_section()
				->submit_controls()
					->button('primary', 'Publish Settings');

		$this->expectOptionReturn(array('valid_field' => 'value'));

		ob_start();
		$this->settings->render('custom-submit-page');
		$output = ob_get_clean();

		self::assertStringContainsString('submit-wrapper', $output, 'Expected submit controls wrapper.');
		self::assertStringContainsString('Publish Settings', $output, 'Expected custom submit button label.');
		self::assertStringNotContainsString('Save Changes', $output, 'Default submit label should be cleared once custom controls provided.');
	}

	public function test_render_field_with_assets_requires_shared_session_to_enqueue(): void {
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
				component_type: 'form_field'
			);
		});

		$menuBuilder = $this->settings->menu_group('asset-group');
		$pageBuilder = $menuBuilder->page('asset-page');

		$pageBuilder
			->section('asset-section', 'Asset Section')
				->field('asset_field', 'Asset Field', 'fields.asset-field')
			->end_section();

		$pageBuilder
			->submit_controls()
				->field('asset_submit', 'Save Changes', 'fields.asset-field')
			->end_submit_controls();

		$pageBuilder->end_page();
		$menuBuilder->end_menu_group();

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

		ob_start();
		$this->settings->render('asset-page');
		ob_end_clean();

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

	public function test_render_field_component_wraps_render_exceptions(): void {
		$manifest = Mockery::mock(ComponentManifest::class);
		$manifest->shouldReceive('render')->andThrow(new \RuntimeException('render failed'));

		$service  = new FormsService($manifest, $this->logger);
		$renderer = new FormElementRenderer($manifest, $service, $this->manifest->get_component_loader(), $this->logger);

		$field   = array('field_id' => 'field', 'component' => 'fields.text', 'label' => 'Label');
		$context = $renderer->prepare_field_context($field, array(), array());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to render field 'field' with component 'fields.text'");

		$renderer->render_field_with_wrapper('fields.text', 'field', 'Label', $context, array(), 'wrappers/simple-wrapper', $this->settings->get_form_session());
		$this->expectLog('error', 'FormElementRenderer: Field with wrapper rendering failed');
	}
}
