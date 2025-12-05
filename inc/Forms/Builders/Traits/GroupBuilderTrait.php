<?php
/**
 * GroupBuilderTrait: Group-specific logic for fluent builders.
 *
 * This trait provides the group-specific implementation on top of
 * SectionFieldContainerTrait. Groups are simpler than fieldsets - they
 * don't have HTML-specific attributes like form, name, or disabled.
 *
 * @package Ran\PluginLib\Forms\Builders\Traits
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits;

/**
 * Group-specific builder logic.
 *
 * Classes using this trait must:
 * 1. Also use SectionFieldContainerTrait
 * 2. Implement abstract methods for context-specific behavior
 */
trait GroupBuilderTrait {
	/**
	 * Get the container type for groups.
	 *
	 * @return string Always 'group'.
	 */
	protected function _get_group_container_type(): string {
		return 'group';
	}

	/**
	 * Emit final group metadata before ending.
	 *
	 * Call this in end_group() to ensure any fields added after
	 * before()/after() calls are reflected in the metadata.
	 */
	protected function _finalize_group(): void {
		$this->_emit_container_metadata();
	}
}
