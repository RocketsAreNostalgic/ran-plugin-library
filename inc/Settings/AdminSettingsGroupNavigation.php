<?php
/**
 * AdminSettingsGroupNavigation: Narrow navigation wrapper for AdminSettingsGroupBuilder.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

/**
 * Navigation wrapper for AdminSettingsGroupBuilder.
 */
final class AdminSettingsGroupNavigation {
	private AdminSettingsGroupBuilder $builder;

	public function __construct(AdminSettingsGroupBuilder $builder) {
		$this->builder = $builder;
	}

	public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsGroupFieldProxy {
		return $this->builder->field($field_id, $label, $component, $args);
	}

	public function end_group(): AdminSettingsSectionBuilder {
		return $this->builder->end_group();
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
