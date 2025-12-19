<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\Builders\SectionBuilderInterface;
use Ran\PluginLib\Forms\Builders\BuilderRootInterface;

final class StubRootBuilder implements BuilderRootInterface {
	public function __construct(private FormsInterface $forms) {
	}

	public function section(string $section_id, string $title, string|callable|null $description_cb = null, ?array $args = null): SectionBuilderInterface {
		throw new \BadMethodCallException('StubRootBuilder::section() is not implemented for these tests.');
	}

	public function heading(string $heading): static {
		return $this;
	}

	public function description(string|callable $description): static {
		return $this;
	}

	public function template(string|callable|null $template_key): static {
		return $this;
	}

	public function order(?int $order): static {
		return $this;
	}

	public function before(?callable $before): static {
		return $this;
	}

	public function after(?callable $after): static {
		return $this;
	}

	public function end(): FormsInterface {
		return $this->forms;
	}

	public function __get_forms(): FormsInterface {
		return $this->forms;
	}

	public function get_settings(): FormsInterface {
		return $this->forms;
	}
}
