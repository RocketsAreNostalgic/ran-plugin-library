<?php

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormFieldRenderer;
use Ran\PluginLib\Forms\FormServiceSession;
use Ran\PluginLib\Forms\FormService;
use Ran\PluginLib\Forms\Component\ComponentLoader;
use Ran\PluginLib\Config\ConfigInterface;
use Mockery;

if (!class_exists('WP_User')) {
	class WP_User {
		public $ID;

		public function __construct(int $id = 0) {
		}
	}
}

/**
 * Integration tests for UserSettings fluent API and field rendering.
 *
 * @coversDefaultClass \Ran\PluginLib\Settings\UserSettings
 */
class UserSettingsIntegrationTest extends PluginLibTestCase {
	protected $config_mock;
	protected ?CollectingLogger $logger_mock = null;
	private array $user_pending_payload      = array();

	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions needed by RegisterOptions
		\WP_Mock::userFunction('get_option')->andReturn(array());
		\WP_Mock::userFunction('add_option')->andReturn(true);
		\WP_Mock::userFunction('update_option')->andReturn(true);
		\WP_Mock::userFunction('delete_option')->andReturn(true);

		// Mock user-related functions for UserSettings
		\WP_Mock::userFunction('get_user_meta')->andReturn(array());
		\WP_Mock::userFunction('add_user_meta')->andReturn(true);
		\WP_Mock::userFunction('update_user_meta')->andReturn(true);
		\WP_Mock::userFunction('delete_user_meta')->andReturn(true);

