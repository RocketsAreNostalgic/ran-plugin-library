<?php
/**
 * GroupBuilderBase: Abstract base class for group builders with context injection.
 *
 * Provides shared implementation for group builders, eliminating duplication
 * across context-specific variants (AdminSettingsGroupBuilder, UserSettingsGroupBuilder).
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderDefinitionInterface;
use Ran\PluginLib\Forms\Builders\Traits\SectionFieldContainerTrait;
use Ran\PluginLib\Forms\Builders\Traits\GroupBuilderTrait;

/**
 * Base class for group builders with context injection.
 *
 * Subclasses only need to implement navigation methods that return
 * context-specific types for IDE autocomplete.
 *
 * @template TParent of SectionBuilderInterface
 */
abstract class GroupBuilderBase implements GroupBuilderInterface {
	use SectionFieldContainerTrait;
	use GroupBuilderTrait;
	use BuilderImmediateUpdateTrait;

	/**
	 * The parent section builder.
	 *
	 * @var TParent
	 */
	protected object $sectionBuilder;

	/**
	 * The builder context providing access to shared dependencies.
	 */
	protected BuilderContextInterface $context;

	/**
	 * @param TParent $sectionBuilder The parent section builder.
	 * @param BuilderContextInterface $context The builder context.
	 * @param string $section_id The section ID.
	 * @param string $group_id The group ID.
	 * @param string $heading The group heading.
	 * @param string|callable|null $description_cb The description callback.
	 * @param array<string,mixed> $args Optional arguments.
	 */
	public function __construct(
		object $sectionBuilder,
		BuilderContextInterface $context,
		string $section_id,
		string $group_id,
		string $heading,
		string|callable|null $description_cb = null,
		array $args = array()
	) {
		$this->sectionBuilder = $sectionBuilder;
		$this->context        = $context;

		// Initialize container (from SectionFieldContainerTrait)
		$this->_init_container(
			$context->get_container_id(),
			$section_id,
			$group_id,
			$heading,
			$description_cb,
			$context->get_update_callback(),
			$args
		);
	}

	/**
	 * Get the FormsInterface instance.
	 *
	 * @return FormsInterface
	 */
	public function get_settings(): FormsInterface {
		return $this->context->get_forms();
	}

	// =========================================================================
	// Shared implementations (no longer abstract in subclasses)
	// =========================================================================

	/**
	 * Get the container type.
	 *
	 * @return string
	 */
	protected function _get_container_type(): string {
		return 'group';
	}

	/**
	 * Get the component builder factory for a given component alias.
	 *
	 * @param string $component The component alias.
	 *
	 * @return callable|null
	 */
	public function __get_component_builder_factory(string $component): ?callable {
		return $this->context->get_component_builder_factory($component);
	}

	/**
	 * Create a field proxy for the given component builder.
	 *
	 * @param ComponentBuilderDefinitionInterface $builder The component builder.
	 * @param string $component_alias The component alias.
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $component_context The component context.
	 *
	 * @return GenericFieldBuilder<static>
	 */
	protected function _create_field_proxy(
		ComponentBuilderDefinitionInterface $builder,
		string $component_alias,
		?string $field_template,
		array $component_context
	): GenericFieldBuilder {
		return new GenericFieldBuilder(
			$builder,
			$this,
			$this->context->get_update_callback(),
			$this->context->get_container_id(),
			$this->section_id,
			$component_alias,
			$this->group_id,
			$field_template,
			$component_context
		);
	}

	// =========================================================================
	// BuilderImmediateUpdateTrait abstract implementations
	// =========================================================================

	/**
	 * Apply a meta update to local state.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 */
	protected function _apply_meta_update(string $key, mixed $value): void {
		$this->_apply_container_meta_update($key, $value);
	}

	/**
	 * Get the update callback.
	 *
	 * @return callable
	 */
	protected function _get_update_callback(): callable {
		return $this->context->get_update_callback();
	}

	/**
	 * Get the update event name.
	 *
	 * @return string
	 */
	protected function _get_update_event_name(): string {
		return 'group_metadata';
	}

	/**
	 * Build the update payload.
	 *
	 * @param string $key The meta key.
	 * @param mixed $value The new value.
	 *
	 * @return array<string,mixed>
	 */
	protected function _build_update_payload(string $key, mixed $value): array {
		return $this->_build_container_payload();
	}

	// =========================================================================
	// Abstract methods for context-specific behavior
	// =========================================================================

	/**
	 * Add a field with a component builder to this group.
	 *
	 * Subclasses should override to provide specific return type annotation.
	 *
	 * @param string $field_id The field identifier.
	 * @param string $label The field label.
	 * @param string $component The component alias.
	 * @param array<string,mixed> $args Optional arguments.
	 *
	 * @return GenericFieldBuilder<static>
	 */
	public function field(string $field_id, string $label, string $component, array $args = array()): GenericFieldBuilder {
		$proxy = $this->_add_field($field_id, $label, $component, $args);
		if (!$proxy instanceof GenericFieldBuilder) {
			throw new \RuntimeException('Unexpected proxy type from _add_field()');
		}
		return $proxy;
	}

	/**
	 * End the group and return to the parent section builder.
	 *
	 * Subclasses must override to return the concrete parent type.
	 *
	 * @return TParent
	 */
	abstract public function end_group(): mixed;
}
