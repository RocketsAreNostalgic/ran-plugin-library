<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilder;

final class StubSectionBuilder extends SectionBuilder {
	public bool $markerCalled = false;

	public function __construct(
		BuilderRootInterface $root,
		string $container,
		string $section,
		string $heading,
		callable $updateFn
	) {
		parent::__construct($root, $container, $section, $heading, $updateFn);
	}

	public function parent_marker(): string {
		$this->markerCalled = true;
		return 'marker';
	}

	public function wasMarkerCalled(): bool {
		return $this->markerCalled;
	}
}
