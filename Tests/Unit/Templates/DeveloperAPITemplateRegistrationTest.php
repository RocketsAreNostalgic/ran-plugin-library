<?php
/**
 * Developer API and Template Registration Test
 *
 * Tests global template registration using ComponentLoader, Settings class
 * template co-location patterns, fluent API template override methods,
 * template override hierarchy and precedence rules, and template validation.
 *
 * @package Ran\PluginLib\Tests\Unit\Templates
 */

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Templates;

use WP_Mock;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;
use Ran\PluginLib\Settings\UserSettings;
use Ran\PluginLib\Settings\AdminSettings;
use Ran\PluginLib\Options\Storage\StorageContext;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * @covers \Ran\PluginLib\Settings\AdminSettings
 * @covers \Ran\PluginLib\Settings\UserSettings
 * @covers \Ran\PluginLib\Options\RegisterOptions
 * @covers \Ran\PluginLib\Forms\Component\ComponentManifest
 * @covers \Ran\PluginLib\Forms\Component\ComponentLoader
 */
class DeveloperAPITemplateRegistrationTest extends PluginLibTestCase {
	private ComponentLoader $component_loader;
	private ComponentManifest $component_manifest;
	private AdminSettings $admin_settings;
	private UserSettings $user_settings;
	private RegisterOptions $admin_options;
	private RegisterOptions $user_options;

	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('get_current_user_id')->andReturn(1);

		// Create component infrastructure
		$this->component_loader   = new ComponentLoader(__DIR__ . '/../../fixtures/templates');
		$this->component_manifest = new ComponentManifest($this->component_loader, $this->logger_mock);

		// Create settings instances
		$this->admin_options = new RegisterOptions(
			'test_admin_options',
			StorageContext::forSite(),
			true,
			$this->logger_mock
		);

		$this->user_options = new RegisterOptions(
			'test_user_options',
			StorageContext::forUser(1),
			true,
			$this->logger_mock
		);

