<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasBeforeAfterInterface {
	public function before(?callable $before): static;
	public function after(?callable $after): static;
}
