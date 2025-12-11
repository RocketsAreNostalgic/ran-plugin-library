<?php

namespace Ran\PluginLib\Tests\Unit\Forms;

use Ran\PluginLib\Util\Logger;
use Ran\PluginLib\Forms\FormsTemplateOverrideResolver;
use PHPUnit\Framework\TestCase;

/**
 * Test FormsTemplateOverrideResolver two-tier storage system
 *
 * Verifies that both tiers of template overrides can be stored and retrieved correctly:
 * - Tier 1: Form-wide Defaults
 * - Tier 2: Individual Element Overrides (Root, Section, Group, Field)
 */
class FormsTemplateOverrideResolverTest extends TestCase {
	private FormsTemplateOverrideResolver $resolver;
	private Logger $logger;

	protected function setUp(): void {
		$this->logger   = $this->createMock(Logger::class);
		$this->resolver = new FormsTemplateOverrideResolver($this->logger);
	}

	/**
	 * Test Tier 1: Form-wide Defaults storage and retrieval
	 */
	public function test_form_defaults_storage(): void {
		$defaults = array(
		    'root-wrapper'    => 'admin.default-page',
		    'section-wrapper' => 'admin.section-wrapper',
		    'field-wrapper'   => 'admin.field-wrapper'
		);

		$this->resolver->set_form_defaults($defaults);
		$retrieved = $this->resolver->get_form_defaults();

		$this->assertEquals($defaults, $retrieved);
		$this->assertArrayHasKey('root-wrapper', $retrieved);
		$this->assertEquals('admin.default-page', $retrieved['root-wrapper']);
	}

	/**
	 * Test Tier 1: Form-wide Defaults override functionality
	 */
	public function test_form_defaults_override(): void {
		// Set initial defaults
		$initial_defaults = array(
		    'root-wrapper'    => 'admin.default-page',
		    'section-wrapper' => 'admin.section-wrapper',
		    'field-wrapper'   => 'admin.field-wrapper'
		);
		$this->resolver->set_form_defaults($initial_defaults);

		// Override specific defaults
		$overrides = array(
		    'section-wrapper' => 'custom.section-wrapper',
		    'field-wrapper'   => 'custom.field-wrapper'
		);
		$this->resolver->override_form_defaults($overrides);

		$retrieved = $this->resolver->get_form_defaults();

		// Should have merged the overrides
		$expected = array(
		    'root-wrapper'    => 'admin.default-page',     // Original
		    'section-wrapper' => 'custom.section-wrapper', // Overridden
		    'field-wrapper'   => 'custom.field-wrapper'    // Overridden
		);

		$this->assertEquals($expected, $retrieved);
		$this->assertEquals('custom.section-wrapper', $retrieved['section-wrapper']);
		$this->assertEquals('admin.default-page', $retrieved['root-wrapper']); // Should remain unchanged
	}

	/**
	 * Test Tier 2: Root Template Overrides storage and retrieval
	 */
	public function test_root_template_overrides_storage(): void {
		$root_id   = 'admin-page-1';
		$overrides = array(
		    'root-wrapper' => 'special.page-wrapper'
		);

		$this->resolver->set_root_template_overrides($root_id, $overrides);
		$retrieved = $this->resolver->get_root_template_overrides($root_id);

		$this->assertEquals($overrides, $retrieved);
		$this->assertEquals('special.page-wrapper', $retrieved['root-wrapper']);

		// Test non-existent root returns empty array
		$empty = $this->resolver->get_root_template_overrides('non-existent');
		$this->assertEquals(array(), $empty);
	}

	/**
	 * Test Tier 2: Section Template Overrides storage and retrieval
	 */
	public function test_section_template_overrides_storage(): void {
		$section_id = 'general-settings';
		$overrides  = array(
		    'section-wrapper' => 'special.section-wrapper',
		    'group-wrapper'   => 'special.group-wrapper'
		);

		$this->resolver->set_section_template_overrides($section_id, $overrides);
		$retrieved = $this->resolver->get_section_template_overrides($section_id);

		$this->assertEquals($overrides, $retrieved);
		$this->assertEquals('special.section-wrapper', $retrieved['section-wrapper']);
		$this->assertEquals('special.group-wrapper', $retrieved['group-wrapper']);

		// Test non-existent section returns empty array
		$empty = $this->resolver->get_section_template_overrides('non-existent');
		$this->assertEquals(array(), $empty);
	}

