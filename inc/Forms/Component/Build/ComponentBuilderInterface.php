<?php

namespace Ran\PluginLib\Forms\Component\Build;

interface ComponentBuilderInterface {
	public function id(string $id): static;
	public function disabled(bool $disabled = true): static;
	public function required(bool $required = true): static;
	public function readonly(bool $readonly = true): static;
	public function attribute(string $key, string $value): static;
	public function ariaLabel(string $label): static;
	public function ariaDescribedBy(string $id): static;
	public function style(string|callable $style): static;
}
