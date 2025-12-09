<?php
/**
 * AdminSettings Fluent Builder API Test
 *
 * Tests the complete fluent builder chain mechanics for AdminSettings:
 * menu_group() -> page() -> section() -> group() -> field() -> input('submit')
 *
 * Validates builder API chaining, metadata storage, and logging.
 * Does NOT test actual component rendering - see AdminSettingsBehaviorTest for render tests.
 *
 * @package Ran\PluginLib\Tests\Unit\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Tools\TestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * Builder API test for AdminSettings fluent builder chain.
 *
 * This test validates that the complete fluent API chain works correctly,
 * ensuring all builders are properly connected and metadata flows through the system.
 *
 * @covers \Ran\PluginLib\Settings\AdminSettings
 * @covers \Ran\PluginLib\Settings\AdminSettingsPageBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsGroupBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsFieldsetBuilder
 */
class AdminSettingsFluentBuilderApiTest extends TestCase {
	use ExpectLogTrait;

	protected CollectingLogger $logger_mock;
	public function setUp(): void {
		\WP_Mock::setUp();

		// Mock WordPress functions needed by ComponentManifest and Settings
		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('is_network_admin')->andReturn(false);
		\WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		\WP_Mock::userFunction('get_transient')->andReturn(false);
		\WP_Mock::userFunction('set_transient')->andReturn(true);
		\WP_Mock::userFunction('delete_transient')->andReturn(true);
		\WP_Mock::userFunction('sanitize_key')->andReturnArg(0);

		$this->logger_mock = new CollectingLogger();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	/**
	 * Helper: Create AdminSettings instance with proper dependencies.
	 */
	private function createAdminSettings(): AdminSettings {
		$logger     = $this->logger_mock;
		$baseDir    = dirname(__DIR__, 3) . '/inc/Forms/Components';
		$loader     = new ComponentLoader($baseDir, $logger);
		$components = new ComponentManifest($loader, $logger);
		$options    = RegisterOptions::site('test_admin_options', true, $logger);

		$options->register_schema(array(
			'general_field'    => $this->stringSchema(),
			'advanced_field'   => $this->stringSchema(),
			'field_one'        => $this->stringSchema(),
			'field_two'        => $this->stringSchema(),
			'field_three'      => $this->stringSchema(),
			'group_field'      => $this->stringSchema(),
			'group_field_1'    => $this->stringSchema(),
			'group_field_2'    => $this->stringSchema(),
			'group_field_3'    => $this->stringSchema(),
			'contact_email'    => $this->stringSchema(),
			'site_title'       => $this->stringSchema(),
			'site_description' => $this->stringSchema(),
			'enable_feature'   => array(
				'default'  => false,
				'sanitize' => array(static function($value) {
					return (bool) $value;
				}),
				'validate' => array(static function($value, callable $emitWarning) {
					return is_bool($value);
				}),
			),
			'api_key' => $this->stringSchema(),
		));

		return new AdminSettings($options, $components, null, $logger);
	}

	private function stringSchema(): array {
		return array(
			'default'  => '',
			'sanitize' => array(static function($value) {
				return is_scalar($value) ? trim((string) $value) : '';
			}),
			'validate' => array(static function($value, callable $emitWarning) {
				return is_string($value);
			}),
		);
	}

	private function booleanSchema(): array {
		return array(
			'default'  => false,
			'sanitize' => array(static function($value) {
				return (bool) $value;
			}),
			'validate' => array(static function($value, callable $emitWarning) {
				return is_bool($value);
			}),
		);
	}

	/**
	 * @param array<int, array{context: array<string, mixed>}> $logs
	 *
	 * @return array<string, mixed>|null
	 */
	private function findLatestContext(array $logs, ?callable $filter = null): ?array {
		for ($index = count($logs) - 1; $index >= 0; $index--) {
			$entry   = $logs[$index];
			$context = $entry['context'] ?? array();
			if ($filter !== null && !$filter($context)) {
				continue;
			}
			return $context;
		}

		return null;
	}

	/**
	 * Helper: Get a property value from AdminSettings via reflection.
	 */
	private function getProperty(AdminSettings $admin, string $property): mixed {
		$reflection = new \ReflectionClass($admin);
		$prop       = $reflection->getProperty($property);
		$prop->setAccessible(true);
		return $prop->getValue($admin);
	}

	/**
	 * Builder API test: Fieldset builder stores metadata and fields.
	 *
	 * @test
	 */
	public function test_admin_settings_fieldset_builder_persists_metadata(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('fieldset-menu')
			->page('fieldset-page')
				->section('fieldset-section', 'Fieldset Section')
					->fieldset('profile-details', 'Profile Details', null, array('style' => 'minimal'))
						->disabled(true)
						->field('field_one', 'Field One', 'fields.input')->end_field()
					->end_fieldset()
				->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$groups = $this->getProperty($admin, 'groups');
		self::assertArrayHasKey('fieldset-page', $groups);
		self::assertArrayHasKey('fieldset-section', $groups['fieldset-page']);
		self::assertArrayHasKey('profile-details', $groups['fieldset-page']['fieldset-section']);

		$fieldset = $groups['fieldset-page']['fieldset-section']['profile-details'];
		self::assertSame('Profile Details', $fieldset['title']);
		self::assertSame('minimal', $fieldset['style']);
		self::assertTrue($fieldset['disabled']);
		self::assertCount(1, $fieldset['fields']);
		self::assertSame('field_one', $fieldset['fields'][0]['id']);
	}

	/**
	 * Builder API test: Complete AdminSettings fluent builder chain.
	 *
	 * Tests the full chain:
	 * menu_group() -> page() -> section() -> group() -> field() -> submit
	 *
	 * Verifies the fluent API chain executes without errors and metadata is stored correctly.
	 *
	 * @test
	 */
	public function test_admin_settings_complete_fluent_chain(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu-group')
			->page('test-page')
				->heading('Test Settings Page')
				->menu_label('Test Settings')
				->capability('manage_options')
				->section('general-section', 'General Settings')
					->before(static fn (): string => '<div class="section-before">SECTION BEFORE</div>')
					->after(static fn (): string => '<div class="section-after">SECTION AFTER</div>')
					->group('basic-group', 'Basic Options')
						->before(static fn (): string => '<div class="group-before">GROUP BEFORE</div>')
						->after(static fn (): string => '<div class="group-after">GROUP AFTER</div>')
						->field('site_title', 'Site Title', 'fields.input', array('context' => array('default' => 'My Site')))->end_field()
						->field('site_description', 'Site Description', 'fields.textarea', array('context' => array('default' => 'A great site')))->end_field()
					->end_group()
					->fieldset('contact-preferences', 'Contact Preferences')
						->style('highlighted')
						->disabled()
						->field('contact_email', 'Contact Email', 'fields.input')->end_field()
					->end_fieldset()
					->field('enable_feature', 'Enable Feature', 'fields.input', array('context' => array('default' => '')))->end_field()
				->end_section()
				->section('advanced-section', 'Advanced Settings')
					->field('api_key', 'API Key', 'fields.input', array('context' => array('default' => '')))->end_field()
					->field('submit', 'Save Settings', 'fields.input')->end_field()
				->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$menuGroups = $this->getProperty($admin, 'menu_groups');
		self::assertArrayHasKey('test-menu-group', $menuGroups);
		$pageMeta = $menuGroups['test-menu-group']['pages']['test-page']['meta'] ?? array();
		self::assertSame('Test Settings Page', $pageMeta['heading'] ?? null);
		self::assertSame('Test Settings', $pageMeta['menu_title'] ?? null);
		self::assertSame('manage_options', $pageMeta['capability'] ?? null);

		$sections = $this->getProperty($admin, 'sections');
		self::assertArrayHasKey('test-page', $sections);
		self::assertArrayHasKey('general-section', $sections['test-page']);
		$generalSection = $sections['test-page']['general-section'];
		self::assertNotNull($generalSection['before']);
		self::assertNotNull($generalSection['after']);

		$groups = $this->getProperty($admin, 'groups');
		self::assertArrayHasKey('test-page', $groups);
		self::assertArrayHasKey('general-section', $groups['test-page']);
		self::assertArrayHasKey('basic-group', $groups['test-page']['general-section']);
		$basicGroup = $groups['test-page']['general-section']['basic-group'];
		self::assertNotNull($basicGroup['before']);
		self::assertNotNull($basicGroup['after']);

		$fields = $this->getProperty($admin, 'fields');
		self::assertArrayHasKey('test-page', $fields);
		self::assertArrayHasKey('general-section', $fields['test-page']);
		$generalFieldIds = array_column($fields['test-page']['general-section'], 'id');
		self::assertSame(array('enable_feature'), $generalFieldIds);

		$contactPrefs = $groups['test-page']['general-section']['contact-preferences'];
		self::assertSame('highlighted', $contactPrefs['style']);
		self::assertTrue($contactPrefs['disabled']);

		self::assertArrayHasKey('advanced-section', $fields['test-page']);
		$advancedFieldIds = array_column($fields['test-page']['advanced-section'], 'id');
		self::assertSame(array('api_key', 'submit'), $advancedFieldIds);

		$basicGroupFields = $basicGroup['fields'];
		self::assertSame(array('site_title', 'site_description'), array_column($basicGroupFields, 'id'));
		self::assertSame(
			array('My Site', 'A great site'),
			array_map(static fn(array $f) => $f['component_context']['default'] ?? null, $basicGroupFields)
		);
	}

	/**
	 * Builder API test: Multiple pages in same menu group.
	 *
	 * @test
	 */
	public function test_admin_settings_multiple_pages_in_menu_group(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('main-menu')
			->page('general-page')
				->heading('General')
				->section('general-section', 'General Section')
					->field('general_field', 'General Field', 'fields.input')->end_field()
				->end_section()
			->end_page()
			->page('advanced-page')
				->heading('Advanced')
				->section('advanced-section', 'Advanced Section')
					->field('advanced_field', 'Advanced Field', 'fields.input')->end_field()
				->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$menuGroups = $this->getProperty($admin, 'menu_groups');
		self::assertArrayHasKey('main-menu', $menuGroups);
		$pages = $menuGroups['main-menu']['pages'] ?? array();
		self::assertSame(array('general-page', 'advanced-page'), array_keys($pages));
		self::assertSame('General', $pages['general-page']['meta']['heading'] ?? null);
		self::assertSame('Advanced', $pages['advanced-page']['meta']['heading'] ?? null);
	}

	/**
	 * Builder API test: Section with only groups (no standalone fields).
	 *
	 * @test
	 */
	public function test_admin_settings_section_with_only_groups(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('grouped-section', 'Grouped Section')
					->heading('Grouped Settings')
					->group('group-one', 'Group One')
						->field('field_one', 'Field One', 'fields.input')->end_field()
					->end_group()
					->group('group-two', 'Group Two')
						->field('field_two', 'Field Two', 'fields.input')->end_field()
					->end_group()
				->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$groups = $this->getProperty($admin, 'groups');
		self::assertArrayHasKey('test-page', $groups);
		self::assertArrayHasKey('grouped-section', $groups['test-page']);
		self::assertSame(array('group-one', 'group-two'), array_keys($groups['test-page']['grouped-section']));

		$fields           = $this->getProperty($admin, 'fields');
		$standaloneFields = $fields['test-page']['grouped-section'] ?? array();
		self::assertSame(array(), array_column($standaloneFields, 'id'), 'No standalone fields should exist.');
	}

	/**
	 * Builder API test: Field ordering and indexing.
	 *
	 * @test
	 */
	public function test_admin_settings_field_ordering(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('test-section', 'Test Section')
					->field('field_three', 'Field Three', 'fields.input', array('order' => 30))->end_field()
					->field('field_one', 'Field One', 'fields.input', array('order' => 10))->end_field()
					->field('field_two', 'Field Two', 'fields.input', array('order' => 20))->end_field()
					->group('group-one', 'Group One')
						->field('group_field_1', 'Grouped Field 1', 'fields.input', array(
							'context' => array('attributes' => array('placeholder' => 'Grouped Placeholder 1')),
							'order'   => 30,
						))->end_field()
						->field('group_field_2', 'Grouped Field 2', 'fields.input', array(
							'context' => array('attributes' => array('placeholder' => 'Grouped Placeholder 2')),
							'order'   => 40,
						))->end_field()
						->field('group_field_3', 'Grouped Field 3', 'fields.input', array(
							'context' => array('attributes' => array('placeholder' => 'Grouped Placeholder 3')),
							'order'   => 10,
						))->end_field()
					->end_group()
				->end_section()
			->end_page()
		->end_menu();

		// Verify state directly instead of logs
		$fields = $this->getProperty($admin, 'fields');
		self::assertArrayHasKey('test-page', $fields);
		self::assertArrayHasKey('test-section', $fields['test-page']);
		$sectionFields = $fields['test-page']['test-section'];
		self::assertSame(array('field_three', 'field_one', 'field_two'), array_column($sectionFields, 'id'));
		self::assertSame(array(30, 10, 20), array_column($sectionFields, 'order'));

		$groups      = $this->getProperty($admin, 'groups');
		$groupFields = $groups['test-page']['test-section']['group-one']['fields'];
		self::assertSame(array('group_field_1', 'group_field_2', 'group_field_3'), array_column($groupFields, 'id'));
		self::assertSame(array(30, 40, 10), array_column($groupFields, 'order'));
		self::assertSame(
			array('Grouped Placeholder 1', 'Grouped Placeholder 2', 'Grouped Placeholder 3'),
			array_map(static fn (array $f) => $f['component_context']['attributes']['placeholder'] ?? null, $groupFields)
		);
	}

	/**
	 * Builder API test: Group builder explicit chaining.
	 *
	 * @test
	 */
	public function test_admin_settings_group_explicit_chain(): void {
		$admin = $this->createAdminSettings();

		$menuBuilder    = $admin->menu_group('implicit-group-menu');
		$pageBuilder    = $menuBuilder->page('implicit-group-page');
		$sectionBuilder = $pageBuilder->section('implicit-group-section', 'Implicit Group Section');
		$groupBuilder   = $sectionBuilder->group('implicit-group', 'Implicit Group');
		$groupBuilder->field('group_field', 'Implicit Field', 'fields.input')->end_field();

		$groupBuilder->end_group()->end_section()->end_page()->end_menu();

		$reflection = new \ReflectionClass($admin);
		$groupsProp = $reflection->getProperty('groups');
		$groupsProp->setAccessible(true);
		$groups = $groupsProp->getValue($admin);

		$this->assertArrayHasKey('implicit-group', $groups['implicit-group-page']['implicit-group-section']);
		$this->assertSame('Implicit Group', $groups['implicit-group-page']['implicit-group-section']['implicit-group']['title']);
		$this->assertCount(1, $groups['implicit-group-page']['implicit-group-section']['implicit-group']['fields']);

		$pagesProp = $reflection->getProperty('pages');
		$pagesProp->setAccessible(true);
		$pages = $pagesProp->getValue($admin);
		$this->assertArrayHasKey('implicit-group-page', $pages);
	}

	/**
	 * Builder API test: Fieldset builder explicit chaining.
	 *
	 * @test
	 */
	public function test_admin_settings_fieldset_explicit_chain(): void {
		$admin = $this->createAdminSettings();

		$menuBuilder     = $admin->menu_group('implicit-fieldset-menu');
		$pageBuilder     = $menuBuilder->page('implicit-fieldset-page');
		$sectionBuilder  = $pageBuilder->section('implicit-fieldset-section', 'Implicit Fieldset Section');
		$fieldsetBuilder = $sectionBuilder->fieldset('implicit-fieldset', 'Implicit Fieldset');
		$fieldsetBuilder->field('contact_email', 'Implicit Field', 'fields.input');

		$fieldsetBuilder->end_fieldset()->end_section()->end_page()->end_menu();

		$reflection = new \ReflectionClass($admin);
		$groupsProp = $reflection->getProperty('groups');
		$groupsProp->setAccessible(true);
		$groups = $groupsProp->getValue($admin);

		$fieldset = $groups['implicit-fieldset-page']['implicit-fieldset-section']['implicit-fieldset'];
		$this->assertSame('Implicit Fieldset', $fieldset['title']);
		$this->assertCount(1, $fieldset['fields']);
	}
}
