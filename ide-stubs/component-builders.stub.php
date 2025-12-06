<?php
/**
 * IDE helper stubs for form component builders.
 */

namespace Ran\PluginLib\Forms\Component\Build {
	if (false) {
		abstract class ComponentBuilderBase {
			public function order(?int $order): static {
				return $this;
			}
			public function attributes(array $attributes): static {
				return $this;
			}
			public function attribute(string $key, string $value): static {
				return $this;
			}
			public function description(string|callable|null  $description_cb): static {
				return $this;
			}
			public function to_array(): array {
				return array();
			}
			protected function _build_base_context(): array {
				return array();
			}
			abstract protected function _get_component(): string;
			abstract protected function _build_component_context(): array;
		}

		abstract class ComponentBuilderInputBase extends ComponentBuilderBase {
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

		abstract class ComponentBuilderTextBase extends ComponentBuilderInputBase {
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
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function type(string $type): self {
				return $this;
			}
			public function disabled(bool $disabled = true): self {
				return $this;
			}
			public function variant(string $variant): self {
				return $this;
			}
			public function icon_html(?string $html): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'components.button';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Elements\ButtonLink {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function target(?string $target): self {
				return $this;
			}
			public function rel(?string $rel): self {
				return $this;
			}
			public function icon_html(?string $html): self {
				return $this;
			}
			public function url(string $url): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'elements.button-link';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Elements\InlineLink {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function target(?string $target): self {
				return $this;
			}
			public function rel(?string $rel): self {
				return $this;
			}
			public function icon_html(?string $html): self {
				return $this;
			}
			public function url(string $url): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'components.inline-link';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Checkbox {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function text(?string $text): self {
				return $this;
			}
			public function name(string $name): self {
				return $this;
			}
			public function values(string $checkedValue, ?string $uncheckedValue = null): self {
				return $this;
			}
			public function defaultChecked(bool $checked = true): self {
				return $this;
			}
			public function attribute(string $key, string $value): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'checkbox';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxGroup {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function legend(?string $legend): self {
				return $this;
			}
			public function defaults(array $values): self {
				return $this;
			}
			public function checkbox(string $value, string $label, ?string $description = null, array $attributes = array(), bool $defaultChecked = false): self {
				return $this;
			}
			public function option(\Ran\PluginLib\Forms\Components\Fields\CheckboxOption\Builder $option): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'checkbox-group';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\CheckboxOption {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function value(string $value): self {
				return $this;
			}
			public function defaultChecked(bool $checked = true): self {
				return $this;
			}
			public function disabled(bool $disabled = true): self {
				return $this;
			}
			public function attribute(string $key, string $value): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'checkbox-option';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\FileUpload {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase {
			public function multiple(bool $multiple = true): self {
				return $this;
			}
			public function accept(string|array|null $accept): self {
				return $this;
			}
			public function existing_files(array $files): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'file-upload';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Input {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase {
			public function type(string $type): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'text';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\MediaPicker {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function data(array $data): self {
				return $this;
			}
			public function name(?string $name): self {
				return $this;
			}
			public function inputId(?string $id): self {
				return $this;
			}
			public function buttonId(?string $id): self {
				return $this;
			}
			public function removeId(?string $id): self {
				return $this;
			}
			public function value(?string $value): self {
				return $this;
			}
			public function descriptionId(?string $descriptionId): self {
				return $this;
			}
			public function selectLabel(?string $label): self {
				return $this;
			}
			public function replaceLabel(?string $label): self {
				return $this;
			}
			public function removeLabel(?string $label): self {
				return $this;
			}
			public function previewHtml(?string $html): self {
				return $this;
			}
			public function multiple(bool $multiple = true): self {
				return $this;
			}
			public function hasSelection(?bool $hasSelection): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'components.media-picker';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Number {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderInputBase {
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
			protected function _get_component(): string {
				return 'number';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\MultiSelect {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function name(?string $name): self {
				return $this;
			}
			public function elementId(?string $id): self {
				return $this;
			}
			public function descriptionId(?string $descriptionId): self {
				return $this;
			}
			public function values(array $values): self {
				return $this;
			}
			public function defaultValues(array $values): self {
				return $this;
			}
			public function disabled(bool $disabled = true): self {
				return $this;
			}
			public function attribute(string $key, string $value): self {
				return $this;
			}
			public function option(string $value, string $label, ?string $group = null, array $attributes = array(), bool $selected = false, bool $disabled = false): self {
				return $this;
			}
			public function options(array $options): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'fields.multi-select';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\RadioGroup {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function legend(?string $legend): self {
				return $this;
			}
			public function name(?string $name): self {
				return $this;
			}
			public function default(string $value): self {
				return $this;
			}
			public function option(string $value, string $label, ?string $description = null, array $attributes = array(), array $labelAttributes = array(), bool $disabled = false): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'radio-group';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\RadioOption {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function checked(bool $checked = true): self {
				return $this;
			}
			public function disabled(bool $disabled = true): self {
				return $this;
			}
			public function attribute(string $key, string $value): self {
				return $this;
			}
			public function labelAttribute(string $key, string $value): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'fields.radio-option';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Select {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderBase {
			public function name(?string $name): self {
				return $this;
			}
			public function elementId(?string $id): self {
				return $this;
			}
			public function descriptionId(?string $descriptionId): self {
				return $this;
			}
			public function value(?string $value): self {
				return $this;
			}
			public function default(?string $default): self {
				return $this;
			}
			public function disabled(bool $disabled = true): self {
				return $this;
			}
			public function required(bool $required = true): self {
				return $this;
			}
			public function attribute(string $key, string $value): self {
				return $this;
			}
			public function option(string $value, string $label, ?string $group = null, array $attributes = array(), bool $selected = false, bool $disabled = false): self {
				return $this;
			}
			public function options(array $options): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'select';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Text {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase {
			public function size(?int $size): static {
				return $this;
			}
			protected function _get_component(): string {
				return 'text';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}

namespace Ran\PluginLib\Forms\Components\Fields\Textarea {
	if (false) {
		class Builder extends \Ran\PluginLib\Forms\Component\Build\ComponentBuilderTextBase {
			public function rows(?int $rows): self {
				return $this;
			}
			public function cols(?int $cols): self {
				return $this;
			}
			protected function _get_component(): string {
				return 'textarea';
			}
			protected function _build_component_context(): array {
				return array();
			}
		}
	}
}
