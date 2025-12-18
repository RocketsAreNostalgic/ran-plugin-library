<?php
/**
 * UserSettings Fluent Builder API Test
 *
 * Tests the complete fluent builder chain mechanics for UserSettings:
 * collection() -> section() -> group() -> field() -> submit
 *
 * Validates builder API chaining, metadata storage, and logging.
 * Does NOT test actual component rendering - see UserSettingsBehaviorTest for render tests.
 *
 * @package Ran\PluginLib\Tests\Unit\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Settings;

use WP_Mock\Tools\TestCase;
use Ran\PluginLib\Util\ExpectLogTrait;
use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Settings\UserSettingsSectionBuilder;
use Ran\PluginLib\Settings\UserSettingsCollectionBuilder;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * Builder API tests for the UserSettings fluent builder chain.
 *
 * @covers \Ran\PluginLib\Settings\UserSettings
 * @covers \Ran\PluginLib\Settings\UserSettingsCollectionBuilder
 * @covers \Ran\PluginLib\Settings\UserSettingsSectionBuilder
 * @covers \Ran\PluginLib\Settings\UserSettingsGroupBuilder
 * @covers \Ran\PluginLib\Settings\UserSettingsFieldsetBuilder
 */
class UserSettingsFluentBuilderApiTest extends TestCase {
	use ExpectLogTrait;

	protected CollectingLogger $logger_mock;
	public function setUp(): void {
		\WP_Mock::setUp();

		// Mock WordPress functions needed by ComponentManifest, RegisterOptions, and UserSettings
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

		$this->logger_mock = new CollectingLogger();
	}


	public function tearDown(): void {
		\WP_Mock::tearDown();
	}

