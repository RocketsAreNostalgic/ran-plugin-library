<?php
/**
 * SubmitControlsBuilder: fluent builder for submission control zones.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use InvalidArgumentException;

final class SubmitControlsBuilder implements SubmitControlsBuilderInterface {
	private const DEFAULT_ZONE_ID = 'primary-controls';

	private BuilderRootInterface $rootBuilder;
	private string $container_id;
	private string $zone_id;
	/** @var callable */
	private $updateFn;
	private string $template_key;
	private bool $has_custom_template = false;
	private string $element_type      = 'root';
	private string $root_id;
	/** @var array<int,array<string,mixed>> */
	private array $controls = array();
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
		$this->zone_id             = $zone_id !== '' ? $zone_id : self::DEFAULT_ZONE_ID;
		$this->root_id             = $container_id;
		$this->updateFn            = $updateFn;
		$this->template_key        = trim((string) ($defaults['template'] ?? 'layout/zone/submit-controls-wrapper')) ?: 'layout/zone/submit-controls-wrapper';
		$this->has_custom_template = $this->template_key !== 'layout/zone/submit-controls-wrapper';
		$this->before              = $defaults['before'] ?? null;
		$this->after               = $defaults['after']  ?? null;

		$this->emit_zone_metadata();
		if ($this->has_custom_template) {
			$this->emit_template_override();
		}
	}

	public function template(string $template_key): self {
		$template_key = trim($template_key);
		if ($template_key === '') {
			throw new InvalidArgumentException('Submit control template key cannot be empty.');
		}
		$this->template_key        = $template_key;
		$this->has_custom_template = true;
		$this->emit_template_override();
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

		$payload = $builder->to_array();

		$this->registerControl(
			$payload['id'],
			$payload['component'],
			$payload['component_context'],
			$payload['label'],
			$payload['order']
		);

		return $this;
	}

	/**
	 * Register any component as a submit control using the shared fluent signature.
	 *
	 * @param string $control_id Unique control identifier.
	 * @param string $label      Optional display label (may be empty for non-button controls).
	 * @param string $component  Registered component alias.
	 * @param array<string,mixed> $args Optional configuration (context/order/label overrides).
	 * @return self|ComponentBuilderProxy
	 */
	public function field(string $control_id, string $label, string $component, array $args = array()): self|ComponentBuilderProxy {
		$control_id = trim($control_id);
		if ($control_id === '') {
			throw new InvalidArgumentException('Submit control id cannot be empty.');
		}

		$component = trim($component);
		if ($component === '') {
			throw new InvalidArgumentException('Submit control component alias cannot be empty.');
		}

		$context = $args['context'] ?? $args['component_context'] ?? array();
		if (!is_array($context)) {
			throw new InvalidArgumentException('Submit control component context must be an array.');
		}

		$resolvedLabel = isset($args['label']) ? (string) $args['label'] : $label;
		$order         = isset($args['order']) ? (int) $args['order'] : 0;

		$this->registerControl($control_id, $component, $context, (string) $resolvedLabel, $order);
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

	private function registerControl(string $control_id, string $component, array $component_context, string $label = '', ?int $order = null): void {
		$this->controls = array_values(array_filter(
			$this->controls,
			static function(array $control) use ($control_id): bool {
				return $control['id'] !== $control_id;
			}
		));

		$this->controls[] = array(
			'id'                => $control_id,
			'label'             => $label,
			'component'         => $component,
			'component_context' => $component_context,
			'order'             => $order ?? 0,
		);

		usort(
			$this->controls,
			static function(array $a, array $b): int {
				return ($a['order'] <=> $b['order']);
			}
		);

		$this->emit_controls_update();
	}

	private function emit_template_override(): void {
		if (!$this->has_custom_template) {
			return;
		}

		($this->updateFn)('template_override', array(
			'element_type' => $this->element_type,
			'element_id'   => $this->root_id,
			'zone_id'      => $this->zone_id,
			'overrides'    => array('submit-controls-wrapper' => $this->template_key),
		));
	}
}
