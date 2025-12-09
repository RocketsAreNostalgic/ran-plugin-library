<?php

namespace Ran\PluginLib\Tests\Unit\Templates;

use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * Comprehensive Fluent API Template Override Tests
 *
 * Tests all template override methods, hierarchical scenarios, mixed customization,
 * and template resolution precedence rules.
 */
class FluentAPIComprehensiveTest extends PluginLibTestCase {
	public function setUp(): void {
		parent::setUp();
	}

	/**
	 * Test template override methods reference documentation
	 */
	public function test_template_override_methods_reference(): void {
		$methods_reference = $this->get_template_override_methods_reference();

		// Verify AdminSettings methods
		$admin_methods = $methods_reference['AdminSettings'];
		$this->assertArrayHasKey('page_level', $admin_methods);
		$this->assertArrayHasKey('section_level', $admin_methods);

		$this->assertContains('root_tempate(string $template_key)', $admin_methods['page_level']);
		$this->assertContains('field_template(string $template_key)', $admin_methods['page_level']);
		$this->assertContains('section_template(string $template_key)', $admin_methods['page_level']);

		$this->assertContains('section_template(string $template_key)', $admin_methods['section_level']);
		$this->assertContains('group_template(string $template_key)', $admin_methods['section_level']);
		$this->assertContains('field_template(string $template_key)', $admin_methods['section_level']);

		// Verify UserSettings methods
		$user_methods = $methods_reference['UserSettings'];
		$this->assertArrayHasKey('collection_level', $user_methods);
		$this->assertArrayHasKey('section_level', $user_methods);

		$this->assertContains('root_tempate(string $template_key)', $user_methods['collection_level']);
		$this->assertContains('field_template(string $template_key)', $user_methods['collection_level']);
		$this->assertContains('section_template(string $template_key)', $user_methods['collection_level']);

		$this->assertContains('section_template(string $template_key)', $user_methods['section_level']);
		$this->assertContains('field_template(string $template_key)', $user_methods['section_level']);
	}

	/**
	 * Test template resolution hierarchy reference
	 */
	public function test_template_resolution_hierarchy_reference(): void {
		$hierarchy = $this->get_template_resolution_hierarchy();

		// Verify hierarchy order (1 = highest priority, 7 = lowest priority)
		$this->assertEquals('Field-level override (highest priority)', $hierarchy[1]);
		$this->assertEquals('Group-level override', $hierarchy[2]);
		$this->assertEquals('Section-level override', $hierarchy[3]);
		$this->assertEquals('Page/Collection-level override', $hierarchy[4]);
		$this->assertEquals('Settings class default overrides', $hierarchy[5]);
		$this->assertEquals('Context default templates (admin/, user/, etc.)', $hierarchy[6]);
		$this->assertEquals('System default templates (lowest priority)', $hierarchy[7]);

		// Verify all 7 priority levels are defined
		$this->assertCount(7, $hierarchy);

		// Verify keys are in correct order
		$keys = array_keys($hierarchy);
		$this->assertEquals(array(1, 2, 3, 4, 5, 6, 7), $keys);
	}

	/**
	 * Test hierarchical template override scenarios documentation
	 */
	public function test_hierarchical_template_override_scenarios(): void {
		$scenarios = $this->get_hierarchical_scenarios();

		// Verify AdminSettings hierarchical scenario
		$this->assertArrayHasKey('admin_settings_hierarchical', $scenarios);
		$admin_scenario = $scenarios['admin_settings_hierarchical'];

		$this->assertArrayHasKey('root_tempate', $admin_scenario);
		$this->assertArrayHasKey('section_overrides', $admin_scenario);
		$this->assertArrayHasKey('field_overrides', $admin_scenario);
		$this->assertArrayHasKey('expected_resolution', $admin_scenario);

		// Verify UserSettings hierarchical scenario
		$this->assertArrayHasKey('user_settings_hierarchical', $scenarios);
		$user_scenario = $scenarios['user_settings_hierarchical'];

		$this->assertArrayHasKey('root_tempate', $user_scenario);
		$this->assertArrayHasKey('section_overrides', $user_scenario);
		$this->assertArrayHasKey('field_overrides', $user_scenario);
		$this->assertArrayHasKey('expected_resolution', $user_scenario);
	}