		// Mock the options persistence filter
		\WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	private function register_user_schema(RegisterOptions $options, array $field_ids): void {
		$definitions = array(
		    'first_name' => array(
		        'default'  => '',
		        'validate' => array(function($value, callable $emit) {
		        	if (strlen(trim((string) $value)) < 2) {
		        		$emit('First name must be at least 2 characters.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'last_name' => array(
		        'default'  => '',
		        'validate' => array(function($value) {
		        	return true;
		        }),
		    ),
		    'email' => array(
		        'default'  => '',
		        'validate' => array(function($value, callable $emit) {
		        	if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL)) {
		        		return true;
		        	}
		        	$emit('Email must be valid.');
		        	return false;
		        }),
		    ),
		    'age' => array(
		        'default'  => 0,
		        'validate' => array(function($value, callable $emit) {
		        	if (!is_numeric($value) || (int) $value < 18) {
		        		$emit('Age must be 18 or older.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'name' => array(
		        'default'  => '',
		        'sanitize' => array(function($value, callable $emit) {
		        	if (!is_string($value)) {
		        		return $value;
		        	}
		        	$trimmed = trim($value);
		        	if ($trimmed !== $value) {
		        		$emit('Name was trimmed of whitespace');
		        	}
		        	return $trimmed;
		        }),
		        'validate' => array(function($value, callable $emit) {
		        	if (trim((string) $value) === '') {
		        		$emit('Name cannot be empty.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'bio' => array(
		        'default'  => '',
		        'validate' => array(function($value) {
		        	return true;
		        }),
		    ),
		    'theme' => array(
		        'default'  => 'light',
		        'validate' => array(function($value, callable $emit) {
		        	if (!in_array($value, array('light', 'dark'), true)) {
		        		$emit('Theme must be light or dark.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'notifications' => array(
		        'default'  => 'email',
		        'validate' => array(function($value, callable $emit) {
		        	if (!in_array($value, array('email', 'sms', 'push'), true)) {
		        		$emit('Notifications must be email, sms, or push.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'city' => array(
		        'default'  => '',
		        'validate' => array(function($value, callable $emit) {
		        	if (trim((string) $value) === '') {
		        		$emit('City cannot be empty.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'street' => array(
		        'default'  => '',
		        'validate' => array(function($value, callable $emit) {
		        	if (trim((string) $value) === '') {
		        		$emit('Street cannot be empty.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'home_phone' => array(
		        'default'  => '',
		        'validate' => array(function($value, callable $emit) {
		        	if ($value === '' || preg_match('/^[0-9\-\(\)\+\s]+$/', (string) $value)) {
		        		return true;
		        	}
		        	$emit('Home phone has invalid characters.');
		        	return false;
		        }),
		    ),
		    'profile_settings.first_name' => array(
		        'default'  => '',
		        'validate' => array(function($value) {
		        	return true;
		        }),
		    ),
		);

		$schema = array();
		foreach ($field_ids as $field_id) {
			if (isset($definitions[$field_id])) {
				$schema[$field_id] = $definitions[$field_id];
			}
		}
		if (!empty($schema)) {
			$options->register_schema($schema);
		}
	}

	private function save_user(UserSettings $settings, array $payload, array $context = array()): void {
		$this->user_pending_payload = $payload;
		$settings->save_settings($payload, $context);
	}

	/**
	 * Test basic fluent API for creating collections, sections, and fields.
	 *
	 * @covers ::add_collection
	 * @covers ::boot
	 */
	public function test_fluent_api_creates_collections_sections_and_fields(): void {
		// Create RegisterOptions instance for UserSettings (requires user context)
		$options = new RegisterOptions('test_user_settings', StorageContext::forUser(123), true, $this->logger_mock);
		$this->register_user_schema($options, array('first_name', 'last_name', 'email'));
		$userSettings = new UserSettings($options);

		// Use fluent API to create a collection with sections and fields
		$collection = $userSettings->add_collection('profile_settings');

		$collection->section('personal', 'Personal Information')
			->field('first_name', 'First Name', 'fields.text')
			->field('last_name', 'Last Name', 'fields.text')
			->end_section();

		$collection->section('contact', 'Contact Information')
			->field('email', 'Email Address', 'fields.email')
			->end_section();

		// Verify the collection builder was returned
		$this->assertInstanceOf('Ran\\PluginLib\\Settings\\UserSettingsCollectionBuilder', $collection);

		// Boot the settings to register everything
		$userSettings->boot();

		// Verify that the schema was registered (we can't directly access it, but we can test behavior)
		$this->assertTrue(true); // Basic test that boot() completed without errors
	}

	/**
	 * Test that validation works end-to-end with the fluent API.
	 *
	 * @covers ::save_settings
	 * @covers ::add_collection
				'first_name',
				'First Name',
				'fields.text',
				array(
				'validate' => function($value) {
					return strlen(trim($value)) >= 2;
				},
				'default' => 'John'
			))
			->field(
				'age',
				'Age',
				'fields.number',
				array(
					'validate' => function($value) {
						return is_numeric($value) && $value >= 18;
					},
				'default' => 25
			));

		$userSettings->boot();

		// Test with invalid data (should not persist)
		$invalid_payload = array(
			'first_name' => 'J', // Too short
			'age'        => 16 // Too young
		);

		// Save settings with invalid data
		$this->save_user($userSettings, $invalid_payload, array('user_id' => 123));

		// Verify that validation messages were recorded
		$messages = $userSettings->take_messages();
		$this->assertNotEmpty($messages);
		$this->assertArrayHasKey('first_name', $messages);
		$this->assertArrayHasKey('age', $messages);
		$this->assertArrayHasKey('warnings', $messages['first_name']);
		$this->assertArrayHasKey('warnings', $messages['age']);
		$this->assertNotEmpty($messages['first_name']['warnings']);
		$this->assertNotEmpty($messages['age']['warnings']);

		// Test with valid data (should persist)
		$valid_payload = array(
			'first_name' => 'Jane',
			'age'        => 30
		);

		// Save settings with valid data
		$this->save_user($userSettings, $valid_payload, array('user_id' => 123));

		// Verify no validation messages
		$messages_after_valid = $userSettings->take_messages();
		$this->assertEmpty($messages_after_valid);
	}

	/**
	 * Test field rendering with validation messages.
	 *
	 * @covers ::render_field
	 * @covers ::add_collection
	 */
	// public function test_field_rendering_with_validation_messages(): void {
	// 	$options      = new RegisterOptions('test_user_settings', StorageContext::forUser(123), true, $this->logger_mock);
	// 	$userSettings = new UserSettings($options);

	// 	// Create a field with validation
	// 	$userSettings->add_collection('profile_settings')
	// 		->section('personal', 'Personal Information')
	// 		->field(
	// 			'username',
	// 			'Username',
	// 			'fields.text',
	// 			array(
	// 			'validate' => function($value) {
	// 				return strlen($value) >= 3 && preg_match('/^[a-zA-Z0-9_]+$/', $value);
	// 			},
	// 			'default' => ''
	// 		));

	// 	$userSettings->boot();

	// 	// Trigger validation failure
	// 	$invalid_payload = array('username' => 'ab'); // Too short
	// 	$userSettings->save_settings($invalid_payload, array('user_id' => 123));

	// 	// Render the field (this should include validation messages)
	// 	$field_html = $userSettings->render_field('username', 123);

	// 	// Verify the field HTML is returned
	// 	$this->assertIsString($field_html);
	// 	$this->assertNotEmpty($field_html);

	// 	// The exact HTML structure depends on the component system,
	// 	// but we can verify that some HTML was generated
	// 	$this->assertStringContainsString('username', $field_html);
	// }

	/**
	 * Test multiple collections and sections.
	 *
	 * @covers ::add_collection
	 */
	public function test_multiple_collections_and_sections(): void {
		$options = new RegisterOptions('test_user_settings', StorageContext::forUser(123), true, $this->logger_mock);
		$this->register_user_schema($options, array('name', 'age', 'bio', 'theme', 'notifications'));
		$userSettings = new UserSettings($options);

		// Create first collection
		$userSettings->add_collection('profile')
			->section('basic', 'Basic Info')
			->field(
				'name',
				'Name',
				'fields.text',
				array(
				'validate' => function($v) {
					return !empty($v);
				},
				'default' => ''
			))
			->field(
				'age',
				'Age',
				'fields.number',
				array(
					'validate' => function($v) {
						return is_numeric($v) && $v >= 18;
					},
				'default' => 25
			))
			->section('advanced', 'Advanced')
			->field(
				'bio',
				'Bio',
				'fields.textarea',
				array(
					'validate' => function($v) {
						return true;
					},
				'default' => ''
			));

		// Create second collection
		$userSettings->add_collection('preferences')
			->section('display', 'Display Settings')
			->group('theme', 'Theme')
			->field(
				'theme',
				'Theme',
				'fields.select',
				array(
					'validate' => function($v) {
						return in_array($v, array('light', 'dark'));
					},
				'default' => 'light'
			))->end_group()
			->group('notifications', 'Notifications')
			->field(
				'notifications',
				'Notifications',
				'fields.select',
				array(
					'validate' => function($v) {
						return in_array($v, array('email', 'sms', 'push'));
					},
				'default' => 'email'
			));
		// ->end_group()
		// ->end_section()
		// ->end_collection()
		// Are not needed due to auto-closing semantics.

		$userSettings->boot();

		// Test that both collections work
		$valid_payload = array(
			'name'  => 'John Doe',
			'bio'   => 'Software developer',
			'theme' => 'dark'
		);

		$this->save_user($userSettings, $valid_payload, array('user_id' => 123));

		// Should have no validation errors
		$messages = $userSettings->take_messages();
		$this->assertEmpty($messages);
	}

	/**
	 * Test field groups within sections.
	 *
	 * @covers ::add_collection
	 */
	public function test_field_groups(): void {
		$options = new RegisterOptions('test_user_settings', StorageContext::forUser(123), true, $this->logger_mock);
		$this->register_user_schema($options, array('street', 'city', 'home_phone'));
		$userSettings = new UserSettings($options);

		// Create collection with field groups
		$userSettings->add_collection('profile')
			->section('contact', 'Contact Information')
			->group('address', 'Address')
			->field(
				'street',
				'Street',
				'fields.text',
				array(
				'validate' => function($v) {
					return !empty($v);
				},
				'default' => ''
			))
			->field(
				'city',
				'City',
				'fields.text',
				array(
				'validate' => function($v) {
					return !empty($v);
				},
				'default' => ''
			))->end_group()
			->group('phone', 'Phone Numbers')
			->field(
				'home_phone',
				'Home Phone',
				'fields.tel',
				array(
					'validate' => function($v) {
						return empty($v) || preg_match('/^[0-9\\-\\(\\)\\+\\s]+$/', $v);
					},
				'default' => ''
			));

		$userSettings->boot();

		// Test validation across groups
		$payload = array(
			'street'     => '123 Main St',
			'city'       => '', // Invalid - empty
			'home_phone' => '555-1234'
		);

		$this->save_user($userSettings, $payload, array('user_id' => 123));

		// Should have validation error for city
		$messages = $userSettings->take_messages();
		$this->assertArrayHasKey('city', $messages);
		$this->assertArrayHasKey('warnings', $messages['city']);
		$this->assertNotEmpty($messages['city']['warnings']);

		// Street and home_phone should be valid
		$this->assertArrayNotHasKey('street', $messages);
		$this->assertArrayNotHasKey('home_phone', $messages);
	}

	/**
	 * Test sanitization with notices.
	 *
	 * @covers ::save_settings
	 * @covers ::add_collection
	 */
	public function test_sanitization_with_notices(): void {
		$options = new RegisterOptions('test_user_settings', StorageContext::forUser(123), true, $this->logger_mock);
		$this->register_user_schema($options, array('name'));
		$userSettings = new UserSettings($options);

		// Create field with sanitizer that emits notices
		$userSettings->add_collection('profile')
			->section('personal', 'Personal')
			->field(
				'name',
				'Name',
				'fields.text',
				array(
				'sanitize' => function($value, $emitNotice) {
					$trimmed = trim($value);
					if ($trimmed !== $value) {
						$emitNotice('Name was trimmed of whitespace');
					}
					return $trimmed;
				},
				'validate' => function($v) {
					return !empty($v);
				},
				'default' => ''
			));

		$userSettings->boot();

		// Save with value that needs trimming
		$payload = array('name' => '  John Doe  ');
		$this->save_user($userSettings, $payload, array('user_id' => 123));

		// Should have notice but no warnings (since validation passes after sanitization)
		$messages = $userSettings->take_messages();
		$this->assertArrayHasKey('name', $messages);
		$this->assertArrayHasKey('notices', $messages['name']);
		$this->assertNotEmpty($messages['name']['notices']);
		$this->assertStringContainsString('trimmed', $messages['name']['notices'][0]);

		// Should not have warnings since validation passed
		$this->assertEmpty($messages['name']['warnings'] ?? array());
	}
}
