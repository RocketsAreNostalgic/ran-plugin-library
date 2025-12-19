<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders\Traits\Capabilities;

trait BeforeAfterMetaTrait {
	public function before(?callable $before): static {
		$this->_update_meta('before', $before);
		return $this;
	}

	public function after(?callable $after): static {
		$this->_update_meta('after', $after);
		return $this;
	}

	abstract protected function _update_meta(string $key, mixed $value): void;
}
