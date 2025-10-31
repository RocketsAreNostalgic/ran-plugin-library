<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use PHPUnit\Framework\TestCase;

/**
 * Integration test demonstrating the two-tier template override system working together
 *
 * This test verifies that both tiers can be configured simultaneously and that
 * the storage system properly isolates overrides by element ID.
 */
class FormsTemplateOverrideResolverIntegrationTest extends TestCase {
	private FormsTemplateOverrideResolver $resolver;
	private Logger $logger;

	protected function setUp(): void {
		$this->logger   = $this->createMock(Logger::class);
		$this->resolver = new FormsTemplateOverrideResolver($this->logger);
	}

	/**
	 * Test complete two-tier system integration
	 * Demonstrates how UserSettings, developer overrides, and fluent builders would work together
	 */
	public function test_complete_two_tier_integration(): void {
		// TIER 1: Form-wide Defaults (set by UserSettings, can be overridden by developers)
		$form_defaults = array(
		    'root-wrapper'    => 'user.root-wrapper',
		    'section-wrapper' => 'user.section-wrapper',
		    'group-wrapper'   => 'user.group-wrapper',
		    'field-wrapper'   => 'user.field-wrapper'
		);
		$this->resolver->set_form_defaults($form_defaults);

		// Developer overrides specific defaults
		$this->resolver->override_form_defaults(array(
		    'section-wrapper' => 'custom.section-wrapper',  // Override system default
		    'field-wrapper'   => 'custom.field-wrapper'     // Override system default
		));

		// TIER 2: Individual Element Overrides (set via fluent builders)

		// Special section gets custom template
		$this->resolver->set_section_template_overrides('special-section', array(
		    'section-wrapper' => 'special.section-wrapper'  // Override form defaults
		));

		// Special field gets custom template
		$this->resolver->set_field_template_overrides('important-field', array(
		    'field-wrapper' => 'important.field-wrapper'    // Override form defaults
		));

		// Regular field in special section gets section-level override
		$this->resolver->set_field_template_overrides('regular-field-in-special-section', array(
		    'field-wrapper' => 'regular.field-wrapper'      // Override form defaults for this field only
		));

		// Verify both tiers are stored correctly
		$expected_form_defaults = array(
		    'root-wrapper'    => 'user.root-wrapper',  // Original
		    'section-wrapper' => 'custom.section-wrapper',   // Overridden
		    'group-wrapper'   => 'user.group-wrapper',       // Original
		    'field-wrapper'   => 'custom.field-wrapper'      // Overridden
		);
		$this->assertEquals($expected_form_defaults, $this->resolver->get_form_defaults());

		$this->assertEquals(array('section-wrapper' => 'special.section-wrapper'),
			$this->resolver->get_section_template_overrides('special-section'));

		$this->assertEquals(array('field-wrapper' => 'important.field-wrapper'),
			$this->resolver->get_field_template_overrides('important-field'));

		$this->assertEquals(array('field-wrapper' => 'regular.field-wrapper'),
			$this->resolver->get_field_template_overrides('regular-field-in-special-section'));

		// Verify isolation - other elements don't have individual overrides
		$this->assertEquals(array(), $this->resolver->get_section_template_overrides('regular-section'));
		$this->assertEquals(array(), $this->resolver->get_field_template_overrides('regular-field'));
	}

	/**
	 * Test AdminSettings vs UserSettings scenario
	 * Demonstrates how different form classes would configure different form defaults
	 */
	public function test_different_form_class_scenarios(): void {
		// Scenario 1: AdminSettings configuration
		$admin_defaults = array(
		    'root-wrapper'    => 'admin.default-page',
		    'section-wrapper' => 'admin.section-wrapper',
		    'field-wrapper'   => 'admin.field-wrapper'
		);
		$this->resolver->set_form_defaults($admin_defaults);

		$this->assertEquals($admin_defaults, $this->resolver->get_form_defaults());

		// Clear and switch to UserSettings configuration
		$this->resolver->clear_all_overrides();

		$user_defaults = array(
		    'root-wrapper'    => 'user.root-wrapper',
		    'section-wrapper' => 'user.section-wrapper',
		    'group-wrapper'   => 'user.group-wrapper',
		    'field-wrapper'   => 'user.field-wrapper',
		);
		$this->resolver->set_form_defaults($user_defaults);

		$this->assertEquals($user_defaults, $this->resolver->get_form_defaults());
		$this->assertNotEquals($admin_defaults, $this->resolver->get_form_defaults());
	}

