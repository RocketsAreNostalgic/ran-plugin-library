<?php

namespace Ran\PluginLib\Tests\Unit\Settings;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\AdminSettingsPageBuilder;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Config\ConfigInterface;

/**
 * Integration tests for AdminSettings fluent API and field rendering.
 *
 * @coversDefaultClass \Ran\PluginLib\Settings\AdminSettings
 */
class AdminSettingsIntegrationTest extends PluginLibTestCase {
	protected $config_mock;
	protected ?CollectingLogger $logger_mock = null;
	private array $admin_pending_payload     = array();
	private array $option_store              = array();

	public function setUp(): void {
		parent::setUp();
		$this->logger_mock  = new CollectingLogger();
		$this->option_store = array();
		$store              = & $this->option_store;

		// Mock WordPress functions needed by RegisterOptions
		\WP_Mock::userFunction('get_option')->andReturnUsing(function($option_name, $default = false) use (&$store) {
			if (array_key_exists($option_name, $store)) {
				return $store[$option_name];
			}
			return func_num_args() >= 2 ? $default : false;
		});
		\WP_Mock::userFunction('add_option')->andReturnUsing(function($option_name, $value = '', $deprecated = '', $autoload = 'yes') use (&$store) {
			if (!array_key_exists($option_name, $store)) {
				$store[$option_name] = $value;
			}
			return true;
		});
		\WP_Mock::userFunction('update_option')->andReturnUsing(function($option_name, $value, $autoload = null) use (&$store) {
			$store[$option_name] = $value;
			return true;
		});
		\WP_Mock::userFunction('delete_option')->andReturnUsing(function($option_name) use (&$store) {
			unset($store[$option_name]);
			return true;
		});

		// Mock the options persistence filter
		\WP_Mock::onFilter('ran/plugin_lib/options/allow_persist')
			->with(\WP_Mock\Functions::type('bool'), \WP_Mock\Functions::type('array'))
			->reply(true);
	}