	/**
	 * Test Tier 2: Group Template Overrides storage and retrieval
	 */
	public function test_group_template_overrides_storage(): void {
		$group_id  = 'appearance-group';
		$overrides = array(
		    'group-wrapper' => 'special.group-wrapper',
		    'field-wrapper' => 'special.field-wrapper'
		);

		$this->resolver->set_group_template_overrides($group_id, $overrides);
		$retrieved = $this->resolver->get_group_template_overrides($group_id);

		$this->assertEquals($overrides, $retrieved);
		$this->assertEquals('special.group-wrapper', $retrieved['group-wrapper']);

		// Test non-existent group returns empty array
		$empty = $this->resolver->get_group_template_overrides('non-existent');
		$this->assertEquals(array(), $empty);
	}

	/**
	 * Test Tier 2: Field Template Overrides storage and retrieval
	 */
	public function test_field_template_overrides_storage(): void {
		$field_id  = 'site-title';
		$overrides = array(
		    'field-wrapper' => 'special.field-wrapper'
		);

		$this->resolver->set_field_template_overrides($field_id, $overrides);
		$retrieved = $this->resolver->get_field_template_overrides($field_id);

		$this->assertEquals($overrides, $retrieved);
		$this->assertEquals('special.field-wrapper', $retrieved['field-wrapper']);

		// Test non-existent field returns empty array
		$empty = $this->resolver->get_field_template_overrides('non-existent');
		$this->assertEquals(array(), $empty);
	}

	/**
	 * Test multiple individual element overrides with proper isolation
	 */
	public function test_multiple_individual_element_overrides_isolation(): void {
		// Set overrides for multiple elements of the same type
		$this->resolver->set_section_template_overrides('section-1', array(
		    'section-wrapper' => 'template-1'
		));
		$this->resolver->set_section_template_overrides('section-2', array(
		    'section-wrapper' => 'template-2'
		));

		// Verify each section has its own overrides
		$section1_overrides = $this->resolver->get_section_template_overrides('section-1');
		$section2_overrides = $this->resolver->get_section_template_overrides('section-2');

		$this->assertEquals('template-1', $section1_overrides['section-wrapper']);
		$this->assertEquals('template-2', $section2_overrides['section-wrapper']);

		// Verify they don't interfere with each other
		$this->assertNotEquals($section1_overrides, $section2_overrides);
	}

	/**
	 * Test both tiers can be stored simultaneously
	 */
	public function test_both_tiers_simultaneous_storage(): void {
		// Tier 1: Form defaults
		$form_defaults = array(
		    'root-wrapper'    => 'system.form',
		    'section-wrapper' => 'system.section'
		);
		$this->resolver->set_form_defaults($form_defaults);

		// Override some form defaults
		$this->resolver->override_form_defaults(array(
		    'section-wrapper' => 'custom.section'
		));

		// Tier 2: Individual element overrides
		$this->resolver->set_field_template_overrides('special-field', array(
		    'field-wrapper' => 'individual.field'
		));

		// Verify both tiers are stored correctly
		$expected_defaults = array(
		    'root-wrapper'    => 'system.form',
		    'section-wrapper' => 'custom.section'  // Overridden
		);
		$this->assertEquals($expected_defaults, $this->resolver->get_form_defaults());
		$this->assertEquals(array('field-wrapper' => 'individual.field'),
			$this->resolver->get_field_template_overrides('special-field'));
	}