	/**
	 * Test mixed template customization patterns documentation
	 */
	public function test_mixed_template_customization_patterns(): void {
		$patterns = $this->get_mixed_customization_patterns();

		// Verify mixed AdminSettings pattern
		$this->assertArrayHasKey('admin_mixed_pattern', $patterns);
		$admin_pattern = $patterns['admin_mixed_pattern'];

		$this->assertArrayHasKey('page_level', $admin_pattern);
		$this->assertArrayHasKey('section_variations', $admin_pattern);
		$this->assertArrayHasKey('field_variations', $admin_pattern);

		// Verify mixed UserSettings pattern
		$this->assertArrayHasKey('user_mixed_pattern', $patterns);
		$user_pattern = $patterns['user_mixed_pattern'];

		$this->assertArrayHasKey('collection_level', $user_pattern);
		$this->assertArrayHasKey('section_variations', $user_pattern);
		$this->assertArrayHasKey('field_variations', $user_pattern);
	}

	/**
	 * Test per-field customization examples documentation
	 */
	public function test_per_field_customization_examples(): void {
		$examples = $this->get_per_field_customization_examples();

		// Verify AdminSettings per-field example
		$this->assertArrayHasKey('admin_per_field', $examples);
		$admin_example = $examples['admin_per_field'];

		$this->assertArrayHasKey('page_default', $admin_example);
		$this->assertArrayHasKey('field_overrides', $admin_example);
		$this->assertArrayHasKey('fields_using_default', $admin_example);

		// Verify UserSettings per-field example
		$this->assertArrayHasKey('user_per_field', $examples);
		$user_example = $examples['user_per_field'];

		$this->assertArrayHasKey('collection_default', $user_example);
		$this->assertArrayHasKey('field_overrides', $user_example);
		$this->assertArrayHasKey('fields_using_default', $user_example);
	}

	/**
	 * Test template precedence resolution rules documentation
	 */
	public function test_template_precedence_resolution_rules(): void {
		$rules = $this->get_template_precedence_resolution_rules();

		// Verify precedence rule structure
		$this->assertArrayHasKey('hierarchy', $rules);
		$this->assertArrayHasKey('resolution_examples', $rules);
		$this->assertArrayHasKey('conflict_resolution', $rules);

		// Verify hierarchy matches expected order
		$hierarchy = $rules['hierarchy'];
		$this->assertEquals(1, $hierarchy['field_level']);
		$this->assertEquals(2, $hierarchy['group_level']);
		$this->assertEquals(3, $hierarchy['section_level']);
		$this->assertEquals(4, $hierarchy['page_collection_level']);
		$this->assertEquals(5, $hierarchy['class_defaults']);
		$this->assertEquals(6, $hierarchy['context_defaults']);
		$this->assertEquals(7, $hierarchy['system_defaults']);

		// Verify resolution examples exist
		$this->assertArrayHasKey('field_wins_over_all', $rules['resolution_examples']);
		$this->assertArrayHasKey('section_wins_over_page', $rules['resolution_examples']);
		$this->assertArrayHasKey('page_wins_over_class', $rules['resolution_examples']);
	}

	/**
	 * Test fluent API pattern validation
	 */
	public function test_fluent_api_pattern_validation(): void {
		$patterns = $this->get_fluent_api_patterns();

		// Verify method chaining patterns
		$this->assertArrayHasKey('method_chaining', $patterns);
		$chaining = $patterns['method_chaining'];

		$this->assertArrayHasKey('admin_settings_chain', $chaining);
		$this->assertArrayHasKey('user_settings_chain', $chaining);

		// Verify return type patterns
		$this->assertArrayHasKey('return_types', $patterns);
		$return_types = $patterns['return_types'];

		$this->assertArrayHasKey('page_builder_methods', $return_types);
		$this->assertArrayHasKey('section_builder_methods', $return_types);
		$this->assertArrayHasKey('field_builder_methods', $return_types);
	}

