<?php
/**
 * UserSettingsSectionBuilder: Fluent builder for user settings sections with template override support.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\UserSettingsCollectionBuilder;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

/**
 * UserSettingsSectionBuilder: Fluent builder for user settings sections with template override support.
 *
 * This class extends the basic SectionBuilder functionality to provide UserSettings-specific
 * template override methods that work within WordPress profile page table constraints.
 *
 * @extends SectionBuilder<UserSettingsCollectionBuilder>
 * @method UserSettingsGroupBuilder group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null)
 * @method UserSettingsFieldsetBuilder fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null)
 * @method UserSettingsSectionBuilder field(string $field_id, string $label, string $component, array $args = array())
 * @method UserSettingsCollectionBuilder end_section()
 */
class UserSettingsSectionBuilder extends SectionBuilder {
	/**
	 * Constructor.
	 *
	 * @param BuilderRootInterface $collectionBuilder The collection builder instance.
	 * @param string $container_id The container ID.
	 * @param string $section_id The section ID.
	 * @param string $heading The section heading.
	 * @param callable $updateFn The update function for immediate data flow.
	 * @param callable|null $before Optional callback invoked before rendering the section.
	 * @param callable|null $after Optional callback invoked after rendering the section.
	 * @param int|null $order Optional section order.
	 */
	public function __construct(
		BuilderRootInterface $collectionBuilder,
		string $container_id,
		string $section_id,
		string $heading,
		callable $updateFn,
		?callable $before = null,
		?callable $after = null,
		?int $order = null
	) {
		parent::__construct(
			$collectionBuilder,
			$container_id,
			$section_id,
			$heading,
			$updateFn,
			$before,
			$after,
			$order
		);
	}

	/**
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * @return UserSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
		$args = $args ?? array();

		return new UserSettingsGroupBuilder(
			$this,
			$this->container_id,
			$this->section_id,
			$group_id,
			$heading,
			$description_cb,
			$this->updateFn,
			$args
		);
	}

	/**
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * @return UserSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
		$args = $args ?? array();

		return new UserSettingsFieldsetBuilder(
			$this,
			$this->container_id,
			$this->section_id,
			$fieldset_id,
			$heading,
			$description_cb,
			$this->updateFn,
			$args
		);
	}

	/**
	 * Set the section template for section container customization.
	 * Configures Tier 2 individual section template override via FormsServiceSession.
	 * This controls the section container layout within the WordPress profile page table constraints.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return UserSettingsSectionBuilder The UserSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function section_template(string $template_key): UserSettingsSectionBuilder {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		// Use updateFn for consistent template override handling
		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('section-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Set the field template for field wrapper customization.
	 * Configures Tier 2 individual field template override via FormsServiceSession.
	 * This controls field wrapper layout, labels, validation display, and help text
	 * within the WordPress profile page table constraints.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return UserSettingsSectionBuilder The UserSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function field_template(string $template_key): UserSettingsSectionBuilder {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		// Use updateFn for consistent template override handling
		// This sets a section-wide field template override that applies to all fields in this section
		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('field-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Set the default group template for all groups in this section.
	 * Configures Tier 2 individual group template override via FormsServiceSession.
	 *
	 * @param string $template_key The template key to use for group containers.
	 *
	 * @return UserSettingsSectionBuilder The UserSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function group_template(string $template_key): UserSettingsSectionBuilder {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		// Use updateFn for consistent template override handling
		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('group-wrapper' => $template_key)
		));

		return $this;
	}

	/**
	 * Override parent field method to support UserSettings field-level template overrides.
	 *
	 * @param string $field_id The field ID.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional configuration (context, order, field_template).
	 *
	 * @return UserSettingsSectionBuilder The UserSettingsSectionBuilder instance.
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsSectionBuilder {
		$component_context = $args['context']        ?? $args['component_context'] ?? array();
		$order             = $args['order']          ?? null;
		$field_template    = $args['field_template'] ?? null;

		// Use updateFn for immediate field data flow
		($this->updateFn)('field', array(
			'container_id' => $this->container_id,
			'section_id'   => $this->section_id,
			'field_data'   => array(
				'id'                => $field_id,
				'label'             => $label,
				'component'         => $component,
				'component_context' => $component_context,
				'order'             => $order,
			)
		));

		// Apply field-level template override if provided
		if ($field_template !== null) {
			($this->updateFn)('template_override', array(
				'element_type' => 'field',
				'element_id'   => $field_id,
				'overrides'    => array('field-wrapper' => $field_template)
			));
		}

		return $this;
	}

	/**
	 * Get the UserSettings instance from the collection builder.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface {
		// Use the clean method access we established
		if ($this->collectionBuilder instanceof \Ran\PluginLib\Settings\UserSettingsCollectionBuilder) {
			return $this->collectionBuilder->get_settings();
		}

		throw new \RuntimeException('UserSettingsSectionBuilder can only access UserSettings when used with UserSettingsCollectionBuilder');
	}

	/**
	 * End the current section and return to the parent collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof UserSettingsCollectionBuilder) {
			throw new \RuntimeException('UserSettingsSectionBuilder must be attached to a UserSettingsCollectionBuilder instance.');
		}

		return $builder;
	}
}