	/**
	 * Test clear_all_overrides functionality
	 */
	public function test_clear_all_overrides(): void {
		// Set up all types of overrides
		$this->resolver->set_form_defaults(array('root-wrapper' => 'test'));
		$this->resolver->set_root_template_overrides('root-1', array('root-wrapper' => 'test'));
		$this->resolver->set_section_template_overrides('section-1', array('section-wrapper' => 'test'));
		$this->resolver->set_group_template_overrides('group-1', array('group-wrapper' => 'test'));
		$this->resolver->set_field_template_overrides('field-1', array('field-wrapper' => 'test'));

		// Clear all overrides
		$this->resolver->clear_all_overrides();

		// Verify all are cleared
		$this->assertEquals(array(), $this->resolver->get_form_defaults());
		$this->assertEquals(array(), $this->resolver->get_root_template_overrides('root-1'));
		$this->assertEquals(array(), $this->resolver->get_section_template_overrides('section-1'));
		$this->assertEquals(array(), $this->resolver->get_group_template_overrides('group-1'));
		$this->assertEquals(array(), $this->resolver->get_field_template_overrides('field-1'));
	}

	/**
	 * Test get_all_overrides debugging functionality
	 */
	public function test_get_all_overrides_debugging(): void {
		// Set up various overrides
		$this->resolver->set_form_defaults(array('root-wrapper' => 'system.form'));
		$this->resolver->override_form_defaults(array('section-wrapper' => 'dev.section'));
		$this->resolver->set_field_template_overrides('field-1', array('field-wrapper' => 'individual.field'));

		$all_overrides = $this->resolver->get_all_overrides();

		// Verify structure
		$this->assertArrayHasKey('tier_1_form_defaults', $all_overrides);
		$this->assertArrayHasKey('tier_2_individual_overrides', $all_overrides);

		// Verify content
		$expected_defaults = array(
		    'root-wrapper'    => 'system.form',
		    'section-wrapper' => 'dev.section'
		);
		$this->assertEquals($expected_defaults, $all_overrides['tier_1_form_defaults']);
		$this->assertEquals(array('field-wrapper' => 'individual.field'),
			$all_overrides['tier_2_individual_overrides']['field']['field-1']);
	}

	/**
	 * Test edge cases and boundary conditions
	 */
	public function test_edge_cases(): void {
		// Test empty arrays
		$this->resolver->set_form_defaults(array());
		$this->assertEquals(array(), $this->resolver->get_form_defaults());

		// Test overwriting existing overrides
		$this->resolver->set_field_template_overrides('field-1', array('field-wrapper' => 'template-1'));
		$this->resolver->set_field_template_overrides('field-1', array('field-wrapper' => 'template-2'));

		$overrides = $this->resolver->get_field_template_overrides('field-1');
		$this->assertEquals('template-2', $overrides['field-wrapper']);

		// Test special characters in IDs
		$special_id = 'field-with-special_chars.123';
		$this->resolver->set_field_template_overrides($special_id, array('field-wrapper' => 'special'));
		$retrieved = $this->resolver->get_field_template_overrides($special_id);
		$this->assertEquals(array('field-wrapper' => 'special'), $retrieved);
	}

	// ========================================
	// TASK 2.3: Two-Tier Template Resolution Algorithm Tests
	// ========================================

	/**
	 * Test simplified two-tier template resolution vs FormsBaseTrait's 6-tier system
	 *
	 * FormsBaseTrait: field → group → section → page → class_defaults → system_defaults (6 tiers)
	 * FormsTemplateOverrideResolver: individual_overrides → form_defaults (2 tiers + emergency fallback)
	 */
	public function test_simplified_two_tier_template_resolution_precedence(): void {
		// Set up Tier 1: Form-wide defaults (replaces both class_defaults + system_defaults)
		$this->resolver->set_form_defaults(array(
			'field-wrapper'   => 'form.default-field-wrapper',
			'section-wrapper' => 'form.default-section-wrapper'
		));

		// Set up Tier 2: Individual element overrides (same hierarchy as FormsBaseTrait)
		$this->resolver->set_field_template_overrides('special-field', array(
			'field-wrapper' => 'individual.special-field-wrapper'
		));

		// Test Tier 2 takes precedence over Tier 1
		$context  = array('field_id' => 'special-field');
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('individual.special-field-wrapper', $resolved);

		// Test Tier 1 is used when no Tier 2 override exists
		$context  = array('field_id' => 'regular-field');
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('form.default-field-wrapper', $resolved);

		// Test emergency fallback is used when form_defaults not configured
		// (This should rarely happen if form classes properly configure form_defaults)
		$resolved = $this->resolver->resolve_template('unknown-wrapper', array());
		$this->assertEquals('shared.root-wrapper', $resolved); // Emergency fallback
	}

