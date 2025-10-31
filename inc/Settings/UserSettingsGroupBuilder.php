<?php
/**
 * UserSettingsGroupBuilder: Context-aware group builder for user settings sections.
 *
 * @package Ran\PluginLib\Settings
 * @method $this heading(string $heading)
 * @method $this description(callable $description_cb)
 * @method $this template(string $template_key)
 * @method $this order(?int $order)
 * @method $this field(string $field_id, string $label, string $component, array $args = array())
 * @method $this before(?callable $before)
 * @method $this after(?callable $after)
 * @method $this group(string $group_id, string $heading, ?callable $description_cb = null, array $args = array())
 * @method UserSettingsSectionBuilder end_group()
 * @method UserSettingsCollectionBuilder end_section()
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Forms\Builders\GroupBuilder;

final class UserSettingsGroupBuilder extends GroupBuilder {
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
			throw new \RuntimeException('UserSettingsGroupBuilder requires UserSettingsSectionBuilder context.');
		}

		return $section;
	}

	public function end_section(): UserSettingsCollectionBuilder {
		$builder = parent::end_section();
		if (!$builder instanceof UserSettingsCollectionBuilder) {
			throw new \RuntimeException('UserSettingsGroupBuilder must be attached to a UserSettingsCollectionBuilder instance.');
		}

		return $builder;
	}
}