	/**
	 * Test complex fluent builder scenario
	 * Demonstrates multiple individual overrides working independently
	 */
	public function test_complex_fluent_builder_scenario(): void {
		// Set up base form defaults
		$this->resolver->set_form_defaults(array(
		    'section-wrapper' => 'default.section',
		    'field-wrapper'   => 'default.field'
		));

		// Simulate fluent builder calls for different form elements

		// Page 1, Section A, Field 1 - gets field-specific override
		$this->resolver->set_field_template_overrides('page1-sectionA-field1', array(
		    'field-wrapper' => 'custom.field1'
		));

		// Page 1, Section A, Field 2 - gets field-specific override
		$this->resolver->set_field_template_overrides('page1-sectionA-field2', array(
		    'field-wrapper' => 'custom.field2'
		));

		// Page 1, Section B - gets section-specific override
		$this->resolver->set_section_template_overrides('page1-sectionB', array(
		    'section-wrapper' => 'custom.sectionB'
		));

		// Page 1, Section B, Field 1 - inherits section override but has field override
		$this->resolver->set_field_template_overrides('page1-sectionB-field1', array(
		    'field-wrapper' => 'custom.sectionB-field1'
		));

		// Verify each element has its own overrides
		$this->assertEquals(array('field-wrapper' => 'custom.field1'),
			$this->resolver->get_field_template_overrides('page1-sectionA-field1'));

		$this->assertEquals(array('field-wrapper' => 'custom.field2'),
			$this->resolver->get_field_template_overrides('page1-sectionA-field2'));

		$this->assertEquals(array('section-wrapper' => 'custom.sectionB'),
			$this->resolver->get_section_template_overrides('page1-sectionB'));

		$this->assertEquals(array('field-wrapper' => 'custom.sectionB-field1'),
			$this->resolver->get_field_template_overrides('page1-sectionB-field1'));

		// Verify elements without overrides return empty arrays
		$this->assertEquals(array(), $this->resolver->get_field_template_overrides('page1-sectionA-field3'));
		$this->assertEquals(array(), $this->resolver->get_section_template_overrides('page1-sectionA'));
	}

	/**
	 * Test debugging and introspection capabilities
	 */
	public function test_debugging_capabilities(): void {
		// Set up a complex configuration
		$this->resolver->set_form_defaults(array('root-wrapper' => 'system.form'));
		$this->resolver->override_form_defaults(array('section-wrapper' => 'dev.section'));
		$this->resolver->set_root_template_overrides('page1', array('root-wrapper' => 'page1.form'));
		$this->resolver->set_section_template_overrides('section1', array('section-wrapper' => 'section1.section'));
		$this->resolver->set_group_template_overrides('group1', array('group-wrapper' => 'group1.group'));
		$this->resolver->set_field_template_overrides('field1', array('field-wrapper' => 'field1.field'));

		// Get all overrides for debugging
		$all_overrides = $this->resolver->get_all_overrides();

		// Verify structure and content
		$this->assertArrayHasKey('tier_1_form_defaults', $all_overrides);
		$this->assertArrayHasKey('tier_2_individual_overrides', $all_overrides);

		$tier2 = $all_overrides['tier_2_individual_overrides'];
		$this->assertArrayHasKey('root', $tier2);
		$this->assertArrayHasKey('section', $tier2);
		$this->assertArrayHasKey('group', $tier2);
		$this->assertArrayHasKey('field', $tier2);

		// Verify specific values
		$expected_form_defaults = array(
		    'root-wrapper'    => 'system.form',
		    'section-wrapper' => 'dev.section'
		);
		$this->assertEquals($expected_form_defaults, $all_overrides['tier_1_form_defaults']);
		$this->assertEquals('page1.form', $tier2['root']['page1']['root-wrapper']);
		$this->assertEquals('section1.section', $tier2['section']['section1']['section-wrapper']);
		$this->assertEquals('group1.group', $tier2['group']['group1']['group-wrapper']);
		$this->assertEquals('field1.field', $tier2['field']['field1']['field-wrapper']);
	}
}
