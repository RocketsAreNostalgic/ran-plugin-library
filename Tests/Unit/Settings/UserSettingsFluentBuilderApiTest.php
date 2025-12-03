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
	 * Builder API test: Fieldset builder stores metadata and fields for user settings.
	 *
	 * @test
	 */
	public function test_user_settings_fieldset_builder_persists_metadata(): void {
		$user = $this->createUserSettings();

		$user->collection('profile')
			->style(static fn (): string => '  collection-style  ')
			->section('preferences', 'Preferences Section')
				->fieldset('profile-details', 'Profile Details', null, array('style' => 'highlighted'))
					->disabled(true)
					->field('field_three', 'Field Three', 'fields.input')->end_field()
				->end_fieldset()
			->end_section()
		->end();

		$groupMetadataLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'preferences'
			    && ($entry['context']['group_id'] ?? null)     === 'profile-details';
		});
		self::assertNotEmpty($groupMetadataLogs, 'Fieldset metadata log missing.');
		$fieldSetContext = $this->findLatestContext($groupMetadataLogs, static function (array $context): bool {
			return ($context['disabled'] ?? false) === true;
		});
		self::assertSame('Profile Details', $fieldSetContext['heading']);
		self::assertSame('highlighted', $fieldSetContext['style']);
		self::assertTrue($fieldSetContext['disabled']);

		$collectionLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.collection.updated'
			    && ($entry['context']['container_id'] ?? null) === 'profile';
		});
		self::assertNotEmpty($collectionLogs, 'Collection metadata log missing.');
		$collectionContext = $this->findLatestContext($collectionLogs);
		self::assertSame('collection-style', $collectionContext['style'] ?? null);

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'preferences'
			    && ($entry['context']['group_id'] ?? null)     === 'profile-details'
			    && ($entry['context']['field_id'] ?? null)     === 'field_three';
		});
		self::assertNotEmpty($fieldLogs, 'Fieldset field update log missing.');
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

		$sectionLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.section.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'display';
		});
		self::assertNotEmpty($sectionLogs, 'Display section metadata log missing.');
		$sectionContext = $this->findLatestContext($sectionLogs, static fn(array $context): bool => ($context['has_after'] ?? false) === true);
		self::assertNotNull($sectionContext, 'Display section metadata log missing.');
		self::assertTrue($sectionContext['has_before']);
		self::assertTrue($sectionContext['has_after']);

		$groupMetaLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'display'
			    && ($entry['context']['group_id'] ?? null)     === 'notifications';
		});
		self::assertNotEmpty($groupMetaLogs, 'Notifications group metadata log missing.');
		$groupContext = $this->findLatestContext($groupMetaLogs, static fn(array $context): bool => !empty($context['fields']));
		self::assertNotNull($groupContext, 'Notifications group metadata log missing.');
		self::assertTrue($groupContext['has_before']);
		self::assertTrue($groupContext['has_after']);
		self::assertSame(array('email_alerts'), $groupContext['fields']);

		$fieldLogsDisplay = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'display';
		});
		$displayOrder      = array();
		$displayContextMap = array();
		foreach ($fieldLogsDisplay as $entry) {
			$context = $entry['context']    ?? array();
			$fieldId = $context['field_id'] ?? null;
			if ($fieldId === null) {
				continue;
			}
			if (!array_key_exists($fieldId, $displayContextMap)) {
				$displayOrder[] = $fieldId;
			}
			$displayContextMap[$fieldId] = $context;
		}
		$displayContexts = array_map(static fn(string $id) => $displayContextMap[$id], $displayOrder);
		self::assertSame(array('display_name', 'bio'), array_column($displayContexts, 'field_id'));

		$fieldSetLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'display'
			    && ($entry['context']['group_id'] ?? null)     === 'contact-preferences';
		});
		self::assertNotEmpty($fieldSetLogs, 'Contact preferences metadata log missing.');
		$fieldsetContext = $this->findLatestContext($fieldSetLogs);
		self::assertNotNull($fieldsetContext);
		self::assertSame('minimal', $fieldsetContext['style']);
		self::assertTrue($fieldsetContext['disabled']);

		$notificationFieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'display'
			    && ($entry['context']['group_id'] ?? null)     === 'notifications';
		});
		self::assertNotEmpty($notificationFieldLogs, 'Notification group field log missing.');

		$contactFieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'display'
			    && ($entry['context']['group_id'] ?? null)     === 'contact-preferences';
		});
		self::assertNotEmpty($contactFieldLogs, 'Contact preferences field log missing.');
		self::assertSame('contact_method', $this->findLatestContext($contactFieldLogs)['field_id'] ?? null);

		$fieldLogsSecurity = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'security';
		});
		$securityOrder      = array();
		$securityContextMap = array();
		foreach ($fieldLogsSecurity as $entry) {
			$context = $entry['context']    ?? array();
			$fieldId = $context['field_id'] ?? null;
			if ($fieldId === null) {
				continue;
			}
			if (!array_key_exists($fieldId, $securityContextMap)) {
				$securityOrder[] = $fieldId;
			}
			$securityContextMap[$fieldId] = $context;
		}
		$securityContexts = array_map(static fn(string $id) => $securityContextMap[$id], $securityOrder);
		self::assertSame(array('two_factor', 'submit'), array_column($securityContexts, 'field_id'));
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

		$groupMetaLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'grouped-section';
		});
		$groupContexts = array();
		foreach ($groupMetaLogs as $entry) {
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
			    && ($entry['context']['container_id'] ?? null) === 'profile'
			    && ($entry['context']['section_id'] ?? null)   === 'grouped-section';
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
		self::assertSame(array(), array_column($fieldContexts, 'field_id'), 'No standalone fields should be logged.');
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

		$sectionLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message'] === 'settings.builder.section.updated'
			    && in_array($entry['context']['container_id'] ?? null, array('profile', 'preferences'), true);
		});
		$sectionContexts = array();
		foreach ($sectionLogs as $entry) {
			$context   = $entry['context']        ?? array();
			$container = $context['container_id'] ?? null;
			if ($container === null) {
				continue;
			}
			$sectionContexts[$container] = $context;
		}
		self::assertArrayHasKey('profile', $sectionContexts);
		self::assertArrayHasKey('preferences', $sectionContexts);

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message'] === 'settings.builder.field.updated'
			    && in_array($entry['context']['container_id'] ?? null, array('profile', 'preferences'), true);
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
		self::assertContains('display_name', array_column($fieldContexts, 'field_id'));
		self::assertContains('preferences_field', array_column($fieldContexts, 'field_id'));
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

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'profile'
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
			    && ($entry['context']['container_id'] ?? null) === 'profile'
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
		self::assertCount(1, $groupFieldContexts, 'Group should contain one field');
		$groupFieldContext = $groupFieldContexts[0];
		self::assertSame('group_field', $groupFieldContext['field_id']);
		self::assertSame(30, $groupFieldContext['order']);
		self::assertSame('Grouped Placeholder', $groupFieldContext['component_context']['attributes']['placeholder'] ?? null);
	}

	/**
	 * Builder API test: Group builder chaining without explicit end_group().
	 *
	 * @test
	 */
	public function test_user_settings_group_chain_without_explicit_end_group(): void {
		$user = $this->createUserSettings();

		$collectionBuilder = $user->collection('implicit-group-collection');
		$sectionBuilder    = $collectionBuilder->section('implicit-group-section', 'Implicit Group Section');
		$groupBuilder      = $sectionBuilder->group('implicit-group', 'Implicit Group');
		$groupBuilder->field('group_field', 'Implicit Field', 'fields.input')->end_field();

		$groupBuilder->end_section()->end_collection()->end();

		$groupMetaLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'implicit-group-collection'
			    && ($entry['context']['section_id'] ?? null)   === 'implicit-group-section'
			    && ($entry['context']['group_id'] ?? null)     === 'implicit-group';
		});
		self::assertNotEmpty($groupMetaLogs, 'Implicit group metadata log missing.');
		$groupContext = $this->findLatestContext($groupMetaLogs);
		self::assertNotNull($groupContext);

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'implicit-group-collection'
			    && ($entry['context']['section_id'] ?? null)   === 'implicit-group-section'
			    && ($entry['context']['group_id'] ?? null)     === 'implicit-group';
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
		self::assertSame(array('group_field'), $fieldOrder);

		$collectionLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.collection.updated'
			    && ($entry['context']['container_id'] ?? null) === 'implicit-group-collection';
		});
		self::assertNotEmpty($collectionLogs, 'Collection update log missing.');
	}

	/**
	 * Builder API test: Fieldset builder chaining without explicit end_fieldset().
	 *
	 * @test
	 */
	public function test_user_settings_fieldset_chain_without_explicit_end_fieldset(): void {
		$user = $this->createUserSettings();

		$collectionBuilder = $user->collection('implicit-fieldset-collection');
		$sectionBuilder    = $collectionBuilder->section('implicit-fieldset-section', 'Implicit Fieldset Section');
		$fieldsetBuilder   = $sectionBuilder->fieldset('implicit-fieldset', 'Implicit Fieldset');
		$fieldsetBuilder->field('contact_method', 'Implicit Field', 'fields.input')->end_field();

		$fieldsetBuilder->end_section()->end_collection()->end(); // final end() is no-op, but exists for API consistency with AdminSettings

		$fieldsetLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group.metadata'
			    && ($entry['context']['container_id'] ?? null) === 'implicit-fieldset-collection'
			    && ($entry['context']['section_id'] ?? null)   === 'implicit-fieldset-section'
			    && ($entry['context']['group_id'] ?? null)     === 'implicit-fieldset';
		});
		self::assertNotEmpty($fieldsetLogs, 'Implicit fieldset metadata log missing.');
		$fieldsetContext = $this->findLatestContext($fieldsetLogs);
		self::assertNotNull($fieldsetContext);
		self::assertSame('Implicit Fieldset', $fieldsetContext['heading']);

		$fieldLogs = $this->logger_mock->find_logs(static function (array $entry): bool {
			return $entry['message']                           === 'settings.builder.group_field.updated'
			    && ($entry['context']['container_id'] ?? null) === 'implicit-fieldset-collection'
			    && ($entry['context']['section_id'] ?? null)   === 'implicit-fieldset-section'
			    && ($entry['context']['group_id'] ?? null)     === 'implicit-fieldset';
		});
		self::assertSame(array('contact_method'), array_column(array_map(static fn(array $entry): array => $entry['context'], $fieldLogs), 'field_id'));
	}
}
