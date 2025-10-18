<?php

namespace Ran\PluginLib\Forms;

use Ran\PluginLib\Util\Logger;

/**
 * TemplateOverrideResolver - Specialized Template Resolution System
 *
 * Handles two-tier template override storage and hierarchical resolution:
 * - Tier 1: Form-wide Defaults (set by form classes and developers)
 * - Tier 2: Individual Element Overrides (set via fluent builders, indexed by element ID)
 *
 * Provides clean separation of concerns for template resolution logic.
 */
class TemplateOverrideResolver {
	/**
	 * Logger instance for debugging template resolution
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Base system fallback templates - minimal essential fallbacks
	 * Pattern matching handles variations (e.g., 'field' matches 'field-wrapper')
	 *
	 * @var array<string, string> Template type => fallback template key mappings
	 */
	private static array $BASE_FALLBACKS = array(
		'root-wrapper'    => 'shared.default-wrapper',
		'root'            => 'shared.default-wrapper',
		'form-wrapper'    => 'shared.default-wrapper',
		'form'            => 'shared.default-wrapper',
		'section-wrapper' => 'shared.section-wrapper',
		'section'         => 'shared.section-wrapper',
		'group-wrapper'   => 'shared.group-wrapper',
		'group'           => 'shared.group-wrapper',
		'field-wrapper'   => 'shared.field-wrapper',
		'field'           => 'shared.field-wrapper',
	);

	/**
	 * Tier 1: Form-wide Defaults
	 * Set by form classes and can be overridden by developers to customize the entire form instance
	 *
	 * @var array<string, string> Template type => template key mappings
	 */
	private array $form_defaults = array();

	/**
	 * Tier 2: Individual Element Overrides - Root Level (Pages/Collections)
	 * Set via fluent builders for specific root elements, indexed by root_id
	 *
	 * @var array<string, array<string, string>> root_id => [template_type => template_key]
	 */
	private array $root_template_overrides = array();

	/**
	 * Tier 2: Individual Element Overrides - Section Level
	 * Set via fluent builders for specific sections, indexed by section_id
	 *
	 * @var array<string, array<string, string>> section_id => [template_type => template_key]
	 */
	private array $section_template_overrides = array();

	/**
	 * Tier 2: Individual Element Overrides - Group Level
	 * Set via fluent builders for specific groups, indexed by group_id
	 *
	 * @var array<string, array<string, string>> group_id => [template_type => template_key]
	 */
	private array $group_template_overrides = array();

	/**
	 * Tier 2: Individual Element Overrides - Field Level
	 * Set via fluent builders for specific fields, indexed by field_id
	 *
	 * @var array<string, array<string, string>> field_id => [template_type => template_key]
	 */
	private array $field_template_overrides = array();

	/**
	 * Constructor
	 *
	 * @param Logger $logger Logger instance for debugging template resolution
	 */
	public function __construct(Logger $logger) {
		$this->logger = $logger;
	}

