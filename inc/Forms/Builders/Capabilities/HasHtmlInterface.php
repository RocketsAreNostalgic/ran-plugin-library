<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Capabilities;

interface HasHtmlInterface {
	public function html(string|callable $content): static;
}