	/**
	 * Test hierarchical resolution within Tier 2: Field > Group > Section > Root
	 */
	public function test_tier_2_hierarchical_precedence(): void {
		// Set up all levels of Tier 2 overrides for the same template type
		$this->resolver->set_root_template_overrides('page-1', array(
			'field-wrapper' => 'root.field-wrapper'
		));
		$this->resolver->set_section_template_overrides('section-1', array(
			'field-wrapper' => 'section.field-wrapper'
		));
		$this->resolver->set_group_template_overrides('group-1', array(
			'field-wrapper' => 'group.field-wrapper'
		));
		$this->resolver->set_field_template_overrides('field-1', array(
			'field-wrapper' => 'field.field-wrapper'
		));

		// Test field-level override has highest precedence
		$context = array(
			'root_id'    => 'page-1',
			'section_id' => 'section-1',
			'group_id'   => 'group-1',
			'field_id'   => 'field-1'
		);
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('field.field-wrapper', $resolved);

		// Test group-level when no field-level override
		$context = array(
			'root_id'    => 'page-1',
			'section_id' => 'section-1',
			'group_id'   => 'group-1',
			'field_id'   => 'different-field' // No override for this field
		);
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('group.field-wrapper', $resolved);

		// Test section-level when no group-level override
		$context = array(
			'root_id'    => 'page-1',
			'section_id' => 'section-1',
			'group_id'   => 'different-group', // No override for this group
			'field_id'   => 'different-field'
		);
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('section.field-wrapper', $resolved);

		// Test root-level when no section-level override
		$context = array(
			'root_id'    => 'page-1',
			'section_id' => 'different-section', // No override for this section
			'group_id'   => 'different-group',
			'field_id'   => 'different-field'
		);
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('root.field-wrapper', $resolved);
	}

	/**
	 * Test individual element overrides only affect specific elements
	 */
	public function test_individual_element_override_isolation(): void {
		// Set up individual overrides for specific elements
		$this->resolver->set_field_template_overrides('field-1', array(
			'field-wrapper' => 'special.field-1-wrapper'
		));
		$this->resolver->set_section_template_overrides('section-1', array(
			'section-wrapper' => 'special.section-1-wrapper'
		));

		// Set up form-wide defaults
		$this->resolver->set_form_defaults(array(
			'field-wrapper'   => 'form.default-field-wrapper',
			'section-wrapper' => 'form.default-section-wrapper'
		));

		// Test field-1 gets its individual override
		$context  = array('field_id' => 'field-1');
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('special.field-1-wrapper', $resolved);

		// Test field-2 gets form-wide default (no individual override)
		$context  = array('field_id' => 'field-2');
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('form.default-field-wrapper', $resolved);

		// Test section-1 gets its individual override
		$context  = array('section_id' => 'section-1');
		$resolved = $this->resolver->resolve_template('section-wrapper', $context);
		$this->assertEquals('special.section-1-wrapper', $resolved);

		// Test section-2 gets form-wide default (no individual override)
		$context  = array('section_id' => 'section-2');
		$resolved = $this->resolver->resolve_template('section-wrapper', $context);
		$this->assertEquals('form.default-section-wrapper', $resolved);
	}

