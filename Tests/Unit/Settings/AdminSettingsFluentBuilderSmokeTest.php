<?php
/**
 * AdminSettings Fluent Builder Smoke Test
 *
 * Validates the complete fluent builder chain for AdminSettings:
 * menu_group() -> page() -> section() -> group() -> field() -> input('submit')
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
 * Smoke test for AdminSettings fluent builder chain.
 *
 * This test validates that the complete fluent API chain works end-to-end
 * without errors, ensuring all builders are properly connected and data flows
 * correctly through the system.
 *
 * @covers \Ran\PluginLib\Settings\AdminSettings
 * @covers \Ran\PluginLib\Settings\AdminSettingsPageBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsSectionBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsGroupBuilder
 * @covers \Ran\PluginLib\Settings\AdminSettingsFieldsetBuilder
 */
class AdminSettingsFluentBuilderSmokeTest extends TestCase {
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

		return new AdminSettings($options, $components, $logger);
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
	 * Smoke test: Fieldset builder stores metadata and fields.
	 *
	 * @test
	 */
	public function test_admin_settings_fieldset_builder_persists_metadata(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('fieldset-menu')
			->page('fieldset-page')
				->section('fieldset-section', 'Fieldset Section')
					->fieldset('profile-details', 'Profile Details', null, array('style' => 'minimal'))
						->required(true)
						->field('field_one', 'Field One', 'fields.input')
					->end_fieldset()
				->end_section()
			->end_page()
		->end_menu_group();

		$fieldsetMetadataLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'fieldset-page'
			    && ($entry['context']['section_id'] ?? null)   === 'fieldset-section'
			    && ($entry['context']['group_id'] ?? null)     === 'profile-details';
		});
		self::assertNotEmpty($fieldsetMetadataLogs, 'Fieldset metadata log missing.');
		$fieldSetContext = $this->findLatestContext($fieldsetMetadataLogs, static function (array $context): bool {
			return ($context['required'] ?? false) === true;
		});
		self::assertSame('Profile Details', $fieldSetContext['heading']);
		self::assertSame('minimal', $fieldSetContext['style']);
		self::assertTrue($fieldSetContext['required']);

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'fieldset-page'
			    && ($entry['context']['section_id'] ?? null)   === 'fieldset-section'
			    && ($entry['context']['group_id'] ?? null)     === 'profile-details'
			    && ($entry['context']['field_id'] ?? null)     === 'field_one';
		});
		self::assertNotEmpty($fieldLogs, 'Fieldset field update log missing.');
	}

	/**
	 * Smoke test: Complete AdminSettings fluent builder chain.
	 *
	 * Tests the full chain:
	 * menu_group() -> page() -> section() -> group() -> field() -> submit
	 *
	 * This is a smoke test - it verifies the fluent API chain executes without
	 * throwing errors. The actual data validation happens at runtime.
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
						->field('site_title', 'Site Title', 'fields.input', array('context' => array('default' => 'My Site')))
						->field('site_description', 'Site Description', 'textarea', array('context' => array('default' => 'A great site')))
					->end_group()
					->fieldset('contact-preferences', 'Contact Preferences')
						->style('highlighted')
						->required()
						->field('contact_email', 'Contact Email', 'fields.input')
					->end_fieldset()
					->field('enable_feature', 'Enable Feature', 'checkbox', array('context' => array('default' => false)))
				->end_section()
				->section('advanced-section', 'Advanced Settings')
					->field('api_key', 'API Key', 'fields.input', array('context' => array('default' => '')))
					->field('submit', 'Save Settings', 'submit-button')
				->end_section()
			->end_page()
		->end_menu_group();

		$pageLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                        === 'settings.builder.page.updated'
			    && ($entry['context']['page_slug'] ?? null) === 'test-page'
			    && ($entry['context']['group_id'] ?? null)  === 'test-menu-group';
		});
		self::assertNotEmpty($pageLogs, 'Page update log missing.');
		$pageContext = $this->findLatestContext($pageLogs, static function (array $context): bool {
			return ($context['page_slug'] ?? null) === 'test-page';
		});
		self::assertNotNull($pageContext, 'Page update log missing.');
		self::assertSame('Test Settings Page', $pageContext['heading']);
		self::assertSame('Test Settings', $pageContext['menu_title']);
		self::assertSame('manage_options', $pageContext['capability']);

		$sectionLog = $this->findLatestContext($this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.section.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'general-section';
		}));
		self::assertNotNull($sectionLog, 'General section metadata log missing.');
		self::assertTrue($sectionLog['has_before']);
		self::assertTrue($sectionLog['has_after']);

		$groupMetaLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'general-section'
			    && ($entry['context']['group_id'] ?? null)     === 'basic-group';
		});
		self::assertNotEmpty($groupMetaLogs, 'Group metadata log missing.');
		$groupLog = $this->findLatestContext($groupMetaLogs);
		self::assertTrue($groupLog['has_before']);
		self::assertTrue($groupLog['has_after']);

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'general-section';
		});
		self::assertNotEmpty($fieldLogs, 'Expected non-group field logs for general section.');
		$fieldOrder      = array();
		$fieldContextMap = array();
		foreach ($fieldLogs as $entry) {
			$context = $entry['context']    ?? array();
			$fieldId = $context['field_id'] ?? null;
			if ($fieldId === null) {
				continue;
			}
			if (!array_key_exists($fieldId, $fieldContextMap)) {
				$fieldOrder[] = $fieldId;
			}
			$fieldContextMap[$fieldId] = $context;
		}
		$generalFieldIds = array_map(static fn(string $id) => $id, $fieldOrder);
		self::assertSame(array('enable_feature'), $generalFieldIds);

		$groupMetaLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'general-section'
			    && ($entry['context']['group_id'] ?? null)     === 'contact-preferences';
		});
		self::assertNotEmpty($groupMetaLogs);
		$fieldSetMetadata = $this->findLatestContext($groupMetaLogs);
		self::assertNotNull($fieldSetMetadata);
		self::assertSame('highlighted', $fieldSetMetadata['style']);
		self::assertTrue($fieldSetMetadata['required']);

		$advancedFields = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'advanced-section';
		});
		$advancedOrder      = array();
		$advancedContextMap = array();
		foreach ($advancedFields as $entry) {
			$context = $entry['context']    ?? array();
			$fieldId = $context['field_id'] ?? null;
			if ($fieldId === null) {
				continue;
			}
			if (!array_key_exists($fieldId, $advancedContextMap)) {
				$advancedOrder[] = $fieldId;
			}
			$advancedContextMap[$fieldId] = $context;
		}
		self::assertSame(array('api_key', 'submit'), array_map(static fn(string $id) => $id, $advancedOrder));

		$basicGroupFieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'general-section'
			    && ($entry['context']['group_id'] ?? null)     === 'basic-group';
		});
		$basicGroupOrder      = array();
		$basicGroupContextMap = array();
		foreach ($basicGroupFieldLogs as $entry) {
			$context = $entry['context']    ?? array();
			$fieldId = $context['field_id'] ?? null;
			if ($fieldId === null) {
				continue;
			}
			if (!array_key_exists($fieldId, $basicGroupContextMap)) {
				$basicGroupOrder[] = $fieldId;
			}
			$basicGroupContextMap[$fieldId] = $context;
		}
		$basicGroupContexts = array_map(static fn(string $id) => $basicGroupContextMap[$id], $basicGroupOrder);
		self::assertSame(array('site_title', 'site_description'), array_column($basicGroupContexts, 'field_id'));
		self::assertSame(
			array('My Site', 'A great site'),
			array_map(static fn(array $context) => $context['component_context']['default'] ?? null, $basicGroupContexts)
		);
	}

	/**
	 * Smoke test: Multiple pages in same menu group.
	 *
	 * @test
	 */
	public function test_admin_settings_multiple_pages_in_menu_group(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('main-menu')
			->page('general-page')
				->heading('General')
				->section('general-section', 'General Section')
					->field('general_field', 'General Field', 'fields.input')
				->end_section()
			->end_page()
			->page('advanced-page')
				->heading('Advanced')
				->section('advanced-section', 'Advanced Section')
					->field('advanced_field', 'Advanced Field', 'fields.input')
				->end_section()
			->end_page()
		->end_menu_group();

		$pageLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                       === 'settings.builder.page.updated'
			    && ($entry['context']['group_id'] ?? null) === 'main-menu';
		});
		self::assertNotEmpty($pageLogs, 'Page update logs missing.');
		$pageContexts = array();
		foreach ($pageLogs as $entry) {
			$context = $entry['context']     ?? array();
			$slug    = $context['page_slug'] ?? null;
			if ($slug === null) {
				continue;
			}
			$pageContexts[$slug] = $context;
		}
		self::assertSame(array('general-page', 'advanced-page'), array_keys($pageContexts));
		self::assertSame('General', $pageContexts['general-page']['heading'] ?? null);
		self::assertSame('Advanced', $pageContexts['advanced-page']['heading'] ?? null);
	}

	/**
	 * Smoke test: Section with only groups (no standalone fields).
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
						->field('field_one', 'Field One', 'fields.input')
					->end_group()
					->group('group-two', 'Group Two')
						->field('field_two', 'Field Two', 'fields.input')
					->end_group()
				->end_section()
			->end_page()
		->end_menu_group();

		$groupMetadataLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'grouped-section';
		});
		$groupContexts = array();
		foreach ($groupMetadataLogs as $entry) {
			$context = $entry['context']    ?? array();
			$groupId = $context['group_id'] ?? null;
			if ($groupId === null) {
				continue;
			}
			$groupContexts[$groupId] = $context;
		}
		self::assertSame(array('group-one', 'group-two'), array_keys($groupContexts));

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'grouped-section';
		});
		self::assertSame(array(), array_column(array_map(static fn(array $entry): array => $entry['context'], $fieldLogs), 'field_id'), 'No standalone fields should be logged.');
	}

	/**
	 * Smoke test: Field ordering and indexing.
	 *
	 * @test
	 */
	public function test_admin_settings_field_ordering(): void {
		$admin = $this->createAdminSettings();

		$admin->menu_group('test-menu')
			->page('test-page')
				->section('test-section', 'Test Section')
					->field('field_three', 'Field Three', 'fields.input', array('order' => 30))
					->field('field_one', 'Field One', 'fields.input', array('order' => 10))
					->field('field_two', 'Field Two', 'fields.input', array('order' => 20))
					->group('group-one', 'Group One')
						->field('group_field_1', 'Grouped Field 1', 'fields.input', array(
							'context' => array('attributes' => array('placeholder' => 'Grouped Placeholder 1')),
							'order'   => 30,
						))
						->field('group_field_2', 'Grouped Field 2', 'fields.input', array(
							'context' => array('attributes' => array('placeholder' => 'Grouped Placeholder 2')),
							'order'   => 40,
						))
						->field('group_field_3', 'Grouped Field 3', 'fields.input', array(
							'context' => array('attributes' => array('placeholder' => 'Grouped Placeholder 3')),
							'order'   => 10,
						))
					->end_group()
				->end_section()
			->end_page()
		->end_menu_group();

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'test-section';
		});
		$fieldOrder      = array();
		$fieldContextMap = array();
		foreach ($fieldLogs as $entry) {
			$context = $entry['context']    ?? array();
			$fieldId = $context['field_id'] ?? null;
			if ($fieldId === null) {
				continue;
			}
			if (!array_key_exists($fieldId, $fieldContextMap)) {
				$fieldOrder[] = $fieldId;
			}
			$fieldContextMap[$fieldId] = $context;
		}
		$fieldContexts = array_map(static fn(string $id) => $fieldContextMap[$id], $fieldOrder);
		self::assertSame(array('field_three', 'field_one', 'field_two'), array_column($fieldContexts, 'field_id'));
		self::assertSame(array(30, 10, 20), array_map(static fn(array $context) => $context['order'], $fieldContexts));

		$groupFieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'test-page'
			    && ($entry['context']['section_id'] ?? null)   === 'test-section'
			    && ($entry['context']['group_id'] ?? null)     === 'group-one';
		});
		$groupFieldOrder      = array();
		$groupFieldContextMap = array();
		foreach ($groupFieldLogs as $entry) {
			$context = $entry['context']    ?? array();
			$fieldId = $context['field_id'] ?? null;
			if ($fieldId === null) {
				continue;
			}
			if (!array_key_exists($fieldId, $groupFieldContextMap)) {
				$groupFieldOrder[] = $fieldId;
			}
			$groupFieldContextMap[$fieldId] = $context;
		}
		$groupFieldContexts = array_map(static fn(string $id) => $groupFieldContextMap[$id], $groupFieldOrder);
		self::assertSame(array('group_field_1', 'group_field_2', 'group_field_3'), array_column($groupFieldContexts, 'field_id'));
		self::assertSame(array(30, 40, 10), array_map(static fn(array $context) => $context['order'], $groupFieldContexts));
		self::assertSame(
			array('Grouped Placeholder 1', 'Grouped Placeholder 2', 'Grouped Placeholder 3'),
			array_map(static fn (array $context) => $context['component_context']['attributes']['placeholder'] ?? null, $groupFieldContexts)
		);
	}

	/**
	 * Smoke test: Group builder chaining without explicit end_group().
	 *
	 * @test
	 */
	public function test_admin_settings_group_chain_without_explicit_end_group(): void {
		$admin = $this->createAdminSettings();

		$menuBuilder    = $admin->menu_group('implicit-group-menu');
		$pageBuilder    = $menuBuilder->page('implicit-group-page');
		$sectionBuilder = $pageBuilder->section('implicit-group-section', 'Implicit Group Section');
		$groupBuilder   = $sectionBuilder->group('implicit-group', 'Implicit Group');
		$groupBuilder->field('group_field', 'Implicit Field', 'fields.input');

		$groupBuilder->end_section()->end_page()->end_menu_group();

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
	 * Smoke test: Fieldset builder chaining without explicit end_fieldset().
	 *
	 * @test
	 */
	public function test_admin_settings_fieldset_chain_without_explicit_end_fieldset(): void {
		$admin = $this->createAdminSettings();

		$menuBuilder     = $admin->menu_group('implicit-fieldset-menu');
		$pageBuilder     = $menuBuilder->page('implicit-fieldset-page');
		$sectionBuilder  = $pageBuilder->section('implicit-fieldset-section', 'Implicit Fieldset Section');
		$fieldsetBuilder = $sectionBuilder->fieldset('implicit-fieldset', 'Implicit Fieldset');
		$fieldsetBuilder->field('contact_email', 'Implicit Field', 'fields.input');

		$fieldsetBuilder->end_section()->end_page()->end_menu_group();

		$reflection = new \ReflectionClass($admin);
		$groupsProp = $reflection->getProperty('groups');
		$groupsProp->setAccessible(true);
		$groups = $groupsProp->getValue($admin);

		$fieldset = $groups['implicit-fieldset-page']['implicit-fieldset-section']['implicit-fieldset'];
		$this->assertSame('Implicit Fieldset', $fieldset['title']);
		$this->assertCount(1, $fieldset['fields']);
	}
}
