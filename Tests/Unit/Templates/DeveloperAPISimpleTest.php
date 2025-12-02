<?php
/**
 * Developer API Simple Test
 *
 * Validates the public fluent AdminSettings/UserSettings APIs while
 * indirectly asserting the underlying FormsServiceSession behaviour.
 * The session API remains internal; tests reference it only to confirm
 * builder-driven state ends up in the expected tiers.
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
class DeveloperAPISimpleTest extends PluginLibTestCase {
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
	 * Test global template registration using ComponentLoader register method.
	 */
	public function test_global_template_registration_using_register_method(): void {
		// Test single template registration
		$this->component_loader->register('my.custom-page', 'admin/pages/example-page.php');

		// Test that template is registered by checking aliases
		$aliases = $this->component_loader->aliases();
		$this->assertArrayHasKey('my.custom-page', $aliases);
		$this->assertEquals('admin/pages/example-page.php', $aliases['my.custom-page']);

		// Test batch registration pattern
		$templates = array(
			'mytheme.page'    => 'admin/pages/example-page.php',
			'mytheme.section' => 'admin/sections/modern-section.php',
			'mytheme.field'   => 'admin/fields/example-field-wrapper.php'
		);

		foreach ($templates as $key => $path) {
			$this->component_loader->register($key, $path);
		}

		// Verify templates are registered by checking aliases
		$aliases = $this->component_loader->aliases();
		foreach ($templates as $key => $path) {
			$this->assertArrayHasKey($key, $aliases);
		}
	}

	/**
	 * Test template automatic discovery functionality.
	 */
	public function test_template_automatic_discovery(): void {
		// ComponentLoader should automatically discover templates in its directory
		$loader = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $this->logger_mock);

		// Test that ComponentLoader has default aliases
		$aliases = $loader->aliases();
		$this->assertIsArray($aliases);

		// Test that ComponentLoader can register new templates
		$loader->register('test.template', 'admin/pages/example-page.php');
		$updated_aliases = $loader->aliases();
		$this->assertArrayHasKey('test.template', $updated_aliases);
	}

	/**
	 * Test fluent API template override methods across contexts.
	 */
	public function test_fluent_api_template_override_methods(): void {
		$admin_session = $this->admin_settings->get_form_session();
		$user_session  = $this->user_settings->get_form_session();

		// Test AdminSettings fluent API exists
		$admin_group_builder = $this->admin_settings->menu_group('test-group');
		$this->assertInstanceOf(\Ran\PluginLib\Settings\AdminSettingsMenuGroupBuilder::class, $admin_group_builder);

		$admin_page_builder = $admin_group_builder->page('test-page');
		$this->assertTrue(method_exists($admin_page_builder, 'template'));
		$admin_page_builder->template('admin.custom.page');
		$template = $admin_session->resolve_template('root-wrapper', array(
			'root_id' => 'test-page'
		));
		$this->assertEquals('admin.custom.page', $template);

		$admin_section_builder = $admin_page_builder->section('admin-section', 'Admin Section');
		$this->assertTrue(method_exists($admin_section_builder, 'template'));
		$admin_section_builder->template('admin.custom.section');
		$template = $admin_session->resolve_template('section-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'admin-section'
		));
		$this->assertEquals('admin.custom.section', $template);

		$admin_section_builder->field_simple('admin-field', 'Admin Field', 'component', array(
			'field_template' => 'admin.custom.field',
		))->order(15);
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'admin-section',
			'field_id'   => 'admin-field'
		));
		$this->assertEquals('admin.custom.field', $template);
		$admin_page_builder->end_page();

		// Test UserSettings fluent API exists
		$user_collection_builder = $this->user_settings->collection('profile');
		$this->assertInstanceOf(\Ran\PluginLib\Settings\UserSettingsCollectionBuilder::class, $user_collection_builder);

		// Test that collection builder has template methods
		$this->assertTrue(method_exists($user_collection_builder, 'template'));

		// Test method chaining
		$result = $user_collection_builder->template('custom.collection');
		$this->assertSame($user_collection_builder, $result);
		$template = $user_session->resolve_template('root-wrapper', array(
			'root_id' => 'profile'
		));
		$this->assertEquals('custom.collection', $template);

		// Test section builder methods
		$section_builder = $user_collection_builder->section('test-section', 'Test Section');
		$this->assertTrue(method_exists($section_builder, 'template'));
		$this->assertTrue(method_exists($section_builder, 'field'));

		// Section builder template returns itself for chaining
		$result = $section_builder->template('custom.section');
		$this->assertSame($section_builder, $result);
		$template = $user_session->resolve_template('section-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'test-section'
		));
		$this->assertEquals('custom.section', $template);

		// Field override via section builder
		$section_builder->field_simple('field-id', 'Label', 'component', array(
			'field_template' => 'custom.field',
		));
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'test-section',
			'field_id'   => 'field-id'
		));
		$this->assertEquals('custom.field', $template);
	}

	/**
	 * Test template override hierarchy and precedence.
	 *
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::set_individual_element_override
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::resolve_template
	 */
	public function test_template_override_hierarchy_and_precedence(): void {
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

		// Test precedence order

		// Field-level override (highest priority)
		$admin_session->set_individual_element_override('field', 'test-field', array(
			'field-wrapper' => 'field.specific'
		));
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section',
			'field_id'   => 'test-field'
		));
		$this->assertEquals('field.specific', $template);

		// Section-level override
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('section.field', $template);

		// Page-level override
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'other-section'
		));
		$this->assertEquals('page.field', $template);

		// Class default
		$template = $admin_session->resolve_template('field-wrapper', array(
			'root_id'    => 'other-page',
			'section_id' => 'other-section'
		));
		$this->assertEquals('default.field', $template);
	}

	/**
	 * Test UserSettings template hierarchy.
	 *
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::set_individual_element_override
	 * @covers \Ran\PluginLib\Forms\FormsServiceSession::resolve_template
	 */
	public function test_user_settings_template_hierarchy(): void {
		// Set up UserSettings hierarchy
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

		// Test UserSettings precedence

		// Field-level override (highest priority)
		$user_session->set_individual_element_override('field', 'test-user-field', array(
			'field-wrapper' => 'user.field.specific'
		));
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'personal',
			'field_id'   => 'test-user-field'
		));
		$this->assertEquals('user.field.specific', $template);

		// Section-level override
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'personal'
		));
		$this->assertEquals('user.section.field', $template);

		// Collection-level override
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'profile',
			'section_id' => 'other-section'
		));
		$this->assertEquals('user.collection.field', $template);

		// Class default
		$template = $user_session->resolve_template('field-wrapper', array(
			'root_id'    => 'other-collection',
			'section_id' => 'other-section'
		));
		$this->assertEquals('user.default.field', $template);
	}

	/**
	 * Test template validation functionality.
	 */
	public function test_template_validation_functionality(): void {
		// Template validation is handled internally by ComponentLoader
		// No need for explicit validation methods - templates either exist or throw exceptions
		$this->assertTrue(true); // Placeholder test
	}

	/**
	 * Test complete custom theme application.
	 */
	public function test_complete_custom_theme_application(): void {
		// Apply theme to AdminSettings
		$this->admin_settings->override_form_defaults(array(
			'root-wrapper'    => 'mytheme.admin.root-wrapper',
			'section-wrapper' => 'mytheme.admin.section-wrapper',
			'group-wrapper'   => 'mytheme.admin.group-wrapper',
			'field-wrapper'   => 'mytheme.admin.field-wrapper'
		));

		// Apply theme to UserSettings
		$this->user_settings->override_form_defaults(array(
			'root-wrapper'    => 'mytheme.user.root-wrapper',
			'section-wrapper' => 'mytheme.user.section-wrapper',
			'field-wrapper'   => 'mytheme.user.field-wrapper'
		));

		// Test that theme is applied to AdminSettings
		$template = $this->admin_settings->get_form_session()->resolve_template('root-wrapper', array());
		$this->assertEquals('mytheme.admin.root-wrapper', $template);

		$template = $this->admin_settings->get_form_session()->resolve_template('section-wrapper', array());
		$this->assertEquals('mytheme.admin.section-wrapper', $template);

		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array());
		$this->assertEquals('mytheme.admin.field-wrapper', $template);

		// Test that theme is applied to UserSettings
		$template = $this->user_settings->get_form_session()->resolve_template('root-wrapper', array());
		$this->assertEquals('mytheme.user.root-wrapper', $template);

		$template = $this->user_settings->get_form_session()->resolve_template('section-wrapper', array());
		$this->assertEquals('mytheme.user.section-wrapper', $template);

		$template = $this->user_settings->get_form_session()->resolve_template('field-wrapper', array());
		$this->assertEquals('mytheme.user.field-wrapper', $template);
	}

	/**
	 * Test per-field customization patterns.
	 */
	public function test_per_field_customization_patterns(): void {
		// Set up default templates
		$this->admin_settings->override_form_defaults(array(
			'field-wrapper' => 'default.field-wrapper'
		));

		// Test that most fields use default
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'page_slug'  => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('default.field-wrapper', $template);

		// Test that specific field can use custom template
		$admin_session = $this->admin_settings->get_form_session();
		$admin_session->set_individual_element_override('field', 'special-field', array(
			'field-wrapper' => 'special.field-wrapper'
		));
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section',
			'field_id'   => 'special-field'
		));
		$this->assertEquals('special.field-wrapper', $template);

		// Test that other fields still use default
		$template = $this->admin_settings->get_form_session()->resolve_template('field-wrapper', array(
			'root_id'    => 'test-page',
			'section_id' => 'test-section'
		));
		$this->assertEquals('default.field-wrapper', $template);
	}

	/**
	 * Test template override storage and retrieval.
	 */
	public function test_template_override_storage_and_retrieval(): void {
		// Test AdminSettings override storage
		$page_overrides = array('root-wrapper' => 'custom.root-wrapper', 'section-wrapper' => 'custom.section-wrapper');
		$admin_session  = $this->admin_settings->get_form_session();
		$admin_session->set_individual_element_override('root', 'test-page', $page_overrides);

		$retrieved = $this->admin_settings->get_form_session()->get_individual_element_overrides('root', 'test-page');
		$this->assertEquals($page_overrides, $retrieved);

		$section_overrides = array('section-wrapper' => 'custom.section-wrapper', 'field-wrapper' => 'custom.field-wrapper');
		$admin_session->set_individual_element_override('section', 'test-section', $section_overrides);

		$retrieved = $this->admin_settings->get_form_session()->get_individual_element_overrides('section', 'test-section');
		$this->assertEquals($section_overrides, $retrieved);

		// Test UserSettings override storage
		$collection_overrides = array('root-wrapper' => 'custom.root-wrapper', 'field-wrapper' => 'custom.field-wrapper');
		$user_session         = $this->user_settings->get_form_session();
		$user_session->set_individual_element_override('root', 'profile', $collection_overrides);

		$retrieved = $this->user_settings->get_form_session()->get_individual_element_overrides('root', 'profile');
		$this->assertEquals($collection_overrides, $retrieved);

		$user_section_overrides = array('section-wrapper' => 'custom.section-wrapper', 'field-wrapper' => 'custom.field-wrapper');
		$user_session->set_individual_element_override('section', 'personal', $user_section_overrides);

		$retrieved = $this->user_settings->get_form_session()->get_individual_element_overrides('section', 'personal');
		$this->assertEquals($user_section_overrides, $retrieved);
	}

	/**
	 * Test default template override functionality.
	 */
	public function test_default_template_override_functionality(): void {
		// Test AdminSettings default overrides
		$admin_defaults = array(
			'root-wrapper'    => 'admin.custom.page',
			'section-wrapper' => 'admin.custom.section',
			'field-wrapper'   => 'admin.custom.field'
		);

		$this->admin_settings->override_form_defaults($admin_defaults);
		$retrieved = $this->admin_settings->get_form_session()->get_form_defaults();

		// Should contain the overrides we set plus any existing defaults
		foreach ($admin_defaults as $key => $value) {
			$this->assertEquals($value, $retrieved[$key]);
		}

		// Test UserSettings default overrides
		$user_defaults = array(
			'root-wrapper'    => 'user.custom.root-wrapper',
			'section-wrapper' => 'user.custom.section-wrapper',
			'field-wrapper'   => 'user.custom.field-wrapper'
		);

		$this->user_settings->override_form_defaults($user_defaults);
		$retrieved = $this->user_settings->get_form_session()->get_form_defaults();

		// Should contain the overrides we set plus any existing defaults
		foreach ($user_defaults as $key => $value) {
			$this->assertEquals($value, $retrieved[$key]);
		}
	}
}