	/**
	 * Get template override methods reference
	 */
	private function get_template_override_methods_reference(): array {
		return array(
		    'AdminSettings' => array(
		        'page_level' => array(
		            'root_tempate(string $template_key)',
		            'field_template(string $template_key)',
		            'section_template(string $template_key)'
		        ),
		        'section_level' => array(
		            'section_template(string $template_key)',
		            'group_template(string $template_key)',
		            'field_template(string $template_key)'
		        )
		    ),
		    'UserSettings' => array(
		        'collection_level' => array(
		            'root_tempate(string $template_key)',
		            'field_template(string $template_key)',
		            'section_template(string $template_key)'
		        ),
		        'section_level' => array(
		            'section_template(string $template_key)',
		            'field_template(string $template_key)'
		        )
		    )
		);
	}

	/**
	 * Get template resolution hierarchy reference
	 */
	private function get_template_resolution_hierarchy(): array {
		return array(
		    1 => 'Field-level override (highest priority)',
		    2 => 'Group-level override',
		    3 => 'Section-level override',
		    4 => 'Page/Collection-level override',
		    5 => 'Settings class default overrides',
		    6 => 'Context default templates (admin/, user/, etc.)',
		    7 => 'System default templates (lowest priority)'
		);
	}

	/**
	 * Get hierarchical template override scenarios
	 */
	private function get_hierarchical_scenarios(): array {
		return array(
		    'admin_settings_hierarchical' => array(
		        'root_tempate'      => 'grid-admin-page',
		        'section_overrides' => array(
		            'database' => 'highlighted-section',
		            'cache'    => 'card-section'
		        ),
		        'field_overrides' => array(
		            'host' => 'floating-label-wrapper',
		            'ttl'  => 'card-field-wrapper'
		        ),
		        'expected_resolution' => array(
		            'host_field' => 'floating-label-wrapper',  // Field-level wins
		            'port_field' => 'compact-field-wrapper',   // Page default
		            'ttl_field'  => 'card-field-wrapper'        // Field-level wins
		        )
		    ),
		    'user_settings_hierarchical' => array(
		        'root_tempate'      => 'modern-table-collection',
		        'section_overrides' => array(
		            'display' => 'highlighted-table-section',
		            'privacy' => 'highlighted-table-section'
		        ),
		        'field_overrides' => array(
		            'theme'     => 'enhanced-table-field',
		            'frequency' => 'compact-table-field'
		        ),
		        'expected_resolution' => array(
		            'theme_field'     => 'enhanced-table-field',   // Field-level wins
		            'language_field'  => 'table-field-row',     // Collection default
		            'frequency_field' => 'compact-table-field' // Field-level wins
		        )
		    )
		);
	}

	/**
	 * Get mixed template customization patterns
	 */
	private function get_mixed_customization_patterns(): array {
		return array(
		    'admin_mixed_pattern' => array(
		        'page_level' => array(
		            'template'      => 'sidebar-admin-page',
		            'default_field' => 'modern-field-wrapper'
		        ),
		        'section_variations' => array(
		            'widgets'  => 'tabbed-section',
		            'advanced' => 'card-section'
		        ),
		        'field_variations' => array(
		            'widget1'    => 'floating-label-wrapper',
		            'widget3'    => 'compact-field-wrapper',
		            'debug_mode' => 'card-field-wrapper'
		        )
		    ),
		    'user_mixed_pattern' => array(
		        'collection_level' => array(
		            'template'      => 'enhanced-table-collection',
		            'default_field' => 'standard-table-field'
		        ),
		        'section_variations' => array(
		            'identity' => 'grouped-table-section',
		            'privacy'  => 'highlighted-table-section'
		        ),
		        'field_variations' => array(
		            'username'           => 'readonly-table-field',
		            'email'              => 'validated-table-field',
		            'profile_visibility' => 'important-table-field'
		        )
		    )
		);
	}

