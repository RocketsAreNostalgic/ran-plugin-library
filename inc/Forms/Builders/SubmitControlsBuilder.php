<?php
/**
 * SubmitControlsBuilder: fluent builder for submission control zones.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use InvalidArgumentException;

final class SubmitControlsBuilder {
	private BuilderRootInterface $rootBuilder;
	private string $container_id;
	private string $zone_id;
	/** @var callable */
	private $updateFn;
	private string $template_key;
	private bool $has_custom_template = false;
	/** @var array<int,array<string,mixed>> */
	private array $controls   = array();
	private string $alignment = 'right';
	private string $layout    = 'inline';
	/** @var callable|null */
	private $before = null;
	/** @var callable|null */
	private $after = null;

	public function __construct(
		BuilderRootInterface $rootBuilder,
		string $container_id,
		string $zone_id,
		callable $updateFn,
		array $defaults = array()
	) {
		$this->rootBuilder         = $rootBuilder;
		$this->container_id        = $container_id;
		$this->zone_id             = $zone_id;
		$this->updateFn            = $updateFn;
		$this->template_key        = trim((string) ($defaults['template'] ?? 'layout/zone/submit-controls-wrapper')) ?: 'layout/zone/submit-controls-wrapper';
		$this->has_custom_template = $this->template_key !== 'layout/zone/submit-controls-wrapper';
		$this->alignment           = $this->normalize_alignment($defaults['alignment'] ?? 'right');
		$this->layout              = $this->normalize_layout($defaults['layout'] ?? 'inline');
		$this->before              = $defaults['before'] ?? null;
		$this->after               = $defaults['after']  ?? null;

		$this->emit_zone_metadata();
		if ($this->has_custom_template) {
			$this->emit_template_override();
		}
	}

	public function alignment(string $alignment): self {
		$this->alignment = $this->normalize_alignment($alignment);
		$this->emit_zone_metadata();
		return $this;
	}

	public function layout(string $layout): self {
		$this->layout = $this->normalize_layout($layout);
		$this->emit_zone_metadata();
		return $this;
	}

	public function template(string $template_key): self {
		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new InvalidArgumentException('Submit control template key cannot be empty.');
		}
		$this->template_key        = $template_key;
		$this->has_custom_template = true;
		return $this;
	}

	public function before(?callable $before): self {
		$this->before = $before;
		$this->emit_zone_metadata();
		return $this;
	}

	public function after(?callable $after): self {
		$this->after = $after;
		$this->emit_zone_metadata();
		return $this;
	}

	/**
	 * Register a submit/control button using the shared Button component builder.
	 *
	 * @param string        $control_id Unique identifier for the control.
	 * @param string        $label      Button label (required).
	 * @param callable|null $configure  Optional configurator for the button builder.
	 */
	public function button(string $control_id, string $label, ?callable $configure = null): self {
		$control_id = trim($control_id);
		if ($control_id === '') {
			throw new InvalidArgumentException('Submit control id cannot be empty.');
		}

		$label = trim($label);
		if ($label === '') {
			throw new InvalidArgumentException(sprintf('Submit control "%s" requires a non-empty label.', $control_id));
		}

		$builder = new ButtonBuilder($control_id, $label);
		$builder->type('submit');

		if ($configure !== null) {
			$configure($builder);
		}

		$payload            = $builder->to_array();
		$payload['context'] = $payload['component_context'];
		unset($payload['component_context']);

		$this->controls = array_values(array_filter(
			$this->controls,
			static function(array $control) use ($control_id): bool {
				return $control['id'] !== $control_id;
			}
		));

		$this->controls[] = array(
			'id'                => $control_id,
			'label'             => $payload['label'],
			'component'         => $payload['component'],
			'component_context' => $payload['context'],
			'order'             => $payload['order'],
		);

		usort(
			$this->controls,
			static function(array $a, array $b): int {
				return ($a['order'] <=> $b['order']);
			}
		);

		$this->emit_controls_update();

		return $this;
	}

	public function end_submit_controls(): BuilderRootInterface {
		return $this->rootBuilder;
	}

	public function end(): BuilderRootInterface {
		return $this->end_submit_controls();
	}

	private function emit_zone_metadata(): void {
		($this->updateFn)('submit_controls_zone', array(
			'container_id' => $this->container_id,
			'zone_id'      => $this->zone_id,
			'alignment'    => $this->alignment,
			'layout'       => $this->layout,
			'before'       => $this->before,
			'after'        => $this->after,
		));
	}

	private function emit_controls_update(): void {
		($this->updateFn)('submit_controls_set', array(
			'container_id' => $this->container_id,
			'zone_id'      => $this->zone_id,
			'controls'     => $this->controls,
		));
	}

	private function emit_template_override(): void {
		// Template overrides for submit controls will be implemented in a future iteration
		// once FormsServiceSession supports a dedicated element type. Intentionally no-op.
	}

	private function normalize_alignment(string $alignment): string {
		$alignment = strtolower(trim($alignment));
		return in_array($alignment, array('left', 'center', 'right', 'stretch'), true) ? $alignment : 'right';
	}

	private function normalize_layout(string $layout): string {
		$layout = strtolower(trim($layout));
		return in_array($layout, array('inline', 'stacked'), true) ? $layout : 'inline';
	}
}
