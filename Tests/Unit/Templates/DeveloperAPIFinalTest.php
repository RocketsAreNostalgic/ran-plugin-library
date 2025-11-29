<?php
/**
 * Developer API Final Test
 *
 * Exercises the fluent AdminSettings/UserSettings APIs end-to-end while
 * asserting the FormsServiceSession state that those builders produce.
 * Session calls remain an internal detail; these tests confirm the
 * contract exposed through the fluent configuration chain.
 *
 * @internal
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
 * @covers \Ran\PluginLib\Forms\FormsServiceSession
 */
class DeveloperAPIFinalTest extends PluginLibTestCase {
	private AdminSettings $admin_settings;
	private UserSettings $user_settings;
	private RegisterOptions $admin_options;
	private RegisterOptions $user_options;
	private ComponentLoader $component_loader;
	private ComponentManifest $component_manifest;

	public function setUp(): void {
		parent::setUp();

		// Mock WordPress functions
		WP_Mock::userFunction('get_option')->andReturn(array());
		WP_Mock::userFunction('get_user_meta')->andReturn(array());
		WP_Mock::userFunction('is_network_admin')->andReturn(false);
		WP_Mock::userFunction('get_current_blog_id')->andReturn(1);
		WP_Mock::userFunction('get_current_user_id')->andReturn(1);

		// Create component infrastructure
		$this->component_loader   = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger_mock);
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

