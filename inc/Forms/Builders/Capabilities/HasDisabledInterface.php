<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasDisabledInterface {
	public function disabled(bool|callable $disabled = true): static;
}
