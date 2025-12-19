<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits\Capabilities;

trait DescriptionMetaTrait {
	public function description(string|callable $description): static {
		$this->_update_meta('description', $description);
		return $this;
	}

	abstract protected function _update_meta(string $key, mixed $value): void;
}