	/**
	 * Test system fallback templates for different template types
	 */
	public function test_system_fallback_templates(): void {
		// Test known template types get appropriate fallbacks
		$this->assertEquals('layout.container.root-wrapper', $this->resolver->resolve_template('root-wrapper', array()));
		$this->assertEquals('layout.zone.section-wrapper', $this->resolver->resolve_template('section-wrapper', array()));
		$this->assertEquals('layout.zone.group-wrapper', $this->resolver->resolve_template('group-wrapper', array()));
		$this->assertEquals('layout.field.field-wrapper', $this->resolver->resolve_template('field-wrapper', array()));

		// Test unknown template types get generic fallback
		$this->assertEquals('shared.root-wrapper', $this->resolver->resolve_template('unknown-wrapper', array()));

		// Test unknown template types get generic fallback (FormsBaseTrait approach)
		$this->assertEquals('shared.root-wrapper', $this->resolver->resolve_template('custom-field-wrapper', array()));
		$this->assertEquals('shared.root-wrapper', $this->resolver->resolve_template('custom-section-wrapper', array()));
	}

	/**
	 * Test base system fallbacks (read-only, single source of truth)
	 */
	public function test_base_system_fallbacks(): void {
		// Test known template types get appropriate fallbacks
		$this->assertEquals('layout.container.root-wrapper', $this->resolver->resolve_template('root-wrapper', array()));
		$this->assertEquals('layout.zone.section-wrapper', $this->resolver->resolve_template('section-wrapper', array()));
		$this->assertEquals('layout.zone.group-wrapper', $this->resolver->resolve_template('group-wrapper', array()));
		$this->assertEquals('layout.field.field-wrapper', $this->resolver->resolve_template('field-wrapper', array()));

		// Test unknown template types get generic fallback (same as FormsBaseTrait approach)
		$this->assertEquals('shared.root-wrapper', $this->resolver->resolve_template('custom-field-wrapper', array()));
		$this->assertEquals('shared.root-wrapper', $this->resolver->resolve_template('unknown-wrapper', array()));
	}

	/**
	 * Test template resolution logging - Tier 1 (form defaults) should NOT log
	 *
	 * Tier 1 is the expected/common path and should not produce debug logs.
	 * Only Tier 2 overrides and emergency fallbacks should log.
	 */
	public function test_template_resolution_tier1_no_logging(): void {
		// Tier 1 (form defaults) should NOT produce any debug logs
		$this->logger->expects($this->never())
			->method('debug');
		$this->logger->expects($this->never())
			->method('warning');

		// Set up form default and resolve
		$this->resolver->set_form_defaults(array('field-wrapper' => 'form.field-wrapper'));
		$resolved = $this->resolver->resolve_template('field-wrapper', array());

		$this->assertEquals('form.field-wrapper', $resolved);
	}

	/**
	 * Test template resolution logging - Tier 2 overrides SHOULD log
	 */
	public function test_template_resolution_tier2_logs_override(): void {
		// Tier 2 override should produce a debug log
		$this->logger->expects($this->once())
			->method('debug')
			->with(
				$this->stringContains('FormsTemplateOverrideResolver: Template resolved via Tier 2 - field override'),
				$this->callback(function($context) {
					return isset($context['template_type'])
						&& isset($context['template'])
						&& isset($context['field_id'])
						&& $context['field_id'] === 'special-field';
				})
			);

		// Set up form default AND field override
		$this->resolver->set_form_defaults(array('field-wrapper' => 'form.field-wrapper'));
		$this->resolver->set_field_template_overrides('special-field', array(
			'field-wrapper' => 'special.field-wrapper'
		));

		$resolved = $this->resolver->resolve_template('field-wrapper', array('field_id' => 'special-field'));
		$this->assertEquals('special.field-wrapper', $resolved);
	}

	/**
	 * Test template resolution - system fallback logs DEBUG in verbose mode
	 *
	 * System fallback is expected behavior when form classes only override specific templates
	 * (e.g., AdminSettings only overrides root-wrapper, letting field-wrapper use system default).
	 * Logging is gated behind RAN_VERBOSE_DEBUG which is enabled in test bootstrap.
	 */
	public function test_template_resolution_system_fallback_logs_debug_in_verbose_mode(): void {
		// System fallback should produce a DEBUG log (expected behavior, not an error)
		// RAN_VERBOSE_DEBUG is defined as true in test_bootstrap.php
		$this->logger->expects($this->once())
			->method('debug')
			->with(
				$this->stringContains('FormsTemplateOverrideResolver: Template resolved via system fallback'),
				$this->callback(function($context) {
					return isset($context['template_type'])
						&& isset($context['template']);
				})
			);

		// Don't set form defaults - this triggers system fallback
		$resolved = $this->resolver->resolve_template('field-wrapper', array());
		$this->assertEquals('layout.field.field-wrapper', $resolved);
	}

