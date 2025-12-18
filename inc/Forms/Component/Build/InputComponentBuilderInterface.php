<?php

namespace Ran\PluginLib\Forms\Component\Build;

interface InputComponentBuilderInterface extends ComponentBuilderInterface {
	public function disabled(bool|callable $disabled = true): static;
	public function required(bool|callable $required = true): static;
}
