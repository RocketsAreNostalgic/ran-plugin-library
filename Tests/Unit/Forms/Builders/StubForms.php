<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\Builders;

use Ran\PluginLib\Forms\FormsInterface;
use Ran\PluginLib\Forms\FormsServiceSession;
use Ran\PluginLib\Options\RegisterOptions;

final class StubForms implements FormsInterface {
	public function __construct(private ?FormsServiceSession $session = null) {
	}

	public function render(string $id_slug, ?array $context = null): void {
		// Not used in tests.
	}

	public function resolve_options(?array $context = null): RegisterOptions {
		throw new \BadMethodCallException('StubForms::resolve_options() should not be called during these tests.');
	}

	public function boot(): void {
		// Not used in tests.
	}

	public function override_form_defaults(array $overrides): void {
		// No-op for tests.
	}

	public function get_form_session(): ?FormsServiceSession {
		return $this->session;
	}
}
