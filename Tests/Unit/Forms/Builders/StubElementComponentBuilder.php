<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase;

final class StubElementComponentBuilder extends ComponentBuilderBase {
	public function __construct(string $id, string $label, private string $componentAlias) {
		parent::__construct($id, $label);
	}

	protected function _get_component(): string {
		return $this->componentAlias;
	}

	protected function _build_component_context(): array {
		return $this->_build_base_context();
	}

	public ?string $customOption = null;

	public function customOption(string $value): self {
		$this->customOption = $value;
		return $this;
	}
}