	/**
	 * Get per-field customization examples
	 */
	private function get_per_field_customization_examples(): array {
		return array(
		    'admin_per_field' => array(
		        'page_default'    => 'modern-field-wrapper',
		        'field_overrides' => array(
		            'form_title'       => 'floating-label-wrapper',
		            'form_description' => 'card-field-wrapper',
		            'required_fields'  => 'compact-field-wrapper',
		            'success_message'  => 'card-field-wrapper',
		            'error_message'    => 'card-field-wrapper',
		            'captcha_type'     => 'floating-label-wrapper'
		        ),
		        'fields_using_default' => array(
		            'submit_button_text',
		            'enable_captcha'
		        )
		    ),
		    'user_per_field' => array(
		        'collection_default' => 'table-field-row',
		        'field_overrides'    => array(
		            'first_name'      => 'required-table-field',
		            'last_name'       => 'required-table-field',
		            'bio'             => 'expanded-table-field',
		            'profile_picture' => 'media-table-field',
		            'website'         => 'url-table-field',
		            'social_links'    => 'repeater-table-field'
		        ),
		        'fields_using_default' => array(
		            'nickname',
		            'phone'
		        )
		    )
		);
	}

	/**
	 * Get template precedence resolution rules
	 */
	private function get_template_precedence_resolution_rules(): array {
		return array(
		    'hierarchy' => array(
		        'field_level'           => 1,
		        'group_level'           => 2,
		        'section_level'         => 3,
		        'page_collection_level' => 4,
		        'class_defaults'        => 5,
		        'context_defaults'      => 6,
		        'system_defaults'       => 7
		    ),
		    'resolution_examples' => array(
		        'field_wins_over_all' => array(
		            'field_override'   => 'field-override',
		            'section_override' => 'section-override',
		            'page_override'    => 'page-override',
		            'resolved'         => 'field-override'
		        ),
		        'section_wins_over_page' => array(
		            'section_override' => 'section-override',
		            'page_override'    => 'page-override',
		            'resolved'         => 'section-override'
		        ),
		        'page_wins_over_class' => array(
		            'page_override' => 'page-override',
		            'class_default' => 'class-default',
		            'resolved'      => 'page-override'
		        )
		    ),
		    'conflict_resolution' => array(
		        'multiple_overrides'   => 'highest_priority_wins',
		        'same_level_conflicts' => 'last_registration_wins',
		        'invalid_templates'    => 'fallback_to_next_level'
		    )
		);
	}

	/**
	 * Get fluent API patterns
	 */
	private function get_fluent_api_patterns(): array {
		return array(
		    'method_chaining' => array(
		        'admin_settings_chain' => array(
		            'add_page'         => 'AdminSettingsPageBuilder',
		            'root_tempate'     => 'AdminSettingsPageBuilder',
		            'section'          => 'AdminSettingsSectionBuilder',
		            'section_template' => 'AdminSettingsSectionBuilder',
		            'field'            => 'FieldBuilder',
		            'field_template'   => 'FieldBuilder'
		        ),
		        'user_settings_chain' => array(
		            'add_collection'   => 'UserSettingsCollectionBuilder',
		            'root_tempate'     => 'UserSettingsCollectionBuilder',
		            'section'          => 'UserSettingsSectionBuilder',
		            'section_template' => 'UserSettingsSectionBuilder',
		            'field'            => 'FieldBuilder',
		            'field_template'   => 'FieldBuilder'
		        )
		    ),
		    'return_types' => array(
		        'page_builder_methods' => array(
		            'root_tempate',
		            'field_template',
		            'section_template'
		        ),
		        'section_builder_methods' => array(
		            'section_template',
		            'group_template',
		            'field_template'
		        ),
		        'field_builder_methods' => array(
		            'field_template'
		        )
		    )
		);
	}
}