	/**
	 * Resolve template with simplified two-tier hierarchical fallback
	 *
	 * SIMPLIFIED vs FormBaseTrait's 6-tier system:
	 * FormBaseTrait: field → group → section → page → class_defaults → system_defaults (6 tiers)
	 * TemplateOverrideResolver: individual_overrides → form_defaults (2 tiers + emergency fallback)
	 *
	 * Two-Tier Precedence:
	 * - Tier 2: Individual Element Overrides (field → group → section → root hierarchy)
	 * - Tier 1: Form-wide Defaults (replaces both class_defaults + system_defaults)
	 * - Emergency Fallback: Only used if form_defaults not properly configured
	 *
	 * @param string $template_type The template type (e.g., 'page', 'section', 'group', 'field-wrapper')
	 * @param array<string, mixed> $context Resolution context containing field_id, section_id, group_id, root_id, etc.
	 *
	 * @return string The resolved template key
	 * @throws \InvalidArgumentException When template_type is empty or invalid
	 */
	public function resolve_template(string $template_type, array $context = array()): string {
		// Validate input
		if (empty($template_type)) {
			$this->logger->error('TemplateOverrideResolver: Empty template_type provided', array(
				'context' => $context
			));
			throw new \InvalidArgumentException('Template type cannot be empty');
		}

		// TIER 2: Individual Element Overrides (Highest Priority)

		// 2.1. Check field-level override (highest priority within Tier 2)
		if (isset($context['field_id'])) {
			$field_overrides = $this->get_field_template_overrides($context['field_id']);
			if (isset($field_overrides[$template_type])) {
				$this->logger->debug('TemplateOverrideResolver: Template resolved via Tier 2 - field override', array(
					'template_type' => $template_type,
					'template'      => $field_overrides[$template_type],
					'field_id'      => $context['field_id'],
					'tier'          => 'Tier 2 - Individual Element Override (Field)'
				));
				return $field_overrides[$template_type];
			}
		}

		// 2.2. Check group-level override
		if (isset($context['group_id'])) {
			$group_overrides = $this->get_group_template_overrides($context['group_id']);
			if (isset($group_overrides[$template_type])) {
				$this->logger->debug('TemplateOverrideResolver: Template resolved via Tier 2 - group override', array(
					'template_type' => $template_type,
					'template'      => $group_overrides[$template_type],
					'group_id'      => $context['group_id'],
					'tier'          => 'Tier 2 - Individual Element Override (Group)'
				));
				return $group_overrides[$template_type];
			}
		}

		// 2.3. Check section-level override
		if (isset($context['section_id'])) {
			$section_overrides = $this->get_section_template_overrides($context['section_id']);
			if (isset($section_overrides[$template_type])) {
				$this->logger->debug('TemplateOverrideResolver: Template resolved via Tier 2 - section override', array(
					'template_type' => $template_type,
					'template'      => $section_overrides[$template_type],
					'section_id'    => $context['section_id'],
					'tier'          => 'Tier 2 - Individual Element Override (Section)'
				));
				return $section_overrides[$template_type];
			}
		}

		// 2.4. Check root-level override (page/collection)
		if (isset($context['root_id'])) {
			$root_overrides = $this->get_root_template_overrides($context['root_id']);
			if (isset($root_overrides[$template_type])) {
				$this->logger->debug('TemplateOverrideResolver: Template resolved via Tier 2 - root override', array(
					'template_type' => $template_type,
					'template'      => $root_overrides[$template_type],
					'root_id'       => $context['root_id'],
					'tier'          => 'Tier 2 - Individual Element Override (Root)'
				));
				return $root_overrides[$template_type];
			}
		}

		// TIER 1: Form-wide Defaults (Final Priority)
		// This replaces BOTH "class instance defaults" AND "system defaults" from FormBaseTrait
		// for a simplified two-tier system
		if (isset($this->form_defaults[$template_type])) {
			$this->logger->debug('TemplateOverrideResolver: Template resolved via Tier 1 - form-wide default', array(
				'template_type' => $template_type,
				'template'      => $this->form_defaults[$template_type],
				'tier'          => 'Tier 1 - Form-wide Default'
			));
			return $this->form_defaults[$template_type];
		}

		// EMERGENCY FALLBACK: If no form-wide default is set, use system fallback
		// This should rarely be used if form classes properly configure form_defaults
		$system_fallback = $this->get_system_fallback_template($template_type);
		$this->logger->debug('TemplateOverrideResolver: Template resolved via emergency fallback', array(
			'template_type' => $template_type,
			'template'      => $system_fallback,
			'tier'          => 'Emergency System Fallback'
		));
		return $system_fallback;
	}

	/**
	 * Get system fallback template for a given template type
	 *
	 * Follows FormBaseTrait approach: check known templates, otherwise use generic fallback
	 *
	 * @param string $template_type The template type
	 * @return string The fallback template key
	 */
	public function get_system_fallback_template(string $template_type): string {
		// Check if we have a specific fallback for this template type
		if (isset(self::$BASE_FALLBACKS[$template_type])) {
			return self::$BASE_FALLBACKS[$template_type];
		}

		// Generic fallback for unknown template types (same as FormBaseTrait approach)
		return 'shared.default-wrapper';
	}



