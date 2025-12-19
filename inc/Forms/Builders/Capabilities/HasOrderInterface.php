<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasOrderInterface {
	public function order(?int $order): static;
}
