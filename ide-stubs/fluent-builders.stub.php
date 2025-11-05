<?php
/**
 * IDE helper stubs for fluent builder autocompletion.
 *
 * These declarations are wrapped in an if(false) guard so they never load at runtime.
 * Editors such as VS Code (Intelephense) or PhpStorm can include this file to surface
 * precise chaining hints without relying on runtime inference.
 */

namespace Ran\PluginLib\Forms\Builders {
	if (false) {
		abstract class ComponentBuilderProxy {
			abstract public function end_field(): SectionBuilder|SectionFieldContainerBuilder;
		}

		abstract class BuilderRootInterface {
		}

		abstract class SectionFieldContainerBuilder {
			abstract public function heading(string $heading): static;
			abstract public function description(?callable $description_cb): static;
			abstract public function before(?callable $before): static;
			abstract public function after(?callable $after): static;
			abstract public function template(string $template_key): static;
			abstract public function order(?int $order): static;
			abstract public function field(string $field_id, string $label, string $component, array $args = array()): ComponentBuilderProxy|static;
			abstract public function end_section(): BuilderRootInterface;
		}

		abstract class SectionBuilder extends SectionFieldContainerBuilder {
			abstract public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): GroupBuilder;
			abstract public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): FieldsetBuilder;
			abstract public function end_group(): static;
		}

		abstract class GroupBuilder extends SectionFieldContainerBuilder {
			abstract public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): GroupBuilder;
			abstract public function end_group(): SectionBuilder;
		}

		abstract class FieldsetBuilder extends SectionFieldContainerBuilder {
			abstract public function style(string $style): static;
			abstract public function required(bool $required = true): static;
			abstract public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): FieldsetBuilder;
			abstract public function end_fieldset(): SectionBuilder;
		}
	}
}

namespace Ran\PluginLib\Settings {
	if (false) {
		abstract class AdminSettingsMenuGroupBuilder {
			abstract public function heading(string $heading): static;
			abstract public function menu_label(string $menu_title): static;
			abstract public function capability(string $capability): static;
			abstract public function parent(?string $parent_slug): static;
			abstract public function icon(?string $icon): static;
			abstract public function position(?int $position): static;
			abstract public function page(string $page_slug, string|callable|null $template = null, array $args = array()): AdminSettingsPageBuilder;
			abstract public function end_group(): AdminSettings;
			abstract public function end_menu_group(): AdminSettings;
		}

		abstract class AdminSettingsPageBuilder {
			abstract public function heading(string $heading): static;
			abstract public function description(string $description): static;
			abstract public function menu_label(string $menu_title): static;
			abstract public function capability(string $capability): static;
			abstract public function order(?int $order): static;
			abstract public function template(string|callable|null $template): static;
			abstract public function before(?callable $before): static;
			abstract public function after(callable $after): static;
			abstract public function section(string $section_id, string $title, ?callable $description_cb = null, ?array $args = null): AdminSettingsSectionBuilder;
			abstract public function page(string $page_slug, string|callable|null $template = null, array $args = array()): AdminSettingsPageBuilder;
			abstract public function end_page(): AdminSettingsMenuGroupBuilder;
			abstract public function end(): AdminSettings;
		}

		abstract class AdminSettingsSectionBuilder extends \Ran\PluginLib\Forms\Builders\SectionBuilder {
			abstract public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder;
			abstract public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder;
			abstract public function field(string $field_id, string $label, string $component, array $args = array()): \Ran\PluginLib\Forms\Builders\ComponentBuilderProxy|static;
			abstract public function end_section(): AdminSettingsPageBuilder;
		}

		abstract class AdminSettingsGroupBuilder extends \Ran\PluginLib\Forms\Builders\GroupBuilder {
			abstract public function field(string $field_id, string $label, string $component, array $args = array()): \Ran\PluginLib\Forms\Builders\ComponentBuilderProxy|static;
			abstract public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder;
			abstract public function end_group(): AdminSettingsSectionBuilder;
			abstract public function end_section(): AdminSettingsPageBuilder;
		}

		abstract class AdminSettingsFieldsetBuilder extends \Ran\PluginLib\Forms\Builders\FieldsetBuilder {
			abstract public function field(string $field_id, string $label, string $component, array $args = array()): \Ran\PluginLib\Forms\Builders\ComponentBuilderProxy|static;
			abstract public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder;
			abstract public function end_fieldset(): AdminSettingsSectionBuilder;
			abstract public function end_section(): AdminSettingsPageBuilder;
		}

		abstract class UserSettingsCollectionBuilder {
			abstract public function heading(string $heading): static;
			abstract public function description(string $description): static;
			abstract public function order(int $order): static;
			abstract public function template(string|callable|null $template): static;
			abstract public function before(callable $before): static;
			abstract public function after(callable $after): static;
			abstract public function section(string $section_id, string $title, ?callable $description_cb = null, ?array $args = null): UserSettingsSectionBuilder;
			abstract public function end_collection(): UserSettings;
			abstract public function end(): UserSettings;
		}

		abstract class UserSettingsSectionBuilder extends \Ran\PluginLib\Forms\Builders\SectionBuilder {
			abstract public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): UserSettingsGroupBuilder;
			abstract public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder;
			abstract public function field(string $field_id, string $label, string $component, array $args = array()): \Ran\PluginLib\Forms\Builders\ComponentBuilderProxy|static;
			abstract public function end_section(): UserSettingsCollectionBuilder;
		}

		abstract class UserSettingsGroupBuilder extends \Ran\PluginLib\Forms\Builders\GroupBuilder {
			abstract public function field(string $field_id, string $label, string $component, array $args = array()): \Ran\PluginLib\Forms\Builders\ComponentBuilderProxy|static;
			abstract public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): UserSettingsGroupBuilder;
			abstract public function end_group(): UserSettingsSectionBuilder;
			abstract public function end_section(): UserSettingsCollectionBuilder;
		}

		abstract class UserSettingsFieldsetBuilder extends \Ran\PluginLib\Forms\Builders\FieldsetBuilder {
			abstract public function field(string $field_id, string $label, string $component, array $args = array()): \Ran\PluginLib\Forms\Builders\ComponentBuilderProxy|static;
			abstract public function fieldset(string $fieldset_id, string $heading, ?callable $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder;
			abstract public function end_fieldset(): UserSettingsSectionBuilder;
			abstract public function end_section(): UserSettingsCollectionBuilder;
		}
	}
}
