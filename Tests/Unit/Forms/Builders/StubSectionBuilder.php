<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Builders\BuilderContextInterface;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\GenericBuilderContext;
use Ran\PluginLib\Forms\Builders\SectionBuilder;

final class StubSectionBuilder extends SectionBuilder {
	public bool $markerCalled = false;

	public function __construct(
		BuilderRootInterface $root,
		BuilderContextInterface $context,
		string $section,
		string $heading = ''
	) {
		parent::__construct($root, $context, $section, $heading);
	}

	/**
	 * Factory method to create a StubSectionBuilder with the old-style parameters.
	 * Useful for tests that need to create a section builder with a custom update function.
	 */
	public static function createWithUpdateFn(
		BuilderRootInterface $root,
		string $container,
		string $section,
		string $heading,
		callable $updateFn
	): self {
		$forms   = $root->__get_forms();
		$context = new GenericBuilderContext($forms, $container, $updateFn);
		return new self($root, $context, $section, $heading);
	}

	public function parent_marker(): string {
		$this->markerCalled = true;
		return 'marker';
	}

	public function wasMarkerCalled(): bool {
		return $this->markerCalled;
	}
}
