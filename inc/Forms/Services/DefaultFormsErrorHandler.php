<?php

declare(strict_types=1);

namespace Ran\PluginLib\Forms\Services;

use Ran\PluginLib\Forms\ErrorNoticeRenderer;
use Psr\Log\LoggerInterface;

class DefaultFormsErrorHandler implements FormsErrorHandlerInterface {
	public function handle_builder_error(
		\Throwable $e,
		string $hook,
		LoggerInterface $logger,
		string $class_name,
		callable $is_dev_environment,
		callable $is_admin,
		callable $current_user_can,
		callable $add_action,
		callable $register_fallback_pages
	): void {
		$context = array(
			'hook'  => $hook,
			'class' => $class_name,
			'file'  => $e->getFile(),
			'line'  => $e->getLine(),
			'trace' => $e->getTraceAsString(),
		);

		$logger->error(
			sprintf('Settings builder error on %s hook: %s', $hook, $e->getMessage()),
			$context
		);

		if (!$is_admin() || !$current_user_can('manage_options')) {
			return;
		}

		$is_dev = (bool) $is_dev_environment();
		if (!$is_dev) {
			return;
		}

		$add_action('admin_notices', function () use ($e, $hook): void {
			ErrorNoticeRenderer::renderWithContext($e, 'Settings Builder Error', 'hook', $hook);
		});
	}

	public function register_admin_menu_fallback_pages(
		array $page_slugs,
		bool $is_dev,
		callable $add_menu_page,
		callable $add_submenu_page,
		callable $did_action,
		callable $add_action
	): void {
		// no-op by default; context-specific handlers (e.g. admin settings) can override.
	}
}
