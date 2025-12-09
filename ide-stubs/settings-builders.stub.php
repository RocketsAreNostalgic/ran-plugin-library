<?php
/**
 * IDE helper stubs for Settings builder chains.
 *
 * These stubs provide explicit return type declarations to help IDEs like
 * Intelephense properly infer types throughout the fluent builder chain.
 *
 * The actual implementations use inheritance with covariant return types,
 * which PHP supports at runtime but static analyzers struggle to follow.
 * These stubs declare the concrete return types explicitly.
 *
 * @package Ran\PluginLib\Settings
 */

namespace Ran\PluginLib\Settings {
	if (false) {
		// =====================================================================
		// UserSettings Builder Chain
		// =====================================================================

		/**
		 * UserSettingsCollectionBuilder - Root builder for user settings collections.
		 */
		class UserSettingsCollectionBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function description(string|callable|null $description_cb): static {
			}
			/** @return static */
			public function order(int $order): static {
			}
			/** @return static */
			public function template(string|callable|null $template): static {
			}
			/** @return static */
			public function before(callable $before): static {
			}
			/** @return static */
			public function after(callable $after): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}

			/**
			 * Start a new section within this collection.
			 * @return UserSettingsSectionBuilder
			 */
			public function section(string $section_id, string $title = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsSectionBuilder {
			}

			/**
			 * End the collection and return to UserSettings.
			 * @return UserSettings
			 */
			public function end_collection(): UserSettings {
			}

			/**
			 * End the collection and return to UserSettings.
			 * @return UserSettings
			 */
			public function end(): UserSettings {
			}

			/** @return UserSettings */
			public function get_settings(): UserSettings {
			}
		}

