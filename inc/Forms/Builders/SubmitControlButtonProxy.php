<?php
/**
 * SubmitControlButtonProxy: Fluent proxy for submit control button customization.
 */

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Builders;

use Ran\PluginLib\Forms\Components\Elements\Button\Builder as ButtonBuilder;
use BadMethodCallException;

final class SubmitControlButtonProxy {
	private SubmitControlsBuilder $parent;
	private ButtonBuilder $builder;

	public function __construct(SubmitControlsBuilder $parent, ButtonBuilder $builder) {
		$this->parent  = $parent;
		$this->builder = $builder;
	}

	/**
	 * Forward fluent calls to the underlying button builder or parent builder.
	 *
	 * @param string $name Method name being invoked.
	 * @param array<int,mixed> $arguments Parameter list.
	 *
	 * @return mixed
	 */
	public function __call(string $name, array $arguments) {
		if (method_exists($this->builder, $name)) {
			$result = $this->builder->$name(...$arguments);
			$this->parent->updateButton($this->builder);

			if ($result instanceof ButtonBuilder || $result === $this->builder) {
				return $this;
			}

			return $result;
		}

		if (method_exists($this->parent, $name)) {
			$this->parent->updateButton($this->builder);
			return $this->parent->$name(...$arguments);
		}

		throw new BadMethodCallException(sprintf('Method "%s" is not supported by SubmitControlButtonProxy.', $name));
	}

	/**
	 * Explicit accessor to return to the parent submit controls builder.
	 */
	public function end_control(): SubmitControlsBuilder {
		$this->parent->updateButton($this->builder);
		return $this->parent;
	}
}
