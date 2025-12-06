<?php
/**
 * AdminSettingsFieldsetNavigation: Narrow navigation wrapper for AdminSettingsFieldsetBuilder.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

/**
 * Navigation wrapper for AdminSettingsFieldsetBuilder.
 */
final class AdminSettingsFieldsetNavigation {
	private AdminSettingsFieldsetBuilder $builder;

	public function __construct(AdminSettingsFieldsetBuilder $builder) {
		$this->builder = $builder;
	}

	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsFieldsetFieldProxy {
		return $this->builder->field($field_id, $label, $component, $args);
	}

	public function end_fieldset(): AdminSettingsSectionBuilder {
		return $this->builder->end_fieldset();
	}

	public function end_section(): AdminSettingsPageBuilder {
		return $this->builder->end_section();
	}

	public function end_page(): AdminSettingsMenuGroupBuilder {
		return $this->builder->end_page();
	}

	public function end(): AdminSettings {
		return $this->builder->end();
	}
}