		/**
		 * UserSettingsSectionBuilder - Section builder for user settings.
		 */
		class UserSettingsSectionBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template_key): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function field_template(string $template_key): static {
			}
			/** @return static */
			public function group_template(string $template_key): static {
			}
			/** @return static */
			public function fieldset_template(string $template_key): static {
			}
			/** @return static */
			public function section_template(string $template_key): static {
			}

			/**
			 * Add a field directly to this section.
			 * @return UserSettingsComponentProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
			}

			/**
			 * Start a fieldset within this section.
			 * @return UserSettingsFieldsetBuilder
			 */
			public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
			}

			/**
			 * Start a group within this section.
			 * @return UserSettingsGroupBuilder
			 */
			public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
			}

			/**
			 * End the section and return to the collection builder.
			 * @return UserSettingsCollectionBuilder
			 */
			public function end_section(): UserSettingsCollectionBuilder {
			}

			/**
			 * Shortcut: End section and collection.
			 * @return UserSettings
			 */
			public function end_collection(): UserSettings {
			}
		}

		/**
		 * UserSettingsFieldsetBuilder - Fieldset builder for user settings.
		 */
		class UserSettingsFieldsetBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function description(string|callable|null $description_cb): static {
			}
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template_key): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function form(string $form_id): static {
			}
			/** @return static */
			public function name(string $name): static {
			}
			/** @return static */
			public function disabled(bool $disabled = true): static {
			}

			/**
			 * Add a field to this fieldset.
			 * @return UserSettingsFieldsetFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsFieldsetFieldProxy {
			}

			/**
			 * End the fieldset and return to the section builder.
			 * @return UserSettingsSectionBuilder
			 */
			public function end_fieldset(): UserSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End fieldset and section.
			 * @return UserSettingsCollectionBuilder
			 */
			public function end_section(): UserSettingsCollectionBuilder {
			}

			/**
			 * Shortcut: End fieldset, section, and collection.
			 * @return UserSettings
			 */
			public function end_collection(): UserSettings {
			}
		}

		/**
		 * UserSettingsGroupBuilder - Group builder for user settings.
		 */
		class UserSettingsGroupBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function description(string|callable|null $description_cb): static {
			}
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template_key): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}

			/**
			 * Add a field to this group.
			 * @return UserSettingsGroupFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsGroupFieldProxy {
			}

			/**
			 * End the group and return to the section builder.
			 * @return UserSettingsSectionBuilder
			 */
			public function end_group(): UserSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End group and section.
			 * @return UserSettingsCollectionBuilder
			 */
			public function end_section(): UserSettingsCollectionBuilder {
			}

			/**
			 * Shortcut: End group, section, and collection.
			 * @return UserSettings
			 */
			public function end_collection(): UserSettings {
			}
		}

		/**
		 * UserSettingsFieldsetNavigation - Navigation wrapper for fieldset builder.
		 */
		class UserSettingsFieldsetNavigation {
			/** @return UserSettingsFieldsetFieldProxy */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsFieldsetFieldProxy {
			}
			/** @return UserSettingsSectionBuilder */
			public function end_fieldset(): UserSettingsSectionBuilder {
			}
			/** @return UserSettingsCollectionBuilder */
			public function end_section(): UserSettingsCollectionBuilder {
			}
			/** @return UserSettings */
			public function end_collection(): UserSettings {
			}
			/** @return UserSettings */
			public function end(): UserSettings {
			}
		}

		/**
		 * UserSettingsFieldsetFieldProxy - Field proxy within a fieldset.
		 */
		class UserSettingsFieldsetFieldProxy {
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function id(string $id): static {
			}

			/**
			 * End field configuration and return to the fieldset navigation.
			 * @return UserSettingsFieldsetNavigation
			 */
			public function end_field(): UserSettingsFieldsetNavigation {
			}

			/**
			 * Start a sibling field in the same fieldset.
			 * @return UserSettingsFieldsetFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsFieldsetFieldProxy {
			}

			/**
			 * Shortcut: End field and fieldset.
			 * @return UserSettingsSectionBuilder
			 */
			public function end_fieldset(): UserSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End field, fieldset, and section.
			 * @return UserSettingsCollectionBuilder
			 */
			public function end_section(): UserSettingsCollectionBuilder {
			}

			/**
			 * Shortcut: End field, fieldset, section, and collection.
			 * @return UserSettings
			 */
			public function end_collection(): UserSettings {
			}

			/**
			 * Shortcut: End all the way back to UserSettings.
			 * @return UserSettings
			 */
			public function end(): UserSettings {
			}
		}

		/**
		 * UserSettingsGroupNavigation - Navigation wrapper for group builder.
		 */
		class UserSettingsGroupNavigation {
			/** @return UserSettingsGroupFieldProxy */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsGroupFieldProxy {
			}
			/** @return UserSettingsSectionBuilder */
			public function end_group(): UserSettingsSectionBuilder {
			}
			/** @return UserSettingsCollectionBuilder */
			public function end_section(): UserSettingsCollectionBuilder {
			}
			/** @return UserSettings */
			public function end_collection(): UserSettings {
			}
			/** @return UserSettings */
			public function end(): UserSettings {
			}
		}

		/**
		 * UserSettingsGroupFieldProxy - Field proxy within a group.
		 */
		class UserSettingsGroupFieldProxy {
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function id(string $id): static {
			}

			/**
			 * End field configuration and return to the group navigation.
			 * @return UserSettingsGroupNavigation
			 */
			public function end_field(): UserSettingsGroupNavigation {
			}

			/**
			 * Start a sibling field in the same group.
			 * @return UserSettingsGroupFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsGroupFieldProxy {
			}

			/**
			 * Shortcut: End field and group.
			 * @return UserSettingsSectionBuilder
			 */
			public function end_group(): UserSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End field, group, and section.
			 * @return UserSettingsCollectionBuilder
			 */
			public function end_section(): UserSettingsCollectionBuilder {
			}

			/**
			 * Shortcut: End field, group, section, and collection.
			 * @return UserSettings
			 */
			public function end_collection(): UserSettings {
			}

			/**
			 * Shortcut: End all the way back to UserSettings.
			 * @return UserSettings
			 */
			public function end(): UserSettings {
			}
		}

		/**
		 * UserSettingsSectionNavigation - Navigation wrapper for section builder.
		 */
		class UserSettingsSectionNavigation {
			/** @return UserSettingsComponentProxy */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
			}
			/** @return UserSettingsGroupBuilder */
			public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
			}
			/** @return UserSettingsFieldsetBuilder */
			public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
			}
			/** @return UserSettingsCollectionBuilder */
			public function end_section(): UserSettingsCollectionBuilder {
			}
			/** @return UserSettings */
			public function end_collection(): UserSettings {
			}
			/** @return UserSettings */
			public function end(): UserSettings {
			}
			/** @return UserSettingsSectionBuilder */
			public function section(string $section_id, string $heading = ''): UserSettingsSectionBuilder {
			}
		}

		/**
		 * UserSettingsComponentProxy - Field proxy directly on a section.
		 */
		class UserSettingsComponentProxy {
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function id(string $id): static {
			}

			/**
			 * End field configuration and return to the section navigation.
			 * @return UserSettingsSectionNavigation
			 */
			public function end_field(): UserSettingsSectionNavigation {
			}

			/**
			 * Start a sibling field in the same section.
			 * @return UserSettingsComponentProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): UserSettingsComponentProxy {
			}

			/**
			 * Start a sibling group in the same section.
			 * @return UserSettingsGroupBuilder
			 */
			public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsGroupBuilder {
			}

			/**
			 * Start a sibling fieldset in the same section.
			 * @return UserSettingsFieldsetBuilder
			 */
			public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): UserSettingsFieldsetBuilder {
			}

			/**
			 * Start a sibling section.
			 * @return UserSettingsSectionBuilder
			 */
			public function section(string $section_id, string $heading = ''): UserSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End field and section.
			 * @return UserSettingsCollectionBuilder
			 */
			public function end_section(): UserSettingsCollectionBuilder {
			}

			/**
			 * Shortcut: End field, section, and collection.
			 * @return UserSettings
			 */
			public function end_collection(): UserSettings {
			}

			/**
			 * Shortcut: End all the way back to UserSettings.
			 * @return UserSettings
			 */
			public function end(): UserSettings {
			}
		}

		// =====================================================================
		// AdminSettings Builder Chain
		// =====================================================================

		/**
		 * AdminSettingsMenuGroupBuilder - Menu group builder for admin settings.
		 */
		class AdminSettingsMenuGroupBuilder {
			/** @return static */
			public function title(string $title): static {
			}
			/** @return static */
			public function icon(string $icon): static {
			}
			/** @return static */
			public function position(?int $position): static {
			}
			/** @return static */
			public function capability(string $capability): static {
			}

			/**
			 * Start a settings page within this menu group.
			 * @return AdminSettingsPageBuilder
			 */
			public function page(string $page_slug, string|callable|null $template = null, array $args = array()): AdminSettingsPageBuilder {
			}

			/**
			 * End the menu group and return to AdminSettings.
			 * @return AdminSettings
			 */
			public function end_group(): AdminSettings {
			}

			/** @return AdminSettings */
			public function get_settings(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsPageBuilder - Page builder for admin settings.
		 */
		class AdminSettingsPageBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function description(string|callable|null $description_cb): static {
			}
			/** @return static */
			public function menu_label(string $menu_title): static {
			}
			/** @return static */
			public function capability(string $capability): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string|callable|null $template): static {
			}
			/** @return static */
			public function before(callable $before): static {
			}
			/** @return static */
			public function after(callable $after): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}

			/**
			 * Start a new section within this page.
			 * @return AdminSettingsSectionBuilder
			 */
			public function section(string $section_id, string $title = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsSectionBuilder {
			}

			/**
			 * Start a sibling page within the same menu group.
			 * @return AdminSettingsPageBuilder
			 */
			public function page(string $page_slug, string|callable|null $template = null, array $args = array()): AdminSettingsPageBuilder {
			}

			/**
			 * Configure submit controls for this page.
			 * @return \Ran\PluginLib\Forms\Builders\SubmitControlsBuilder
			 */
			public function submit_controls(?string $template = null): \Ran\PluginLib\Forms\Builders\SubmitControlsBuilder {
			}

			/**
			 * End the page and return to the menu group builder.
			 * @return AdminSettingsMenuGroupBuilder
			 */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}

			/**
			 * End the page and menu group, return to AdminSettings.
			 * @return AdminSettings
			 */
			public function end(): AdminSettings {
			}

			/** @return AdminSettings */
			public function get_settings(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsSectionBuilder - Section builder for admin settings.
		 */
		class AdminSettingsSectionBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template_key): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function field_template(string $template_key): static {
			}
			/** @return static */
			public function group_template(string $template_key): static {
			}
			/** @return static */
			public function fieldset_template(string $template_key): static {
			}
			/** @return static */
			public function section_template(string $template_key): static {
			}

			/**
			 * Add a field directly to this section.
			 * @return AdminSettingsComponentProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy {
			}

			/**
			 * Start a fieldset within this section.
			 * @return AdminSettingsFieldsetBuilder
			 */
			public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder {
			}

			/**
			 * Start a group within this section.
			 * @return AdminSettingsGroupBuilder
			 */
			public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder {
			}

			/**
			 * End the section and return to the page builder.
			 * @return AdminSettingsPageBuilder
			 */
			public function end_section(): AdminSettingsPageBuilder {
			}

			/**
			 * Shortcut: End section and page.
			 * @return AdminSettingsMenuGroupBuilder
			 */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}

			/**
			 * Shortcut: End section, page, and menu group.
			 * @return AdminSettings
			 */
			public function end(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsFieldsetBuilder - Fieldset builder for admin settings.
		 */
		class AdminSettingsFieldsetBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function description(string|callable|null $description_cb): static {
			}
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template_key): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function form(string $form_id): static {
			}
			/** @return static */
			public function name(string $name): static {
			}
			/** @return static */
			public function disabled(bool $disabled = true): static {
			}

			/**
			 * Add a field to this fieldset.
			 * @return AdminSettingsFieldsetFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsFieldsetFieldProxy {
			}

			/**
			 * End the fieldset and return to the section builder.
			 * @return AdminSettingsSectionBuilder
			 */
			public function end_fieldset(): AdminSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End fieldset and section.
			 * @return AdminSettingsPageBuilder
			 */
			public function end_section(): AdminSettingsPageBuilder {
			}

			/**
			 * Shortcut: End fieldset, section, and page.
			 * @return AdminSettingsMenuGroupBuilder
			 */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}

			/**
			 * Shortcut: End fieldset, section, page, and menu group.
			 * @return AdminSettings
			 */
			public function end(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsGroupBuilder - Group builder for admin settings.
		 */
		class AdminSettingsGroupBuilder {
			/** @return static */
			public function heading(string $heading): static {
			}
			/** @return static */
			public function description(string|callable|null $description_cb): static {
			}
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template_key): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}

			/**
			 * Add a field to this group.
			 * @return AdminSettingsGroupFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsGroupFieldProxy {
			}

			/**
			 * End the group and return to the section builder.
			 * @return AdminSettingsSectionBuilder
			 */
			public function end_group(): AdminSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End group and section.
			 * @return AdminSettingsPageBuilder
			 */
			public function end_section(): AdminSettingsPageBuilder {
			}

			/**
			 * Shortcut: End group, section, and page.
			 * @return AdminSettingsMenuGroupBuilder
			 */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}

			/**
			 * Shortcut: End group, section, page, and menu group.
			 * @return AdminSettings
			 */
			public function end(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsFieldsetNavigation - Navigation wrapper for fieldset builder.
		 */
		class AdminSettingsFieldsetNavigation {
			/** @return AdminSettingsFieldsetFieldProxy */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsFieldsetFieldProxy {
			}
			/** @return AdminSettingsSectionBuilder */
			public function end_fieldset(): AdminSettingsSectionBuilder {
			}
			/** @return AdminSettingsPageBuilder */
			public function end_section(): AdminSettingsPageBuilder {
			}
			/** @return AdminSettingsMenuGroupBuilder */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}
			/** @return AdminSettings */
			public function end(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsFieldsetFieldProxy - Field proxy within a fieldset.
		 */
		class AdminSettingsFieldsetFieldProxy {
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function id(string $id): static {
			}

			/**
			 * End field configuration and return to the fieldset navigation.
			 * @return AdminSettingsFieldsetNavigation
			 */
			public function end_field(): AdminSettingsFieldsetNavigation {
			}

			/**
			 * Start a sibling field in the same fieldset.
			 * @return AdminSettingsFieldsetFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsFieldsetFieldProxy {
			}

			/**
			 * Shortcut: End field and fieldset.
			 * @return AdminSettingsSectionBuilder
			 */
			public function end_fieldset(): AdminSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End field, fieldset, and section.
			 * @return AdminSettingsPageBuilder
			 */
			public function end_section(): AdminSettingsPageBuilder {
			}

			/**
			 * Shortcut: End field, fieldset, section, and page.
			 * @return AdminSettingsMenuGroupBuilder
			 */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}

			/**
			 * Shortcut: End all the way back to AdminSettings.
			 * @return AdminSettings
			 */
			public function end(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsGroupNavigation - Navigation wrapper for group builder.
		 */
		class AdminSettingsGroupNavigation {
			/** @return AdminSettingsGroupFieldProxy */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsGroupFieldProxy {
			}
			/** @return AdminSettingsSectionBuilder */
			public function end_group(): AdminSettingsSectionBuilder {
			}
			/** @return AdminSettingsPageBuilder */
			public function end_section(): AdminSettingsPageBuilder {
			}
			/** @return AdminSettingsMenuGroupBuilder */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}
			/** @return AdminSettings */
			public function end(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsGroupFieldProxy - Field proxy within a group.
		 */
		class AdminSettingsGroupFieldProxy {
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function id(string $id): static {
			}

			/**
			 * End field configuration and return to the group navigation.
			 * @return AdminSettingsGroupNavigation
			 */
			public function end_field(): AdminSettingsGroupNavigation {
			}

			/**
			 * Start a sibling field in the same group.
			 * @return AdminSettingsGroupFieldProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsGroupFieldProxy {
			}

			/**
			 * Shortcut: End field and group.
			 * @return AdminSettingsSectionBuilder
			 */
			public function end_group(): AdminSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End field, group, and section.
			 * @return AdminSettingsPageBuilder
			 */
			public function end_section(): AdminSettingsPageBuilder {
			}

			/**
			 * Shortcut: End field, group, section, and page.
			 * @return AdminSettingsMenuGroupBuilder
			 */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}

			/**
			 * Shortcut: End all the way back to AdminSettings.
			 * @return AdminSettings
			 */
			public function end(): AdminSettings {
			}
		}

		/**
		 * AdminSettingsSectionNavigation - Navigation wrapper for section builder.
		 */
		class AdminSettingsSectionNavigation {
			/** @return AdminSettingsComponentProxy */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy {
			}
			/** @return AdminSettingsGroupBuilder */
			public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder {
			}
			/** @return AdminSettingsFieldsetBuilder */
			public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder {
			}
			/** @return AdminSettingsPageBuilder */
			public function end_section(): AdminSettingsPageBuilder {
			}
			/** @return AdminSettingsMenuGroupBuilder */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}
			/** @return AdminSettings */
			public function end(): AdminSettings {
			}
			/** @return AdminSettingsSectionBuilder */
			public function section(string $section_id, string $heading = ''): AdminSettingsSectionBuilder {
			}
		}

		/**
		 * AdminSettingsComponentProxy - Field proxy directly on a section.
		 */
		class AdminSettingsComponentProxy {
			/** @return static */
			public function before(?callable $before): static {
			}
			/** @return static */
			public function after(?callable $after): static {
			}
			/** @return static */
			public function order(?int $order): static {
			}
			/** @return static */
			public function template(string $template): static {
			}
			/** @return static */
			public function style(string|callable $style): static {
			}
			/** @return static */
			public function id(string $id): static {
			}

			/**
			 * End field configuration and return to the section navigation.
			 * @return AdminSettingsSectionNavigation
			 */
			public function end_field(): AdminSettingsSectionNavigation {
			}

			/**
			 * Start a sibling field in the same section.
			 * @return AdminSettingsComponentProxy
			 */
			public function field(string $field_id, string $label, string $component, array $args = array()): AdminSettingsComponentProxy {
			}

			/**
			 * Start a sibling group in the same section.
			 * @return AdminSettingsGroupBuilder
			 */
			public function group(string $group_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsGroupBuilder {
			}

			/**
			 * Start a sibling fieldset in the same section.
			 * @return AdminSettingsFieldsetBuilder
			 */
			public function fieldset(string $fieldset_id, string $heading = '', string|callable|null $description_cb = null, ?array $args = null): AdminSettingsFieldsetBuilder {
			}

			/**
			 * Start a sibling section.
			 * @return AdminSettingsSectionBuilder
			 */
			public function section(string $section_id, string $heading = ''): AdminSettingsSectionBuilder {
			}

			/**
			 * Shortcut: End field and section.
			 * @return AdminSettingsPageBuilder
			 */
			public function end_section(): AdminSettingsPageBuilder {
			}

			/**
			 * Shortcut: End field, section, and page.
			 * @return AdminSettingsMenuGroupBuilder
			 */
			public function end_page(): AdminSettingsMenuGroupBuilder {
			}

			/**
			 * Shortcut: End all the way back to AdminSettings.
			 * @return AdminSettings
			 */
			public function end(): AdminSettings {
			}
		}
	}
}
