<?php
/**
 * GroupBuilder: Fluent builder for grouped fields within a section.
 *
 * @package Ran\PluginLib\Forms\Builders
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Builders\SectionFieldContainerBuilder;
use Ran\PluginLib\Forms\Builders\SectionBuilder;
use Ran\PluginLib\Forms\Builders\GroupBuilderInterface;

class GroupBuilder extends SectionFieldContainerBuilder implements GroupBuilderInterface {
	public function __construct(
		SectionBuilder $sectionBuilder,
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

	/**
	 * Commit buffered data and return to the section builder.
	 *
	 * Emits a final group_metadata event to ensure any fields added
	 * after before()/after() calls are reflected in the metadata.
	 *
	 * @return SectionBuilderInterface
	 */
	public function end_group(): SectionBuilderInterface {
		// Emit final group metadata to capture any fields added after before()/after()
		$this->emit_group_metadata();
		return $this->section();
	}

	/**
	 * Not valid in group context - throws exception.
	 *
	 * @return SectionBuilderInterface
	 * @throws \RuntimeException Always throws - use end_group() instead.
	 */
	public function end_fieldset(): SectionBuilderInterface {
		throw new \RuntimeException('Cannot call end_fieldset() from group context. Use end_group() instead.');
	}

	/**
	 * Start a sibling group on the same section and return its GroupBuilder.
	 *
	 * @param string $group_id
	 * @param string $heading
	 * @param callable|null $description_cb
	 * @param array<string,mixed>|null $args
	 *
	 * @return GroupBuilderInterface
	 */
	public function group(string $group_id, string $heading, ?callable $description_cb = null, ?array $args = null): GroupBuilderInterface {
		return $this->section()->group($group_id, $heading, $description_cb, $args ?? array());
	}
}
