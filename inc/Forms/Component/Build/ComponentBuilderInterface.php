<?php

namespace Ran\PluginLib\Forms\Component\Build;

interface ComponentBuilderInterface {
	public function id(string $id): static;
	public function attribute(string $key, string $value): static;
	public function aria_label(string $label): static;
	public function aria_described_by(string $id): static;
	public function style(string|callable $style): static;
}
