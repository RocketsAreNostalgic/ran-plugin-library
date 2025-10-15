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

use Ran\PluginLib\Settings\SectionBuilder;
use Ran\PluginLib\Settings\CollectionBuilderInterface;
use Ran\PluginLib\Settings\UserSettingsInterface;

/**
 * UserSettingsSectionBuilder: Fluent builder for user settings sections with template override support.
 *
 * This class extends the basic SectionBuilder functionality to provide UserSettings-specific
 * template override methods that work within WordPress profile page table constraints.
 */
class UserSettingsSectionBuilder extends SectionBuilder {
	/** @var array<string, string> Template overrides for this section */
	private array $template_overrides = array();

	/**
	 * @param CollectionBuilderInterface $collectionBuilder The collection builder instance
	 * @param string $collection_slug The collection slug
	 * @param string $section_id The section ID
	 * @param callable $onAddSection          function(string $collection, string $section, string $title, ?callable $desc, ?int $order): void
	 * @param callable $onAddField            function(string $collection, string $section, string $id, string $label, string $component, array $context, ?int $order): void
	 * @param callable $onAddGroup            function(string $collection, string $section, string $group, string $title, array $fields, ?callable $before, ?callable $after, ?int $order): void
	 * @param callable $onAddFieldDefinition  function(string $collection, string $section, BuilderDefinitionInterface $definition): void
	 * @param callable $onSectionCommit       function(string $collection, string $section): void
	 */
	public function __construct(
		CollectionBuilderInterface $collectionBuilder,
		string $collection_slug,
		string $section_id,
		callable $onAddSection,
		callable $onAddField,
		callable $onAddGroup,
		callable $onAddFieldDefinition,
		callable $onSectionCommit
	) {
		parent::__construct(
			$collectionBuilder,
			$collection_slug,
			$section_id,
			$onAddSection,
			$onAddField,
			$onAddGroup,
			$onAddFieldDefinition,
			$onSectionCommit
		);
	}

	/**
	 * Commit buffered data on destruction.
	 * Override parent to apply UserSettings template overrides.
	 */
	public function __destruct() {
		$this->_apply_template_overrides();
		parent::__destruct();
	}



	/**
	 * Set the section template for section container customization.
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
		$this->template_overrides['section'] = $template_key;
		return $this;
	}

	/**
	 * Set the field template for field wrapper customization.
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
		$this->template_overrides['field-wrapper'] = $template_key;
		return $this;
	}



	/**
	 * Return to the CollectionBuilder.
	 * Override parent to apply UserSettings template overrides before committing.
	 *
	 * @return CollectionBuilderInterface The CollectionBuilder instance.
	 */
	public function end_section(): CollectionBuilderInterface {
		$this->_apply_template_overrides();
		return parent::end_section();
	}

	/**
	 * Apply template overrides to the UserSettings instance.
	 * Override the parent method to work with UserSettings instead of AdminSettings.
	 */
	protected function _apply_template_overrides(): void {
		if (!empty($this->template_overrides)) {
			// Get UserSettings instance through the collection builder
			$user_settings = $this->get_user_settings();
			if ($user_settings instanceof UserSettingsInterface) {
				// Use reflection to get section_id from parent
				$reflection = new \ReflectionClass(parent::class);
				$property   = $reflection->getProperty('section_id');
				$property->setAccessible(true);
				$section_id = $property->getValue($this);

				$user_settings->set_section_template_overrides($section_id, $this->template_overrides);
			}
		}
	}

	/**
	 * Get the UserSettings instance from the collection builder.
	 *
	 * @return UserSettingsInterface
	 */
	public function get_user_settings(): UserSettingsInterface {
		// Get collection builder from parent using reflection
		$reflection = new \ReflectionClass(parent::class);
		$property   = $reflection->getProperty('collectionBuilder');
		$property->setAccessible(true);
		$collectionBuilder = $property->getValue($this);

		// For UserSettingsCollectionBuilder, we can access through reflection
		if ($collectionBuilder instanceof \Ran\PluginLib\Settings\UserSettingsCollectionBuilder) {
			$reflection = new \ReflectionClass($collectionBuilder);

			// Try to find the settings property
			if ($reflection->hasProperty('settings')) {
				$property = $reflection->getProperty('settings');
				$property->setAccessible(true);
				$value = $property->getValue($collectionBuilder);

				if ($value instanceof UserSettingsInterface) {
					return $value;
				}
			}
		}

		throw new \RuntimeException('Unable to access UserSettings instance from collection builder');
	}
}
