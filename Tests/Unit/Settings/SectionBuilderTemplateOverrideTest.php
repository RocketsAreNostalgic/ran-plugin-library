<?php
/**
 * Section Builder Template Override Tests
 *
 * Tests the template override methods (field_templates, group_template, section_template)
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
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder::field_templates
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder::group_templates
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder::template
 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder::field_templates
 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder::group_templates
 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder::template
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
		$loader     = new ComponentLoader($baseDir, $this->logger);
		$components = new ComponentManifest($loader, $this->logger);
		$options    = RegisterOptions::site('test_admin_options', true, $this->logger);

		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(static fn($v) => is_scalar($v) ? trim((string) $v) : ''),
				'validate' => array(static fn($v, $e) => is_string($v)),
			),
		));

		return new AdminSettings($options, $components, null, $this->logger);
	}

	private function createUserSettings(): UserSettings {
		$baseDir    = dirname(__DIR__, 3) . '/inc/Forms/Components';
		$loader     = new ComponentLoader($baseDir, $this->logger);
		$components = new ComponentManifest($loader, $this->logger);
		$options    = RegisterOptions::user('test_user_options', 123, false, $this->logger);

		$options->register_schema(array(
			'test_field' => array(
				'default'  => '',
				'sanitize' => array(static fn($v) => is_scalar($v) ? trim((string) $v) : ''),
				'validate' => array(static fn($v, $e) => is_string($v)),
			),
		));

		return new UserSettings($options, $components, null, $this->logger);
	}

	/**
	 * Get a property value from a settings object via reflection.
	 */
	private function getProperty(object $settings, string $property): mixed {
		$reflection = new \ReflectionClass($settings);
		$prop       = $reflection->getProperty($property);
		$prop->setAccessible(true);
		return $prop->getValue($settings);
	}

	/**
	 * Get individual element overrides from form_session.
	 *
	 * @param object $settings The settings object (AdminSettings or UserSettings).
	 * @param string $elementType The element type (section, field, group, etc.).
	 * @param string $elementId The element ID.
	 * @return array<string, string> The overrides for the element.
	 */
	private function getElementOverrides(object $settings, string $elementType, string $elementId): array {
		$session = $this->getProperty($settings, 'form_session');
		if ($session === null) {
			return array();
		}
		return $session->get_individual_element_overrides($elementType, $elementId);
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
					->field_templates('custom-field-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_field()->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($admin, 'section', 'template-section');
		self::assertArrayHasKey('field-wrapper', $overrides);
		self::assertSame('custom-field-wrapper', $overrides['field-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_admin_section_group_template_dispatches_override_event(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->group_templates('custom-group-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_field()->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($admin, 'section', 'template-section');
		self::assertArrayHasKey('group-wrapper', $overrides);
		self::assertSame('custom-group-wrapper', $overrides['group-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_admin_section_section_template_dispatches_override_event(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->template('custom-section-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_field()->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($admin, 'section', 'template-section');
		self::assertArrayHasKey('section-wrapper', $overrides);
		self::assertSame('custom-section-wrapper', $overrides['section-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_admin_section_fieldset_template_dispatches_override_event(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section')
					->fieldset_templates('custom-fieldset-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_field()->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($admin, 'section', 'template-section');
		self::assertArrayHasKey('fieldset-wrapper', $overrides);
		self::assertSame('custom-fieldset-wrapper', $overrides['fieldset-wrapper']);
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
					->field_templates('');
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
					->group_templates('   ');
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
					->fieldset_templates('');
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
					->template('');
	}

	/**
	 * @test
	 */
	public function test_admin_section_template_methods_return_builder_for_chaining(): void {
		$admin = $this->createAdminSettings();

		$section = $admin->menu_group('test-menu')
			->page('test-page')
				->section('template-section', 'Template Section');

		$result1 = $section->field_templates('custom-field');
		self::assertInstanceOf(AdminSettingsSectionBuilder::class, $result1);

		$result2 = $result1->group_templates('custom-group');
		self::assertInstanceOf(AdminSettingsSectionBuilder::class, $result2);

		$result3 = $result2->fieldset_templates('custom-fieldset');
		self::assertInstanceOf(AdminSettingsSectionBuilder::class, $result3);

		$result4 = $result3->template('custom-section');
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
					->field_templates('custom-field-wrapper')
					->group_templates('custom-group-wrapper')
					->fieldset_templates('custom-fieldset-wrapper')
					->template('custom-section-wrapper')
					->field('test_field', 'Test Field', 'fields.input')
				->end_field()->end_section()
			->end_page()
		->end_menu();

		// Note: Each template method replaces the previous overrides.
		// The last one (section-wrapper) should be present.
		$overrides = $this->getElementOverrides($admin, 'section', 'multi-override-section');
		self::assertArrayHasKey('section-wrapper', $overrides, 'Expected section-wrapper override (last in chain).');
		self::assertSame('custom-section-wrapper', $overrides['section-wrapper']);
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
				->field_templates('custom-field-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_field()->end_section()
		->end_collection();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($user, 'section', 'template-section');
		self::assertArrayHasKey('field-wrapper', $overrides);
		self::assertSame('custom-field-wrapper', $overrides['field-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_user_section_group_template_dispatches_override_event(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->group_templates('custom-group-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_field()->end_section()
		->end_collection();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($user, 'section', 'template-section');
		self::assertArrayHasKey('group-wrapper', $overrides);
		self::assertSame('custom-group-wrapper', $overrides['group-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_user_section_section_template_dispatches_override_event(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->template('custom-section-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_field()->end_section()
		->end_collection();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($user, 'section', 'template-section');
		self::assertArrayHasKey('section-wrapper', $overrides);
		self::assertSame('custom-section-wrapper', $overrides['section-wrapper']);
	}

	/**
	 * @test
	 */
	public function test_user_section_fieldset_template_dispatches_override_event(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('template-section', 'Template Section')
				->fieldset_templates('custom-fieldset-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_field()->end_section()
		->end_collection();

		// Verify state directly instead of logs
		$overrides = $this->getElementOverrides($user, 'section', 'template-section');
		self::assertArrayHasKey('fieldset-wrapper', $overrides);
		self::assertSame('custom-fieldset-wrapper', $overrides['fieldset-wrapper']);
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
				->field_templates('');
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
				->group_templates('   ');
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
				->fieldset_templates('');
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
				->template('');
	}

	/**
	 * @test
	 */
	public function test_user_section_template_methods_return_builder_for_chaining(): void {
		$user = $this->createUserSettings();

		$section = $user->collection('profile')
			->section('template-section', 'Template Section');

		$result1 = $section->field_templates('custom-field');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result1);

		$result2 = $result1->group_templates('custom-group');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result2);

		$result3 = $result2->fieldset_templates('custom-fieldset');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result3);

		$result4 = $result3->template('custom-section');
		self::assertInstanceOf(UserSettingsSectionBuilder::class, $result4);
	}

	/**
	 * @test
	 */
	public function test_user_section_multiple_template_overrides_in_chain(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('multi-override-section', 'Multi Override Section')
				->field_templates('custom-field-wrapper')
				->group_templates('custom-group-wrapper')
				->fieldset_templates('custom-fieldset-wrapper')
				->template('custom-section-wrapper')
				->field('test_field', 'Test Field', 'fields.input')
			->end_field()->end_section()
		->end_collection();

		// Note: Each template method replaces the previous overrides.
		// The last one (section-wrapper) should be present.
		$overrides = $this->getElementOverrides($user, 'section', 'multi-override-section');
		self::assertArrayHasKey('section-wrapper', $overrides, 'Expected section-wrapper override (last in chain).');
		self::assertSame('custom-section-wrapper', $overrides['section-wrapper']);
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

		self::assertContains('field_templates', $adminMethods, 'AdminSettingsSectionBuilder must have field_template method.');
		self::assertContains('group_templates', $adminMethods, 'AdminSettingsSectionBuilder must have group_template method.');
		self::assertContains('fieldset_templates', $adminMethods, 'AdminSettingsSectionBuilder must have fieldset_template method.');
		self::assertContains('template', $adminMethods, 'AdminSettingsSectionBuilder must have section_template method.');

		self::assertContains('field_templates', $userMethods, 'UserSettingsSectionBuilder must have field_template method.');
		self::assertContains('group_templates', $userMethods, 'UserSettingsSectionBuilder must have group_template method.');
		self::assertContains('fieldset_templates', $userMethods, 'UserSettingsSectionBuilder must have fieldset_template method.');
		self::assertContains('template', $userMethods, 'UserSettingsSectionBuilder must have section_template method.');
	}
}
