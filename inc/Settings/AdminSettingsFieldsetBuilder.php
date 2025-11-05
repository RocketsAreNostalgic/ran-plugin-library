<?php
/**
 * AdminSettingsFieldsetBuilder: Context-aware fieldset builder for admin settings sections.
 *
 * @package Ran\PluginLib\Settings
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\FieldsetBuilder;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;

final class AdminSettingsFieldsetBuilder extends FieldsetBuilder {
	public function __construct(
		AdminSettingsSectionBuilder $sectionBuilder,
		string $container_id,
		string $section_id,
		string $group_id,
		string $heading,
		?callable $description_cb,
		callable $updateFn,
		array $args = array()
	) {
		parent::__construct(
			$sectionBuilder,
			$container_id,
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$updateFn,
			$args
		);
	}

	public function end_fieldset(): AdminSettingsSectionBuilder {
		$section = parent::end_fieldset();
		if (!$section instanceof AdminSettingsSectionBuilder) {
			throw new \RuntimeException('AdminSettingsFieldsetBuilder requires AdminSettingsSectionBuilder context.');
		}

		return $section;
	}

	public function end_section(): AdminSettingsPageBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof AdminSettingsPageBuilder) {
			throw new \RuntimeException('AdminSettingsFieldsetBuilder must be attached to an AdminSettingsPageBuilder instance.');
		}

		return $builder;
	}
}
