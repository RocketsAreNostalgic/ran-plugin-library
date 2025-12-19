<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasDescriptionInterface {
	public function description(string|callable $description): static;
}
