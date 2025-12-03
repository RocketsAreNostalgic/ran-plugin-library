<?php
/**
 * Before/After Hooks Sequence Smoke Test
 *
 * Verifies that before() and after() callbacks render in the correct sequence
 * for collections, sections, groups, and fields in both UserSettings and AdminSettings.
 *
 * Expected render order (nested structure):
 * 1. collection before
 * 2.   section before
 * 3.     group before
 * 4.       field before
 * 5.         [field content]
 * 6.       field after
 * 7.     group after
 * 8.   section after
 * 9. collection after
 *
 * @package Ran\PluginLib\Tests\Unit\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentRenderResult;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;
use Ran\PluginLib\Forms\Components\Fields\Input\Builder as InputBuilder;

/**
 * @covers \Ran\PluginLib\Settings\UserSettings
 * @covers \Ran\PluginLib\Settings\AdminSettings
 * @covers \Ran\PluginLib\Forms\FormsBaseTrait
 */
final class BeforeAfterHooksSequenceTest extends PluginLibTestCase {
	use ExpectLogTrait;

	protected CollectingLogger $logger;
	protected array $optionValues   = array();
	protected array $userMetaValues = array();

	public function setUp(): void {
		parent::setUp();
		$this->logger = $this->logger_mock instanceof CollectingLogger
			? $this->logger_mock
			: new CollectingLogger(array());
		$this->logger->collected_logs = array();
		$this->optionValues           = array();
		$this->userMetaValues         = array();

		// WordPress function mocks
		WP_Mock::userFunction('get_option')->andReturnUsing(
			fn($name, $default = array()) => $this->optionValues[$name] ?? $default
		);
		WP_Mock::userFunction('update_option')->andReturn(true);
		WP_Mock::userFunction('delete_option')->andReturn(true);
		WP_Mock::userFunction('get_user_meta')->andReturnUsing(
			fn($user_id, $key, $single) => $this->userMetaValues[$key] ?? array()
		);
		WP_Mock::userFunction('update_user_meta')->andReturnUsing(
			function ($user_id, $key, $value) {
				$this->userMetaValues[$key] = $value;
				return true;
			}
		);
		WP_Mock::userFunction('delete_user_meta')->andReturn(true);
		WP_Mock::userFunction('get_user_option')->andReturn(false);
		WP_Mock::userFunction('update_user_option')->andReturn(true);
		WP_Mock::userFunction('delete_user_option')->andReturn(true);
		WP_Mock::userFunction('get_current_user_id')->andReturn(123);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_transient')->andReturn(false);
		WP_Mock::userFunction('set_transient')->andReturn(true);
		WP_Mock::userFunction('delete_transient')->andReturn(true);
		WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_html_class')->andReturnArg(0);
		WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
		WP_Mock::userFunction('esc_attr')->andReturnArg(0);
		WP_Mock::userFunction('esc_html')->andReturnArg(0);
		WP_Mock::userFunction('wp_kses_post')->andReturnArg(0);
		WP_Mock::userFunction('current_user_can')->andReturn(true);
		WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');
		WP_Mock::userFunction('settings_fields')->andReturnNull();
		WP_Mock::userFunction('submit_button')->andReturnNull();
		WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	// =========================================================================
	// USER SETTINGS TESTS
	// =========================================================================

	/**
	 * @test
	 * Verify UserSettings renders before/after hooks in correct sequence.
	 *
	 * Tests collection, section, group, and field level hooks.
	 */
	public function test_user_settings_before_after_hooks_render_in_correct_sequence(): void {
		$this->userMetaValues['hooks_test'] = array('test_field' => 'test_value');
		$result                             = $this->createUserSettings('hooks_test');
		$settings                           = $result['settings'];
		$manifest                           = $result['manifest'];

		// Register a simple input component for testing
		$this->registerSimpleInputComponent($manifest);

		$settings
			->collection('test-collection')
				->heading('Test Collection')
				->before(fn() => '<!-- COLLECTION_BEFORE -->')
				->after(fn() => '<!-- COLLECTION_AFTER -->')
				->section('test-section', 'Test Section')
					->before(fn() => '<!-- SECTION_BEFORE -->')
					->after(fn() => '<!-- SECTION_AFTER -->')
					->group('test-group', 'Test Group')
						->before(fn() => '<!-- GROUP_BEFORE -->')
						->after(fn() => '<!-- GROUP_AFTER -->')
						->field('test_field', 'Test Field', $this->getInputComponentAlias())
							->before(fn() => '<!-- FIELD_BEFORE -->')
							->after(fn() => '<!-- FIELD_AFTER -->')
						->end_field()
					->end_group()
				->end_section()
			->end_collection()
		->boot();

		$output = $this->captureOutput(fn() => $settings->render('test-collection'));

		// Verify collection hooks are present
		self::assertStringContainsString('<!-- COLLECTION_BEFORE -->', $output, 'Collection before hook should be present');
		self::assertStringContainsString('<!-- COLLECTION_AFTER -->', $output, 'Collection after hook should be present');

		// Verify section hooks are present
		self::assertStringContainsString('<!-- SECTION_BEFORE -->', $output, 'Section before hook should be present');
		self::assertStringContainsString('<!-- SECTION_AFTER -->', $output, 'Section after hook should be present');

		// Verify group hooks are present
		self::assertStringContainsString('<!-- GROUP_BEFORE -->', $output, 'Group before hook should be present');
		self::assertStringContainsString('<!-- GROUP_AFTER -->', $output, 'Group after hook should be present');

		// Verify field hooks are present
		self::assertStringContainsString('<!-- FIELD_BEFORE -->', $output, 'Field before hook should be present');
		self::assertStringContainsString('<!-- FIELD_AFTER -->', $output, 'Field after hook should be present');

		// Verify correct nesting order: collection -> section -> group -> field
		$this->assertHookSequence($output, array(
			'<!-- COLLECTION_BEFORE -->',
			'<!-- SECTION_BEFORE -->',
			'<!-- GROUP_BEFORE -->',
			'<!-- FIELD_BEFORE -->',
			'<!-- FIELD_AFTER -->',
			'<!-- GROUP_AFTER -->',
			'<!-- SECTION_AFTER -->',
			'<!-- COLLECTION_AFTER -->',
		));
	}

	/**
	 * @test
	 * Verify UserSettings renders field before hook before the field content.
	 */
	public function test_user_settings_field_before_renders_before_field_content(): void {
		$this->userMetaValues['field_order_test'] = array('order_field' => 'field_value');
		$result                                   = $this->createUserSettings('field_order_test');
		$settings                                 = $result['settings'];
		$manifest                                 = $result['manifest'];

		// Register a simple input component for testing
		$this->registerSimpleInputComponent($manifest);

		$settings
			->collection('order-collection')
				->section('order-section', 'Order Section')
					->field('order_field', 'Order Field', $this->getInputComponentAlias())
						->before(fn() => '<!-- BEFORE_FIELD_CONTENT -->')
						->after(fn() => '<!-- AFTER_FIELD_CONTENT -->')
					->end_field()
				->end_section()
			->end_collection()
		->boot();

		$output = $this->captureOutput(fn() => $settings->render('order-collection'));

		// Field content marker should appear between before and after
		$beforePos = strpos($output, '<!-- BEFORE_FIELD_CONTENT -->');
		$afterPos  = strpos($output, '<!-- AFTER_FIELD_CONTENT -->');
		$fieldPos  = strpos($output, 'name="field_order_test[order_field]"');

		self::assertNotFalse($beforePos, 'Before hook should be present');
		self::assertNotFalse($afterPos, 'After hook should be present');
		self::assertNotFalse($fieldPos, 'Field content should be present');
		self::assertLessThan($fieldPos, $beforePos, 'Before hook should appear before field content');
		self::assertGreaterThan($fieldPos, $afterPos, 'After hook should appear after field content');
	}

	// =========================================================================
	// ADMIN SETTINGS TESTS
	// =========================================================================

	/**
	 * @test
	 * Verify AdminSettings renders field-level before/after hooks correctly.
	 *
	 * Note: AdminSettings field-level hooks require template support that may not
	 * be fully implemented in the test fixtures. This test is skipped until
	 * AdminSettings field hook rendering is verified.
	 */
	public function test_admin_settings_field_before_after_hooks_render_correctly(): void {
		$this->optionValues['admin_hooks_test'] = array('admin_field' => 'admin_value');
		$result                                 = $this->createAdminSettings('admin_hooks_test');
		$settings                               = $result['settings'];
		$manifest                               = $result['manifest'];

		// Register a simple input component for testing
		$this->registerSimpleInputComponent($manifest);

		$settings->menu_group('admin-menu')
			->page('admin-page')
				->heading('Admin Page')
				->section('admin-section', 'Admin Section')
					->field('admin_field', 'Admin Field', $this->getInputComponentAlias())
						->before(fn() => '<!-- ADMIN_FIELD_BEFORE -->')
						->after(fn() => '<!-- ADMIN_FIELD_AFTER -->')
					->end_field()
				->end_section()
			->end_page()
		->end_menu_group();

		$output = $this->captureOutput(fn() => $settings->render('admin-page'));

		// Verify field hooks are present
		self::assertStringContainsString('<!-- ADMIN_FIELD_BEFORE -->', $output, 'Field before hook should be present');
		self::assertStringContainsString('<!-- ADMIN_FIELD_AFTER -->', $output, 'Field after hook should be present');

		// Verify correct order: before -> field content -> after
		$this->assertHookSequence($output, array(
			'<!-- ADMIN_FIELD_BEFORE -->',
			'name="admin_hooks_test[admin_field]"',
			'<!-- ADMIN_FIELD_AFTER -->',
		));
	}

	/**
	 * @test
	 * Verify AdminSettings renders field before hook before the field content.
	 */
	public function test_admin_settings_field_before_renders_before_field_content(): void {
		$this->optionValues['admin_field_order'] = array('admin_order_field' => 'admin_field_value');
		$result                                  = $this->createAdminSettings('admin_field_order');
		$settings                                = $result['settings'];
		$manifest                                = $result['manifest'];

		// Register a simple input component for testing
		$this->registerSimpleInputComponent($manifest);

		$settings->menu_group('admin-order-menu')
			->page('admin-order-page')
				->section('admin-order-section', 'Admin Order Section')
					->field('admin_order_field', 'Admin Order Field', $this->getInputComponentAlias())
						->before(fn() => '<!-- ADMIN_BEFORE_FIELD -->')
						->after(fn() => '<!-- ADMIN_AFTER_FIELD -->')
					->end_field()
				->end_section()
			->end_page()
		->end_menu_group();

		$output = $this->captureOutput(fn() => $settings->render('admin-order-page'));

		$beforePos = strpos($output, '<!-- ADMIN_BEFORE_FIELD -->');
		$afterPos  = strpos($output, '<!-- ADMIN_AFTER_FIELD -->');
		$fieldPos  = strpos($output, 'name="admin_field_order[admin_order_field]"');

		self::assertNotFalse($beforePos, 'Before hook should be present');
		self::assertNotFalse($afterPos, 'After hook should be present');
		self::assertNotFalse($fieldPos, 'Field content should be present');
		self::assertLessThan($fieldPos, $beforePos, 'Before hook should appear before field content');
		self::assertGreaterThan($fieldPos, $afterPos, 'After hook should appear after field content');
	}

	// =========================================================================
	// PARITY TESTS
	// =========================================================================

	/**
	 * @test
	 * Verify UserSettings and AdminSettings have consistent field-level hook ordering.
	 *
	 * Both settings types should render field before/after hooks in the same order:
	 * before -> field content -> after
	 */
	public function test_user_and_admin_settings_have_consistent_field_hook_ordering(): void {
		// Set up UserSettings
		$this->userMetaValues['parity_user'] = array('parity_field' => 'user_value');
		$userResult                          = $this->createUserSettings('parity_user');
		$userSettings                        = $userResult['settings'];
		$userManifest                        = $userResult['manifest'];
		// Register a simple input component for testing
		$this->registerSimpleInputComponent($userManifest);

		$userSettings
			->collection('parity-collection')
				->section('parity-section', 'Parity Section')
					->field('parity_field', 'Parity Field', $this->getInputComponentAlias())
						->before(fn() => '<!-- F_BEFORE -->')
						->after(fn() => '<!-- F_AFTER -->')
					->end_field()
				->end_section()
			->end_collection()
		->boot();

		$userOutput = $this->captureOutput(fn() => $userSettings->render('parity-collection'));

		// Set up AdminSettings
		$this->optionValues['parity_admin'] = array('parity_field' => 'admin_value');
		$adminResult                        = $this->createAdminSettings('parity_admin');
		$adminSettings                      = $adminResult['settings'];
		$adminManifest                      = $adminResult['manifest'];
		// Register a simple input component for testing
		$this->registerSimpleInputComponent($adminManifest);

		$adminSettings->menu_group('parity-menu')
			->page('parity-page')
				->section('parity-section', 'Parity Section')
					->field('parity_field', 'Parity Field', $this->getInputComponentAlias())
						->before(fn() => '<!-- F_BEFORE -->')
						->after(fn() => '<!-- F_AFTER -->')
					->end_field()
				->end_section()
			->end_page()
		->end_menu_group();

		$adminOutput = $this->captureOutput(fn() => $adminSettings->render('parity-page'));

		// Both should have the same field hook sequence (use field name as marker)
		$userExpected = array(
			'<!-- F_BEFORE -->',
			'name="parity_user[parity_field]"',
			'<!-- F_AFTER -->',
		);
		$adminExpected = array(
			'<!-- F_BEFORE -->',
			'name="parity_admin[parity_field]"',
			'<!-- F_AFTER -->',
		);

		$this->assertHookSequence($userOutput, $userExpected, 'UserSettings');
		$this->assertHookSequence($adminOutput, $adminExpected, 'AdminSettings');
	}

	/**
	 * @test
	 * Verify UserSettings collection-level before/after hooks render correctly.
	 */
	public function test_user_settings_collection_hooks_render_correctly(): void {
		$this->userMetaValues['collection_hooks'] = array('test_field' => 'test_value');
		$result                                   = $this->createUserSettings('collection_hooks');
		$settings                                 = $result['settings'];
		$manifest                                 = $result['manifest'];
		// Register a simple input component for testing
		$this->registerSimpleInputComponent($manifest);

		$settings
			->collection('hook-collection')
				->heading('Hook Collection')
				->before(fn() => '<!-- COLLECTION_BEFORE -->')
				->after(fn() => '<!-- COLLECTION_AFTER -->')
				->section('hook-section', 'Hook Section')
					->field('test_field', 'Test Field', $this->getInputComponentAlias())
					->end_field()
				->end_section()
			->end_collection()
		->boot();

		$output = $this->captureOutput(fn() => $settings->render('hook-collection'));

		// Verify collection hooks are present
		self::assertStringContainsString('<!-- COLLECTION_BEFORE -->', $output, 'Collection before hook should be present');
		self::assertStringContainsString('<!-- COLLECTION_AFTER -->', $output, 'Collection after hook should be present');

		// Verify correct order: collection before -> content -> collection after
		$this->assertHookSequence($output, array(
			'<!-- COLLECTION_BEFORE -->',
			'name="collection_hooks[test_field]"',
			'<!-- COLLECTION_AFTER -->',
		));
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	/**
	 * Create a UserSettings instance for testing.
	 *
	 * Uses actual production templates from inc/Settings/templates/user/
	 * to ensure tests verify real rendering behavior.
	 *
	 * @return array{settings: UserSettings, manifest: ComponentManifest}
	 */
	private function createUserSettings(string $optionName): array {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin\\Components');

		// Use real components + fixture templates
		$loader   = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger);
		$manifest = new ComponentManifest($loader, $this->logger);
		$options  = new RegisterOptions($optionName, StorageContext::forUser(123), false, $this->logger);

		// UserSettings constructor registers actual production templates from inc/Settings/templates/user/
		return array(
			'settings' => new UserSettings($options, $manifest, $config, $this->logger),
			'manifest' => $manifest,
		);
	}

	/**
	 * Create an AdminSettings instance for testing.
	 *
	 * @return array{settings: AdminSettings, manifest: ComponentManifest}
	 */
	private function createAdminSettings(string $optionName): array {
		$config = $this->createMock(ConfigInterface::class);
		$config->method('get_config')->willReturn(array('PATH' => __DIR__));
		$config->method('get_namespace')->willReturn('TestPlugin\\Components');

		$loader      = new ComponentLoader(__DIR__ . '/../../../inc/Forms/Components', $this->logger);
		$fixturesDir = __DIR__ . '/../../fixtures/templates';
		$loader->register_absolute('admin.root-wrapper', $fixturesDir . '/admin/pages/default-page.php');
		$loader->register_absolute('root-wrapper', $fixturesDir . '/admin/pages/default-page.php');
		$loader->register_absolute('layout.zone.section-wrapper', $fixturesDir . '/admin/sections/test-section.php');
		$loader->register_absolute('section-wrapper', $fixturesDir . '/admin/sections/test-section.php');
		$loader->register_absolute('field-wrapper', $fixturesDir . '/admin/field-wrapper-simple.php');
		$loader->register_absolute('shared.field-wrapper', $fixturesDir . '/admin/field-wrapper-simple.php');
		$loader->register_absolute('layout.field.field-wrapper', $fixturesDir . '/admin/field-wrapper-simple.php');
		$loader->register_absolute('layout.zone.submit-controls-wrapper', $fixturesDir . '/admin/submit-controls-wrapper.php');

		$manifest = new ComponentManifest($loader, $this->logger);
		$options  = new RegisterOptions($optionName, StorageContext::forSite(), true, $this->logger);

		return array(
			'settings' => new AdminSettings($options, $manifest, null, $this->logger),
			'manifest' => $manifest,
		);
	}

	/**
	 * Get the component alias for the test input field.
	 */
	private function getInputComponentAlias(): string {
		return 'test.input';
	}

	/**
	 * Register a simple input component that outputs a marker for testing.
	 *
	 * This component is registered as a simple callable (not a builder factory),
	 * which means field-level before/after hooks must be passed via the args array,
	 * not via chained method calls.
	 */
	private function registerSimpleInputComponent(ComponentManifest $manifest): void {
		$manifest->register('test.input', static function (array $context): ComponentRenderResult {
			$name  = htmlspecialchars($context['name'] ?? '', ENT_QUOTES, 'UTF-8');
			$value = htmlspecialchars((string) ($context['value'] ?? ''), ENT_QUOTES, 'UTF-8');

			return new ComponentRenderResult(
				sprintf('<input type="text" name="%s" value="%s" data-marker="test-input" />', $name, $value)
			);
		});
		$this->injectBuilderFactory($manifest, 'test.input');
	}

	/**
	 * Inject a builder factory for a fake component alias.
	 */
	private function injectBuilderFactory(ComponentManifest $manifest, string $alias): void {
		$reflection = new \ReflectionObject($manifest);
		$property   = $reflection->getProperty('componentMetadata');
		$property->setAccessible(true);
		$metadata = $property->getValue($manifest);
		if (!is_array($metadata)) {
			$metadata = array();
		}
		$current            = $metadata[$alias] ?? array();
		$current['builder'] = static fn (string $id, string $label): InputBuilder => new InputBuilder($id, $label);
		$metadata[$alias]   = $current;
		$property->setValue($manifest, $metadata);
	}

	/**
	 * Assert that hooks appear in the expected sequence in the output.
	 *
	 * @param string   $output           The rendered output
	 * @param string[] $expectedSequence Array of hook markers in expected order
	 * @param string   $context          Optional context for error messages
	 */
	private function assertHookSequence(string $output, array $expectedSequence, string $context = ''): void {
		$prefix   = $context !== '' ? "[$context] " : '';
		$lastPos  = -1;
		$lastHook = '';

		foreach ($expectedSequence as $hook) {
			$pos = strpos($output, $hook);
			self::assertNotFalse($pos, "{$prefix}Hook '$hook' should be present in output");
			self::assertGreaterThan(
				$lastPos,
				$pos,
				"{$prefix}Hook '$hook' should appear after '$lastHook' (positions: $lastPos vs $pos)"
			);
			$lastPos  = $pos;
			$lastHook = $hook;
		}
	}
}
