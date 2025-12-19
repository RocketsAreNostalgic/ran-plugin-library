<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasDefaultValuesInterface {
	public function default_values(array|callable $values): static;
}