	private function register_admin_schema(RegisterOptions $options, array $field_ids): void {
		$definitions = array(
		    'site_title' => array(
		        'default'  => '',
		        'validate' => array(function($value) {
		        	return true;
		        }),
		    ),
		    'site_description' => array(
		        'default'  => '',
		        'validate' => array(function($value) {
		        	return true;
		        }),
		    ),
		    'admin_email' => array(
		        'default'  => '',
		        'validate' => array(function($value) {
		        	return true;
		        }),
		    ),
		    'required_field' => array(
		        'default'  => 'default_value',
		        'validate' => array(function($value, callable $emit) {
		        	$normalized = trim((string) $value);
		        	if ($normalized === '') {
		        		$emit('Required field must not be empty.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'numeric_field' => array(
		        'default'  => 10,
		        'validate' => array(function($value, callable $emit) {
		        	if (!is_numeric($value) || (float) $value <= 0) {
		        		$emit('Numeric field must be a positive number.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'trimmed_field' => array(
		        'default'  => '',
		        'sanitize' => array(function($value, callable $emit) {
		        	if (!is_string($value)) {
		        		return $value;
		        	}
		        	$trimmed = trim($value);
		        	if ($trimmed !== $value) {
		        		$emit('Value was trimmed');
		        	}
		        	return $trimmed;
		        }),
		        'validate' => array(function($value, callable $emit) {
		        	if (trim((string) $value) === '') {
		        		$emit('Trimmed field must not be empty.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'plugin_enabled' => array(
		        'default'  => true,
		        'validate' => array(function($value, callable $emit) {
		        	if (is_bool($value) || $value === '0' || $value === '1') {
		        		return true;
		        	}
		        	$emit('Enable API must be boolean-like.');
		        	return false;
		        }),
		    ),
		    'cache_timeout' => array(
		        'default'  => 3600,
		        'validate' => array(function($value, callable $emit) {
		        	if (!is_numeric($value) || (int) $value < 0) {
		        		$emit('Cache timeout must be zero or greater.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'test_field' => array(
		        'default'  => 'expected',
		        'validate' => array(function($value, callable $emit) {
		        	if ((string) $value !== 'expected') {
		        		$emit('Test field must equal expected.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'db_host' => array(
		        'default'  => 'localhost',
		        'validate' => array(function($value, callable $emit) {
		        	if (trim((string) $value) === '') {
		        		$emit('Database host must not be empty.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'db_port' => array(
		        'default'  => 3306,
		        'validate' => array(function($value, callable $emit) {
		        	if (!is_numeric($value)) {
		        		$emit('Database port must be numeric.');
		        		return false;
		        	}
		        	$port = (int) $value;
		        	if ($port <= 0 || $port > 65535) {
		        		$emit('Database port must be between 1 and 65535.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'db_username' => array(
		        'default'  => '',
		        'validate' => array(function($value, callable $emit) {
		        	if (trim((string) $value) === '') {
		        		$emit('Username must not be empty.');
		        		return false;
		        	}
		        	return true;
		        }),
		    ),
		    'db_password' => array(
		        'default'  => '',
		        'validate' => array(function($value) {
		        	return true;
		        }),
		    ),
		    'api_enabled' => array(
		        'default'  => false,
		        'validate' => array(function($value, callable $emit) {
		        	if (is_bool($value) || $value === '0' || $value === '1') {
		        		return true;
		        	}
		        	$emit('API enabled must be boolean-like.');
		        	return false;
		        }),
		    ),
		    'api_key' => array(
		        'default'  => '',
		        'validate' => array(function($value, callable $emit) {
		        	$enabled = !empty($this->admin_pending_payload['api_enabled']);
		        	if (!$enabled) {
		        		return true;
		        	}
		        	$trimmed = trim((string) $value);
		        	if ($trimmed === '' || strlen($trimmed) < 10) {
		        		$emit('API key must be at least 10 characters when API is enabled.');
		        		return false;
		        	}
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

	private function sanitize_admin(AdminSettings $settings, array $payload, array $previous = array()): array {
		$this->admin_pending_payload = $payload;
		return $settings->_sanitize($payload, $previous);
	}

	/**
	 * Test basic fluent API for creating pages, sections, and fields.
	 *
	 * @covers ::boot
	 */
	public function test_fluent_api_creates_pages_sections_and_fields(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('site_title', 'site_description', 'admin_email'));
		$adminSettings = new AdminSettings($options);

		$group = $adminSettings->menu_group('test_settings');
		$group->page_heading('Test Settings')->menu_label('Test Settings');
		// Use fluent API to create a settings group with pages, sections and fields
		$page = $group->page('general_page');

		$page->section('site_info', 'Site Information')
			->field('site_title', 'Site Title', 'fields.text')
			->field('site_description', 'Site Description', 'fields.textarea')
			->end_section();

		$page->section('contact', 'Contact Settings')
			->field('admin_email', 'Admin Email', 'fields.email')
			->end_section();

		// Verify the page builder was returned
		$this->assertInstanceOf('Ran\\PluginLib\\Settings\\AdminSettingsPageBuilder', $page);

		$group->end_group();

		$adminSettings->boot();

		// Verify that boot completed without errors
		$this->assertTrue(true);
	}

	/**
	 * Test WordPress Settings API integration with validation.
	 *
	 * @covers ::_sanitize
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::page
	 * @covers \Ran\PluginLib\Settings\AdminSettingsPageBuilder::section
	 */
	public function test_wordpress_settings_api_validation_integration(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('required_field', 'numeric_field'));
		$adminSettings = new AdminSettings($options);

		$group = $adminSettings->menu_group('test_settings');
		$group->page_heading('Test Settings')->menu_label('Test Settings');
		// Create fields with validation rules
		$group->page('test_settings')
			->page_heading('Test Settings')
			->section('validation_test', 'Validation Test')
			->field(
				'required_field',
				'Insert a text',
				'fields.text',
				array(
					'validate' => function($value) {
						return !empty(trim($value));
					},
					'default' => 'default_value'
			))
			->field(
				'numeric_field',
				'Insert a number',
				'fields.number',
				array(
					'validate' => function($value) {
						return is_numeric($value) && $value > 0;
					},
					'default' => 10
			));

		$group->end_group();

		$adminSettings->boot();

		// Simulate WordPress Settings API sanitization with invalid data
		$previous_options = array(
			'required_field' => 'default_value',
			'numeric_field'  => 10
		);
		\WP_Mock::userFunction('get_option')->andReturnUsing(function($option_name, $default = false) use ($previous_options) {
			if ($option_name === 'test_admin_settings') {
				return $previous_options;
			}
			return $default === false ? array() : $default;
		});
		$options->stage_options($previous_options)->commit_merge();
		$fetched_options = get_option('test_admin_settings', array());
		$this->assertEquals($previous_options, $fetched_options);
		$current_cached_options = $options->get_options();
		$this->assertEquals($previous_options, $current_cached_options);

		$invalid_payload = array(
			'required_field' => '', // Invalid - empty
			'numeric_field'  => -5   // Invalid - negative
		);

		// Call the sanitization method (this is what WordPress Settings API calls)
		$result                  = $this->sanitize_admin($adminSettings, $invalid_payload, $previous_options);
		$sanitizedPayloadFromLog = $this->get_latest_logger_context('AdminSettings::_sanitize returning sanitized payload.', 'sanitized_payload');
		if ($result !== $previous_options) {
			$this->fail(sprintf(
				'Sanitized payload mismatch. Expected: %s Actual: %s Logged sanitized payload: %s Logs: %s',
				json_encode($previous_options),
				json_encode($result),
				json_encode($sanitizedPayloadFromLog),
				json_encode($this->logger_mock?->get_logs())
			));
		}

		// Should have validation messages
		$messages = $adminSettings->take_messages();
		$this->assertNotEmpty($messages);
		$this->assertArrayHasKey('required_field', $messages);
		$this->assertArrayHasKey('numeric_field', $messages);
		$this->assertArrayHasKey('warnings', $messages['required_field']);
		$this->assertArrayHasKey('warnings', $messages['numeric_field']);

		\WP_Mock::userFunction('get_option')->andReturn(array());
	}

	private function get_latest_logger_context(string $message, string $contextKey) {
		if ($this->logger_mock === null) {
			return null;
		}
		$records = $this->logger_mock->get_logs();
		for ($i = count($records) - 1; $i >= 0; $i--) {
			$record = $records[$i];
			if ($record['message'] === $message && isset($record['context'][$contextKey])) {
				return $record['context'][$contextKey];
			}
		}
		return null;
	}

	/**
	 * Test successful validation returns sanitized data.
	 *
	 * @covers ::_sanitize
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::page
	 * @covers \Ran\PluginLib\Settings\SectionBuilder::field
	 */
	public function test_successful_validation_returns_sanitized_data(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('trimmed_field'));
		$adminSettings = new AdminSettings($options);

		// Create field with sanitizer and validator
		$group = $adminSettings->menu_group('test_settings');
		$group->page_heading('Test Settings')->menu_label('Test Settings');
		$group->page('test_settings')
			->page_heading('Test Settings')
			->section('sanitization_test', 'Sanitization Test')
			->field('trimmed_field', 'Trimmed Field', 'fields.text',
				array(
					'sanitize' => function($value, $emitNotice) {
						$trimmed = trim($value);
						if ($trimmed !== $value) {
							$emitNotice('Value was trimmed');
						}
						return $trimmed;
					},
				'validate' => function($value) {
					return !empty($value);
				},
				'default' => ''
			));

		$adminSettings->boot();

		$previous_options = array('trimmed_field' => '');
		$payload          = array('trimmed_field' => '  hello world  ');

		// Call sanitization
		$result = $this->sanitize_admin($adminSettings, $payload, $previous_options);

		// Should return sanitized data
		$this->assertEquals(array('trimmed_field' => 'hello world'), $result);

		// Should have notice but no warnings
		$messages = $adminSettings->take_messages();
		$this->assertArrayHasKey('trimmed_field', $messages);
		$this->assertArrayHasKey('notices', $messages['trimmed_field']);
		$this->assertNotEmpty($messages['trimmed_field']['notices']);
		$this->assertEmpty($messages['trimmed_field']['warnings'] ?? array());
	}

	/**
	 * Test multiple pages and menu groups.
	 *
	 * @covers ::menu_group
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::page
	 * @covers \Ran\PluginLib\Settings\AdminSettingsPageBuilder::section
	 */
	public function test_multiple_pages_and_menu_groups(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('plugin_enabled', 'cache_timeout'));
		$adminSettings = new AdminSettings($options);

		$menuGroup = $adminSettings->menu_group('my_plugin');
		$menuGroup->page_heading('My Plugin')->menu_label('My Plugin')->capability('manage_options');

		$generalPage = $menuGroup->page('general');
		$generalPage->page_heading('General Settings')
			->section('basic', 'Basic Settings')
			->field('plugin_enabled', 'Enable Plugin', 'fields.checkbox',
				array(
				    'validate' => function($v) {
				    	return is_bool($v) || in_array($v, array('0', '1'));
				    },
				    'default' => true
				))
			->end_section();
		$generalPage->end_page();

		$menuGroup->page('advanced')
			->page_heading('Advanced Settings')
			->section('performance', 'Performance')
			->field(
				'cache_timeout',
				'Cache Timeout (seconds)',
				'fields.number',
				array(
				    'validate' => function($v) {
				    	return is_numeric($v) && $v >= 0;
				    },
				    'default' => 3600
				))
			->end_section();

		$menuGroup->end_group();
		$adminSettings->boot();

		$valid_payload = array(
		    'plugin_enabled' => true,
		    'cache_timeout'  => 7200
		);
		$this->sanitize_admin($adminSettings, $valid_payload, array());

		$messages = $adminSettings->take_messages();
		$this->assertEmpty($messages);
	}

	/**
	 * Test field rendering with template context.
	 *
	 * @covers ::render_page
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::page
	 * @covers \Ran\PluginLib\Settings\SectionBuilder::field
	 */
	public function test_field_rendering_with_template_context(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('test_field'));
		$adminSettings = new AdminSettings($options);

		// Create a simple page with a field
		$group = $adminSettings->menu_group('test_settings');
		$page  = $group->page('render_test');
		$page->section('test_section', 'Test Section')
			->field('test_field', 'Test Field', 'fields.text',
				array(
					'validate' => function($value) {
						return $value === 'expected';
					},
					'default' => 'expected'
				));

		$group->end_group();

		$adminSettings->boot();

		// Trigger validation failure to generate messages
		$invalid_payload = array('test_field' => 'ab'); // Too short
		$this->sanitize_admin($adminSettings, $invalid_payload, array('test_field' => 'test'));

		// Render the page
		ob_start();
		$adminSettings->render_page('render_test');
		$page_html = (string) ob_get_clean();

		// Verify HTML was generated
		$this->assertIsString($page_html);
		$this->assertNotEmpty($page_html);

		// Should contain some expected elements
		$this->assertStringContainsString('<form', $page_html);
		$this->assertStringContainsString('Settings', $page_html);
	}

	/**
	 * Test field groups within sections.
	 *
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::page
	 * @covers \Ran\PluginLib\Settings\SectionBuilder::group
	 * @covers \Ran\PluginLib\Settings\SectionGroupBuilder::field
	 */
	public function test_groups_in_admin_settings(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('db_host', 'db_port', 'db_username', 'db_password'));
		$adminSettings = new AdminSettings($options);

		$group = $adminSettings->menu_group('test_settings');
		$group->page_heading('Test Settings')->menu_label('Test Settings');
		$group->page('grouped_settings')
			->page_heading('Grouped Settings')
			->section('database', 'Database Settings')
			->group('connection', 'Connection Settings')
			->field(
				'db_host',
				'Database Host',
				'fields.text',
				array(
					'validate' => function($v) {
						return !empty($v);
					},
				'default' => 'localhost'
			))
			->field(
				'db_port',
				'Database Port',
				'fields.number',
				array(
					'validate' => function($v) {
						return is_numeric($v) && $v > 0 && $v <= 65535;
					},
				'default' => 3306
			))
			->end_group()
			->group('credentials', 'Credentials')
			->field(
				'db_username',
				'Username',
				'fields.text',
				array(
					'validate' => function($v) {
						return !empty($v);
					},
				'default' => ''
			))
			->field(
				'db_password',
				'Password',
				'fields.password',
				array(
					'validate' => function($v) {
						return true;
					}, // Password can be empty
				'default' => ''
			));

		$adminSettings->boot();

		// Test validation across groups
		$payload = array(
			'db_host'     => 'localhost',
			'db_port'     => 99999, // Invalid - too high
			'db_username' => 'admin',
			'db_password' => 'secret'
		);

		$result = $this->sanitize_admin($adminSettings, $payload, array());

		// Should have validation error for db_port
		$messages = $adminSettings->take_messages();
		$this->assertArrayHasKey('db_port', $messages);
		$this->assertArrayHasKey('warnings', $messages['db_port']);
		$this->assertNotEmpty($messages['db_port']['warnings']);

		// Other fields should be valid
		$this->assertArrayNotHasKey('db_host', $messages);
		$this->assertArrayNotHasKey('db_username', $messages);
		$this->assertArrayNotHasKey('db_password', $messages);
	}

	/**
	 * Test conditional field validation.
	 *
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::page
	 * @covers \Ran\PluginLib\Settings\SectionBuilder::field
	 */
	public function test_conditional_field_validation(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('api_enabled', 'api_key'));
		$adminSettings = new AdminSettings($options);

		// Create fields with conditional validation
		$group = $adminSettings->menu_group('test_settings');
		$group->page_heading('Test Settings')->menu_label('Test Settings');
		$group->page('conditional_settings')
			->page_heading('Conditional Settings')
			->section('api', 'API Settings')
			->field(
				'api_enabled',
				'Enable API',
				'fields.checkbox',
				array(
					'validate' => function($v) {
						return is_bool($v) || in_array($v, array('0', '1'));
					},
				'default' => false
			))
			->field(
				'api_key',
				'API Key',
				'fields.text',
				array(
					'validate' => function($value, $all_values) {
						// Only validate if API is enabled
						if (!empty($all_values['api_enabled'])) {
							return !empty(trim($value)) && strlen($value) >= 10;
						}
						return true; // Valid if API is disabled
					},
				'default' => ''
			));

		$adminSettings->boot();

		// Test with API enabled but invalid key
		$payload = array(
			'api_enabled' => true,
			'api_key'     => 'short' // Too short
		);

		$result = $this->sanitize_admin($adminSettings, $payload, array());

		// Should have validation error for api_key
		$messages = $adminSettings->take_messages();
		$this->assertArrayHasKey('api_key', $messages);
		$this->assertArrayHasKey('warnings', $messages['api_key']);

		// Test with API disabled (should be valid regardless of key)
		$payload2 = array(
			'api_enabled' => false,
			'api_key'     => 'short' // Still short, but API is disabled
		);

		$result2 = $this->sanitize_admin($adminSettings, $payload2, array());

		// Should have no validation errors
		$messages2 = $adminSettings->take_messages();
		$this->assertEmpty($messages2);
	}

	/**
	 * Test that warnings are properly extracted for admin notices.
	 *
	 * @covers ::take_messages
	 * @covers \Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::page
	 * @covers \Ran\PluginLib\Settings\SectionBuilder::field
	 */
	public function test_warnings_extraction_for_admin_notices(): void {
		$options = new RegisterOptions('test_admin_settings', StorageContext::forSite(), true, $this->logger_mock);
		$this->register_admin_schema($options, array('test_field'));
		$adminSettings = new AdminSettings($options);


		// Create field that will fail validation
		$group = $adminSettings->menu_group('test_settings');
		$group->page_heading('Test Settings')->menu_label('Test Settings');
		$group->page('warning_test')
			->page_heading('Warning Test')
			->section('test', 'Test Section')
			->field('test_field', 'Test Field', 'fields.text',
				array(
					'validate' => function($v) {
						return strlen($v) >= 5;
					},
				'default' => 'default'
			));

		$group->end_group();

		$adminSettings->boot();

		// Trigger validation failure
		$payload = array('test_field' => 'abc'); // Too short
		$this->sanitize_admin($adminSettings, $payload, array('test_field' => 'default'));

		// Extract warnings (this is what would be used for admin notices)
		$messages = $adminSettings->take_messages();
		$this->assertArrayHasKey('test_field', $messages);
		$this->assertArrayHasKey('warnings', $messages['test_field']);
		$this->assertNotEmpty($messages['test_field']['warnings']);
		$this->assertIsString($messages['test_field']['warnings'][0]);

		// Verify warnings are cleared after taking them
		$this->assertEmpty($adminSettings->take_messages());
	}
}