	/**
	 * Set form-wide defaults (Tier 1)
	 * These are the base defaults set by form classes and can be customized by developers
	 *
	 * @param array<string, string> $defaults Template type => template key mappings
	 * @return void
	 */
	public function set_form_defaults(array $defaults): void {
		$this->form_defaults = $defaults;
	}

	/**
	 * Override specific form-wide defaults (Tier 1)
	 * Allows developers to customize specific templates without replacing all defaults
	 *
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function override_form_defaults(array $overrides): void {
		$this->form_defaults = array_merge($this->form_defaults, $overrides);
	}

	/**
	 * Get form-wide defaults (Tier 1)
	 *
	 * @return array<string, string> Template type => template key mappings
	 */
	public function get_form_defaults(): array {
		return $this->form_defaults;
	}

	/**
	 * Set template overrides for a specific root element (page/collection)
	 * These have highest precedence for the specific root element (Tier 2)
	 *
	 * @param string $root_id The unique identifier for the root element
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function set_root_template_overrides(string $root_id, array $overrides): void {
		$this->root_template_overrides[$root_id] = $overrides;
	}

	/**
	 * Get template overrides for a specific root element
	 *
	 * @param string $root_id The unique identifier for the root element
	 * @return array<string, string> Template type => template key mappings
	 */
	public function get_root_template_overrides(string $root_id): array {
		return $this->root_template_overrides[$root_id] ?? array();
	}

	/**
	 * Set template overrides for a specific section
	 * These have highest precedence for the specific section (Tier 2)
	 *
	 * @param string $section_id The unique identifier for the section
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function set_section_template_overrides(string $section_id, array $overrides): void {
		$this->section_template_overrides[$section_id] = $overrides;
	}

	/**
	 * Get template overrides for a specific section
	 *
	 * @param string $section_id The unique identifier for the section
	 * @return array<string, string> Template type => template key mappings
	 */
	public function get_section_template_overrides(string $section_id): array {
		return $this->section_template_overrides[$section_id] ?? array();
	}

	/**
	 * Set template overrides for a specific group
	 * These have highest precedence for the specific group (Tier 2)
	 *
	 * @param string $group_id The unique identifier for the group
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function set_group_template_overrides(string $group_id, array $overrides): void {
		$this->group_template_overrides[$group_id] = $overrides;
	}

	/**
	 * Get template overrides for a specific group
	 *
	 * @param string $group_id The unique identifier for the group
	 * @return array<string, string> Template type => template key mappings
	 */
	public function get_group_template_overrides(string $group_id): array {
		return $this->group_template_overrides[$group_id] ?? array();
	}

	/**
	 * Set template overrides for a specific field
	 * These have highest precedence for the specific field (Tier 2)
	 *
	 * @param string $field_id The unique identifier for the field
	 * @param array<string, string> $overrides Template type => template key mappings
	 * @return void
	 */
	public function set_field_template_overrides(string $field_id, array $overrides): void {
		$this->field_template_overrides[$field_id] = $overrides;
	}

	/**
	 * Get template overrides for a specific field
	 *
	 * @param string $field_id The unique identifier for the field
	 * @return array<string, string> Template type => template key mappings
	 */
	public function get_field_template_overrides(string $field_id): array {
		return $this->field_template_overrides[$field_id] ?? array();
	}

	/**
	 * Clear all template overrides (useful for testing)
	 *
	 * @return void
	 */
	public function clear_all_overrides(): void {
		$this->form_defaults              = array();
		$this->root_template_overrides    = array();
		$this->section_template_overrides = array();
		$this->group_template_overrides   = array();
		$this->field_template_overrides   = array();
	}

	/**
	 * Get all stored overrides for debugging purposes
	 *
	 * @return array<string, mixed> All override data organized by tier
	 */
	public function get_all_overrides(): array {
		return array(
			'tier_1_form_defaults'        => $this->form_defaults,
			'tier_2_individual_overrides' => array(
				'root'    => $this->root_template_overrides,
				'section' => $this->section_template_overrides,
				'group'   => $this->group_template_overrides,
				'field'   => $this->field_template_overrides,
			),

		);
	}
}