		$this->admin_settings = new AdminSettings($this->admin_options, $this->component_manifest, null, $this->logger_mock);
		$this->user_settings  = new UserSettings($this->user_options, $this->component_manifest, null, $this->logger_mock);
	}

	/**
	 * Test global template registration API exists.
	 */
	public function test_global_template_registration_api_exists(): void {
		// Test that ComponentLoader has register method for global registration
		$component_loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger_mock);
		$this->assertTrue(method_exists($component_loader, 'register'));
		$this->assertTrue(method_exists($component_loader, 'aliases'));
		$this->assertTrue(method_exists($component_loader, 'render'));

		// Test that registration method can be called without errors
		$component_loader->register('test.template', '/path/to/template.php');
		$this->assertTrue(true); // If we get here, registration didn't throw an error
	}

	/**
	 * Test fluent API template override methods exist across all contexts.
	 */
	public function test_fluent_api_template_override_methods_exist(): void {
		// Test AdminSettings fluent API
		$admin_group_builder = $this->admin_settings->menu_group('test-group');
		$this->assertInstanceOf(\Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::class, $admin_group_builder);

		// Test UserSettings fluent API
		$user_collection_builder = $this->user_settings->collection('profile');
		$this->assertInstanceOf(\Ran\PluginLib\Settings\UserSettingsCollectionBuilder::class, $user_collection_builder);

		// Test that collection builder has template methods
		$this->assertTrue(method_exists($user_collection_builder, 'template'));

		// Test section builder methods
		$section_builder = $user_collection_builder->section('test-section', 'Test Section');

		// Test method chaining returns correct types
		$result = $user_collection_builder->template('custom.collection');
		$this->assertSame($user_collection_builder, $result);
	}

	/**
	 * Test template override hierarchy and precedence rules work correctly.
	 */
	public function test_template_override_hierarchy_and_precedence_rules(): void {
		// Set up AdminSettings hierarchy
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

		// Test precedence order: field > section > page > class default > system default

		// 1. Field-level override (highest priority)
		$admin_session->set_individual_element_override('field', 'test-field', array(
			'field-wrapper' => 'field.specific'
		));
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section',
			'field_id'   => 'test-field'
		));
		$this->assertEquals('field.specific', $template);

		// 2. Section-level override
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('section.field', $template);

		// 3. Page-level override
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'other-section'
		));
		$this->assertEquals('page.field', $template);

		// 4. Class default
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'other-page',
			'section_id' => 'other-section'
		));
		$this->assertEquals('default.field', $template);

		// 5. System default (when no overrides)
		$clean_admin_settings = new AdminSettings($this->admin_options, $this->component_manifest, null, $this->logger_mock);
		$template             = $clean_admin_settings->get_form_session()->resolve_template('field-wrapper', array());
		$this->assertEquals('layout.field.field-wrapper', $template); // Should use field-specific fallback
	}

	/**
	 * Test UserSettings template hierarchy works correctly.
	 */
	public function test_user_settings_template_hierarchy(): void {
		// Set up UserSettings hierarchy
		$this->user_settings->override_form_defaults(array(
			'field-wrapper' => 'user.default.field-wrapper'
		));

		$user_session = $this->user_settings->get_form_session();

		$user_session->set_individual_element_override('root', 'profile', array(
			'field-wrapper' => 'user.collection.field-wrapper'
		));

		$user_session->set_individual_element_override('section', 'personal', array(
			'field-wrapper' => 'user.section.field-wrapper'
		));

		// Test UserSettings precedence: field > section > collection > class default > system default

		// 1. Field-level override (highest priority)
		$user_session->set_individual_element_override('field', 'test-user-field', array(
			'field-wrapper' => 'user.field-wrapper.specific'
		));
		$template = $this->user_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'personal',
			'field_id'   => 'test-user-field'
		));
		$this->assertEquals('user.field-wrapper.specific', $template);

		// 2. Section-level override
		$template = $this->user_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'personal'
		));
		$this->assertEquals('user.section.field-wrapper', $template);

		// 3. Collection-level override
		$template = $this->user_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'other-section'
		));
		$this->assertEquals('user.collection.field-wrapper', $template);

		// 4. Class default
		$template = $this->user_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'other-collection',
			'section_id' => 'other-section'
		));
		$this->assertEquals('user.default.field-wrapper', $template);

		// 5. System default (when no overrides)
		$clean_user_settings = new UserSettings($this->user_options, $this->component_manifest, null, $this->logger_mock);
		$template            = $clean_user_settings->get_form_session()->resolve_template('field-wrapper', array());
		$this->assertStringContainsString('user', $template); // Should use user context default
	}

	/**
	 * Test template validation and error handling functionality.
	 */
	public function test_template_validation_and_error_handling(): void {
		// Test template validation through ComponentManifest
		$admin_manifest = $this->admin_settings->get_form_session()->manifest();
		$user_manifest  = $this->user_settings->get_form_session()->manifest();

		// Test validation with non-existent template returns false
		$this->assertFalse($admin_manifest->has('invalid.template'));
		$this->assertFalse($user_manifest->has('invalid.template'));

		// Test validation with empty template key returns false
		$this->assertFalse($admin_manifest->has(''));
		$this->assertFalse($user_manifest->has(''));

		// Test validation with valid registered template returns true
		$admin_manifest->register('valid.test.template', function() {
			return 'test';
		});
		$this->assertTrue($admin_manifest->has('valid.test.template'));

		$user_manifest->register('valid.user.template', function() {
			return 'test';
		});
		$this->assertTrue($user_manifest->has('valid.user.template'));
	}

	/**
	 * Test template override storage and retrieval methods.
	 */
	public function test_template_override_storage_and_retrieval(): void {
		// Test AdminSettings override storage and retrieval
		$page_overrides = array('root-wrapper' => 'custom.root-wrapper', 'section-wrapper' => 'custom.section-wrapper');
		$admin_session  = $this->admin_settings->get_form_session();
		$admin_session->set_individual_element_override('root', 'test-page', $page_overrides);

		$retrieved = $this->admin_settings->get_form_session()->get_individual_element_overrides('root', 'test-page');
		$this->assertEquals($page_overrides, $retrieved);

		$section_overrides = array('section-wrapper' => 'custom.section-wrapper', 'field-wrapper' => 'custom.field-wrapper');
		$admin_session->set_individual_element_override('section', 'test-section', $section_overrides);

		$retrieved = $this->admin_settings->get_form_session()->get_individual_element_overrides('section', 'test-section');
		$this->assertEquals($section_overrides, $retrieved);

		// Test default overrides
		$default_overrides = array('field-wrapper' => 'default.field-wrapper');
		$this->admin_settings->override_form_defaults($default_overrides);

		$retrieved = $this->admin_settings->get_form_session()->get_form_defaults();
		$this->assertArrayHasKey('field-wrapper', $retrieved);
		$this->assertEquals('default.field-wrapper', $retrieved['field-wrapper']);

		// Test UserSettings override storage and retrieval
		$collection_overrides = array('root-wrapper' => 'custom.root-wrapper', 'field-wrapper' => 'custom.field-wrapper');
		$user_session         = $this->user_settings->get_form_session();
		$user_session->set_individual_element_override('root', 'profile', $collection_overrides);

		$retrieved = $this->user_settings->get_form_session()->get_individual_element_overrides('root', 'profile');
		$this->assertEquals($collection_overrides, $retrieved);

		$user_section_overrides = array('section-wrapper' => 'custom.section-wrapper', 'field-wrapper' => 'custom.field-wrapper');
		$user_session->set_individual_element_override('section', 'personal', $user_section_overrides);

		$retrieved = $this->user_settings->get_form_session()->get_individual_element_overrides('section', 'personal');
		$this->assertEquals($user_section_overrides, $retrieved);

		// Test UserSettings default overrides
		$user_default_overrides = array('field-wrapper' => 'user.default.field-wrapper');
		$this->user_settings->override_form_defaults($user_default_overrides);

		$retrieved = $this->user_settings->get_form_session()->get_form_defaults();
		$this->assertArrayHasKey('field-wrapper', $retrieved);
		$this->assertEquals('user.default.field-wrapper', $retrieved['field-wrapper']);
	}

	/**
	 * Test complete custom theme application across contexts.
	 */
	public function test_complete_custom_theme_application(): void {
		// Apply theme to AdminSettings
		$admin_theme = array(
			'page'          => 'mytheme.admin.page',
			'section'       => 'mytheme.admin.section',
			'group'         => 'mytheme.admin.group',
			'field-wrapper' => 'mytheme.admin.field'
		);
		$this->admin_settings->override_form_defaults($admin_theme);

		// Apply theme to UserSettings
		$user_theme = array(
			'root-wrapper'  => 'mytheme.user.collection',
			'section'       => 'mytheme.user.section',
			'field-wrapper' => 'mytheme.user.field'
		);
		$this->user_settings->override_form_defaults($user_theme);

		// Test that theme is applied to AdminSettings
		foreach ($admin_theme as $type => $template) {
			$resolved = $this->admin_settings->get_form_session()->resolve_template($type, array());
			$this->assertEquals($template, $resolved);
		}

		// Test that theme is applied to UserSettings
		foreach ($user_theme as $type => $template) {
			$resolved = $this->user_settings->get_form_session()->resolve_template($type, array());
			$this->assertEquals($template, $resolved);
		}
	}

	/**
	 * Test per-field customization patterns work correctly.
	 */
	public function test_per_field_customization_patterns(): void {
		// Set up default templates
		$this->admin_settings->override_form_defaults(array(
			'field-wrapper' => 'default.field'
		));
		$admin_session = $this->admin_settings->get_form_session();

		// Test that most fields use default
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'page_slug'  => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('default.field', $template);

		// Test that specific field can use custom template via field override
		$admin_session->set_individual_element_override('field', 'special-field', array(
			'field-wrapper' => 'special.field'
		));
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
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
	 * Test that all required API methods exist for developer use.
	 */
	public function test_all_required_api_methods_exist(): void {
		// Test AdminSettings API methods (new template consolidation API)
		$this->assertTrue(method_exists($this->admin_settings, 'override_form_defaults'));
		$this->assertTrue(method_exists($this->admin_settings, 'menu_group'));
		$this->assertTrue(method_exists($this->admin_settings, 'get_form_session'));

		// Test that FormsServiceSession has the template resolution methods
		$form_session = $this->admin_settings->get_form_session();
		$this->assertNotNull($form_session);
		$this->assertTrue(method_exists($form_session, 'resolve_template'));
		$this->assertTrue(method_exists($form_session, 'get_form_defaults'));
		$this->assertTrue(method_exists($form_session, 'get_individual_element_overrides'));
		$this->assertTrue(method_exists($form_session, 'set_individual_element_override'));

		// Test UserSettings API methods (new template consolidation API)
		$this->assertTrue(method_exists($this->user_settings, 'override_form_defaults'));
		$this->assertTrue(method_exists($this->user_settings, 'collection'));
		$this->assertTrue(method_exists($this->user_settings, 'get_form_session'));

		// Test UserSettings-specific methods
		$this->assertTrue(method_exists(\Ran\PluginLib\Settings\UserSettingsCollectionBuilder::class, 'template'));
		$this->assertTrue(method_exists(\Ran\PluginLib\Settings\UserSettingsSectionBuilder::class, 'template'));

		// Test ComponentLoader API methods
		$component_loader = new ComponentLoader(__DIR__, $this->logger_mock);
		$this->assertTrue(method_exists($component_loader, 'register'));
		$this->assertTrue(method_exists($component_loader, 'aliases'));
		$this->assertTrue(method_exists($component_loader, 'render'));
	}
}
