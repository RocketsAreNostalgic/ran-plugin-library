<?php

namespace Ran\PluginLib\Forms\Component\Build;

interface ReadonlyComponentBuilderInterface extends ComponentBuilderInterface {
	public function readonly(bool|callable $readonly = true): static;
}
