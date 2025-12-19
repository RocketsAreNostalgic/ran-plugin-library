<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasReadonlyInterface {
	public function readonly(bool|callable $readonly = true): static;
}