	/**
	 * Test error handling for invalid template types
	 */
	public function test_error_handling_invalid_template_type(): void {
		// Test empty template type throws exception
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Template type cannot be empty');

		// Set up logger expectation for error logging
		$this->logger->expects($this->once())
			->method('error')
			->with(
				$this->stringContains('FormsTemplateOverrideResolver: Empty template_type provided'),
				$this->isType('array')
			);

		$this->resolver->resolve_template('', array());
	}



	/**
	 * Test that FormsTemplateOverrideResolver is truly simplified vs FormsBaseTrait
	 */
	public function test_simplified_vs_formbasetrait_comparison(): void {
		// FormsBaseTrait has 6 tiers: field → group → section → page → class_defaults → system_defaults
		// FormsTemplateOverrideResolver has 2 tiers: individual_overrides → form_defaults

		// Set up form-wide defaults (Tier 1) - this replaces BOTH class_defaults AND system_defaults
		$this->resolver->set_form_defaults(array(
			'field-wrapper' => 'unified.form-default' // Single source instead of class + system defaults
		));

		// Set up individual override (Tier 2)
		$this->resolver->set_field_template_overrides('special-field', array(
			'field-wrapper' => 'individual.override'
		));

		// Test: Individual override wins (same as FormsBaseTrait)
		$resolved = $this->resolver->resolve_template('field-wrapper', array('field_id' => 'special-field'));
		$this->assertEquals('individual.override', $resolved);

		// Test: Form-wide default used (simplified - no separate class vs system defaults)
		$resolved = $this->resolver->resolve_template('field-wrapper', array('field_id' => 'regular-field'));
		$this->assertEquals('unified.form-default', $resolved);

		// Key difference: No separate "class instance defaults" tier
		// FormsBaseTrait would check $this->default_template_overrides before system defaults
		// FormsTemplateOverrideResolver goes directly from individual overrides to form_defaults
	}

	/**
	 * Test complete two-tier resolution workflow
	 */
	public function test_complete_two_tier_resolution_workflow(): void {
		// Scenario: Admin form with custom field template

		// Step 1: Set form-wide defaults (what AdminSettings would do)
		$this->resolver->set_form_defaults(array(
			'root-wrapper'    => 'admin.default-page',
			'section-wrapper' => 'admin.section-wrapper',
			'field-wrapper'   => 'admin.field-wrapper'
		));

		// Step 2: Developer overrides some form-wide defaults
		$this->resolver->override_form_defaults(array(
			'section-wrapper' => 'custom.section-wrapper'
		));

		// Step 3: Individual field gets special treatment via fluent builder
		$this->resolver->set_field_template_overrides('special-field', array(
			'field-wrapper' => 'special.field-wrapper'
		));

		// Test resolution for regular field (uses form-wide default)
		$context  = array('field_id' => 'regular-field');
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('admin.field-wrapper', $resolved);

		// Test resolution for special field (uses individual override)
		$context  = array('field_id' => 'special-field');
		$resolved = $this->resolver->resolve_template('field-wrapper', $context);
		$this->assertEquals('special.field-wrapper', $resolved);

		// Test resolution for section (uses developer override of form-wide default)
		$context  = array('section_id' => 'any-section');
		$resolved = $this->resolver->resolve_template('section-wrapper', $context);
		$this->assertEquals('custom.section-wrapper', $resolved);

		// Test resolution for form wrapper (uses original form-wide default)
		$resolved = $this->resolver->resolve_template('root-wrapper', array());
		$this->assertEquals('admin.default-page', $resolved);

		// Test resolution for unknown template (uses system fallback)
		$resolved = $this->resolver->resolve_template('unknown-wrapper', array());
		$this->assertEquals('shared.root-wrapper', $resolved);
	}
}
