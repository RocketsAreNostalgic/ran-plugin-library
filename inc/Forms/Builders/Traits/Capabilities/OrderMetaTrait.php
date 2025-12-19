<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits\Capabilities;

trait OrderMetaTrait {
	public function order(?int $order): static {
		$this->_update_meta('order', $order);
		return $this;
	}

	abstract protected function _update_meta(string $key, mixed $value): void;
}
