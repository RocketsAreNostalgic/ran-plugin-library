<?php
/**
 * IDE helper stubs for form component builders.
 *
 * These stubs provide explicit return type declarations to help IDEs
 * properly infer types throughout fluent builder chains.
 *
 * @package Ran\PluginLib\Forms\Component\Build
 */

namespace Ran\PluginLib\Forms\Component\Build {
	if (false) {
		class ComponentBuilderBase {
			public function order(?int $order): static {
				return $this;
			}
			public function attributes(array $attributes): static {
				return $this;
			}
			public function attribute(string $key, string $value): static {
				return $this;
			}
			public function description(string|callable|null $description_cb): static {
				return $this;
			}
			public function to_array(): array {
				return array();
			}
		}

		class ComponentBuilderInputBase extends ComponentBuilderBase {
			public function placeholder(?string $placeholder): static {
				return $this;
			}
			public function default(?string $value): static {
				return $this;
			}
			public function name(?string $name): static {
				return $this;
			}
			public function disabled(bool $disabled = true): static {
				return $this;
			}
			public function readonly(bool $readonly = true): static {
				return $this;
			}
			public function required(bool $required = true): static {
				return $this;
			}
			public function autofocus(bool $autofocus = true): static {
				return $this;
			}
		}

		class ComponentBuilderTextBase extends ComponentBuilderInputBase {
			public function autocomplete(?string $autocomplete): static {
				return $this;
			}
			public function autocapitalize(?string $autocapitalize): static {
				return $this;
			}
			public function spellcheck(?bool $spellcheck): static {
				return $this;
			}
			public function minlength(?int $minlength): static {
				return $this;
			}
			public function maxlength(?int $maxlength): static {
				return $this;
			}
			public function pattern(?string $pattern): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Elements\Button {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function type(string $type): static {
				return $this;
			}
			public function disabled(bool $disabled = true): static {
				return $this;
			}
			public function variant(string $variant): static {
				return $this;
			}
			public function icon_html(?string $html): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Elements\ButtonLink {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function target(?string $target): static {
				return $this;
			}
			public function rel(?string $rel): static {
				return $this;
			}
			public function icon_html(?string $html): static {
				return $this;
			}
			public function url(string $url): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Elements\InlineLink {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function target(?string $target): static {
				return $this;
			}
			public function rel(?string $rel): static {
				return $this;
			}
			public function icon_html(?string $html): static {
				return $this;
			}
			public function url(string $url): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Checkbox {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase {
			public function text(?string $text): static {
				return $this;
			}
			public function values(string $checkedValue, ?string $uncheckedValue = null): static {
				return $this;
			}
			public function defaultChecked(bool $checked = true): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxGroup {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function legend(?string $legend): static {
				return $this;
			}
			public function defaults(array $values): static {
				return $this;
			}
			public function checkbox(string $value, string $label, ?string $description = null, array $attributes = array(), bool $defaultChecked = false): static {
				return $this;
			}
			public function option(\Ran\PluginLib\Forms\Components\Fields\CheckboxOption\Builder $option): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxOption {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function value(string $value): static {
				return $this;
			}
			public function defaultChecked(bool $checked = true): static {
				return $this;
			}
			public function disabled(bool $disabled = true): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase {
			public function multiple(bool $multiple = true): static {
				return $this;
			}
			public function accept(string|array|null $accept): static {
				return $this;
			}
			public function existing_files(array $files): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Input {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase {
			public function type(string $type): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\MediaPicker {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function data(array $data): static {
				return $this;
			}
			public function name(?string $name): static {
				return $this;
			}
			public function inputId(?string $id): static {
				return $this;
			}
			public function buttonId(?string $id): static {
				return $this;
			}
			public function removeId(?string $id): static {
				return $this;
			}
			public function value(?string $value): static {
				return $this;
			}
			public function descriptionId(?string $descriptionId): static {
				return $this;
			}
			public function selectLabel(?string $label): static {
				return $this;
			}
			public function replaceLabel(?string $label): static {
				return $this;
			}
			public function removeLabel(?string $label): static {
				return $this;
			}
			public function previewHtml(?string $html): static {
				return $this;
			}
			public function multiple(bool $multiple = true): static {
				return $this;
			}
			public function hasSelection(?bool $hasSelection): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Number {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase {
			public function min(int|float|null $min): static {
				return $this;
			}
			public function max(int|float|null $max): static {
				return $this;
			}
			public function step(int|float|null $step): static {
				return $this;
			}
			public function range(int|float $min, int|float $max): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\MultiSelect {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function name(?string $name): static {
				return $this;
			}
			public function elementId(?string $id): static {
				return $this;
			}
			public function descriptionId(?string $descriptionId): static {
				return $this;
			}
			public function values(array $values): static {
				return $this;
			}
			public function defaultValues(array $values): static {
				return $this;
			}
			public function disabled(bool $disabled = true): static {
				return $this;
			}
			public function attribute(string $key, string $value): static {
				return $this;
			}
			public function option(string $value, string $label, ?string $group = null, array $attributes = array(), bool $selected = false, bool $disabled = false): static {
				return $this;
			}
			public function options(array $options): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function legend(?string $legend): static {
				return $this;
			}
			public function name(?string $name): static {
				return $this;
			}
			public function default(string $value): static {
				return $this;
			}
			public function option(string $value, string $label, ?string $description = null, array $attributes = array(), array $labelAttributes = array(), bool $disabled = false): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\RadioOption {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function checked(bool $checked = true): static {
				return $this;
			}
			public function disabled(bool $disabled = true): static {
				return $this;
			}
			public function labelAttribute(string $key, string $value): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Select {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function name(?string $name): static {
				return $this;
			}
			public function elementId(?string $id): static {
				return $this;
			}
			public function descriptionId(?string $descriptionId): static {
				return $this;
			}
			public function value(?string $value): static {
				return $this;
			}
			public function default(?string $default): static {
				return $this;
			}
			public function disabled(bool $disabled = true): static {
				return $this;
			}
			public function required(bool $required = true): static {
				return $this;
			}
			public function attribute(string $key, string $value): static {
				return $this;
			}
			public function option(string $value, string $label, ?string $group = null, array $attributes = array(), bool $selected = false, bool $disabled = false): static {
				return $this;
			}
			public function options(array $options): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Text {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase {
			public function size(?int $size): static {
				return $this;
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Textarea {
	if (false) {
		final class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase {
			public function rows(?int $rows): static {
				return $this;
			}
			public function cols(?int $cols): static {
				return $this;
			}
		}
	}
}
