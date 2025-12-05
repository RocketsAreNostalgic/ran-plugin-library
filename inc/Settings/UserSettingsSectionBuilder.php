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

use Ran\PluginLib\Settings\UserSettingsComponentProxy;
use Ran\PluginLib\Settings\UserSettingsCollectionBuilder;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

/**
 * UserSettingsSectionBuilder: Fluent builder for user settings sections with template override support.
 *
 * This class extends the basic SectionBuilder functionality to provide UserSettings-specific
 * template override methods that work within WordPress profile page table constraints.
 *
 * @extends SectionBuilder<UserSettingsCollectionBuilder>
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
	 * Add a field with a component builder to this section.
	 *
	 * @return UserSettingsComponentProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
		$result = parent::field($field_id, $label, $component, $args);
		if ($result instanceof UserSettingsComponentProxy) {
			return $result;
		}
		throw new \RuntimeException('Unexpected return type from parent::field()');
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
	 * No-op when called on the section builder directly.
	 * Enables consistent chaining whether field() returned a proxy or $this.
	 *
	 * @return static
	 */
	public function end_field(): static {
		return $this;
	}

	/**
	 * Not valid in section context - throws exception.
	 *
	 * This method exists for API consistency with union return types.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws - cannot end fieldset from section context.
	 */
	public function end_fieldset(): never {
		throw new \RuntimeException('Cannot call end_fieldset() from section context. You are not inside a fieldset.');
	}

	/**
	 * Not valid in section context - throws exception.
	 *
	 * This method exists for API consistency with union return types.
	 *
	 * @return never
	 * @throws \RuntimeException Always throws - cannot end group from section context.
	 */
	public function end_group(): never {
		throw new \RuntimeException('Cannot call end_group() from section context. You are not inside a group.');
	}

	/**
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * @return UserSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
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
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * @return UserSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
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
	 * Set the default fieldset template for all fieldsets in this section.
	 * Configures Tier 2 individual fieldset template override via FormsServiceSession.
	 *
	 * @param string $template_key The template key to use for fieldset containers.
	 *
	 * @return UserSettingsSectionBuilder The UserSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function fieldset_template(string $template_key): UserSettingsSectionBuilder {
		if (trim($template_key) === '') {
			throw new \InvalidArgumentException('Template key cannot be empty');
		}

		// Use updateFn for consistent template override handling
		($this->updateFn)('template_override', array(
			'element_type' => 'section',
			'element_id'   => $this->section_id,
			'overrides'    => array('fieldset-wrapper' => $template_key)
		));

		return $this;
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
	 * End the current section and return to the parent collection builder.
	 *
	 * @return UserSettingsCollectionBuilder
	 */
	public function end_section(): UserSettingsCollectionBuilder {
		$builder = parent::end_section();
		/** @var UserSettingsCollectionBuilder $builder */
		if (!$builder instanceof UserSettingsCollectionBuilder) {
			throw new \RuntimeException('UserSettingsSectionBuilder must be attached to a UserSettingsCollectionBuilder instance.');
		}

		return $builder;
	}

	/**
	 * Factory method to create UserSettingsComponentProxy.
	 *
	 * @return UserSettingsComponentProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $group_id,
		?string $field_template,
		array $component_context
	): UserSettingsComponentProxy {
		return new UserSettingsComponentProxy(
			$builder,
			$this,
			$this->updateFn,
			$this->container_id,
			$this->section_id,
			$component_alias,
			$group_id,
			$field_template,
			$component_context
		);
	}
}
