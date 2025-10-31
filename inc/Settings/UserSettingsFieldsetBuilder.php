<?php
/**
 * UserSettingsFieldsetBuilder: Context-aware fieldset builder for user settings sections.
 *
 * @package Ran\PluginLib\Settings
 * @method $this style(string $style)
 * @method $this required(bool $required = true)
 * @method UserSettingsFieldsetBuilder|ComponentBuilderProxy field(string $field_id, string $label, string $component, array $args = array())
 * @method UserSettingsSectionBuilder end_group()
 * @method UserSettingsSectionBuilder end_fieldset()
 * @method UserSettingsCollectionBuilder end_section()
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\FieldsetBuilder;
use Ran\PluginLib\Forms\Builders\ComponentBuilderProxy;

final class UserSettingsFieldsetBuilder extends FieldsetBuilder {
	public function __construct(
		UserSettingsSectionBuilder $sectionBuilder,
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

	public function end_group(): UserSettingsSectionBuilder {
		$section = parent::end_group();
		if (!$section instanceof UserSettingsSectionBuilder) {
			throw new \RuntimeException('UserSettingsFieldsetBuilder requires UserSettingsSectionBuilder context.');
		}

		return $section;
	}

	public function end_fieldset(): UserSettingsSectionBuilder {
		$section = parent::end_fieldset();
		if (!$section instanceof UserSettingsSectionBuilder) {
			throw new \RuntimeException('UserSettingsFieldsetBuilder requires UserSettingsSectionBuilder context.');
		}

		return $section;
	}

	public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsFieldsetBuilder|ComponentBuilderProxy {
		$result = parent::field($field_id, $label, $component, $args);

		return $result instanceof ComponentBuilderProxy ? $result : $this;
	}

	public function end_section(): UserSettingsCollectionBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof UserSettingsCollectionBuilder) {
			throw new \RuntimeException('UserSettingsFieldsetBuilder must be attached to a UserSettingsCollectionBuilder instance.');
		}

		return $builder;
	}
}
