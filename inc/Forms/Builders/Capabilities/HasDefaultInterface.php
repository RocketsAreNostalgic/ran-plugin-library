<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasDefaultInterface {
	public function default(string|callable|null $value): static;
}
