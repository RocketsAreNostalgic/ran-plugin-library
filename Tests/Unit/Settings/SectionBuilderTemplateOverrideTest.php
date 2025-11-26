<?php
/**
 * Section Builder Template Override Tests
 *
 * Tests the template override methods (field_template, group_template, section_template)
 * for both AdminSettingsSectionBuilder and UserSettingsSectionBuilder.
 *
 * These tests ensure that template override functionality is not lost during refactoring
 * and behaves correctly across both settings ecosystems.
 *
 * @package Ran\PluginLib\Tests\Unit\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Tools\TestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Settings\UserSettingsSectionBuilder;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\AdminSettingsSectionBuilder;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * Tests for template override methods on section builders.
 *
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder::field_template
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder::group_template
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder::section_template
 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder::field_template
 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder::group_template
 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder::section_template
 */
class SectionBuilderTemplateOverrideTest extends TestCase {
	use ExpectLogTrait;

	protected CollectingLogger $logger;

	public function setUp(): void {
		\WP_Mock::setUp();

		// Mock WordPress functions needed by ComponentManifest, RegisterOptions, and Settings
		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('update_option')->andReturn(true);
		\WP_Mock::userFunction('delete_option')->andReturn(true);
		\WP_Mock::userFunction('get_user_meta')->andReturn(array());
		\WP_Mock::userFunction('update_user_meta')->andReturn(true);
		\WP_Mock::userFunction('get_user_option')->andReturn(false);
		\WP_Mock::userFunction('update_user_option')->andReturn(true);
		\WP_Mock::userFunction('delete_user_option')->andReturn(true);
		\WP_Mock::userFunction('get_current_user_id')->andReturn(123);
		\WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		\WP_Mock::userFunction('is_network_admin')->andReturn(false);
		\WP_Mock::userFunction('get_transient')->andReturn(false);
		\WP_Mock::userFunction('set_transient')->andReturn(true);
		\WP_Mock::userFunction('delete_transient')->andReturn(true);
		\WP_Mock::userFunction('sanitize_key')->andReturnArg(0);
		\WP_Mock::userFunction('wp_get_environment_type')->andReturn('production');

		$this->logger = new CollectingLogger();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Helper methods
	// ─────────────────────────────────────────────────────────────────────────

	private function createAdminSettings(): AdminSettings {
		$baseDir    = dirname(__DIR__, 3) . '/inc/Forms/Components';
		$loader     = new ComponentLoader($baseDir, array(), $this->logger);
		$components = new ComponentManifest($loader, $this->logger);
		$options    = RegisterOptions::site('test_admin_options', true, $this->logger);

		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(static fn($v) => is_scalar($v) ? trim((string) $v) : ''),
				'validate' => array(static fn($v, $e) => is_string($v)),
			),
		));

		return new AdminSettings($options, $components, $this->logger);
	}

	private function createUserSettings(): UserSettings {
		$baseDir    = dirname(__DIR__, 3) . '/inc/Forms/Components';
		$loader     = new ComponentLoader($baseDir, array(), $this->logger);
		$components = new ComponentManifest($loader, $this->logger);
		$options    = RegisterOptions::user('test_user_options', 123, false, $this->logger);

		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(static fn($v) => is_scalar($v) ? trim((string) $v) : ''),
				'validate' => array(static fn($v, $e) => is_string($v)),
			),
		));

		return new UserSettings($options, $components, $this->logger);
	}

	/**
	 * Find template override logs matching given criteria.
	 *
	 * @param string $sectionId The section ID to filter by.
	 * @param string $overrideKey The override key (field-wrapper, group-wrapper, section-wrapper).
	 * @return array<int, array{message: string, context: array<string, mixed>}>
	 */
	private function findTemplateOverrideLogs(string $sectionId, string $overrideKey): array {
		return $this->logger->find_logs(static function (array $entry) use ($sectionId, $overrideKey): bool {
			return $entry['message']                         === 'settings.builder.template_override'
			    && ($entry['context']['element_id'] ?? null) === $sectionId
			    && isset($entry['context']['overrides'][$overrideKey]);
		});
	}

	// ─────────────────────────────────────────────────────────────────────────
	// AdminSettingsSectionBuilder Template Override Tests
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @test
	 */
	public function test_admin_section_field_template_dispatches_override_event(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->field_template('custom-field-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$logs = $this->findTemplateOverrideLogs('template-section', 'field-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for field-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-field-wrapper', $context['overrides']['field-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_admin_section_group_template_dispatches_override_event(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->group_template('custom-group-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$logs = $this->findTemplateOverrideLogs('template-section', 'group-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for group-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-group-wrapper', $context['overrides']['group-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_admin_section_section_template_dispatches_override_event(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->section_template('custom-section-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$logs = $this->findTemplateOverrideLogs('template-section', 'section-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for section-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-section-wrapper', $context['overrides']['section-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_admin_section_fieldset_template_dispatches_override_event(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->fieldset_template('custom-fieldset-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$logs = $this->findTemplateOverrideLogs('template-section', 'fieldset-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for fieldset-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-fieldset-wrapper', $context['overrides']['fieldset-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_admin_section_field_template_throws_on_empty_key(): void {
		$admin = $this->createAdminSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->field_template('');
	}

	/**
	 * @test
	 */
	public function test_admin_section_group_template_throws_on_empty_key(): void {
		$admin = $this->createAdminSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->group_template('   ');
	}

	/**
	 * @test
	 */
	public function test_admin_section_fieldset_template_throws_on_empty_key(): void {
		$admin = $this->createAdminSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->fieldset_template('');
	}

	/**
	 * @test
	 */
	public function test_admin_section_section_template_throws_on_empty_key(): void {
		$admin = $this->createAdminSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->section_template('');
	}

	/**
	 * @test
	 */
	public function test_admin_section_template_methods_return_builder_for_chaining(): void {
		$admin = $this->createAdminSettings();

		$section = $admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section');

		$result1 = $section->field_template('custom-field');
		self::assertInstanceOf(AdminSettingsSectionBuilder::class, $result1);

		$result2 = $result1->group_template('custom-group');
		self::assertInstanceOf(AdminSettingsSectionBuilder::class, $result2);

		$result3 = $result2->fieldset_template('custom-fieldset');
		self::assertInstanceOf(AdminSettingsSectionBuilder::class, $result3);

		$result4 = $result3->section_template('custom-section');
		self::assertInstanceOf(AdminSettingsSectionBuilder::class, $result4);
	}

	/**
	 * @test
	 */
	public function test_admin_section_multiple_template_overrides_in_chain(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('multi-override-section', 'Multi Override Section')
					->field_template('custom-field-wrapper')
					->group_template('custom-group-wrapper')
					->fieldset_template('custom-fieldset-wrapper')
					->section_template('custom-section-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$fieldLogs    = $this->findTemplateOverrideLogs('multi-override-section', 'field-wrapper');
		$groupLogs    = $this->findTemplateOverrideLogs('multi-override-section', 'group-wrapper');
		$fieldsetLogs = $this->findTemplateOverrideLogs('multi-override-section', 'fieldset-wrapper');
		$sectionLogs  = $this->findTemplateOverrideLogs('multi-override-section', 'section-wrapper');

		self::assertNotEmpty($fieldLogs, 'Expected field-wrapper override log.');
		self::assertNotEmpty($groupLogs, 'Expected group-wrapper override log.');
		self::assertNotEmpty($fieldsetLogs, 'Expected fieldset-wrapper override log.');
		self::assertNotEmpty($sectionLogs, 'Expected section-wrapper override log.');
	}

	// ─────────────────────────────────────────────────────────────────────────
	// UserSettingsSectionBuilder Template Override Tests
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @test
	 */
	public function test_user_section_field_template_dispatches_override_event(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->field_template('custom-field-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_section()
		->end_collection();

		$logs = $this->findTemplateOverrideLogs('template-section', 'field-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for field-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-field-wrapper', $context['overrides']['field-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_user_section_group_template_dispatches_override_event(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->group_template('custom-group-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_section()
		->end_collection();

		$logs = $this->findTemplateOverrideLogs('template-section', 'group-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for group-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-group-wrapper', $context['overrides']['group-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_user_section_section_template_dispatches_override_event(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->section_template('custom-section-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_section()
		->end_collection();

		$logs = $this->findTemplateOverrideLogs('template-section', 'section-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for section-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-section-wrapper', $context['overrides']['section-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_user_section_fieldset_template_dispatches_override_event(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->fieldset_template('custom-fieldset-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_section()
		->end_collection();

		$logs = $this->findTemplateOverrideLogs('template-section', 'fieldset-wrapper');
		self::assertNotEmpty($logs, 'Expected template_override log for fieldset-wrapper.');

		$context = $logs[0]['context'] ?? array();
		self::assertSame('section', $context['element_type']);
		self::assertSame('template-section', $context['element_id']);
		self::assertSame('custom-fieldset-wrapper', $context['overrides']['fieldset-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_user_section_field_template_throws_on_empty_key(): void {
		$user = $this->createUserSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->field_template('');
	}

	/**
	 * @test
	 */
	public function test_user_section_group_template_throws_on_empty_key(): void {
		$user = $this->createUserSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->group_template('   ');
	}

	/**
	 * @test
	 */
	public function test_user_section_fieldset_template_throws_on_empty_key(): void {
		$user = $this->createUserSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->fieldset_template('');
	}

	/**
	 * @test
	 */
	public function test_user_section_section_template_throws_on_empty_key(): void {
		$user = $this->createUserSettings();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template key cannot be empty');

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->section_template('');
	}

	/**
	 * @test
	 */
	public function test_user_section_template_methods_return_builder_for_chaining(): void {
		$user = $this->createUserSettings();

		$section = $user->collection('profile')
			->section('template-section', 'Template Section');

		$result1 = $section->field_template('custom-field');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result1);

		$result2 = $result1->group_template('custom-group');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result2);

		$result3 = $result2->fieldset_template('custom-fieldset');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result3);

		$result4 = $result3->section_template('custom-section');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result4);
	}

	/**
	 * @test
	 */
	public function test_user_section_multiple_template_overrides_in_chain(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('multi-override-section', 'Multi Override Section')
				->field_template('custom-field-wrapper')
				->group_template('custom-group-wrapper')
				->fieldset_template('custom-fieldset-wrapper')
				->section_template('custom-section-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_section()
		->end_collection();

		$fieldLogs    = $this->findTemplateOverrideLogs('multi-override-section', 'field-wrapper');
		$groupLogs    = $this->findTemplateOverrideLogs('multi-override-section', 'group-wrapper');
		$fieldsetLogs = $this->findTemplateOverrideLogs('multi-override-section', 'fieldset-wrapper');
		$sectionLogs  = $this->findTemplateOverrideLogs('multi-override-section', 'section-wrapper');

		self::assertNotEmpty($fieldLogs, 'Expected field-wrapper override log.');
		self::assertNotEmpty($groupLogs, 'Expected group-wrapper override log.');
		self::assertNotEmpty($fieldsetLogs, 'Expected fieldset-wrapper override log.');
		self::assertNotEmpty($sectionLogs, 'Expected section-wrapper override log.');
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Parity Tests - Ensure both ecosystems behave identically
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * @test
	 */
	public function test_admin_and_user_section_builders_have_same_template_api(): void {
		// Verify method existence via reflection - ensures API parity
		$adminMethods = get_class_methods(AdminSettingsSectionBuilder::class);
		$userMethods  = get_class_methods(UserSettingsSectionBuilder::class);

		self::assertContains('field_template', $adminMethods, 'AdminSettingsSectionBuilder must have field_template method.');
		self::assertContains('group_template', $adminMethods, 'AdminSettingsSectionBuilder must have group_template method.');
		self::assertContains('fieldset_template', $adminMethods, 'AdminSettingsSectionBuilder must have fieldset_template method.');
		self::assertContains('section_template', $adminMethods, 'AdminSettingsSectionBuilder must have section_template method.');

		self::assertContains('field_template', $userMethods, 'UserSettingsSectionBuilder must have field_template method.');
		self::assertContains('group_template', $userMethods, 'UserSettingsSectionBuilder must have group_template method.');
		self::assertContains('fieldset_template', $userMethods, 'UserSettingsSectionBuilder must have fieldset_template method.');
		self::assertContains('section_template', $userMethods, 'UserSettingsSectionBuilder must have section_template method.');
	}
}
