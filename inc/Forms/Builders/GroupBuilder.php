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
	 * @return SectionBuilder
	 */
	public function end_group(): SectionBuilder {
		return $this->section();
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