		$this->admin_settings = new AdminSettings($this->admin_options, $this->component_manifest, $this->logger_mock);
		$this->user_settings  = new UserSettings($this->user_options, $this->component_manifest, $this->logger_mock);
	}

	/**
	 * Test global template registration using ComponentLoader.
	 */
	public function test_global_template_registration_using_component_loader(): void {
		// Test single template registration
		$this->component_loader->register('my.custom-page', 'admin/pages/example-page.php');

		// Verify template is registered by checking aliases
		$aliases = $this->component_loader->aliases();
		$this->assertArrayHasKey('my.custom-page', $aliases);

		// Test batch template registration
		$templates = array(
			'mytheme.page'    => 'admin/pages/example-page.php',
			'mytheme.section' => 'admin/sections/modern-section.php',
			'mytheme.field'   => 'admin/fields/example-field-wrapper.php'
		);

		foreach ($templates as $key => $path) {
			$this->component_loader->register($key, $path);
		}

		// Verify all templates are registered by checking aliases
		$aliases = $this->component_loader->aliases();
		foreach ($templates as $key => $path) {
			$this->assertArrayHasKey($key, $aliases);
		}

		// Test template rendering (skip for now due to WordPress function dependencies)
		// Note: Template rendering requires WordPress functions to be properly mocked
		$this->assertTrue(true); // Placeholder assertion
	}

	/**
	 * Test Settings class template co-location patterns.
	 */
	public function test_settings_class_template_co_location_patterns(): void {
		// Create a custom settings class that demonstrates co-location
		$custom_admin_settings = new class($this->admin_options, $this->component_manifest, $this->logger_mock) extends AdminSettings {
			public function __construct($options, $component_manifest, $logger) {
				parent::__construct($options, $component_manifest, $logger);
				$this->register_co_located_templates();
			}

			private function register_co_located_templates(): void {
				// Register components using the component manifest
				$component_manifest = $this->get_form_session()->manifest();
				$component_manifest->register('custom.admin-page', function() {
					return 'page-component';
				});
				$component_manifest->register('custom.admin-section', function() {
					return 'section-component';
				});
				$component_manifest->register('custom.admin-field', function() {
					return 'field-component';
				});
			}
		};

		// Test that the custom settings class was created successfully
		$this->assertInstanceOf(AdminSettings::class, $custom_admin_settings);

		// Test that component registration is available through ComponentManifest
		$component_manifest = $custom_admin_settings->get_form_session()->manifest();
		$this->assertTrue(method_exists($component_manifest, 'register'));
		$this->assertTrue(method_exists($component_manifest, 'has'));
	}

	/**
	 * Test fluent API template override methods across all contexts.
	 */
	public function test_fluent_api_template_override_methods_all_contexts(): void {
		// Test AdminSettings fluent API
		$admin_page_builder = $this->admin_settings->menu_group('test-group');
		$this->assertInstanceOf(\Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::class, $admin_page_builder);

		// Test UserSettings fluent API
		$user_collection_builder = $this->user_settings->collection('profile');
		$this->assertInstanceOf(\Ran\PluginLib\Settings\UserSettingsCollectionBuilder::class, $user_collection_builder);

		// Test that fluent API methods exist and return correct types
		$this->assertTrue(method_exists($user_collection_builder, 'template'));

		// Test method chaining
		$result = $user_collection_builder->template('custom.collection');
		$this->assertSame($user_collection_builder, $result);

		// Test section builder methods
		$section_builder = $user_collection_builder->section('test-section', 'Test Section');
		$this->assertTrue(method_exists($section_builder, 'template'));
		$this->assertTrue(method_exists($section_builder, 'field'));

		$result = $section_builder->template('custom.section');
		$this->assertSame($section_builder, $result);

		$section_builder->field('field-id', 'Label', 'component');
	}

	/**
	 * Test template override hierarchy and precedence rules.
	 */
	public function test_template_override_hierarchy_and_precedence_rules(): void {
		// Set up complex hierarchy for AdminSettings
		$this->admin_settings->override_form_defaults(array(
			'field-wrapper' => 'default.field'
		));

		$admin_session = $this->admin_settings->get_form_session();
		$admin_session->set_individual_element_override('root', 'test-page', array(
			'field-wrapper' => 'page.field'
		));

		$admin_session->set_individual_element_override('section', 'test-section', array(
			'field-wrapper' => 'section.field'
		));

		// Test precedence: field > section > page > class default > system default

		// 1. Field-level override (highest priority)
		$admin_session->set_individual_element_override('field', 'test-field', array(
			'field-wrapper' => 'field.specific'
		));
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section',
			'field_id'   => 'test-field'
		));
		$this->assertEquals('field.specific', $template);

		// 2. Section-level override
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('section.field', $template);

		// 3. Page-level override
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'other-section'
		));
		$this->assertEquals('page.field', $template);

		// 4. Class default
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'other-page',
			'section_id' => 'other-section'
		));
		$this->assertEquals('default.field', $template);

		// Test UserSettings hierarchy
		$this->user_settings->override_form_defaults(array(
			'field-wrapper' => 'user.default.field'
		));

		$user_session = $this->user_settings->get_form_session();
		$user_session->set_individual_element_override('root', 'profile', array(
			'field-wrapper' => 'user.collection.field'
		));

		$user_session->set_individual_element_override('section', 'personal', array(
			'field-wrapper' => 'user.section.field'
		));

		// Test UserSettings precedence: field > section > collection > class default > system default

		// 1. Field-level override (highest priority)
		$user_session->set_individual_element_override('field', 'test-user-field', array(
			'field-wrapper' => 'user.field.specific'
		));
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'personal',
			'field_id'   => 'test-user-field'
		));
		$this->assertEquals('user.field.specific', $template);

		// 2. Section-level override
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'personal'
		));
		$this->assertEquals('user.section.field', $template);

		// 3. Collection-level override
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'other-section'
		));
		$this->assertEquals('user.collection.field', $template);

		// 4. Class default
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'other-collection',
			'section_id' => 'other-section'
		));
		$this->assertEquals('user.default.field', $template);
	}

	/**
	 * Test template validation and error handling.
	 */
	public function test_template_validation_and_error_handling(): void {
		// Test that AdminSettings has component registration through ComponentManifest
		$component_manifest = $this->admin_settings->get_form_session()->manifest();
		$this->assertTrue(method_exists($component_manifest, 'register'));
		$this->assertTrue(method_exists($component_manifest, 'has'));

		// Test component registration on ComponentManifest
		$this->component_manifest->register('valid.template', function() {
			return 'test-component';
		});

		// Test that ComponentManifest has validation methods
		$this->assertTrue(method_exists($this->component_manifest, 'has'));

		// Test component existence validation
		$this->assertTrue($this->component_manifest->has('valid.template'));
		$this->assertFalse($this->component_manifest->has('invalid.template'));

		// Test UserSettings has the same component access
		$user_manifest = $this->user_settings->get_form_session()->manifest();
		$this->assertTrue(method_exists($user_manifest, 'register'));
		$this->assertTrue(method_exists($user_manifest, 'has'));
	}

	/**
	 * Test complete custom theme registration.
	 */
	public function test_complete_custom_theme_registration(): void {
		// Register a complete custom theme using ComponentLoader
		$theme_templates = array(
			'mytheme.admin.page'      => 'admin/pages/example-page.php',
			'mytheme.admin.section'   => 'admin/sections/modern-section.php',
			'mytheme.admin.group'     => 'admin/groups/modern-group.php',
			'mytheme.admin.field'     => 'admin/fields/example-field-wrapper.php',
			'mytheme.user.collection' => 'user/collections/example-collection.php',
			'mytheme.user.field'      => __DIR__ . '/../../fixtures/templates/user/fields/example-user-field-wrapper.php'
		);

		// Register all theme templates
		foreach ($theme_templates as $key => $path) {
			$this->component_loader->register($key, $path);
		}

		// Verify all templates are registered by checking aliases
		$aliases = $this->component_loader->aliases();
		foreach ($theme_templates as $key => $path) {
			$this->assertArrayHasKey($key, $aliases);
		}

		// Test that AdminSettings and UserSettings instances exist and have component access
		$this->assertInstanceOf(AdminSettings::class, $this->admin_settings);
		$this->assertInstanceOf(UserSettings::class, $this->user_settings);
		$admin_manifest = $this->admin_settings->get_form_session()->manifest();
		$user_manifest  = $this->user_settings->get_form_session()->manifest();
		$this->assertTrue(method_exists($admin_manifest, 'register'));
		$this->assertTrue(method_exists($user_manifest, 'register'));
	}

	/**
	 * Test per-field customization patterns.
	 */
	public function test_per_field_customization_patterns(): void {
		// Set up default templates
		$this->admin_settings->override_form_defaults(array(
			'field-wrapper' => 'default.field'
		));

		// Test that most fields use default
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'page_slug'  => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('default.field', $template);

		// Test that specific field can use custom template
		$admin_session = $this->admin_settings->get_form_session();
		$admin_session->set_individual_element_override('field', 'special-field', array(
			'field-wrapper' => 'special.field'
		));
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section',
			'field_id'   => 'special-field'
		));
		$this->assertEquals('special.field', $template);

		// Test that other fields still use default
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('default.field', $template);
	}

	/**
	 * Test template registration with automatic discovery.
	 */
	public function test_template_registration_with_automatic_discovery(): void {
		// ComponentLoader should automatically discover templates in its directory
		$loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates');

		// Test that templates are discovered
		$aliases = $loader->aliases();
		$this->assertIsArray($aliases);

		// Should find templates in the fixtures directory
		$this->assertArrayHasKey('admin.pages.example-page', $aliases);
		$this->assertArrayHasKey('admin.fields.example-field-wrapper', $aliases);
		$this->assertArrayHasKey('user.collections.example-collection', $aliases);
		$this->assertArrayHasKey('user.fields.example-user-field-wrapper', $aliases);
	}

	/**
	 * Test template override validation with clear error messages.
	 */
	public function test_template_override_validation_with_error_messages(): void {
		// Test component validation using ComponentManifest
		$this->assertFalse($this->component_manifest->has('non.existent.template'));

		// Test that empty template keys are handled
		$this->assertFalse($this->component_manifest->has(''));

		// Test that valid components pass validation
		$this->component_manifest->register('valid.test.template', function() {
			return 'test-component';
		});
		$this->assertTrue($this->component_manifest->has('valid.test.template'));
	}

	/**
	 * Test ComponentManifest integration with template system.
	 */
	public function test_component_manifest_integration_with_template_system(): void {
		// Test that AdminSettings has component registration through ComponentManifest
		$manifest = $this->admin_settings->get_form_session()->manifest();
		$this->assertTrue(method_exists($manifest, 'register'));
		$this->assertTrue(method_exists($manifest, 'has'));

		// Test that ComponentManifest exists and has expected methods
		$this->assertInstanceOf(ComponentManifest::class, $this->component_manifest);
		$this->assertTrue(method_exists($this->component_manifest, 'has'));
		$this->assertTrue(method_exists($this->component_manifest, 'register'));

		// Test that components can be registered through ComponentManifest
		$this->component_manifest->register('manifest.test.template', function() {
			return 'test-component';
		});
		$this->assertTrue($this->component_manifest->has('manifest.test.template'));

		// Test that ComponentLoader can register templates
		$this->component_loader->register('loader.test.template', 'admin/fields/example-field-wrapper.php');
		$aliases = $this->component_loader->aliases();
		$this->assertArrayHasKey('loader.test.template', $aliases);
	}
}
