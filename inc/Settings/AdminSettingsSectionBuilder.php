<?php
/**
 * AdminSettingsSectionBuilder: Section builder specialized for AdminSettings pages.
 *
 * @extends SectionBuilder<AdminSettingsPageBuilder>
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\AdminSettingsPageBuilder;
use Ran\PluginLib\Settings\AdminSettingsComponentProxy;
use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

class AdminSettingsSectionBuilder extends SectionBuilder {
	private AdminSettingsPageBuilder $pageBuilder;

	public function __construct(
		AdminSettingsPageBuilder $pageBuilder,
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
		$this->pageBuilder = $pageBuilder;
	}

	/**
	 * Get the FormsInterface instance for the current page.
	 *
	 * @return FormsInterface
	 */
	public function get_forms(): FormsInterface {
		return $this->pageBuilder->get_forms();
	}

	/**
	 * Set the heading for this section.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function heading(string $heading): AdminSettingsSectionBuilder {
		parent::heading($heading);
		return $this;
	}

	/**
	 * Set the before callback for this section.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function before(?callable $before): AdminSettingsSectionBuilder {
		parent::before($before);
		return $this;
	}

	/**
	 * Set the after callback for this section.
	 *
	 * @return AdminSettingsSectionBuilder
	 */
	public function after(callable $after): AdminSettingsSectionBuilder {
		parent::after($after);
		return $this;
	}

	/**
	 * Set the field template for field wrapper customization.
	 * Configures Tier 2 individual field template override via FormsServiceSession.
	 * This controls field wrapper layout, labels, validation display, and help text
	 * within the WordPress admin settings page constraints.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return AdminSettingsSectionBuilder The AdminSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function field_template(string $template_key): AdminSettingsSectionBuilder {
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
	 * @return AdminSettingsSectionBuilder The AdminSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function group_template(string $template_key): AdminSettingsSectionBuilder {
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
	 * @return AdminSettingsSectionBuilder The AdminSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function fieldset_template(string $template_key): AdminSettingsSectionBuilder {
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
	 * This controls the section container layout within the WordPress admin settings page constraints.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return AdminSettingsSectionBuilder The AdminSettingsSectionBuilder instance.
	 * @throws \InvalidArgumentException If template key is empty.
	 */
	public function section_template(string $template_key): AdminSettingsSectionBuilder {
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
	 * Add a field to this section with admin-specific typing.
	 *
	 * @return AdminSettingsComponentProxy|static
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy|static {
		$result = parent::field($field_id, $label, $component, $args);
		return $result instanceof AdminSettingsComponentProxy ? $result : $this;
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
	 * Begin configuring a semantic fieldset grouping within this section.
	 *
	 * @return AdminSettingsFieldsetBuilder
	 */
	public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder {
		$args = $args ?? array();

		return new AdminSettingsFieldsetBuilder(
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
	 * @return AdminSettingsGroupBuilder
	 */
	public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder {
		$args = $args ?? array();

		return new AdminSettingsGroupBuilder(
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
	 * End the current section and return to the parent page builder.
	 *
	 * @return AdminSettingsPageBuilder
	 */
	public function end_section(): AdminSettingsPageBuilder {
		$builder = parent::end_section();

		if (!$builder instanceof AdminSettingsPageBuilder) {
			throw new \RuntimeException('AdminSettingsSectionBuilder must be attached to an AdminSettingsPageBuilder instance.');
		}

		return $builder;
	}


	/**
	 * Factory method to create AdminSettingsComponentProxy.
	 *
	 * @return AdminSettingsComponentProxy
	 */
	protected function _create_component_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $group_id,
		?string $field_template,
		array $component_context
	): AdminSettingsComponentProxy {
		return new AdminSettingsComponentProxy(
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
