<?php
/**
 * AdminSettingsSectionBuilder: Section builder specialized for AdminSettings pages.
 *
 * @extends SectionBuilder<AdminSettingsPageBuilder>
 * @method AdminSettingsGroupBuilder group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null)
 * @method AdminSettingsFieldsetBuilder fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null)
 * @method AdminSettingsSectionBuilder|ComponentBuilderProxy field(string $field_id, string $label, string $component, array $args = array())
 * @method AdminSettingsPageBuilder end_section()
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\AdminSettingsPageBuilder;
use Ran\PluginLib\Forms\FormsInterface;
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
	 * Add a field to this section with admin-specific typing.
	 *
	 * @return AdminSettingsSectionBuilder|ComponentBuilderProxy
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsSectionBuilder|ComponentBuilderProxy {
		$result = parent::field($field_id, $label, $component, $args);

		return $result instanceof ComponentBuilderProxy ? $result : $this;
	}

	public function heading(string $heading): AdminSettingsSectionBuilder {
		parent::heading($heading);
		return $this;
	}

	public function before(?callable $before): AdminSettingsSectionBuilder {
		parent::before($before);
		return $this;
	}

	public function after(callable $after): AdminSettingsSectionBuilder {
		parent::after($after);
		return $this;
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
	 * Get the FormsInterface instance for the current page.
	 *
	 * @return FormsInterface
	 */
	public function get_forms(): FormsInterface {
		return $this->pageBuilder->get_forms();
	}
}