	/**
	 * Helper: Create UserSettings instance with proper dependencies.
	 */
	private function createUserSettings(): UserSettings {
		$logger     = $this->logger_mock;
		$baseDir    = dirname(__DIR__, 3) . '/inc/Forms/Components';
		$loader     = new ComponentLoader($baseDir, $logger);
		$components = new ComponentManifest($loader, $logger);
		$options    = RegisterOptions::user('test_user_options', 123, false, $logger);

		$options->register_schema(array(
			'display_name'       => $this->stringSchema(),
			'bio'                => $this->stringSchema(),
			'two_factor'         => $this->booleanSchema(),
			'preferences_field'  => $this->stringSchema(),
			'field_one'          => $this->stringSchema(),
			'field_two'          => $this->stringSchema(),
			'field_three'        => $this->stringSchema(),
			'group_field'        => $this->stringSchema(),
			'group_field_1'      => $this->stringSchema(),
			'group_field_2'      => $this->stringSchema(),
			'group_field_3'      => $this->stringSchema(),
			'contact_method'     => $this->stringSchema(),
			'email_alerts'       => $this->booleanSchema(),
			'profile_visibility' => $this->stringSchema(),
			'submit'             => $this->stringSchema(),
		));

		return new UserSettings($options, $components, null, $logger);
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
	 * Helper: Get a property value from UserSettings via reflection.
	 */
	private function getProperty(UserSettings $user, string $property): mixed {
		$reflection = new \ReflectionClass($user);
		$prop       = $reflection->getProperty($property);
		$prop->setAccessible(true);
		return $prop->getValue($user);
	}

	/**
	 * Builder API test: Fieldset builder stores metadata and fields for user settings.
	 *
	 * @test
	 */
	public function test_user_settings_fieldset_builder_persists_metadata(): void {
		$user = $this->createUserSettings();

		$collection_style = static fn (): string => '  collection-style  ';
		$user->collection('profile')
			->style($collection_style)
			->section('preferences', 'Preferences Section')
				->fieldset('profile-details', 'Profile Details', null, array('style' => 'highlighted'))
					->disabled(true)
					->field('field_three', 'Field Three', 'fields.input')->end_field()
				->end_fieldset()
			->end_section()
		->end();

		// Verify state directly instead of logs
		$groups = $this->getProperty($user, 'groups');
		self::assertArrayHasKey('profile', $groups);
		self::assertArrayHasKey('preferences', $groups['profile']);
		self::assertArrayHasKey('profile-details', $groups['profile']['preferences']);

		$fieldset = $groups['profile']['preferences']['profile-details'];
		self::assertSame('Profile Details', $fieldset['title']);
		self::assertSame('highlighted', $fieldset['style']);
		self::assertTrue($fieldset['disabled']);
		self::assertCount(1, $fieldset['fields']);
		self::assertSame('field_three', $fieldset['fields'][0]['id']);

		$collections = $this->getProperty($user, 'collections');
		self::assertArrayHasKey('profile', $collections);
		self::assertSame($collection_style, $collections['profile']['style'] ?? null);
	}

	/**
	 * Builder API test: Complete UserSettings fluent builder chain.
	 *
	 * @test
	 */
	public function test_user_settings_complete_fluent_chain(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->heading('User Profile Settings')
			->section('display', 'Display Preferences')
				->before(function (): string {
					return '<p class="user-section-before">USER SECTION BEFORE</p>';
				})
				->after(function (): string {
					return '<p class="user-section-after">USER SECTION AFTER</p>';
				})
				->field('display_name', 'Display Name', 'fields.input', array('context' => array('default' => 'Test User')))->end_field()
				->field('bio', 'Biography', 'fields.textarea', array('context' => array('default' => 'Test biography')))->end_field()
				->group('notifications', 'Notification Preferences')
					->before(function (): string {
						return '<span class="user-group-before">USER GROUP BEFORE</span>';
					})
					->after(function (): string {
						return '<span class="user-group-after">USER GROUP AFTER</span>';
					})
					->field(
						'email_alerts',
						'Email Alerts',
						'fields.input',
						array('context' => array('default' => ''))
					)
						->order(30)->end_field()
				->end_group()
				->fieldset('contact-preferences', 'Contact Preferences')
					->style('minimal')
					->disabled()
					->field('contact_method', 'Preferred Contact Method', 'fields.input')->end_field()
				->end_fieldset()
			->end_section()
			->section('security', 'Security Settings')
				->field('two_factor', 'Two-Factor Authentication', 'fields.input', array('context' => array('default' => '')))->end_field()
				->field('submit', 'Save Settings', 'fields.input')->end_field()
			->end_section()
		->end();

		// Verify state directly instead of logs
		$sections = $this->getProperty($user, 'sections');
		self::assertArrayHasKey('profile', $sections);
		self::assertArrayHasKey('display', $sections['profile']);
		$displaySection = $sections['profile']['display'];
		self::assertNotNull($displaySection['before']);
		self::assertNotNull($displaySection['after']);

		$groups = $this->getProperty($user, 'groups');
		self::assertArrayHasKey('profile', $groups);
		self::assertArrayHasKey('display', $groups['profile']);
		self::assertArrayHasKey('notifications', $groups['profile']['display']);
		$notificationsGroup = $groups['profile']['display']['notifications'];
		self::assertNotNull($notificationsGroup['before']);
		self::assertNotNull($notificationsGroup['after']);
		self::assertSame(array('email_alerts'), array_column($notificationsGroup['fields'], 'id'));

		$fields = $this->getProperty($user, 'fields');
		self::assertArrayHasKey('profile', $fields);
		self::assertArrayHasKey('display', $fields['profile']);
		$displayFieldIds = array_column($fields['profile']['display'], 'id');
		self::assertSame(array('display_name', 'bio'), $displayFieldIds);

		$contactPrefs = $groups['profile']['display']['contact-preferences'];
		self::assertSame('minimal', $contactPrefs['style']);
		self::assertTrue($contactPrefs['disabled']);
		self::assertSame(array('contact_method'), array_column($contactPrefs['fields'], 'id'));

		self::assertArrayHasKey('security', $fields['profile']);
		$securityFieldIds = array_column($fields['profile']['security'], 'id');
		self::assertSame(array('two_factor', 'submit'), $securityFieldIds);
	}

	/**
	 * Builder API test: Section with only groups (no standalone fields).
	 *
	 * @test
	 */
	public function test_user_settings_section_with_only_groups(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('grouped-section', 'Grouped Section')
				->heading('Grouped Settings')
				->group('group-one', 'Group One')
					->field('field_one', 'Field One', 'fields.input')->end_field()
				->end_group()
				->group('group-two', 'Group Two')
					->field('field_two', 'Field Two', 'fields.input')->end_field()
				->end_group()
			->end_section()
		->end();

		// Verify state directly instead of logs
		$groups = $this->getProperty($user, 'groups');
		self::assertArrayHasKey('profile', $groups);
		self::assertArrayHasKey('grouped-section', $groups['profile']);
		self::assertSame(array('group-one', 'group-two'), array_keys($groups['profile']['grouped-section']));

		$fields           = $this->getProperty($user, 'fields');
		$standaloneFields = $fields['profile']['grouped-section'] ?? array();
		self::assertSame(array(), array_column($standaloneFields, 'id'), 'No standalone fields should exist.');
	}

	/**
	 * Builder API test: Multiple collections with sections.
	 *
	 * @test
	 */
	public function test_user_settings_multiple_collections(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->heading('Profile Information')
			->section('basic', 'Basic Information')
				->field('display_name', 'Display Name', 'fields.input')->end_field()
			->end_section()
		->end_collection()
		->collection('preferences')
			->heading('User Preferences')
			->section('general', 'General Preferences')
				->field('preferences_field', 'Preferences Field', 'fields.input')->end_field()
			->end_section()
		->end();

		// Verify state directly instead of logs
		$sections = $this->getProperty($user, 'sections');
		self::assertArrayHasKey('profile', $sections);
		self::assertArrayHasKey('preferences', $sections);

		$fields          = $this->getProperty($user, 'fields');
		$profileFieldIds = array_column($fields['profile']['basic'] ?? array(), 'id');
		$prefsFieldIds   = array_column($fields['preferences']['general'] ?? array(), 'id');
		self::assertContains('display_name', $profileFieldIds);
		self::assertContains('preferences_field', $prefsFieldIds);
	}

	/**
	 * Builder API test: Field ordering and indexing.
	 *
	 * @test
	 */
	public function test_user_settings_field_ordering(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->section('test-section', 'Test Section')
				->field('field_three', 'Field Three', 'fields.input', array('order' => 30))->end_field()
				->field('field_one', 'Field One', 'fields.input', array('order' => 10))->end_field()
				->field('field_two', 'Field Two', 'fields.input', array('order' => 20))->end_field()
				->group('group-one', 'Group One')
					->field(
						'group_field',
						'Grouped Field',
						'fields.input',
						array(
							'context' => array('attributes' => array('placeholder' => 'Grouped Placeholder')),
							'order'   => 30,
						)
					)->end_field()
				->end_group()
			->end_section()
		->end();

		// Verify state directly instead of logs
		$fields = $this->getProperty($user, 'fields');
		self::assertArrayHasKey('profile', $fields);
		self::assertArrayHasKey('test-section', $fields['profile']);
		$sectionFields = $fields['profile']['test-section'];
		self::assertSame(array('field_three', 'field_one', 'field_two'), array_column($sectionFields, 'id'));
		self::assertSame(array(30, 10, 20), array_column($sectionFields, 'order'));

		$groups      = $this->getProperty($user, 'groups');
		$groupFields = $groups['profile']['test-section']['group-one']['fields'];
		self::assertCount(1, $groupFields, 'Group should contain one field');
		self::assertSame('group_field', $groupFields[0]['id']);
		self::assertSame(30, $groupFields[0]['order']);
		self::assertSame('Grouped Placeholder', $groupFields[0]['component_context']['attributes']['placeholder'] ?? null);
	}

	/**
	 * Builder API test: Group builder explicit chaining.
	 *
	 * @test
	 */
	public function test_user_settings_group_explicit_chain(): void {
		$user = $this->createUserSettings();

		$collectionBuilder = $user->collection('implicit-group-collection');
		$sectionBuilder    = $collectionBuilder->section('implicit-group-section', 'Implicit Group Section');
		$groupBuilder      = $sectionBuilder->group('implicit-group', 'Implicit Group');
		$groupBuilder->field('group_field', 'Implicit Field', 'fields.input')->end_field();

		$groupBuilder->end_group()->end_section()->end_collection()->end();

		// Verify state directly instead of logs
		$groups = $this->getProperty($user, 'groups');
		self::assertArrayHasKey('implicit-group-collection', $groups);
		self::assertArrayHasKey('implicit-group-section', $groups['implicit-group-collection']);
		self::assertArrayHasKey('implicit-group', $groups['implicit-group-collection']['implicit-group-section']);

		$group = $groups['implicit-group-collection']['implicit-group-section']['implicit-group'];
		self::assertSame('Implicit Group', $group['title']);
		self::assertSame(array('group_field'), array_column($group['fields'], 'id'));

		$collections = $this->getProperty($user, 'collections');
		self::assertArrayHasKey('implicit-group-collection', $collections);
	}

	/**
	 * Builder API test: Fieldset builder explicit chaining.
	 *
	 * @test
	 */
	public function test_user_settings_fieldset_explicit_chain(): void {
		$user = $this->createUserSettings();

		$collectionBuilder = $user->collection('implicit-fieldset-collection');
		$sectionBuilder    = $collectionBuilder->section('implicit-fieldset-section', 'Implicit Fieldset Section');
		$fieldsetBuilder   = $sectionBuilder->fieldset('implicit-fieldset', 'Implicit Fieldset');
		$fieldsetBuilder->field('contact_method', 'Implicit Field', 'fields.input')->end_field();

		$fieldsetBuilder->end_fieldset()->end_section()->end_collection()->end(); // final end() is no-op, but exists for API consistency with AdminSettings

		// Verify state directly instead of logs
		$groups = $this->getProperty($user, 'groups');
		self::assertArrayHasKey('implicit-fieldset-collection', $groups);
		self::assertArrayHasKey('implicit-fieldset-section', $groups['implicit-fieldset-collection']);
		self::assertArrayHasKey('implicit-fieldset', $groups['implicit-fieldset-collection']['implicit-fieldset-section']);

		$fieldset = $groups['implicit-fieldset-collection']['implicit-fieldset-section']['implicit-fieldset'];
		self::assertSame('Implicit Fieldset', $fieldset['title']);
		self::assertSame(array('contact_method'), array_column($fieldset['fields'], 'id'));
	}
}
