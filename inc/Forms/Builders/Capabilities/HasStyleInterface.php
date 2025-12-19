<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasStyleInterface {
	public function style(string|callable $style): static;
}
