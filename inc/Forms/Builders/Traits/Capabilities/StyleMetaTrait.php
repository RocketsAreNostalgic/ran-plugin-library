<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits\Capabilities;

trait StyleMetaTrait {
	public function style(string|callable $style): static {
		if ($style === '') {
			$this->_update_meta('style', '');
			return $this;
		}

		$this->_update_meta('style', $style);
		return $this;
	}

	abstract protected function _update_meta(string $key, mixed $value): void;
}
