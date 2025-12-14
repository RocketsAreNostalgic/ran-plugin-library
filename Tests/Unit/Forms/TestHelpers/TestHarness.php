<?php
declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Forms\TestHelpers;

use Ran\PluginLib\Util\CollectingLogger;
use Ran\PluginLib\Options\RegisterOptions;
use Ran\PluginLib\Forms\Renderer\FormMessageHandler;
use Ran\PluginLib\Forms\Renderer\FormElementRenderer;
use Ran\PluginLib\Forms\FormsService;
use Ran\PluginLib\Forms\FormsBaseTrait;
use Ran\PluginLib\Forms\Component\ComponentManifest;
use Ran\PluginLib\Forms\Component\ComponentLoader;

/**
 * Minimal concrete harness exposing FormsBaseTrait internals for testing.
 */
final class TestHarness extends FormsBaseTrait {
	public function __construct(CollectingLogger $logger) {
		$this->main_option     = 'test_option';
		$this->pending_values  = null;
		$component_loader      = new ComponentLoader(__DIR__ . '/../../fixtures/templates', $logger);
		$this->components      = new ComponentManifest($component_loader, $logger);
		$this->form_service    = new FormsService($this->components, $logger);
		$this->field_renderer  = new FormElementRenderer($this->components, $this->form_service, $component_loader, $logger);
		$this->message_handler = new FormMessageHandler();
		$this->logger          = $logger;
		$this->base_options    = new RegisterOptions('test_option', null, true, $logger);
	}

	public function boot(bool $eager = false): void {
		// no-op for tests
	}

	protected function _should_load(): bool {
		return true;
	}

	public function __render(string $id_slug, ?array $context = null): void {
		// no-op for tests
	}

	public function make_update_function(): callable {
		return $this->_create_update_function();
	}

	public function _get_submit_controls_for_page(string $container_id): array {
		return $this->submit_controls[$container_id] ?? array();
	}

	public function debug_submit_controls(): array {
		return $this->submit_controls;
	}

	protected function _handle_context_update(string $type, array $data): void {
		// no-op for test harness; concrete classes override for additional cases
	}

	protected function _resolve_context(array $context): array {
		return array(
			'id'      => 'test-context',
			'storage' => array('scope' => 'test'),
		);
	}

	protected function _get_form_type_suffix(): string {
		return 'test';
	}

	protected function _do_sanitize_key(string $key): string {
		return $key;
	}
}
