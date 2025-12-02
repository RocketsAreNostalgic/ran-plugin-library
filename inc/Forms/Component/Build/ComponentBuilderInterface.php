<?php

namespace Ran\PluginLib\Forms\Component\Build;

interface ComponentBuilderInterface {
	public function id(string $id): self;
	public function disabled(bool $disabled = true): self;
	public function required(bool $required = true): self;
	public function readonly(bool $readonly = true): self;
	public function attribute(string $key, string $value): self;
	public function ariaLabel(string $label): self;
	public function ariaDescribedBy(string $id): self;
	public function style(string|callable $style): self;
}
