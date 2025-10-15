<?php
/**
 * SectionBuilder: Fluent builder for html sections within a Settings collection.
 *
 * @package Ran\PluginLib\Settings
 * @author  Ran Plugin Lib <bnjmnrsh@gmail.com>
 * @license GPL-2.0+ <http://www.gnu.org/licenses/gpl-2.0.txt>
 * @link    https://github.com/RocketsAreNostalgic
 * @since   0.2.0
 */

declare(strict_types=1);

namespace Ran\PluginLib\Settings;

use Ran\PluginLib\Settings\SectionGroupBuilder;
use Ran\PluginLib\Settings\SectionBuilderInterface;
use Ran\PluginLib\Settings\CollectionBuilderInterface;
use Ran\PluginLib\Settings\AdminSettingsInterface;
use Ran\PluginLib\Forms\Component\Build\BuilderDefinitionInterface;

class SectionBuilder implements SectionBuilderInterface {
	private CollectionBuilderInterface $collectionBuilder;
	private string $collection_slug;
	private string $section_id;
	/** @var callable */
	private $onAddSection;
	/** @var callable */
	private $onAddField;
	/** @var callable */
	private $onAddGroup;
	/** @var callable */
	private $onAddFieldDefinition;
	/** @var callable */
	private $onSectionCommit;
	private bool $committed = false;
	/** @var array<string, string> Template overrides for this section */
	private array $template_overrides = array();

	/**
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
		$this->collectionBuilder    = $collectionBuilder;
		$this->collection_slug      = $collection_slug;
		$this->section_id           = $section_id;
		$this->onAddSection         = $onAddSection;
		$this->onAddField           = $onAddField;
		$this->onAddGroup           = $onAddGroup;
		$this->onAddFieldDefinition = $onAddFieldDefinition;
		$this->onSectionCommit      = $onSectionCommit;
	}

	/**
	 * Commit buffered data on destruction.
	 */
	public function __destruct() {
		$this->_commit();
	}

	/**
	 * Start a sibling section on the same collection and return its SectionBuilder.
	 * Convenient when you want to chain multiple sections without returning to the collection builder.
	 *
	 * @param string $section_id The section ID.
	 * @param string $title The section title.
	 * @param callable|null $description_cb The section description callback.
	 * @param int|null $order The section order.
	 *
	 * @return SectionBuilder The SectionBuilder instance for the new section.
	 */
	public function section(string $section_id, string $title, ?callable $description_cb = null, ?int $order = null): SectionBuilder {
		$this->_commit();
		return $this->collectionBuilder->section($section_id, $title, $description_cb, $order);
	}

	/**
	 * Begin configuring a grouped set of fields within this section.
	 *
	 * @param string $group_id The group identifier.
	 * @param string $title The human-readable group title.
	 * @param callable|null $before Optional callback invoked before rendering grouped fields.
	 * @param callable|null $after Optional callback invoked after rendering grouped fields.
	 * @param int|null $order Optional ordering index.
	 *
	 * @return SectionGroupBuilder The fluent group builder instance.
	 */
	public function group(string $group_id, string $title, ?callable $before = null, ?callable $after = null, ?int $order = null): SectionGroupBuilder {
		return new SectionGroupBuilder($this, $this->collection_slug, $this->section_id, $group_id, $title, $this->onAddGroup, $before, $after, $order);
	}

	/**
	 * Add a field to this section.
	 *
	 * @param string $field_id The field ID.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $component_context The component context.
	 * @param int|null $order The order.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
	 */
	public function field(string $field_id, string $label, string $component, array $component_context = array(), ?int $order = null): self {
		($this->onAddField)($this->collection_slug, $this->section_id, $field_id, $label, $component, $component_context, $order);
		return $this;
	}

	/**
	 * Add a section definition.
	 *
	 * @param BuilderDefinitionInterface $definition The field definition.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
	 */
	public function definition(BuilderDefinitionInterface $definition): self {
		($this->onAddFieldDefinition)($this->collection_slug, $this->section_id, $definition);
		return $this;
	}

	/**
	 * Set the section template for section container customization.
	 *
	 * @param string $template_key The template key to use for section container.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
	 */
	public function section_template(string $template_key): self {
		$this->template_overrides['section'] = $template_key;
		return $this;
	}

	/**
	 * Set the group template for group container styling.
	 *
	 * @param string $template_key The template key to use for group containers.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
	 */
	public function group_template(string $template_key): self {
		$this->template_overrides['group'] = $template_key;
		return $this;
	}

	/**
	 * Set the field template for field wrapper customization.
	 *
	 * @param string $template_key The template key to use for field wrappers.
	 *
	 * @return SectionBuilder The SectionBuilder instance.
	 */
	public function field_template(string $template_key): self {
		$this->template_overrides['field-wrapper'] = $template_key;
		return $this;
	}

	/**
	 * Return to the CollectionBuilder.
	 *
	 * @return CollectionBuilderInterface The CollectionBuilder instance.
	 */
	public function end_section(): CollectionBuilderInterface {
		$this->_commit();
		return $this->collectionBuilder;
	}

	/**
	 * Check if this section has been committed.
	 *
	 * @return bool Whether this section has been committed.
	 */
	public function is_committed(): bool {
		return $this->committed;
	}

	/**
	 * Apply template overrides to the AdminSettings instance.
	 */
	private function _apply_template_overrides(): void {
		if (!empty($this->template_overrides)) {
			// Get AdminSettings instance through the collection builder
			$admin_settings = $this->get_admin_settings();
			if ($admin_settings instanceof AdminSettingsInterface) {
				$admin_settings->set_section_template_overrides($this->section_id, $this->template_overrides);
			}
		}
	}

	/**
	 * Get the AdminSettings instance from the collection builder.
	 *
	 * @return AdminSettingsInterface
	 */
	public function get_admin_settings(): AdminSettingsInterface {
		// For AdminSettingsPageBuilder, we can access through reflection
		if ($this->collectionBuilder instanceof \Ran\PluginLib\Settings\AdminSettingsPageBuilder) {
			return $this->collectionBuilder->get_admin_settings();
		}

		// For other collection builders, we need to find another way
		// This is a fallback that should work for most cases
		$reflection = new \ReflectionClass($this->collectionBuilder);

		// Try to find a property that holds the AdminSettings instance
		$properties = array('admin_settings', 'settings', 'group');
		foreach ($properties as $prop_name) {
			if ($reflection->hasProperty($prop_name)) {
				$property = $reflection->getProperty($prop_name);
				$property->setAccessible(true);
				$value = $property->getValue($this->collectionBuilder);

				if ($value instanceof AdminSettingsInterface) {
					return $value;
				}

				// If it's a group builder, try to get settings from it
				if ($value && method_exists($value, 'get_admin_settings')) {
					return $value->get_admin_settings();
				}
			}
		}

		throw new \RuntimeException('Unable to access AdminSettings instance from collection builder');
	}

	/**
	 * Commit buffered data.
	 */
	private function _commit(): void {
		if ($this->committed) {
			return;
		}

		// Apply template overrides before committing
		$this->_apply_template_overrides();

		($this->onSectionCommit)($this->collection_slug, $this->section_id);
		$this->committed = true;
	}
}
