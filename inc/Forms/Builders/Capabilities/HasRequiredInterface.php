<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasRequiredInterface {
	public function required(bool|callable $required = true): static;
}
