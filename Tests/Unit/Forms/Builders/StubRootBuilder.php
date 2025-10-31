<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\Builders\BuilderRootInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;
use Ran\PluginLib\Forms\FormsInterface;

final class StubRootBuilder implements BuilderRootInterface {
	public function __construct(private FormsInterface $forms) {
	}

	public function section(string $section_id, string $title, ?callable $description_cb = null, ?array $args = null): SectionBuilderInterface {
		throw new \BadMethodCallException('StubRootBuilder::section() is not implemented for these tests.');
	}

	public function heading(string $heading): self {
		return $this;
	}

	public function description(string $description): self {
		return $this;
	}

	public function template(string $template_key): self {
		return $this;
	}

	public function order(int $order): self {
		return $this;
	}

	public function before(callable $before): self {
		return $this;
	}

	public function after(callable $after): self {
		return $this;
	}

	public function end(): FormsInterface {
		return $this->forms;
	}

	public function get_forms(): FormsInterface {
		return $this->forms;
	}

	public function get_settings(): FormsInterface {
		return $this->forms;
	}
}
