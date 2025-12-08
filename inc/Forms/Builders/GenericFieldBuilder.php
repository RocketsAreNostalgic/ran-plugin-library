<?php
/**
 * GenericFieldBuilder: Type-safe generic field builder using @template for parent type.
 *
 * Unified field builder that replaces all context-specific proxy classes with a single
 * generic class that preserves parent type for IDE autocomplete via @template.
 *
 * Used by:
 * - Core Forms builders (SectionBuilder, GroupBuilder, FieldsetBuilder)
 * - Settings wrappers (AdminSettings, UserSettings)
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderInterface;
use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;
use Ran\PluginLib\Forms\Builders\Traits\FieldProxyTrait;

/**
 * Generic field builder that preserves parent type for IDE autocomplete.
 *
 * Uses @template to allow Intelephense to resolve the correct parent type
 * when end_field() is called, enabling full autocomplete on the returned builder.
 *
 * @template TParent of object
 */
class GenericFieldBuilder implements ComponentBuilderInterface {
	use FieldProxyTrait;

	/**
	 * The parent builder to return to after field configuration.
	 *
	 * @var TParent
	 */
	private object $parent;

	/**
	 * Constructor.
	 *
	 * @param ComponentBuilderBase $builder The underlying component builder.
	 * @param TParent $parent The parent builder to return to.
	 * @param callable $updateFn The update callback for immediate data flow.
	 * @param string $container_id The container (collection/page) ID.
	 * @param string $section_id The section ID.
	 * @param string $component_alias The component alias.
	 * @param string|null $group_id The group ID (null for section-level fields).
	 * @param string|null $field_template The field template override.
	 * @param array<string,mixed> $pending_context Additional context for the component.
	 */
	public function __construct(
		ComponentBuilderBase $builder,
		object $parent,
		callable $updateFn,
		string $container_id,
		string $section_id,
		string $component_alias,
		?string $group_id = null,
		?string $field_template = null,
		array $pending_context = array()
	) {
		$this->parent = $parent;
		$this->_init_proxy(
			$builder,
			$updateFn,
			$container_id,
			$section_id,
			$component_alias,
			$group_id,
			$field_template,
			$pending_context
		);
	}

	/**
	 * End field configuration and return to the parent builder.
	 *
	 * The return type is inferred by Intelephense based on the @template TParent
	 * bound when the GenericFieldBuilder was created.
	 *
	 * @return TParent The parent builder for continued chaining.
	 * @phpstan-return TParent
	 * @psalm-return TParent
	 */
	public function end_field(): object {
		return $this->parent;
	}
}
